<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StorePageRequest;
use App\Http\Requests\Workspaces\UpdatePageRequest;
use App\Http\Sorts\Page\OwnerNameSort;
use App\Http\Sorts\Page\ShopNameSort;
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
use Modules\Pancake\Jobs\FetchPageOrders;
use Modules\Pancake\Jobs\FetchShopCustomers;
use Modules\Pancake\Jobs\FetchShopUsers;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class PageController extends Controller
{
    use AuthorizesRequests;

    private function assertPageLimitNotReached(Workspace $workspace): void
    {
        $info = $workspace->pageLimitInfo();

        if ($info['reached']) {
            throw ValidationException::withMessages([
                'page_limit' => "You've reached your plan's page limit ({$info['limit']}). Upgrade your plan to add more pages.",
            ]);
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::ViewPages->value, $workspace);

        $pendingChecklistsSub = DB::table('workspace_checklists as wc')
            ->selectRaw('COUNT(*)')
            ->where('wc.workspace_id', $workspace->id)
            ->where('wc.target', 'Page')
            ->where('wc.required', true)
            ->whereNotExists(function ($sub) use ($workspace) {
                $sub->select(DB::raw(1))
                    ->from('workspace_checklist_completions as wcc')
                    ->whereColumn('wcc.workspace_checklist_id', 'wc.id')
                    ->whereColumn('wcc.target_id', 'pages.id')
                    ->where('wcc.workspace_id', $workspace->id)
                    ->where('wcc.target_type', Page::class);
            });

        $baseQuery = Page::where('pages.workspace_id', $workspace->id)
            ->select('pages.*')
            ->selectSub($pendingChecklistsSub, 'pending_required_checklists_count');

        $pages = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts([
                'name',
                'created_at',
                'orders_last_synced_at',
                'deleted_at',
                AllowedSort::custom('shop_name', new ShopNameSort),
                AllowedSort::custom('owner_name', new OwnerNameSort),
                'parcel_journey_enabled',
                AllowedSort::custom('pending_required_checklists_count', new PendingRequiredChecklistsSort),
            ])
            ->with(['shop', 'owner'])
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $pageLimitInfo = $workspace->pageLimitInfo();

        return Inertia::render('workspaces/pages/index', [
            'pages' => $pages,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'users' => $workspace->users()->get(['users.id', 'users.name']),
            'pageLimit' => $pageLimitInfo['limit'],
            'pageCount' => $pageLimitInfo['count'],
            'pageLimitReached' => $pageLimitInfo['reached'],
        ]);
    }

    public function create(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::EditPages->value, $workspace);

        $info = $workspace->pageLimitInfo();
        if ($info['reached']) {
            return redirect()
                ->route('workspaces.pages.index', $workspace)
                ->with('error', "You've reached your plan's page limit ({$info['limit']}). Upgrade your plan to add more pages.");
        }

        return Inertia::render('workspaces/pages/create', [
            'workspace' => $workspace,
            'users' => $workspace->users()->get(['users.id', 'users.name']),
        ]);
    }

    public function edit(Request $request, Workspace $workspace, Page $page)
    {
        $this->authorize(Permission::EditPages->value, $workspace);

        return Inertia::render('workspaces/pages/edit', [
            'workspace' => $workspace,
            'page' => $page,
            'users' => $workspace->users()->get(['users.id', 'users.name']),
        ]);
    }

    public function store(StorePageRequest $request, Workspace $workspace)
    {
        $this->authorize(Permission::EditPages->value, $workspace);

        $this->assertPageLimitNotReached($workspace);

        $validated = $request->validated();
        $response = Http::get('https://pos.pages.fm/api/v1/shops/'.$validated['shop_id'], [
            'api_key' => $validated['pos_token'],
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages(['pos_token' => 'Invalid API Key.']);
        }

        $resJson = $response->json();
        $pageData = collect($resJson['shop']['pages'])->firstWhere('id', $validated['id']);

        if (! $pageData) {
            throw ValidationException::withMessages(['id' => 'Page not found']);
        }

        $shop = Shop::firstOrCreate([
            'id' => $validated['shop_id'],
            'workspace_id' => $workspace->id,
        ], [
            'name' => $resJson['shop']['name'],
            'avatar_url' => $resJson['shop']['avatar_url'] ?? null,
        ]);

        $page = Page::create([
            'id' => $validated['id'],
            'workspace_id' => $workspace->id,
            'owner_id' => $request->user()->id,
            'shop_id' => $validated['shop_id'],
            'name' => $validated['name'],
            'pos_token' => $validated['pos_token'] ?? null,
            'botcake_token' => $validated['botcake_token'] ?? null,
            'pancake_token' => $validated['pancake_token'] ?? null,
            'infotxt_token' => $validated['infotxt_token'] ?? null,
            'infotxt_user_id' => $validated['infotxt_user_id'] ?? null,
            'parcel_journey_custom_field_id' => $validated['parcel_journey_custom_field_id'] ?? null,
            'parcel_journey_flow_id' => $validated['parcel_journey_flow_id'] ?? null,
            'parcel_journey_enabled' => $validated['parcel_journey_enabled'] ?? false,
            'status' => $validated['status'] ?? 'active',
        ]);

        dispatch(new FetchPageOrders($page, 1, Carbon::now()->subMonth()->unix(), Carbon::now()->unix()))->onQueue('pancake');
        dispatch(new FetchShopCustomers($shop, 1, Carbon::now()->subMonth()->unix(), Carbon::now()->unix()))->onQueue('pancake');
        dispatch(new FetchShopUsers($shop))->onQueue('pancake');

        (new PostHogService)->capture((string) $request->user()->id, 'page_connected', [
            'workspace_id' => $workspace->id,
            'page_id' => $page->id,
            'page_name' => $page->name,
            'shop_id' => $shop->id,
        ]);

        return redirect()->route('workspaces.pages.index', $workspace)
            ->with('success', 'Page created successfully.');
    }

    public function update(UpdatePageRequest $request, Workspace $workspace, Page $page)
    {
        $this->authorize(Permission::EditPages->value, $workspace);

        $page->update($request->validated());

        return redirect()->route('workspaces.pages.index', $workspace)->with('success', 'Page updated successfully.');
    }

    public function refresh(Request $request, Workspace $workspace, Page $page)
    {
        $this->authorize(Permission::RefreshPages->value, $workspace);

        if ($page->workspace_id !== $workspace->id) {
            abort(403);
        }

        $page->update(['orders_last_synced_at' => null, 'is_sync_logic_updated' => true]);
        dispatch(new FetchPageOrders($page, 1, now()->subMonth()->unix(), now()->unix()))->onQueue('pancake');

        return redirect()->route('workspaces.pages.index', $workspace);
    }

    public function archive(Request $request, Workspace $workspace, Page $page)
    {
        $this->authorize(Permission::ArchivePages->value, $workspace);

        if ($page->workspace_id !== $workspace->id) {
            abort(403);
        }

        $page->deactivate();

        return redirect()->route('workspaces.pages.index', $workspace)->with('success', 'Page archived successfully.');
    }

    public function restore(Request $request, Workspace $workspace, Page $page)
    {
        $this->authorize(Permission::ArchivePages->value, $workspace);

        if ($page->workspace_id !== $workspace->id) {
            abort(403);
        }

        $page->activate();

        return redirect()->route('workspaces.pages.index', $workspace);
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

    public function validatePancakeToken(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $request->validate([
            'page_id' => 'required|string',
            'token' => 'required|string',
        ]);

        try {
            $response = Http::timeout(10)->get('https://pages.fm/api/public_api/v1/pages/'.$validated['page_id'].'/page_customers', [
                'page_access_token' => $validated['token'],
            ]);

            if ($response->successful() && $response->json()['success']) {
                return response()->json(['valid' => true, 'message' => 'Pancake token is valid.', 'data' => $response->json()], 200);
            }

            return response()->json([
                'valid' => false,
                'message' => 'Invalid Pancake token',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Could not reach Pancake API.',
            ]);
        }
    }

    public function validateBotcakeToken(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $request->validate([
            'page_id' => 'required|string',
            'token' => 'required|string',
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeader('access-token', $validated['token'])
                ->get('https://botcake.io/api/public_api/v1/pages/'.$validated['page_id'].'/flows/');

            if ($response->successful()) {
                return response()->json(['valid' => true, 'message' => 'Botcake token is valid.']);
            }

            return response()->json([
                'valid' => false,
                'message' => 'Invalid Botcake token or page ID.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Could not reach Botcake API.',
            ]);
        }
    }
}
