<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PagesController extends Controller
{
    /**
     * Pages page — every workspace page, with a live rollup of the daily and
     * lifetime budget across the ad-sets that target that page (joined on
     * `pages.id` = `meta_ads_sets.meta_page_id`).
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $base = Page::query()
            ->where('pages.workspace_id', $workspace->id)
            ->select(
                'pages.id',
                'pages.name',
                'pages.facebook_url',
                'pages.shop_id',
                'pages.owner_id',
                'pages.orders_last_synced_at',
            )
            ->selectRaw("COALESCE((SELECT SUM(s.daily_budget) FROM meta_ads_sets s WHERE s.meta_page_id = pages.id AND s.effective_status = 'ACTIVE'), 0) AS daily_budget")
            ->selectRaw("COALESCE((SELECT SUM(s.lifetime_budget) FROM meta_ads_sets s WHERE s.meta_page_id = pages.id AND s.effective_status = 'ACTIVE'), 0) AS lifetime_budget")
            ->selectRaw("COALESCE((SELECT COUNT(*) FROM meta_ads_sets s WHERE s.meta_page_id = pages.id AND s.effective_status = 'ACTIVE'), 0) AS ad_sets_count");

        $pages = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'pages.name'),
            ])
            ->allowedSorts(['name', 'daily_budget', 'lifetime_budget', 'ad_sets_count', 'orders_last_synced_at'])
            ->defaultSort('-daily_budget')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('workspaces/integrations/meta-pages', [
            'workspace' => $workspace,
            'pages' => $pages,
            'query' => [
                ...$request->only(['sort', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
