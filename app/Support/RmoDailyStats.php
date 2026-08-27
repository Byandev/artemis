<?php

namespace App\Support;

use App\Models\CallLog;
use App\Models\Workspace;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * The RMO day in numbers, for one workspace.
 *
 * The same ten figures the RMO management page puts on its stat cards. They
 * live here rather than in the controller because the daily Discord report
 * quotes them too, and a report that disagrees with the page it summarises is
 * worse than no report.
 *
 * Everything here is workspace-wide: no page, shop, assignee or CSR filter.
 * The page narrows its own order figures when its filters are set; this is the
 * whole day, across all users, which is what the report is for.
 */
class RmoDailyStats
{
    /**
     * How long a call has to last before it counts as connected.
     *
     * Anything under this is the dial tone and a hang-up — the network logs it,
     * but nobody spoke. Hit rate is only meaningful if those are kept out of
     * the numerator.
     */
    public const CONNECTED_CALL_MIN_SECONDS = 5;

    /**
     * @return array{
     *     total_for_delivery: int,
     *     called: int,
     *     delivered: int,
     *     returning: int,
     *     problematic: int,
     *     total_call_logs: int,
     *     total_call_duration: int,
     *     connected_call_logs: int,
     *     avg_call_duration: float|null,
     *     hit_rate: float|null,
     * }
     */
    public static function for(Workspace $workspace, string $date): array
    {
        $orders = self::orderStats($workspace, $date);

        $totalCallLogs = self::callLogStat($workspace, $date, 'COUNT(*)');
        $totalCallDuration = self::callLogStat($workspace, $date, 'COALESCE(SUM(duration), 0)');
        $connectedCallLogs = self::callLogStat(
            $workspace,
            $date,
            'COUNT(CASE WHEN duration >= '.self::CONNECTED_CALL_MIN_SECONDS.' THEN 1 END)'
        );

        return [
            ...$orders,
            'total_call_logs' => $totalCallLogs,
            'total_call_duration' => $totalCallDuration,
            'connected_call_logs' => $connectedCallLogs,
            // Both are undefined rather than zero when nothing has happened
            // yet: a 0% hit rate reads as "everyone hung up", which is not the
            // same as "nobody has called".
            'avg_call_duration' => $connectedCallLogs > 0
                ? $totalCallDuration / $connectedCallLogs
                : null,
            'hit_rate' => $totalCallLogs > 0
                ? $connectedCallLogs / $totalCallLogs * 100
                : null,
        ];
    }

    /**
     * One call-log figure.
     *
     * Straight off call_logs for the workspace and date — no join to the
     * orders on the page, no filter on who placed the call. A day's calls are
     * reported as a day's calls, so a workspace with no orders loaded yet still
     * gets real numbers.
     *
     * $aggregate is what to measure: COUNT(*), SUM(duration), or either of
     * those narrowed to connected calls. One figure, one query — the cards
     * don't share an aggregate, so a change to any one of them can't move the
     * others.
     */
    public static function callLogStat(Workspace $workspace, string $date, string $aggregate): int
    {
        $value = CallLog::where('workspace_id', $workspace->id)
            ->whereDate('call_date', $date)
            ->selectRaw($aggregate.' as value')
            ->value('value');

        return (int) ($value ?? 0);
    }

    /**
     * The five order figures, rolled up in a single aggregate query.
     *
     * @return array{total_for_delivery: int, called: int, delivered: int, returning: int, problematic: int}
     */
    private static function orderStats(Workspace $workspace, string $date): array
    {
        $row = OrderForDelivery::where('workspace_id', $workspace->id)
            ->whereDate('delivery_date', $date)
            ->selectRaw("
                COUNT(*) as total_for_delivery,
                SUM(CASE WHEN status != 'PENDING' THEN 1 ELSE 0 END) as called,
                SUM(CASE WHEN parcel_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN parcel_status = 'returning' THEN 1 ELSE 0 END) as returning_count,
                SUM(CASE WHEN parcel_status = 'undeliverable' THEN 1 ELSE 0 END) as problematic
            ")
            ->first();

        return [
            'total_for_delivery' => (int) ($row->total_for_delivery ?? 0),
            'called' => (int) ($row->called ?? 0),
            'delivered' => (int) ($row->delivered ?? 0),
            'returning' => (int) ($row->returning_count ?? 0),
            'problematic' => (int) ($row->problematic ?? 0),
        ];
    }

    /** Seconds as `1h 04m` / `2m 10s` / `45s`, dropping units that read as zero. */
    public static function formatDuration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        if ($h > 0) {
            return sprintf('%dh %02dm', $h, $m);
        }

        return $m > 0 ? sprintf('%dm %02ds', $m, $s) : "{$s}s";
    }
}
