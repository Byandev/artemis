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
        $query = QueryBuilder::for(
            Campaign::query()
                ->whereHas('adAccount.facebook_accounts.workspaces', function ($query) use ($workspace) {
                    $query->where('workspace_id', $workspace->id);
                })
        )
            ->with(['adAccount'])
            ->allowedFilters([
                AllowedFilter::scope('search'),
                AllowedFilter::exact('status'),
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

        return $query;
    }

    public function index(Workspace $workspace, Request $request)
    {
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');
        $metrics = $request->input('metrics', []); // Default metrics

        $campaigns = $this->buildQuery($workspace, $request);

        // Apply metric filters (outside of QueryBuilder to avoid filter validation)
        if ($request->filled('metric_filters')) {
            try {
                $metricFilters = json_decode(urldecode($request->input('metric_filters')), true);
                if (is_array($metricFilters)) {
                    foreach ($metricFilters as $filter) {
                        $metric = $filter['metric'] ?? null;
                        $operator = $filter['operator'] ?? null;
                        $value = $filter['value'] ?? null;

                        if ($metric && $operator && $value !== null) {
                            $sqlOperator = match ($operator) {
                                'greater_than' => '>',
                                'less_than' => '<',
                                'equal' => '=',
                                'greater_than_or_equal' => '>=',
                                'less_than_or_equal' => '<=',
                                default => '='
                            };

                            $campaigns->having($metric, $sqlOperator, $value)
                                ->groupBy('campaigns.id');
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log error if needed
            }
        }

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
                    'start_date' => $request->get('filter.start_date'),
                    'end_date' => $request->get('filter.end_date'),
                ],
                'metric_filters' => $request->get('metric_filters'),
                'metrics' => $metrics,
            ],
        ]);
    }
}
