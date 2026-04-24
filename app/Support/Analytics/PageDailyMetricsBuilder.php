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
                    ->orWhereBetween('returned_at', [$start, $end]);
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
                SUM(CASE WHEN returned_at BETWEEN ? AND ? THEN final_amount ELSE 0 END) AS returned_amount
            ', [
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
            ])
            ->first();

        WorkspacePageDailyMetric::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'page_id' => $pageId,
                'date' => $date,
            ],
            [
                'confirmed_count' => (int) ($row->confirmed_count ?? 0),
                'confirmed_amount' => (float) ($row->confirmed_amount ?? 0),
                'shipped_count' => (int) ($row->shipped_count ?? 0),
                'shipped_amount' => (float) ($row->shipped_amount ?? 0),
                'delivered_count' => (int) ($row->delivered_count ?? 0),
                'delivered_amount' => (float) ($row->delivered_amount ?? 0),
                'entered_returning_count' => (int) ($row->entered_returning_count ?? 0),
                'entered_returning_amount' => (float) ($row->entered_returning_amount ?? 0),
                'returned_count' => (int) ($row->returned_count ?? 0),
                'returned_amount' => (float) ($row->returned_amount ?? 0),
            ]
        );
    }
}
