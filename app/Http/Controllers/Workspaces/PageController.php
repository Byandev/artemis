<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StorePageRequest;
use App\Http\Requests\Workspaces\UpdatePageRequest;
use App\Http\Sorts\Page\OwnerNameSort;
use App\Http\Sorts\Page\ShopNameSort;
use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
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
    public function index(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $pages = QueryBuilder::for(Page::where('pages.workspace_id', $workspace->id))
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
            ])
            ->with(['shop', 'owner'])
            ->paginate(10)
            ->withQueryString();

        $users = User::get(['id', 'name']);

        return Inertia::render('workspaces/pages/index', [
            'pages' => $pages,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'users' => $users,
        ]);
    }

    public function create(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $users = User::get(['id', 'name']);

        return Inertia::render('workspaces/pages/create', [
            'workspace' => $workspace,
            'users' => $users,
        ]);
    }

    public function edit(Request $request, Workspace $workspace, Page $page)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $users = User::get(['id', 'name']);

        return Inertia::render('workspaces/pages/edit', [
            'workspace' => $workspace,
            'page' => $page,
            'users' => $users,
        ]);
    }

    public function store(StorePageRequest $request, Workspace $workspace)
    {

        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $request->validated();

        $response = Http::get('https://pos.pages.fm/api/v1/shops/'.$validated['shop_id'], [
            'api_key' => $validated['pos_token'],
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'pos_token' => 'Invalid API Key. Please double-check and try again.',
            ]);
        }

        $response = $response->json();

        $pages = collect($response['shop']['pages']);

        $page = $pages->firstWhere('id', $validated['id']);

        if (! $page) {
            throw ValidationException::withMessages([
                'id' => 'Page not found',
            ]);
        }

        $shop = Shop::firstOrCreate([
            'id' => $validated['shop_id'],
            'workspace_id' => $workspace->id,
        ], [
            'name' => $response['shop']['name'],
            'avatar_url' => isset($response['shop']['avatar_url']) ? $response['shop']['avatar_url'] : null,
        ]);

        $page = Page::create([
            'id' => $validated['id'],
            'workspace_id' => $workspace->id,
            'owner_id' => $request->user()->id,
            'shop_id' => $validated['shop_id'],
            'name' => $validated['name'],
            'pos_token' => $validated['pos_token'] ?? null,
            'botcake_token' => $validated['botcake_token'] ?? null,
            'infotxt_token' => $validated['infotxt_token'] ?? null,
            'infotxt_user_id' => $validated['infotxt_user_id'] ?? null,
            'pancake_token' => $validated['pancake_token'] ?? null,
            'parcel_journey_enabled' => $validated['parcel_journey_enabled'] ?? null,
            'parcel_journey_flow_id' => $validated['parcel_journey_flow_id'] ?? null,
            'parcel_journey_custom_field_id' => $validated['parcel_journey_custom_field_id'] ?? null,
        ]);

        dispatch(new FetchPageOrders($page, 1, \Carbon\Carbon::now()->subYear(1)->startOfYear()->unix(), \Carbon\Carbon::now()->unix()))->onQueue('pancake');
        dispatch(new FetchShopCustomers($shop, 1, \Carbon\Carbon::now()->subYear(1)->startOfYear()->unix(), \Carbon\Carbon::now()->unix()))->onQueue('pancake');
        dispatch(new FetchShopUsers($shop))->onQueue('pancake');

        return redirect()->route('workspaces.pages.index', $workspace)
            ->with('success', 'Page created successfully.');
    }

    public function update(UpdatePageRequest $request, Workspace $workspace, Page $page)
    {
        $validated = $request->validated();

        $page->update([
            'shop_id' => $validated['shop_id'],
            'name' => $validated['name'],
            'facebook_url' => $validated['facebook_url'] ?? null,
            'pos_token' => $validated['pos_token'] ?? null,
            'botcake_token' => $validated['botcake_token'] ?? null,
            'infotxt_token' => $validated['infotxt_token'] ?? null,
            'infotxt_user_id' => $validated['infotxt_user_id'] ?? null,
            'pancake_token' => $validated['pancake_token'] ?? null,
            'parcel_journey_enabled' => $validated['parcel_journey_enabled'] ?? null,
            'parcel_journey_flow_id' => $validated['parcel_journey_flow_id'] ?? null,
            'parcel_journey_custom_field_id' => $validated['parcel_journey_custom_field_id'] ?? null,
            'owner_id' => $validated['owner_id'],
        ]);

        return redirect()->route('workspaces.pages.index', $workspace)
            ->with('success', 'Page updated successfully.');
    }

    public function refresh(Request $request, Workspace $workspace, Page $page)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Ensure the page belongs to the workspace
        if ($page->workspace_id !== $workspace->id) {
            abort(403);
        }

        $page->update([
            'orders_last_synced_at' => null,
            'is_sync_logic_updated' => true
        ]);

        dispatch(new FetchPageOrders($page, 1, \Carbon\Carbon::now()->subYear()->startOfYear()->unix(), \Carbon\Carbon::now()->unix()))->onQueue('pancake');

        return redirect()->route('workspaces.pages.index', $workspace);
    }

    public function archive(Request $request, Workspace $workspace, Page $page)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        if ($page->workspace_id !== $workspace->id) {
            abort(403);
        }

        $page->archive();

        return redirect()->route('workspaces.pages.index', $workspace)
            ->with('success', 'Page archived successfully.');
    }

    public function restore(Request $request, Workspace $workspace, Page $page)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        if ($page->workspace_id !== $workspace->id) {
            abort(403);
        }

        $page->restore();

        return redirect()->route('workspaces.pages.index', $workspace)
            ->with('success', 'Page restored successfully.');
    }
}
