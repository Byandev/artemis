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
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PageController extends Controller
{
    
    use AuthorizesRequests;
    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('View Pages', $workspace);

        $pages = QueryBuilder::for(Page::where('pages.workspace_id', $workspace->id))
            ->allowedFilters([AllowedFilter::partial('search', 'name')])
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
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/pages/index', [
            'pages' => $pages,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'users' => User::get(['id', 'name']),
        ]);
    }

    public function create(Request $request, Workspace $workspace)
    {
        $this->authorize('Edit Pages', $workspace);

        return Inertia::render('workspaces/pages/create', [
            'workspace' => $workspace,
            'users' => User::get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, Workspace $workspace, Page $page)
    {
        $this->authorize('Edit Pages', $workspace);

        return Inertia::render('workspaces/pages/edit', [
            'workspace' => $workspace,
            'page' => $page,
            'users' => User::get(['id', 'name']),
        ]);
    }

    public function store(StorePageRequest $request, Workspace $workspace)
    {
        $this->authorize('Edit Pages', $workspace);

        $validated = $request->validated();
        $response = Http::get('https://pos.pages.fm/api/v1/shops/' . $validated['shop_id'], [
            'api_key' => $validated['pos_token'],
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages(['pos_token' => 'Invalid API Key.']);
        }

        $resJson = $response->json();
        $pageData = collect($resJson['shop']['pages'])->firstWhere('id', $validated['id']);

        if (!$pageData) {
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
            'status' => $validated['status'] ?? 'active',
            // ... (rest of your field assignments)
        ]);

        dispatch(new FetchPageOrders($page, 1, now()->subMonth()->unix(), now()->unix()))->onQueue('pancake');

        return redirect()->route('workspaces.pages.index', $workspace)->with('success', 'Page created.');
    }

    public function update(UpdatePageRequest $request, Workspace $workspace, Page $page)
    {
        $this->authorize('Edit Pages', $workspace);

        $page->update($request->validated());

        return redirect()->route('workspaces.pages.index', $workspace)->with('success', 'Page updated.');
    }

    public function refresh(Request $request, Workspace $workspace, Page $page)
    {
        $this->authorize('Refresh Pages', $workspace);

        if ($page->workspace_id !== $workspace->id)
            abort(403);

        $page->update(['orders_last_synced_at' => null, 'is_sync_logic_updated' => true]);
        dispatch(new FetchPageOrders($page, 1, now()->subMonth()->unix(), now()->unix()))->onQueue('pancake');

        return redirect()->route('workspaces.pages.index', $workspace);
    }

    public function archive(Request $request, Workspace $workspace, Page $page)
    {
        $this->authorize('Archive Pages', $workspace);

        if ($page->workspace_id !== $workspace->id)
            abort(403);

        $page->deactivate();
        return redirect()->route('workspaces.pages.index', $workspace);
    }

    public function restore(Request $request, Workspace $workspace, Page $page)
    {
        $this->authorize('Archive Pages', $workspace);

        if ($page->workspace_id !== $workspace->id)
            abort(403);

        $page->activate();
        return redirect()->route('workspaces.pages.index', $workspace);
    }
}