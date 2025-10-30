<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Jobs\FetchPageOrders;
use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PageController extends Controller
{
    public function index(Workspace $workspace)
    {
        $pages = Page::ofWorkspace($workspace)
            ->paginate();

        return Inertia::render('workspaces/pages/index', [
            'pages' => $pages,
            'workspace' => $workspace,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'shop_id' => 'required|integer',
            'name' => 'required|string',
            'pos_token' => 'required|string',
            'botcake_token' => 'nullable|string',
            'infotxt_token' => 'nullable|string',
            'infotxt_user_id' => 'nullable|string',
        ]);

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
            'owner_id' => auth()->id(),
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

    public function update(Request $request, Workspace $workspace, Page $page)
    {
        // Ensure the page belongs to the workspace
        if ($page->workspace_id !== $workspace->id) {
            abort(403);
        }

        $validated = $request->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'name' => 'required|string|max:255',
            'facebook_url' => 'nullable|url|max:500',
            'pos_token' => 'nullable|string|max:255',
            'botcake_token' => 'nullable|string|max:255',
            'infotxt_token' => 'nullable|string|max:255',
            'infotxt_user_id' => 'nullable|string|max:255',
        ]);

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
}
