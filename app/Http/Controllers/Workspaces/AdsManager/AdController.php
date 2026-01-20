<?php

namespace App\Http\Controllers\Workspaces\AdsManager;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AdController extends Controller
{
    /**
     * Build the query for ads.
     */
    private function buildQuery(Workspace $workspace, Request $request)
    {
        return QueryBuilder::for(
            Ad::query()
                ->whereHas('adAccount.facebook_accounts.workspaces', function ($query) use ($workspace) {
                    $query->where('workspace_id', $workspace->id);
                })
        )
            ->with(['campaign', 'adSet', 'adAccount'])
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
                'campaign_id',
                'ad_set_id',
                'created_at',
                'updated_at',
            ])
            ->defaultSort('-created_at');
    }

    public function index(Workspace $workspace, Request $request)
    {
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');
        $metrics = $request->input('metrics', []);

        $ads = $this->buildQuery($workspace, $request);

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

                            $ads->having($metric, $sqlOperator, $value)
                                ->groupBy('ads.id');
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log error if needed
            }
        }

        // Add base select
        $ads->addSelect('ads.*');

        // Aggregate metrics from ad_records based on requested metrics
        foreach ($metrics as $metric) {
            if ($startDate && $endDate) {
                $ads->selectRaw(
                    "(SELECT COALESCE(SUM({$metric}), 0) FROM ad_records WHERE ad_records.ad_id = ads.id AND ad_records.date BETWEEN ? AND ?) as {$metric}",
                    [$startDate, $endDate]
                );
            } else {
                $ads->selectRaw(
                    "(SELECT COALESCE(SUM({$metric}), 0) FROM ad_records WHERE ad_records.ad_id = ads.id) as {$metric}"
                );
            }
        }

        return Inertia::render('workspaces/ads-manager/ads', [
            'workspace' => $workspace,
            'ads' => $ads
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
