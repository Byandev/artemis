<?php

namespace App\Support\Reports;

use App\Metrics\Orders\RtsRate;
use App\Metrics\ParcelJourney\SmsSentCount;
use App\Models\PancakeUserRmoDailyReport;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Aggregates the client (workspace) report shown on the admin side: the RTS rate
 * over the trailing period with a per-month breakdown, the total parcel-journey
 * notifications sent, and the RMO call totals (calls made + time on calls).
 *
 * Each metric is delegated to its existing source of truth so the numbers match
 * the rest of the app: RtsRate and SmsSentCount reuse the analytics metric
 * classes, and the RMO totals come straight off the daily RMO rollup.
 */
final class ClientReport
{
    /** Trailing window, in whole calendar months (current month + the previous two). */
    private const MONTHS = 3;

    public function __construct(private readonly Workspace $workspace) {}

    /**
     * @return array{
     *     range: array{start: string, end: string, months: int},
     *     rts_rate: float,
     *     rts_monthly: array<int, array{period: string, rate: float}>,
     *     notifications_sent: int,
     *     rmo_called: int,
     *     call_time_seconds: int,
     * }
     */
    public function build(): array
    {
        $end = Carbon::today();
        $start = $end->copy()->startOfMonth()->subMonthsNoOverflow(self::MONTHS - 1);

        $range = [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];

        $rms = $this->rmoTotals($range);

        return [
            'range' => [
                'start' => $range['start_date'],
                'end' => $range['end_date'],
                'months' => self::MONTHS,
            ],
            'rts_rate' => $this->rtsRate($range),
            'rts_monthly' => $this->rtsMonthly($range),
            'notifications_sent' => $this->notificationsSent($range),
            'rmo_called' => (int) $rms->called,
            'call_time_seconds' => (int) $rms->call_time,
        ];
    }

    /** Overall RTS rate (returning / (returning + delivered)) for the window, as a 0..1 ratio. */
    private function rtsRate(array $range): float
    {
        return (float) (new RtsRate)->compute($this->workspace->id, $range, []);
    }

    /**
     * RTS rate split per calendar month, ready for the trend chart.
     *
     * @return array<int, array{period: string, rate: float}>
     */
    private function rtsMonthly(array $range): array
    {
        return (new RtsRate)
            ->breakdown($this->workspace->id, $range, [], 'monthly')
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'rate' => (float) $row->value,
            ])
            ->values()
            ->all();
    }

    /** Total parcel-journey notifications sent (historical rollup + live sent rows). */
    private function notificationsSent(array $range): int
    {
        return (int) (new SmsSentCount)->compute($this->workspace->id, $range, []);
    }

    /** Summed RMO calls and call time (seconds) from the daily RMO rollup. */
    private function rmoTotals(array $range): object
    {
        return PancakeUserRmoDailyReport::forWorkspaceRange(
            $this->workspace->id,
            $range['start_date'],
            $range['end_date'],
        )
            ->selectRaw('COALESCE(SUM(total_called), 0) as called')
            ->selectRaw('COALESCE(SUM(total_call_time), 0) as call_time')
            ->first();
    }
}
