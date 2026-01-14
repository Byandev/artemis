<?php

namespace App\Http\Controllers\Workspaces\AdsManager;

use App\Http\Controllers\Controller;
use App\Models\AdSet;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AdSetController extends Controller
{
    /**
     * Build the query for ad sets.
     */
    private function buildQuery(Workspace $workspace, Request $request)
    {
        return QueryBuilder::for(
            AdSet::query()
                ->whereHas('adAccount.facebook_accounts.workspaces', function ($query) use ($workspace) {
                    $query->where('workspace_id', $workspace->id);
                })
        )
            ->with(['campaign', 'adAccount'])
            ->allowedFilters([
                AllowedFilter::scope('search'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts([
                'name',
                'status',
                'impressions',
                'clicks',
                'spend',
                'daily_budget',
                'campaign_id',
                'created_at',
                'updated_at',
            ])
            ->defaultSort('-created_at');
    }

    public function index(Workspace $workspace, Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $adSets = $this->buildQuery($workspace, $request);

        // Aggregate metrics from ad_records for the given date range
        if ($startDate && $endDate) {
            $adSets->addSelect('ad_sets.*')
                ->selectRaw(
                    '(SELECT COALESCE(SUM(impressions), 0) FROM ad_records WHERE ad_records.ad_set_id = ad_sets.id AND ad_records.date BETWEEN ? AND ?) as impressions',
                    [$startDate, $endDate]
                )
                ->selectRaw(
                    '(SELECT COALESCE(SUM(clicks), 0) FROM ad_records WHERE ad_records.ad_set_id = ad_sets.id AND ad_records.date BETWEEN ? AND ?) as clicks',
                    [$startDate, $endDate]
                )
                ->selectRaw(
                    '(SELECT COALESCE(SUM(spend), 0) FROM ad_records WHERE ad_records.ad_set_id = ad_sets.id AND ad_records.date BETWEEN ? AND ?) as spend',
                    [$startDate, $endDate]
                );
        } else {
            // Get all data when no date range is specified
            $adSets->addSelect('ad_sets.*')
                ->selectRaw(
                    '(SELECT COALESCE(SUM(impressions), 0) FROM ad_records WHERE ad_records.ad_set_id = ad_sets.id) as impressions'
                )
                ->selectRaw(
                    '(SELECT COALESCE(SUM(clicks), 0) FROM ad_records WHERE ad_records.ad_set_id = ad_sets.id) as clicks'
                )
                ->selectRaw(
                    '(SELECT COALESCE(SUM(spend), 0) FROM ad_records WHERE ad_records.ad_set_id = ad_sets.id) as spend'
                );
        }

        return Inertia::render('workspaces/ads-manager/ad-sets', [
            'workspace' => $workspace,
            'adSets' => $adSets
                ->paginate($request->get('perPage', 10))
                ->withQueryString(),
            'query' => [
                'sort' => $request->get('sort'),
                'perPage' => $request->get('perPage', 10),
                'page' => $request->get('page', 1),
                'filter' => [
                    'search' => $request->get('filter.search'),
                    'status' => $request->get('filter.status'),
                    'start_date' => $request->get('start_date'),
                    'end_date' => $request->get('end_date'),
                ],
            ],
        ]);
    }
}
