<?php

namespace App\Http\Controllers\Workspaces\AdsManager;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CampaignController extends Controller
{
    /**
     * Build the query for campaigns.
     */
    private function buildQuery(Workspace $workspace, Request $request)
    {
        return QueryBuilder::for(
            Campaign::query()
                ->whereHas('adAccount.facebook_accounts.workspaces', function ($query) use ($workspace) {
                    $query->where('workspace_id', $workspace->id);
                })
        )
            ->with(['adAccount'])
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
                'start_time',
                'end_time',
                'ad_account_id',
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

        $campaigns = $this->buildQuery($workspace, $request);

        // Add base select
        $campaigns->addSelect('campaigns.*');

        // Aggregate metrics from ad_records based on requested metrics
        foreach ($metrics as $metric) {
            if ($startDate && $endDate) {
                $campaigns->selectRaw(
                    "(SELECT COALESCE(SUM({$metric}), 0) FROM ad_records WHERE ad_records.campaign_id = campaigns.id AND ad_records.date BETWEEN ? AND ?) as {$metric}",
                    [$startDate, $endDate]
                );
            } else {
                $campaigns->selectRaw(
                    "(SELECT COALESCE(SUM({$metric}), 0) FROM ad_records WHERE ad_records.campaign_id = campaigns.id) as {$metric}"
                );
            }
        }

        return Inertia::render('workspaces/ads-manager/campaigns', [
            'workspace' => $workspace,
            'campaigns' => $campaigns
                ->paginate($request->get('perPage', 10))
                ->withQueryString(),
            'query' => [
                'sort' => $request->get('sort'),
                'perPage' => $request->get('perPage', 10),
                'page' => $request->get('page', 1),
                'filter' => [
                    'search' => $request->get('filter.search'),
                    'status' => $request->get('filter.status'),
                    'impressions_greater_than' => $request->get('filter.impressions_greater_than'),
                    'clicks_greater_than' => $request->get('filter.clicks_greater_than'),
                    'spend_greater_than' => $request->get('filter.spend_greater_than'),
                    'daily_budget_greater_than' => $request->get('filter.daily_budget_greater_than'),
                    'start_date' => $request->get('start_date'),
                    'end_date' => $request->get('end_date'),
                ],
            ],
        ]);
    }
}
