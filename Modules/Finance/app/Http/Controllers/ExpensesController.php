<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;

class ExpensesController extends Controller
{
    private const SUB_CATEGORIES = [
        'ad_spent', 'cogs', 'subscription', 'shipping_fee',
        'operation_expense', 'salary', 'transfer_fee', 'seminar_fee', 'others',
    ];

    public function __invoke(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $from = $this->parseDate($request->query('from')) ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $this->parseDate($request->query('to')) ?? Carbon::now()->endOfMonth()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return Inertia::render('workspaces/finance/expenses', [
            'workspace' => $workspace,
            'range' => ['from' => $from, 'to' => $to],
            ...$this->expenseBreakdown($workspace->id, $from, $to),
            'cashflowSeries' => $this->cashflowSeries($workspace->id, $from, $to),
            'expenseMoM' => $this->expenseMoM($workspace->id, $from, $to),
            ...$this->burnAndRunway($workspace->id, $from, $to),
        ]);
    }

    private function expenseBreakdown(int $workspaceId, string $from, string $to): array
    {
        $rows = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('transaction_type', 'expenses')
            ->whereBetween('date', [$from, $to])
            ->selectRaw('sub_category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('sub_category')
            ->get();

        $byKey = $rows->keyBy(fn ($r) => $r->sub_category ?? 'unassigned');

        $breakdown = collect(self::SUB_CATEGORIES)
            ->map(fn ($key) => [
                'sub_category' => $key,
                'total' => (float) ($byKey[$key]->total ?? 0),
                'count' => (int) ($byKey[$key]->count ?? 0),
            ])
            ->push([
                'sub_category' => 'unassigned',
                'total' => (float) ($byKey['unassigned']->total ?? 0),
                'count' => (int) ($byKey['unassigned']->count ?? 0),
            ])
            ->values()
            ->all();

        return [
            'breakdown' => $breakdown,
            'totalExpenses' => (float) $rows->sum('total'),
            'totalCount' => (int) $rows->sum('count'),
        ];
    }

    private function cashflowSeries(int $workspaceId, string $from, string $to): array
    {
        $fromDate = CarbonImmutable::parse($from);
        $toDate = CarbonImmutable::parse($to);
        $spanDays = $fromDate->diffInDays($toDate) + 1;

        [$bucket, $sqlExpr, $formatter] = match (true) {
            $spanDays <= 31 => ['day', 'DATE(date)', fn (string $v) => Carbon::parse($v)->format('M j')],
            $spanDays <= 180 => ['week', "DATE_FORMAT(date, '%x-%v')", fn (string $v) => 'W'.substr($v, -2).' '.substr($v, 0, 4)],
            default => ['month', "DATE_FORMAT(date, '%Y-%m')", fn (string $v) => Carbon::parse($v.'-01')->format('M Y')],
        };

        $rows = DB::table('finance_transactions')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("$sqlExpr as bucket, type, SUM(amount) as total")
            ->groupBy('bucket', 'type')
            ->orderBy('bucket')
            ->get();

        $grouped = [];
        foreach ($rows as $r) {
            $key = (string) $r->bucket;
            $grouped[$key] ??= ['in' => 0.0, 'out' => 0.0];
            $grouped[$key][$r->type] = (float) $r->total;
        }

        $buckets = $this->enumerateBuckets($fromDate, $toDate, $bucket);

        return array_map(function ($key) use ($grouped, $formatter) {
            $row = $grouped[$key] ?? ['in' => 0.0, 'out' => 0.0];

            return [
                'bucket' => $key,
                'label' => $formatter($key),
                'in' => (float) $row['in'],
                'out' => (float) $row['out'],
            ];
        }, $buckets);
    }

    private function enumerateBuckets(CarbonImmutable $from, CarbonImmutable $to, string $bucket): array
    {
        $keys = [];
        $cursor = $from;
        while ($cursor->lte($to)) {
            $keys[] = match ($bucket) {
                'day' => $cursor->format('Y-m-d'),
                'week' => $cursor->format('o-W'),
                'month' => $cursor->format('Y-m'),
            };
            $cursor = match ($bucket) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
            };
        }

        return array_values(array_unique($keys));
    }

    private function expenseMoM(int $workspaceId, string $from, string $to): array
    {
        $fromDate = CarbonImmutable::parse($from);
        $toDate = CarbonImmutable::parse($to);
        $spanDays = $fromDate->diffInDays($toDate) + 1;

        $prevTo = $fromDate->subDay();
        $prevFrom = $prevTo->subDays($spanDays - 1);

        $current = $this->sumBySubCategory($workspaceId, $fromDate->toDateString(), $toDate->toDateString());
        $previous = $this->sumBySubCategory($workspaceId, $prevFrom->toDateString(), $prevTo->toDateString());

        $keys = [...self::SUB_CATEGORIES, 'unassigned'];

        return array_map(function ($key) use ($current, $previous) {
            $c = (float) ($current[$key] ?? 0);
            $p = (float) ($previous[$key] ?? 0);
            $delta = $c - $p;
            $deltaPct = $p > 0 ? ($delta / $p) * 100 : ($c > 0 ? null : 0.0);

            return [
                'sub_category' => $key,
                'current' => $c,
                'previous' => $p,
                'delta' => $delta,
                'delta_pct' => $deltaPct,
            ];
        }, $keys);
    }

    private function sumBySubCategory(int $workspaceId, string $from, string $to): array
    {
        return Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('transaction_type', 'expenses')
            ->whereBetween('date', [$from, $to])
            ->selectRaw('sub_category, SUM(amount) as total')
            ->groupBy('sub_category')
            ->get()
            ->mapWithKeys(fn ($r) => [($r->sub_category ?? 'unassigned') => (float) $r->total])
            ->all();
    }

    private function burnAndRunway(int $workspaceId, string $from, string $to): array
    {
        $fromDate = CarbonImmutable::parse($from);
        $toDate = CarbonImmutable::parse($to);
        $spanDays = max(1, $fromDate->diffInDays($toDate) + 1);
        $monthsInRange = $spanDays / 30.4375;

        $rangeOut = (float) DB::table('finance_transactions')
            ->where('workspace_id', $workspaceId)
            ->where('type', 'out')
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        $burnRate = $monthsInRange > 0 ? $rangeOut / $monthsInRange : 0.0;

        $openingSum = (float) Account::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->sum('opening_balance');

        $txnTotals = DB::table('finance_transactions as t')
            ->join('finance_accounts as a', 'a.id', '=', 't.account_id')
            ->where('t.workspace_id', $workspaceId)
            ->where('a.is_active', true)
            ->where('t.date', '<=', $to)
            ->selectRaw("SUM(CASE WHEN t.type = 'in' THEN t.amount ELSE 0 END) as in_sum, SUM(CASE WHEN t.type = 'out' THEN t.amount ELSE 0 END) as out_sum")
            ->first();

        $balanceAtEnd = $openingSum + ((float) ($txnTotals->in_sum ?? 0)) - ((float) ($txnTotals->out_sum ?? 0));
        $runwayMonths = $burnRate > 0 ? $balanceAtEnd / $burnRate : null;

        return [
            'totalBalance' => $balanceAtEnd,
            'burnRate' => $burnRate,
            'runwayMonths' => $runwayMonths,
            'rangeOut' => $rangeOut,
        ];
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
