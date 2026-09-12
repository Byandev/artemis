<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreShopRequest;
use App\Http\Sorts\PendingRequiredChecklistsSort;
use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;
use App\Services\PostHogService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Pancake\Jobs\FetchShopOrders;
use Modules\Pancake\Jobs\FetchShopUsers;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ShopController extends Controller
{
    use AuthorizesRequests;

    /**
     * Pancake order status codes, from the POS API's own `status` enum
     * (x-enum-descriptions). Only the two the presets below restrict to.
     */
    private const POS_STATUS_NEW = 0;

    private const POS_STATUS_CANCELED = 6;

    /**
     * The order tags every shop is expected to have, and the order statuses
     * each one may be applied to. Created on demand from the shops page so
     * they don't have to be typed into Pancake shop by shop.
     *
     * @var list<array{name: string, tag_color: string, statuses: list<int>}>
     */
    private const ORDER_TAG_PRESETS = [
        ['name' => 'High RTS', 'tag_color' => '#f04134', 'statuses' => [self::POS_STATUS_CANCELED]],
        ['name' => 'Cancel by Customer', 'tag_color' => '#fa8c16', 'statuses' => [self::POS_STATUS_CANCELED]],
        ['name' => 'Has Returned Orders', 'tag_color' => '#faad14', 'statuses' => [self::POS_STATUS_CANCELED]],
        ['name' => 'Troll', 'tag_color' => '#722ed1', 'statuses' => [self::POS_STATUS_CANCELED]],
        ['name' => 'Reserved', 'tag_color' => '#096dd9', 'statuses' => [self::POS_STATUS_NEW]],
        ['name' => 'Incomplete Details', 'tag_color' => '#13c2c2', 'statuses' => [self::POS_STATUS_NEW]],
    ];

    private function assertShopLimitNotReached(Workspace $workspace): void
    {
        $info = $workspace->shopLimitInfo();

        if ($info['reached']) {
            throw ValidationException::withMessages([
                'shop_limit' => "You've reached your plan's shop limit ({$info['limit']}). Upgrade your plan to add more shops.",
            ]);
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::ViewShops->value, $workspace);

        $pendingChecklistsSub = DB::table('workspace_checklists as wc')
            ->selectRaw('COUNT(*)')
            ->where('wc.workspace_id', $workspace->id)
            ->where('wc.target', 'Shop')
            ->where('wc.required', true)
            ->whereNotExists(function ($sub) use ($workspace) {
                $sub->select(DB::raw(1))
                    ->from('workspace_checklist_completions as wcc')
                    ->whereColumn('wcc.workspace_checklist_id', 'wc.id')
                    ->whereColumn('wcc.target_id', 'shops.id')
                    ->where('wcc.workspace_id', $workspace->id)
                    ->where('wcc.target_type', Shop::class);
            });

        $baseQuery = Shop::where('shops.workspace_id', $workspace->id)
            ->visibleTo($request->user(), $workspace)
            ->select('shops.*')
            ->selectSub($pendingChecklistsSub, 'pending_required_checklists_count');

        $pages = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts([
                'name',
                'created_at',
                'deleted_at',
                'orders_last_synced_at',
                AllowedSort::custom('pending_required_checklists_count', new PendingRequiredChecklistsSort),
            ])
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        // Reveal the POS token only to users who can edit shops, so the edit form
        // can pre-fill it. It stays hidden from everyone else.
        if ($request->user()->can(Permission::EditShops->value, $workspace)) {
            $pages->getCollection()->each->makeVisible('pos_token');
        }

        $shopLimitInfo = $workspace->shopLimitInfo();

        return Inertia::render('workspaces/shops/index', [
            'pages' => $pages,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'shopLimit' => $shopLimitInfo['limit'],
            'shopCount' => $shopLimitInfo['count'],
            'shopLimitReached' => $shopLimitInfo['reached'],
        ]);
    }

    public function store(StoreShopRequest $request, Workspace $workspace)
    {
        $this->authorize(Permission::CreateShops->value, $workspace);

        $this->assertShopLimitNotReached($workspace);

        $validated = $request->validated();

        // The "shop already added" case is caught by StoreShopRequest validation
        // before we get here, so no POS API call is made for a duplicate.
        $response = Http::get('https://pos.pages.fm/api/v1/shops/'.$validated['shop_id'], [
            'api_key' => $validated['pos_token'],
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages(['pos_token' => 'Invalid API Key.']);
        }

        $resJson = $response->json();

        $shop = Shop::create([
            'id' => $validated['shop_id'],
            'workspace_id' => $workspace->id,
            'name' => $resJson['shop']['name'] ?? 'Shop '.$validated['shop_id'],
            'avatar_url' => $resJson['shop']['avatar_url'] ?? null,
            'pos_token' => $validated['pos_token'],
        ]);

        $createdPages = $this->syncShopPages($shop, $workspace, $resJson, $request->user()->id);

        // Auto-attach the new shop to every team the connecting user belongs to
        // in this workspace so their teammates can see it without a manual step.
        $userTeamIds = $request->user()->teams()
            ->where('teams.workspace_id', $workspace->id)
            ->pluck('teams.id');

        if ($userTeamIds->isNotEmpty()) {
            $shop->teams()->syncWithoutDetaching($userTeamIds);
        }

        dispatch(new FetchShopUsers($shop))->onQueue('pancake');
        dispatch(new FetchShopOrders($shop, 1, Carbon::now()->subMonths(2)->unix(), Carbon::now()->unix()))->onQueue('pancake');

        (new PostHogService)->capture((string) $request->user()->id, 'shop_connected', [
            'workspace_id' => $workspace->id,
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'pages_created' => $createdPages,
        ]);

        return redirect()->route('workspaces.shops.index', $workspace)
            ->with('success', "Shop added. {$createdPages} page(s) imported and syncing.");
    }

    public function update(Request $request, Workspace $workspace, Shop $shop)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::EditShops->value, $workspace);

        if ($shop->workspace_id !== $workspace->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Optional — leave blank to keep the current token. When changed it is
            // verified against the POS API before being saved.
            'pos_token' => 'nullable|string|max:255',
        ]);

        $tokenChanged = ! empty($validated['pos_token'])
            && $validated['pos_token'] !== $shop->pos_token;

        if ($tokenChanged) {
            $response = Http::get('https://pos.pages.fm/api/v1/shops/'.$shop->id, [
                'api_key' => $validated['pos_token'],
            ]);

            if ($response->failed()) {
                throw ValidationException::withMessages(['pos_token' => 'Invalid API Key.']);
            }
        }

        $shop->update([
            'name' => $validated['name'],
            ...($tokenChanged ? ['pos_token' => $validated['pos_token']] : []),
        ]);

        return redirect()->route('workspaces.shops.index', $workspace)
            ->with('success', 'Shop updated.');
    }

    /**
     * Create/refresh the pages that belong to a shop from the POS API response.
     * Orders are pulled at the shop level (FetchShopOrders), so no per-page
     * fetch is queued here. Existing pages keep their owner and status — only
     * the name/shop link is refreshed; newly-discovered pages get $ownerId and
     * become active. Returns the number of pages touched.
     */
    private function syncShopPages(Shop $shop, Workspace $workspace, array $resJson, int $ownerId): int
    {
        $pages = collect($resJson['shop']['pages'] ?? []);
        $count = 0;

        foreach ($pages as $pageData) {
            if (! isset($pageData['id'])) {
                continue;
            }

            // A page id is globally unique; skip pages already owned by another workspace.
            $existing = Page::withTrashed()->find($pageData['id']);
            if ($existing && $existing->workspace_id !== $workspace->id) {
                continue;
            }

            if ($existing) {
                $existing->update([
                    'shop_id' => $shop->id,
                    'name' => $pageData['name'] ?? $existing->name,
                ]);
            } else {
                Page::create([
                    'id' => $pageData['id'],
                    'workspace_id' => $workspace->id,
                    'shop_id' => $shop->id,
                    'owner_id' => $ownerId,
                    'name' => $pageData['name'] ?? 'Page '.$pageData['id'],
                    'status' => 'active',
                ]);
            }

            $count++;
        }

        return $count;
    }

    public function refreshPages(Request $request, Workspace $workspace, Shop $shop)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::RefreshShops->value, $workspace);

        if ($shop->workspace_id !== $workspace->id) {
            abort(403);
        }

        if (! $shop->pos_token) {
            return redirect()->route('workspaces.shops.index', $workspace)
                ->with('error', 'This shop has no POS token, so its page list cannot be refreshed.');
        }

        $response = Http::get('https://pos.pages.fm/api/v1/shops/'.$shop->id, [
            'api_key' => $shop->pos_token,
        ]);

        if ($response->failed()) {
            return redirect()->route('workspaces.shops.index', $workspace)
                ->with('error', 'Could not reach the POS API to refresh the page list.');
        }

        $count = $this->syncShopPages($shop, $workspace, $response->json(), $request->user()->id);

        return redirect()->route('workspaces.shops.index', $workspace)
            ->with('success', "Page list refreshed. {$count} page(s) synced.");
    }

    public function validatePosToken(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $request->validate([
            'shop_id' => 'required|string',
            'token' => 'required|string',
        ]);

        try {
            $response = Http::timeout(10)->get('https://pos.pages.fm/api/v1/shops/'.$validated['shop_id'], [
                'api_key' => $validated['token'],
            ]);

            if ($response->successful()) {
                return response()->json(['valid' => true, 'message' => 'POS token is valid.', 'data' => $response->json()], 200);
            }

            return response()->json([
                'valid' => false,
                'message' => 'Invalid POS token or shop ID.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Could not reach Pancake API.',
            ]);
        }
    }

    /**
     * Proxy the shop's POS order tags (GET /shops/{id}/orders/tags) for the
     * "Order Tags" modal on the shops page. Read-only and not persisted — the
     * modal always shows what Pancake has right now.
     */
    public function orderTags(Request $request, Workspace $workspace, Shop $shop)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::ViewShops->value, $workspace);

        if ($shop->workspace_id !== $workspace->id) {
            abort(403);
        }

        if (! $shop->pos_token) {
            return response()->json([
                'tags' => [],
                'message' => 'This shop has no POS token, so its order tags cannot be fetched.',
            ], 422);
        }

        try {
            $response = Http::timeout(15)
                ->get('https://pos.pages.fm/api/v1/shops/'.$shop->id.'/orders/tags', [
                    'api_key' => $shop->pos_token,
                ]);
        } catch (\Throwable $e) {
            return response()->json([
                'tags' => [],
                'message' => 'Could not reach the Pancake API.',
            ], 502);
        }

        if ($response->failed()) {
            return response()->json([
                'tags' => [],
                'message' => 'Pancake rejected the request. Check the shop\'s POS token.',
            ], 502);
        }

        return response()->json([
            'tags' => $response->json('data') ?? [],
        ]);
    }

    /**
     * Create the preset order tags (self::ORDER_TAG_PRESETS) on this shop via
     * POST /shops/{id}/orders/tags. A preset whose name already exists on the
     * shop is skipped, so the button is safe to press more than once.
     *
     * Pancake creates tags one at a time with no transaction, so a failure
     * part-way through leaves the earlier tags in place — the response reports
     * each preset's outcome rather than pretending it was all-or-nothing.
     */
    public function createPresetOrderTags(Request $request, Workspace $workspace, Shop $shop)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::EditShops->value, $workspace);

        if ($shop->workspace_id !== $workspace->id) {
            abort(403);
        }

        if (! $shop->pos_token) {
            return response()->json([
                'message' => 'This shop has no POS token, so its order tags cannot be created.',
            ], 422);
        }

        $endpoint = 'https://pos.pages.fm/api/v1/shops/'.$shop->id.'/orders/tags';

        try {
            $existingResponse = Http::timeout(15)->get($endpoint, ['api_key' => $shop->pos_token]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not reach the Pancake API.'], 502);
        }

        if ($existingResponse->failed()) {
            return response()->json([
                'message' => 'Could not read the existing tags, so nothing was created.',
            ], 502);
        }

        // Match on the name alone — the list endpoint does not return each tag's
        // statuses, so an existing tag is left exactly as the shop has it.
        $existingNames = collect($existingResponse->json('data') ?? [])
            ->map(fn ($tag) => Str::lower(trim((string) ($tag['name'] ?? ''))))
            ->all();

        $created = [];
        $skipped = [];
        $failed = [];

        foreach (self::ORDER_TAG_PRESETS as $preset) {
            if (in_array(Str::lower($preset['name']), $existingNames, true)) {
                $skipped[] = $preset['name'];

                continue;
            }

            try {
                $response = Http::timeout(15)
                    ->withQueryParameters(['api_key' => $shop->pos_token])
                    ->post($endpoint, $preset);
            } catch (\Throwable $e) {
                $failed[] = ['name' => $preset['name'], 'message' => 'Could not reach the Pancake API.'];

                continue;
            }

            if ($response->failed() || $response->json('success') === false) {
                $failed[] = [
                    'name' => $preset['name'],
                    'message' => $response->json('message') ?? 'Pancake rejected the tag.',
                ];

                continue;
            }

            $created[] = $preset['name'];
        }

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }

    public function refreshUsers(Request $request, Workspace $workspace, Shop $shop)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::RefreshShops->value, $workspace);

        if ($shop->workspace_id !== $workspace->id) {
            abort(403);
        }

        dispatch(new FetchShopUsers($shop))->onQueue('pancake');

        return redirect()->route('workspaces.shops.index', $workspace);
    }

    public function refreshOrders(Request $request, Workspace $workspace, Shop $shop)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::RefreshShops->value, $workspace);

        if ($shop->workspace_id !== $workspace->id) {
            abort(403);
        }

        $shop->update(['orders_last_synced_at' => null]);

        // Pull the last month across all sources (incl. Webcake). The job advances
        // orders_last_synced_at when it finishes, so the hourly sync resumes from here.
        dispatch(new FetchShopOrders($shop, 1, now()->subMonths(3)->unix(), now()->unix()))
            ->onQueue('pancake');

        return redirect()->route('workspaces.shops.index', $workspace);
    }

    public function destroy(Request $request, Workspace $workspace, Shop $shop)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::DeleteShops->value, $workspace);

        if ($shop->workspace_id !== $workspace->id) {
            abort(403);
        }

        // Deleting the shop cascades (via FK) to pages, team_shop, products and
        // pancake_shop_users. The tables below key off shop_id but have no FK
        // cascade, and the checklist completions are polymorphic — so clean them
        // up explicitly within a transaction alongside the shop delete.
        DB::transaction(function () use ($shop) {
            DB::table('pancake_orders')->where('shop_id', $shop->id)->delete();
            DB::table('pancake_customers')->where('shop_id', $shop->id)->delete();
            DB::table('pancake_order_for_delivery')->where('shop_id', $shop->id)->delete();
            DB::table('parcel_journey_notification_logs')->where('shop_id', $shop->id)->delete();

            $shop->checklistCompletions()->delete();

            $shop->delete();
        });

        return redirect()->route('workspaces.shops.index', $workspace)
            ->with('success', 'Shop and its related data were deleted.');
    }
}
