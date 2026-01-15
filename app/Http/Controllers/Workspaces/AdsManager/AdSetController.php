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
                AllowedFilter::scope('impressions_greater_than'),
                AllowedFilter::scope('clicks_greater_than'),
                AllowedFilter::scope('spend_greater_than'),
                AllowedFilter::scope('daily_budget_greater_than'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
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
        $metrics = $request->input('metrics', ['impressions', 'clicks', 'spend']); // Default metrics

        $adSets = $this->buildQuery($workspace, $request);

        // Add base select
        $adSets->addSelect('ad_sets.*');

        // Aggregate metrics from ad_records based on requested metrics
        foreach ($metrics as $metric) {
            if ($startDate && $endDate) {
                $adSets->selectRaw(
                    "(SELECT COALESCE(SUM({$metric}), 0) FROM ad_records WHERE ad_records.ad_set_id = ad_sets.id AND ad_records.date BETWEEN ? AND ?) as {$metric}",
                    [$startDate, $endDate]
                );
            } else {
                $adSets->selectRaw(
                    "(SELECT COALESCE(SUM({$metric}), 0) FROM ad_records WHERE ad_records.ad_set_id = ad_sets.id) as {$metric}"
                );
            }
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
