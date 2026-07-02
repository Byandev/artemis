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

        $response = Http::get('https://pos.pages.fm/api/v1/shops/'.$validated['shop_id'], [
            'api_key' => $validated['pos_token'],
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages(['pos_token' => 'Invalid API Key.']);
        }

        $resJson = $response->json();

        if (Shop::where('id', $validated['shop_id'])->where('workspace_id', $workspace->id)->exists()) {
            throw ValidationException::withMessages(['shop_id' => 'This shop has already been added to this workspace.']);
        }

        $shop = Shop::create([
            'id' => $validated['shop_id'],
            'workspace_id' => $workspace->id,
            'name' => $resJson['shop']['name'] ?? 'Shop '.$validated['shop_id'],
            'avatar_url' => $resJson['shop']['avatar_url'] ?? null,
            'pos_token' => $validated['pos_token'],
        ]);

        $createdPages = $this->syncShopPages($shop, $workspace, $resJson, $request->user()->id);

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
        dispatch(new FetchShopOrders($shop, 1, now()->subMonths(2)->unix(), now()->unix()))
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
