<?php

namespace App\Support\Analytics;

use App\Models\WorkspacePageDailyMetric;
use Illuminate\Support\Facades\DB;

class PageDailyMetricsBuilder
{
    public function rebuild(int $workspaceId, int $pageId, string $date): void
    {
        $start = $date.' 00:00:00';
        $end = $date.' 23:59:59';

        $row = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->where('page_id', $pageId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('confirmed_at', [$start, $end])
                    ->orWhereBetween('shipped_at', [$start, $end])
                    ->orWhereBetween('delivered_at', [$start, $end])
                    ->orWhereBetween('returning_at', [$start, $end])
                    ->orWhereBetween('returned_at', [$start, $end])
                    ->orWhereBetween('first_delivery_attempt', [$start, $end]);
            })
            ->selectRaw('
                SUM(CASE WHEN confirmed_at BETWEEN ? AND ? AND status NOT IN (6, 7) THEN 1 ELSE 0 END) AS confirmed_count,
                SUM(CASE WHEN confirmed_at BETWEEN ? AND ? AND status NOT IN (6, 7) THEN final_amount ELSE 0 END) AS confirmed_amount,

                SUM(CASE WHEN shipped_at BETWEEN ? AND ? AND status NOT IN (6, 7) THEN 1 ELSE 0 END) AS shipped_count,
                SUM(CASE WHEN shipped_at BETWEEN ? AND ? AND status NOT IN (6, 7) THEN final_amount ELSE 0 END) AS shipped_amount,

                SUM(CASE WHEN delivered_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN delivered_at BETWEEN ? AND ? THEN final_amount ELSE 0 END) AS delivered_amount,

                SUM(CASE WHEN returning_at BETWEEN ? AND ? AND status NOT IN (6, 7) THEN 1 ELSE 0 END) AS entered_returning_count,
                SUM(CASE WHEN returning_at BETWEEN ? AND ? AND status NOT IN (6, 7) THEN final_amount ELSE 0 END) AS entered_returning_amount,

                SUM(CASE WHEN returned_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS returned_count,
                SUM(CASE WHEN returned_at BETWEEN ? AND ? THEN final_amount ELSE 0 END) AS returned_amount,

                COALESCE(SUM(CASE WHEN shipped_at BETWEEN ? AND ? AND confirmed_at IS NOT NULL AND status NOT IN (6, 7) THEN TIMESTAMPDIFF(DAY, confirmed_at, shipped_at) ELSE 0 END), 0) AS sum_days_confirmed_to_shipped,
                SUM(CASE WHEN shipped_at BETWEEN ? AND ? AND confirmed_at IS NOT NULL AND status NOT IN (6, 7) THEN 1 ELSE 0 END) AS count_confirmed_to_shipped,

                COALESCE(SUM(CASE WHEN first_delivery_attempt BETWEEN ? AND ? AND confirmed_at IS NOT NULL AND status NOT IN (6, 7) THEN TIMESTAMPDIFF(DAY, confirmed_at, first_delivery_attempt) ELSE 0 END), 0) AS sum_days_confirmed_to_first_attempt,
                SUM(CASE WHEN first_delivery_attempt BETWEEN ? AND ? AND confirmed_at IS NOT NULL AND status NOT IN (6, 7) THEN 1 ELSE 0 END) AS count_confirmed_to_first_attempt,

                COALESCE(SUM(CASE WHEN delivered_at BETWEEN ? AND ? AND confirmed_at IS NOT NULL AND status NOT IN (6, 7) THEN TIMESTAMPDIFF(DAY, confirmed_at, delivered_at) ELSE 0 END), 0) AS sum_days_confirmed_to_delivered,
                SUM(CASE WHEN delivered_at BETWEEN ? AND ? AND confirmed_at IS NOT NULL AND status NOT IN (6, 7) THEN 1 ELSE 0 END) AS count_confirmed_to_delivered,

                COALESCE(SUM(CASE WHEN first_delivery_attempt BETWEEN ? AND ? AND shipped_at IS NOT NULL AND status NOT IN (6, 7) THEN TIMESTAMPDIFF(DAY, shipped_at, first_delivery_attempt) ELSE 0 END), 0) AS sum_days_shipped_to_first_attempt,
                SUM(CASE WHEN first_delivery_attempt BETWEEN ? AND ? AND shipped_at IS NOT NULL AND status NOT IN (6, 7) THEN 1 ELSE 0 END) AS count_shipped_to_first_attempt,

                COALESCE(SUM(CASE WHEN delivered_at BETWEEN ? AND ? AND shipped_at IS NOT NULL AND status NOT IN (6, 7) THEN TIMESTAMPDIFF(DAY, shipped_at, delivered_at) ELSE 0 END), 0) AS sum_days_shipped_to_delivered,
                SUM(CASE WHEN delivered_at BETWEEN ? AND ? AND shipped_at IS NOT NULL AND status NOT IN (6, 7) THEN 1 ELSE 0 END) AS count_shipped_to_delivered,

                COALESCE(SUM(CASE WHEN returned_at BETWEEN ? AND ? AND returning_at IS NOT NULL THEN TIMESTAMPDIFF(DAY, returning_at, returned_at) ELSE 0 END), 0) AS sum_days_returning_to_returned,
                SUM(CASE WHEN returned_at BETWEEN ? AND ? AND returning_at IS NOT NULL THEN 1 ELSE 0 END) AS count_returning_to_returned,

                COALESCE(SUM(CASE WHEN delivered_at BETWEEN ? AND ? AND delivery_attempts IS NOT NULL THEN delivery_attempts ELSE 0 END), 0) AS sum_delivery_attempts_delivered,
                SUM(CASE WHEN delivered_at BETWEEN ? AND ? AND delivery_attempts IS NOT NULL THEN 1 ELSE 0 END) AS count_delivery_attempts_delivered,

                COALESCE(SUM(CASE WHEN returning_at BETWEEN ? AND ? AND status NOT IN (6, 7) AND delivery_attempts IS NOT NULL THEN delivery_attempts ELSE 0 END), 0) AS sum_delivery_attempts_returned,
                SUM(CASE WHEN returning_at BETWEEN ? AND ? AND status NOT IN (6, 7) AND delivery_attempts IS NOT NULL THEN 1 ELSE 0 END) AS count_delivery_attempts_returned
            ', array_merge(
                // 10 existing event count/amount pairs (10 binding pairs)
                [$start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end],
                // 6 latency pairs (12 binding pairs: sum + count for each)
                [$start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $start, $end],
                // 2 attempts pairs (4 binding pairs)
                [$start, $end, $start, $end, $start, $end, $start, $end],
            ))
            ->first();

        // Meta-reported ad spend rolled up to the page for the day (insights are
        // keyed by ad set, whose meta_page_id equals the local Page id). Mirrors
        // App\Queries\PageRoasTrackerQuery::adSpend() so saved ROAS matches the tracker.
        $adSpend = (float) DB::table('meta_ads_insights')
            ->join('meta_ads_sets as s', 's.id', '=', 'meta_ads_insights.meta_ads_set_id')
            ->where('s.meta_page_id', $pageId)
            ->whereBetween('meta_ads_insights.date', [$date, $date])
            ->sum('meta_ads_insights.spend');

        $metrics = [
            'confirmed_count' => (int) ($row->confirmed_count ?? 0),
            'confirmed_amount' => (float) ($row->confirmed_amount ?? 0),
            // Page ROAS tracker values. The tracker's "orders"/"sales" are the
            // confirmed_count / confirmed_amount above, so we only store ad spend
            // and derive ROAS = confirmed_amount / ad_spend.
            'ad_spend' => $adSpend,
            'roas' => $adSpend > 0 ? round(((float) ($row->confirmed_amount ?? 0)) / $adSpend, 2) : 0.0,
            'shipped_count' => (int) ($row->shipped_count ?? 0),
            'shipped_amount' => (float) ($row->shipped_amount ?? 0),
            'delivered_count' => (int) ($row->delivered_count ?? 0),
            'delivered_amount' => (float) ($row->delivered_amount ?? 0),
            'entered_returning_count' => (int) ($row->entered_returning_count ?? 0),
            'entered_returning_amount' => (float) ($row->entered_returning_amount ?? 0),
            'returned_count' => (int) ($row->returned_count ?? 0),
            'returned_amount' => (float) ($row->returned_amount ?? 0),
            'sum_days_confirmed_to_shipped' => (int) ($row->sum_days_confirmed_to_shipped ?? 0),
            'count_confirmed_to_shipped' => (int) ($row->count_confirmed_to_shipped ?? 0),
            'sum_days_confirmed_to_first_attempt' => (int) ($row->sum_days_confirmed_to_first_attempt ?? 0),
            'count_confirmed_to_first_attempt' => (int) ($row->count_confirmed_to_first_attempt ?? 0),
            'sum_days_confirmed_to_delivered' => (int) ($row->sum_days_confirmed_to_delivered ?? 0),
            'count_confirmed_to_delivered' => (int) ($row->count_confirmed_to_delivered ?? 0),
            'sum_days_shipped_to_first_attempt' => (int) ($row->sum_days_shipped_to_first_attempt ?? 0),
            'count_shipped_to_first_attempt' => (int) ($row->count_shipped_to_first_attempt ?? 0),
            'sum_days_shipped_to_delivered' => (int) ($row->sum_days_shipped_to_delivered ?? 0),
            'count_shipped_to_delivered' => (int) ($row->count_shipped_to_delivered ?? 0),
            'sum_days_returning_to_returned' => (int) ($row->sum_days_returning_to_returned ?? 0),
            'count_returning_to_returned' => (int) ($row->count_returning_to_returned ?? 0),
            'sum_delivery_attempts_delivered' => (int) ($row->sum_delivery_attempts_delivered ?? 0),
            'count_delivery_attempts_delivered' => (int) ($row->count_delivery_attempts_delivered ?? 0),
            'sum_delivery_attempts_returned' => (int) ($row->sum_delivery_attempts_returned ?? 0),
            'count_delivery_attempts_returned' => (int) ($row->count_delivery_attempts_returned ?? 0),
        ];

        if (! array_filter($metrics)) {
            return;
        }

        WorkspacePageDailyMetric::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'page_id' => $pageId,
                'date' => $date,
            ],
            $metrics
        );
    }
}
