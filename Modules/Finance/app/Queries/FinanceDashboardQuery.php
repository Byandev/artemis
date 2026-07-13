<?php

namespace Modules\Finance\Queries;

use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Remittance;
use Modules\Finance\Models\Transaction;

/**
 * Read model for the finance dashboard. One method per widget, each resolving
 * its own focused, indexed aggregate so the per-statistic endpoints stay cheap.
 *
 * The window defaults to month-to-date; deltas compare against the immediately
 * preceding window of equal length. Transfers (and their fee sub-category) are
 * excluded from cash-flow figures so internal moves never inflate the totals.
 */
class FinanceDashboardQuery
{
    private CarbonImmutable $start;

    private CarbonImmutable $end;

    private CarbonImmutable $prevStart;

    private CarbonImmutable $prevEnd;

    public function __construct(private readonly Workspace $workspace, ?string $start = null, ?string $end = null)
    {
        $this->end = $end ? CarbonImmutable::parse($end) : CarbonImmutable::today();
        $this->start = $start ? CarbonImmutable::parse($start) : $this->end->startOfMonth();

        $length = (int) $this->start->diffInDays($this->end);
        $this->prevEnd = $this->start->subDay();
        $this->prevStart = $this->prevEnd->subDays($length);
    }

    /**
     * Section 1 — headline KPI tiles.
     *
     * @return array<string, mixed>
     */
    public function kpis(): array
    {
        $current = $this->flowTotals($this->start, $this->end);
        $previous = $this->flowTotals($this->prevStart, $this->prevEnd);

        $unreconciled = Remittance::where('workspace_id', $this->workspace->id)
            ->whereNull('transaction_id');

        return [
            'total_balance' => $this->totalBalance(),
            'cash_in' => $current['in'],
            'cash_out' => $current['out'],
            'net_cash_flow' => round($current['in'] - $current['out'], 2),
            'unreconciled_count' => (clone $unreconciled)->count(),
            'unreconciled_amount' => round((float) (clone $unreconciled)->sum('net_amount'), 2),
            'deltas' => [
                'cash_in' => $this->pctChange($previous['in'], $current['in']),
                'cash_out' => $this->pctChange($previous['out'], $current['out']),
                'net_cash_flow' => $this->pctChange(
                    $previous['in'] - $previous['out'],
                    $current['in'] - $current['out'],
                ),
            ],
            'range' => $this->rangePayload(),
        ];
    }

    /**
     * Section 2 — cash in/out/net bucketed over time (day/week/month).
     *
     * @return array{unit: string, points: list<array<string, mixed>>}
     */
    public function cashFlowSeries(): array
    {
        $cfg = $this->periodConfig();

        $rows = $this->flowQuery($this->start, $this->end)
            ->selectRaw("{$cfg['sql']} as period,
                SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'unit' => $cfg['unit'],
            'points' => $rows->map(fn ($r) => [
                'period' => $r->period,
                'in' => round((float) $r->total_in, 2),
                'out' => round((float) $r->total_out, 2),
                'net' => round((float) $r->total_in - (float) $r->total_out, 2),
            ])->all(),
        ];
    }

    /**
     * Section 2 — cumulative net cash flow across the range (starts at 0 and
     * accumulates each period's net movement). Includes every transaction, so
     * transfers between accounts cancel out at the workspace level.
     *
     * @return array{unit: string, points: list<array<string, mixed>>}
     */
    public function balanceHistory(): array
    {
        $cfg = $this->periodConfig();

        $rows = Transaction::where('workspace_id', $this->workspace->id)
            ->whereBetween('date', [$this->start->toDateString(), $this->end->toDateString()])
            ->selectRaw("{$cfg['sql']} as period,
                SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END) as net")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $running = 0.0;
        $points = $rows->map(function ($r) use (&$running) {
            $running += (float) $r->net;

            return ['period' => $r->period, 'balance' => round($running, 2)];
        })->all();

        return ['unit' => $cfg['unit'], 'points' => $points];
    }

    /**
     * Section 3 — out-flows grouped by category, largest first.
     *
     * @return list<array{category: string, amount: float}>
     */
    public function expenseBreakdown(): array
    {
        return $this->breakdownByType('out');
    }

    /**
     * Section 3 — in-flows grouped by category, largest first.
     *
     * @return list<array{category: string, amount: float}>
     */
    public function incomeBreakdown(): array
    {
        return $this->breakdownByType('in');
    }

    /**
     * Section 3 — the largest individual movements in the window.
     *
     * @return list<array<string, mixed>>
     */
    public function topMovements(int $limit = 8): array
    {
        return $this->flowQuery($this->start, $this->end)
            ->orderByDesc('amount')
            ->limit($limit)
            ->get(['id', 'date', 'description', 'type', 'transaction_type', 'amount'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'date' => (string) $t->date,
                'description' => $t->description,
                'type' => $t->type,
                'transaction_type' => $t->transaction_type,
                'amount' => round((float) $t->amount, 2),
            ])->all();
    }

    /**
     * Section 4 — unreconciled remittances with aging buckets. Aging is measured
     * from each SOA's billing end date to today (future dates fall in 0–7).
     *
     * @return array<string, mixed>
     */
    public function reconciliation(int $limit = 8): array
    {
        $items = Remittance::where('workspace_id', $this->workspace->id)
            ->whereNull('transaction_id')
            ->orderBy('billing_date_to')
            ->get(['id', 'soa_number', 'courier', 'billing_date_to', 'net_amount']);

        $today = CarbonImmutable::today();
        $buckets = [
            '0-7' => ['label' => '0–7 days', 'count' => 0, 'amount' => 0.0],
            '8-30' => ['label' => '8–30 days', 'count' => 0, 'amount' => 0.0],
            '30+' => ['label' => '30+ days', 'count' => 0, 'amount' => 0.0],
        ];

        $mapped = $items->map(function ($r) use ($today, &$buckets) {
            $age = (int) CarbonImmutable::parse($r->billing_date_to)->diffInDays($today, false);
            $key = $age <= 7 ? '0-7' : ($age <= 30 ? '8-30' : '30+');
            $buckets[$key]['count']++;
            $buckets[$key]['amount'] += (float) $r->net_amount;

            return [
                'id' => $r->id,
                'soa_number' => $r->soa_number,
                'courier' => $r->courier,
                'billing_date_to' => (string) $r->billing_date_to,
                'net_amount' => round((float) $r->net_amount, 2),
                'days_outstanding' => max($age, 0),
            ];
        });

        return [
            'total_count' => $items->count(),
            'total_amount' => round((float) $items->sum('net_amount'), 2),
            'buckets' => array_map(function ($b) {
                $b['amount'] = round($b['amount'], 2);

                return $b;
            }, array_values($buckets)),
            'items' => $mapped->take($limit)->values()->all(),
        ];
    }

    /**
     * Section 5 (cross-domain, phase 2) — profitability. Combines Pancake order
     * revenue with recorded ad spend. Deliberately conservative and transparent:
     * revenue is booked (all statuses), COGS is excluded, so the returned
     * `assumptions` must be surfaced to the reader.
     *
     * @return array<string, mixed>
     */
    public function profitability(): array
    {
        $revenue = round((float) DB::table('pancake_orders')
            ->where('workspace_id', $this->workspace->id)
            ->whereBetween('inserted_at', [$this->start->startOfDay(), $this->end->endOfDay()])
            ->sum('final_amount'), 2);

        $adSpend = round((float) DB::table('page_daily_budget_records')
            ->where('workspace_id', $this->workspace->id)
            ->whereBetween('date', [$this->start->toDateString(), $this->end->toDateString()])
            ->sum('budget'), 2);

        $grossProfit = round($revenue - $adSpend, 2);

        return [
            'revenue' => $revenue,
            'ad_spend' => $adSpend,
            'gross_profit' => $grossProfit,
            'margin_pct' => $revenue > 0 ? round($grossProfit / $revenue * 100, 1) : null,
            'mer' => $adSpend > 0 ? round($revenue / $adSpend, 2) : null,
            'assumptions' => [
                'Revenue is booked order value (all statuses), not delivered-only.',
                'COGS is not deducted — gross profit excludes cost of goods.',
                'Ad spend is the recorded daily page budget total.',
            ],
            'range' => $this->rangePayload(),
        ];
    }

    /**
     * Out/in-flows grouped by transaction_type (uncategorised bucketed under a
     * shared label), largest first.
     *
     * @return list<array{category: string, amount: float}>
     */
    private function breakdownByType(string $type): array
    {
        return $this->flowQuery($this->start, $this->end)
            ->where('type', $type)
            ->selectRaw("COALESCE(transaction_type, 'uncategorized') as category, SUM(amount) as amount")
            ->groupBy('category')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($r) => [
                'category' => (string) $r->category,
                'amount' => round((float) $r->amount, 2),
            ])->all();
    }

    /**
     * Money in/out within a window in a single pass.
     *
     * @return array{in: float, out: float}
     */
    private function flowTotals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->flowQuery($from, $to)
            ->selectRaw("
                SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out
            ")
            ->first();

        return [
            'in' => round((float) ($row->total_in ?? 0), 2),
            'out' => round((float) ($row->total_out ?? 0), 2),
        ];
    }

    /**
     * Base query for real cash movements in a window: workspace-scoped, within
     * dates, excluding transfers and transfer fees.
     */
    private function flowQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Transaction::where('workspace_id', $this->workspace->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where(function ($q) {
                $q->whereNull('transaction_type')
                    ->orWhereNotIn('transaction_type', ['transfer']);
            })
            ->where(function ($q) {
                $q->whereNull('sub_category')
                    ->orWhereNotIn('sub_category', ['transfer_fee']);
            });
    }

    /**
     * Current balance across all accounts: the running_balance of each
     * account's latest transaction, falling back to its opening balance.
     *
     * Note: sums across currencies naively — multi-currency conversion is a
     * later concern (see the dashboard plan). Period-independent by design.
     */
    private function totalBalance(): float
    {
        $accounts = Account::where('workspace_id', $this->workspace->id)
            ->get(['id', 'opening_balance']);

        $lastTxnPerAccount = Transaction::where('workspace_id', $this->workspace->id)
            ->whereIn('id', function ($q) {
                $q->selectRaw('(SELECT t2.id FROM finance_transactions t2 WHERE t2.account_id = finance_transactions.account_id AND t2.workspace_id = ? ORDER BY t2.date DESC, t2.position DESC LIMIT 1)', [$this->workspace->id])
                    ->from('finance_transactions')
                    ->where('workspace_id', $this->workspace->id)
                    ->groupBy('account_id');
            })
            ->get(['account_id', 'running_balance'])
            ->keyBy('account_id');

        return round($accounts->sum(function ($account) use ($lastTxnPerAccount) {
            $last = $lastTxnPerAccount->get($account->id);

            return (float) ($last->running_balance ?? $account->opening_balance);
        }), 2);
    }

    /**
     * Percentage change from a previous value to the current one. Null when
     * there is no baseline (previous = 0), so the UI can render "—" instead of
     * a misleading infinite jump.
     */
    private function pctChange(float $previous, float $current): ?float
    {
        if ($previous == 0.0) {
            return null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    /**
     * Bucketing granularity + the SQL expression that labels each bucket,
     * chosen from the window length so trends never render hundreds of points.
     *
     * @return array{unit: string, sql: string}
     */
    private function periodConfig(): array
    {
        $days = (int) $this->start->diffInDays($this->end) + 1;

        if ($days <= 31) {
            return ['unit' => 'day', 'sql' => "DATE_FORMAT(date, '%Y-%m-%d')"];
        }

        if ($days <= 120) {
            return ['unit' => 'week', 'sql' => "DATE_FORMAT(date, '%x-W%v')"];
        }

        return ['unit' => 'month', 'sql' => "DATE_FORMAT(date, '%Y-%m')"];
    }

    /**
     * @return array{start: string, end: string}
     */
    private function rangePayload(): array
    {
        return [
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
        ];
    }
}
