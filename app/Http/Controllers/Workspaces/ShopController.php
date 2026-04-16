<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Sorts\PendingRequiredChecklistsSort;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Pancake\Jobs\FetchShopCustomers;
use Modules\Pancake\Jobs\FetchShopUsers;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ShopController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

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
            ->select('shops.*')
            ->selectSub($pendingChecklistsSub, 'pending_required_checklists_count');

        $pages = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts([
                'name',
                'created_at',
                'customers_last_synced_at',
                'deleted_at',
                AllowedSort::custom('pending_required_checklists_count', new PendingRequiredChecklistsSort),
            ])
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/shops/index', [
            'pages' => $pages,
            'workspace' => $workspace,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function refresh(Request $request, Workspace $workspace, Shop $shop)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Ensure the page belongs to the workspace
        if ($shop->workspace_id !== $workspace->id) {
            abort(403);
        }

        $shop->update(['customers_last_synced_at' => null]);

        dispatch(new FetchShopCustomers($shop, 1, \Carbon\Carbon::now()->subMonth()->unix(), \Carbon\Carbon::now()->unix()))->onQueue('pancake');
        dispatch(new FetchShopUsers($shop))->onQueue('pancake');

        return redirect()->route('workspaces.shops.index', $workspace);
    }
}
