<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StorePageRequest;
use App\Http\Requests\Workspaces\UpdatePageRequest;
use App\Jobs\FetchPageOrders;
use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PageController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $query = Page::ofWorkspace($workspace)
            ->with(['shop', 'owner'])
            // Filter by archive status
            ->when($request->get('status') === 'archived', function ($q) {
                $q->archived();
            }, function ($q) {
                $q->active();
            })
            // Filter by owner
            ->when($request->filled('owner_id'), function ($q) use ($request) {
                $q->where('owner_id', $request->get('owner_id'));
            })
            // Filter by shop (product)
            ->when($request->filled('shop_id'), function ($q) use ($request) {
                $q->where('shop_id', $request->get('shop_id'));
            })
            // Search by name
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->get('search') . '%');
            });

        // Sorting
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        
        $allowedSortFields = ['name', 'shop_id', 'owner_id', 'created_at', 'orders_last_synced_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        // Pagination
        $pages = $query->paginate(10)->withQueryString();

        // Get filter options
        $owners = User::whereIn('id', Page::ofWorkspace($workspace)->pluck('owner_id')->unique())
            ->select('id', 'name')
            ->get();

        $shops = Shop::whereIn('id', Page::ofWorkspace($workspace)->pluck('shop_id')->unique())
            ->select('id', 'name')
            ->get();

        return Inertia::render('workspaces/pages/index', [
            'pages' => $pages,
            'workspace' => $workspace,
            'filters' => [
                'search' => $request->get('search', ''),
                'owner_id' => $request->get('owner_id', ''),
                'shop_id' => $request->get('shop_id', ''),
                'status' => $request->get('status', 'active'),
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
            'owners' => $owners,
            'shops' => $shops,
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

        dispatch(new FetchPageOrders($page, 1, \Carbon\Carbon::now()->subMonths(2)->startOfMonth()->unix(), \Carbon\Carbon::now()->unix()));

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

        dispatch(new FetchPageOrders($page, 1, \Carbon\Carbon::now()->subMonths(2)->startOfMonth()->unix(), \Carbon\Carbon::now()->unix()));

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
