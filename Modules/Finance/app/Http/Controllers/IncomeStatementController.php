<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionType;

/**
 * Monthly income statement for non-gencys-partner workspaces.
 *
 * Revenue is delivered Pancake orders; expenses are the month's finance
 * transactions grouped by transaction type. The user picks which types to
 * include, and a save snapshots the header + one breakdown row per included
 * type. Saved statements are frozen; Regenerate re-pulls the amounts while
 * keeping the previously-included types.
 */
class IncomeStatementController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        $statements = IncomeStatement::where('workspace_id', $workspace->id)
            ->orderByDesc('period_month')
            ->get(['id', 'period_month', 'total_delivered', 'total_expenses', 'net_profit', 'status', 'generated_at']);

        return Inertia::render('workspaces/finance/income-statements/index', [
            'workspace' => $workspace,
            'statements' => $statements,
            'currentMonth' => Carbon::now()->format('Y-m'),
        ]);
    }

    /**
     * Live preview for a month (nothing saved). Shows delivered revenue and every
     * transaction type's expense total with a checkbox. If a saved statement
     * already exists for the month, its included types are pre-checked; otherwise
     * every type defaults to checked.
     */
    public function preview(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        [$periodMonth, $from, $to] = $this->resolveMonth($request->input('month'));

        $revenue = $this->deliveredRevenue($workspace, $from, $to);
        $buckets = $this->expenseBuckets($workspace, $from, $to);

        $existing = IncomeStatement::with('breakdown')
            ->where('workspace_id', $workspace->id)
            ->whereDate('period_month', $periodMonth)
            ->first();

        $includedKeys = $existing
            ? $existing->breakdown->map(fn ($b) => (int) ($b->transaction_type_id ?? 0))->all()
            : null; // null → all checked by default

        return Inertia::render('workspaces/finance/income-statements/show', [
            'workspace' => $workspace,
            'mode' => 'preview',
            'statement' => [
                'id' => $existing?->id,
                'period_month' => $periodMonth,
                'delivered' => $revenue['delivered'],
                'orders' => $revenue['orders'],
                'expenses' => $buckets->map(fn ($b) => [
                    ...$b,
                    'included' => $includedKeys === null || in_array($b['type_key'], $includedKeys, true),
                ])->values(),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'included_keys' => ['array'],
            'included_keys.*' => ['integer'],
        ]);

        [$periodMonth, $from, $to] = $this->resolveMonth($validated['month']);

        $statement = $this->persist(
            $workspace,
            $periodMonth,
            $from,
            $to,
            collect($validated['included_keys'] ?? []),
        );

        return redirect()
            ->route('workspaces.finance.income-statements.show', [$workspace->slug, $statement->id])
            ->with('success', 'Income statement saved.');
    }

    public function show(Request $request, Workspace $workspace, IncomeStatement $incomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        $incomeStatement->load('breakdown');

        return Inertia::render('workspaces/finance/income-statements/show', [
            'workspace' => $workspace,
            'mode' => 'saved',
            'statement' => [
                'id' => $incomeStatement->id,
                'period_month' => $incomeStatement->period_month->toDateString(),
                'delivered' => (float) $incomeStatement->total_delivered,
                'orders' => (int) $incomeStatement->delivered_orders,
                'total_expenses' => (float) $incomeStatement->total_expenses,
                'net_profit' => (float) $incomeStatement->net_profit,
                'generated_at' => $incomeStatement->generated_at?->toIso8601String(),
                'expenses' => $incomeStatement->breakdown->map(fn ($b) => [
                    'type_key' => (int) ($b->transaction_type_id ?? 0),
                    'type_name' => $b->type_name,
                    'amount' => (float) $b->amount,
                    'included' => true,
                ])->values(),
            ],
        ]);
    }

    /** Re-pull the month's numbers, keeping the same included types, and overwrite. */
    public function regenerate(Request $request, Workspace $workspace, IncomeStatement $incomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        [$periodMonth, $from, $to] = $this->resolveMonth($incomeStatement->period_month->format('Y-m'));

        $includedKeys = $incomeStatement->breakdown()
            ->pluck('transaction_type_id')
            ->map(fn ($id) => (int) ($id ?? 0));

        $this->persist($workspace, $periodMonth, $from, $to, $includedKeys);

        return redirect()->back()->with('success', 'Income statement regenerated.');
    }

    public function export(Request $request, Workspace $workspace, IncomeStatement $incomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        $incomeStatement->load('breakdown');
        $label = $incomeStatement->period_month->format('Y-m');
        $fileName = "income-statement-{$label}.csv";

        return response()->streamDownload(function () use ($incomeStatement) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Income Statement', $incomeStatement->period_month->format('F Y')]);
            fputcsv($out, []);
            fputcsv($out, ['Total Delivered', $incomeStatement->total_delivered]);
            fputcsv($out, ['Delivered Orders', $incomeStatement->delivered_orders]);
            fputcsv($out, []);
            fputcsv($out, ['Expenses by Type', 'Amount']);
            foreach ($incomeStatement->breakdown as $row) {
                fputcsv($out, [$row->type_name, $row->amount]);
            }
            fputcsv($out, ['Total Expenses', $incomeStatement->total_expenses]);
            fputcsv($out, []);
            fputcsv($out, ['Net Profit', $incomeStatement->net_profit]);

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    public function destroy(Request $request, Workspace $workspace, IncomeStatement $incomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        $incomeStatement->delete();

        return redirect()
            ->route('workspaces.finance.income-statements.index', $workspace->slug)
            ->with('success', 'Income statement deleted.');
    }

    /**
     * Recompute the month's revenue + included expense totals and (over)write the
     * snapshot: the header plus one breakdown row per included type. Amounts are
     * always recomputed server-side, never trusted from the client.
     */
    private function persist(Workspace $workspace, string $periodMonth, Carbon $from, Carbon $to, Collection $includedKeys): IncomeStatement
    {
        $revenue = $this->deliveredRevenue($workspace, $from, $to);

        $included = $this->expenseBuckets($workspace, $from, $to)
            ->filter(fn ($b) => $includedKeys->contains($b['type_key']))
            ->values();

        $totalExpenses = (float) $included->sum('amount');
        $netProfit = $revenue['delivered'] - $totalExpenses;

        return DB::transaction(function () use ($workspace, $periodMonth, $revenue, $included, $totalExpenses, $netProfit) {
            $statement = IncomeStatement::updateOrCreate(
                ['workspace_id' => $workspace->id, 'period_month' => $periodMonth],
                [
                    'total_delivered' => $revenue['delivered'],
                    'delivered_orders' => $revenue['orders'],
                    'total_expenses' => $totalExpenses,
                    'net_profit' => $netProfit,
                    'status' => 'final',
                    'generated_at' => now(),
                ],
            );

            $statement->breakdown()->delete();

            foreach ($included as $b) {
                $statement->breakdown()->create([
                    'transaction_type_id' => $b['type_key'] === 0 ? null : $b['type_key'],
                    'type_name' => $b['type_name'],
                    'amount' => $b['amount'],
                ]);
            }

            return $statement;
        });
    }

    /** Delivered Pancake revenue + order count for the month (by delivered_at). */
    private function deliveredRevenue(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        $row = Order::where('workspace_id', $workspace->id)
            ->whereNotNull('delivered_at')
            ->whereBetween('delivered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('COALESCE(SUM(final_amount), 0) as delivered, COUNT(*) as orders')
            ->first();

        return [
            'delivered' => (float) $row->delivered,
            'orders' => (int) $row->orders,
        ];
    }

    /**
     * The month's outflow transactions grouped by transaction type. Key 0 is the
     * "Uncategorized" bucket (no transaction_type_id). Ordered by amount desc.
     *
     * @return Collection<int, array{type_key:int, type_name:string, amount:float}>
     */
    private function expenseBuckets(Workspace $workspace, Carbon $from, Carbon $to): Collection
    {
        $typeNames = TransactionType::where('workspace_id', $workspace->id)->pluck('name', 'id');

        return Transaction::where('workspace_id', $workspace->id)
            ->where('type', 'out')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(transaction_type_id, 0) as type_key, SUM(amount) as total')
            ->groupBy('type_key')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'type_key' => (int) $r->type_key,
                'type_name' => $r->type_key ? ($typeNames[$r->type_key] ?? 'Unknown') : 'Uncategorized',
                'amount' => (float) $r->total,
            ]);
    }

    /**
     * @return array{0:string, 1:Carbon, 2:Carbon} [periodMonth (Y-m-d, 1st), from, to]
     */
    private function resolveMonth(?string $month): array
    {
        $start = $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : Carbon::now()->startOfMonth();

        return [$start->toDateString(), $start->copy()->startOfMonth(), $start->copy()->endOfMonth()];
    }

    private function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    private function ensureOwns(Workspace $workspace, IncomeStatement $incomeStatement): void
    {
        if ($incomeStatement->workspace_id !== $workspace->id) {
            abort(404);
        }
    }
}
