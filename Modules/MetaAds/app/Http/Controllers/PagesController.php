<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PagesController extends Controller
{
    /**
     * Pages page — every workspace page, with a live rollup of the daily and
     * lifetime budget across the ad-sets whose ads point at that page (joined
     * on `pages.id` = `meta_ads_creatives.meta_page_id`).
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        // Distinct (page, ad-set) pairs derived from creatives. We can't sum
        // ad-set budgets directly because one ad-set has many ads, all sharing
        // the same budget — we need DISTINCT on the ad-set side.
        $pageAdSetPairs = DB::table('meta_ads_ads as a')
            ->join('meta_ads_creatives as c', 'c.id', '=', 'a.meta_ads_creative_id')
            ->whereNotNull('c.meta_page_id')
            ->select('c.meta_page_id', 'a.meta_ads_set_id')
            ->distinct();

        $budgetRollup = DB::query()
            ->fromSub($pageAdSetPairs, 'ps')
            ->join('meta_ads_sets as s', 's.id', '=', 'ps.meta_ads_set_id')
            ->select(
                'ps.meta_page_id',
                DB::raw('SUM(s.daily_budget) AS daily_budget'),
                DB::raw('SUM(s.lifetime_budget) AS lifetime_budget'),
                DB::raw('COUNT(DISTINCT ps.meta_ads_set_id) AS ad_sets_count'),
            )
            ->groupBy('ps.meta_page_id');

        $base = Page::query()
            ->where('pages.workspace_id', $workspace->id)
            ->leftJoinSub($budgetRollup, 'b', 'b.meta_page_id', '=', 'pages.id')
            ->select(
                'pages.id',
                'pages.name',
                'pages.facebook_url',
                'pages.shop_id',
                'pages.owner_id',
                'pages.orders_last_synced_at',
                DB::raw('COALESCE(b.daily_budget, 0) AS daily_budget'),
                DB::raw('COALESCE(b.lifetime_budget, 0) AS lifetime_budget'),
                DB::raw('COALESCE(b.ad_sets_count, 0) AS ad_sets_count'),
            );

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
