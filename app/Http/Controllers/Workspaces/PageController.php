<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StorePageRequest;
use App\Http\Requests\Workspaces\UpdatePageRequest;
use App\Http\Sorts\Page\OwnerNameSort;
use App\Http\Sorts\Page\ShopNameSort;
use App\Jobs\FetchPageOrders;
use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
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
            ->select('pages.*')
            ->selectRaw("
        (
            COALESCE(
                (
                    SELECT SUM(s.daily_budget)
                    FROM ad_sets s
                    WHERE s.effective_status = 'ACTIVE'
                      AND s.id IN (
                          SELECT DISTINCT a.ad_set_id
                          FROM ads a
                          WHERE a.page_id = pages.id
                      )
                ), 0
            )
            +
            COALESCE(
                (
                    SELECT SUM(c.daily_budget)
                    FROM campaigns c
                    WHERE c.effective_status = 'ACTIVE'
                      AND c.id IN (
                          SELECT DISTINCT a.campaign_id
                          FROM ads a
                          WHERE a.page_id = pages.id
                      )
                ), 0
            )
        ) AS current_budget
    ")
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
            ])
            ->with(['shop', 'owner'])
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('workspaces/pages/index', [
            'pages' => $pages,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
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

        Shop::firstOrCreate([
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
        ]);

        dispatch(new FetchPageOrders($page, 1, \Carbon\Carbon::now()->subYear(2)->startOfYear()->unix(), \Carbon\Carbon::now()->unix()));

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

        $page->update(['orders_last_synced_at' => null]);

        dispatch(new FetchPageOrders($page, 1, \Carbon\Carbon::now()->subYear()->startOfYear()->unix(), \Carbon\Carbon::now()->unix()));

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
