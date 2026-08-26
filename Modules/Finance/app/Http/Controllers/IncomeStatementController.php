<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\IncomeStatementSetting;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionType;
use Modules\Finance\Services\ProductIncomeStatementService;
use Modules\Finance\Services\UserIncomeStatementService;
use Modules\GencysERP\Models\GencysDailySalesOrder;

/**
 * Monthly workspace-wide income statement for gencys-partner workspaces.
 *
 * Two-tier P&L:
 *   Gross Profit = Delivered − Cost of Sales
 *   Net Profit   = Gross Profit − Advisory Share − OPEX
 *
 * Cost of Sales = the auto lines (Shipping Fee, COD Fee, VAT) plus every
 * transaction type whose `income_statement_section` is `cost_of_sales`. OPEX =
 * types marked `opex`. Types with a null section are excluded and never appear.
 * Which side a line sits on is stored per breakdown row (`section`) so a later
 * change doesn't reclassify a closed statement.
 */
class IncomeStatementController extends Controller
{
    use AuthorizesRequests;

    /** gencys_orders.parcel_status value that counts as delivered revenue. */
    private const DELIVERED_STATUS = 'DELIVERED';

    /** Sentinel type_keys for the auto-computed cost-of-sales lines. */
    private const SHIPPING_FEE_KEY = -1;

    private const COD_FEE_KEY = -2;

    private const VAT_KEY = -3;

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        $statements = IncomeStatement::where('workspace_id', $workspace->id)
            ->orderByDesc('period_month')
            ->get(['id', 'period_month', 'total_delivered', 'gross_profit', 'total_expenses', 'net_profit', 'status', 'generated_at']);

        return Inertia::render('workspaces/finance/income-statements/index', [
            'workspace' => $workspace,
            'statements' => $statements,
            'currentMonth' => Carbon::now()->format('Y-m'),
        ]);
    }

    /**
     * Live preview for a month (nothing saved). Shows delivered revenue, the
     * cost-of-sales lines and the OPEX transaction buckets, each with a checkbox
     * and its section, plus the (default) rates.
     */
    public function preview(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        [$periodMonth, $from, $to] = $this->resolveMonth($request->input('month'));

        $existing = IncomeStatement::with('breakdown')
            ->where('workspace_id', $workspace->id)
            ->whereDate('period_month', $periodMonth)
            ->first();

        [$defaultCod, $defaultVat, $defaultAdvisory] = $this->workspaceRates($workspace);
        $codRate = (float) ($request->input('cod_rate') ?? $existing?->cod_fee_rate ?? $defaultCod);
        $vatRate = (float) ($request->input('vat_rate') ?? $existing?->vat_rate ?? $defaultVat);
        $advisoryRate = (float) ($request->input('advisory_rate') ?? $existing?->advisory_rate ?? $defaultAdvisory);

        $revenue = $this->deliveredRevenue($workspace, $from, $to);
        $lines = $this->expenseLines($workspace, $from, $to, $revenue['delivered'], $codRate, $vatRate);

        // Preview always defaults every line checked, so lines that appear after a
        // statement was last saved (e.g. a newly-flagged type, or new transactions)
        // are picked up on the next Save. Regenerate is the "keep my exact set" path.

        return Inertia::render('workspaces/finance/income-statements/show', [
            'workspace' => $workspace,
            'mode' => 'preview',
            'statement' => [
                'id' => $existing?->id,
                'period_month' => $periodMonth,
                'delivered' => $revenue['delivered'],
                'orders' => $revenue['orders'],
                'cod_fee_rate' => $codRate,
                'vat_rate' => $vatRate,
                'advisory_rate' => $advisoryRate,
                'gencys_partner' => (bool) $workspace->is_gencys_partner,
                'expenses' => $lines->map(fn ($l) => [
                    ...$l,
                    'included' => true,
                ])->values(),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace, UserIncomeStatementService $userStatements, ProductIncomeStatementService $productStatements)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'included_keys' => ['array'],
            'included_keys.*' => ['integer'],
            'cod_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'advisory_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        [$periodMonth, $from, $to] = $this->resolveMonth($validated['month']);

        [$defaultCod, $defaultVat, $defaultAdvisory] = $this->workspaceRates($workspace);
        $codRate = (float) ($validated['cod_rate'] ?? $defaultCod);
        $vatRate = (float) ($validated['vat_rate'] ?? $defaultVat);
        $advisoryRate = (float) ($validated['advisory_rate'] ?? $defaultAdvisory);

        $statement = $this->persist(
            $workspace,
            $periodMonth,
            $from,
            $to,
            collect($validated['included_keys'] ?? []),
            $codRate,
            $vatRate,
            $advisoryRate,
        );

        IncomeStatementSetting::updateOrCreate(
            ['workspace_id' => $workspace->id],
            ['cod_fee_rate' => $codRate, 'vat_rate' => $vatRate, 'advisory_rate' => $advisoryRate],
        );

        $userStatements->snapshot($statement);
        $productStatements->snapshot($statement);

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
                'gross_profit' => (float) $incomeStatement->gross_profit,
                'total_expenses' => (float) $incomeStatement->total_expenses,
                'net_profit' => (float) $incomeStatement->net_profit,
                'cod_fee_rate' => (float) $incomeStatement->cod_fee_rate,
                'vat_rate' => (float) $incomeStatement->vat_rate,
                'advisory_rate' => (float) $incomeStatement->advisory_rate,
                'advisory_share' => (float) $incomeStatement->advisory_share,
                'gencys_partner' => (bool) $workspace->is_gencys_partner,
                'generated_at' => $incomeStatement->generated_at?->toIso8601String(),
                'expenses' => $incomeStatement->breakdown->map(fn ($b) => [
                    'type_key' => $this->keyForRow($b),
                    'type_name' => $b->type_name,
                    'amount' => (float) $b->amount,
                    'source' => $b->source,
                    'section' => $b->section,
                    'included' => true,
                ])->values(),
            ],
        ]);
    }

    /** Re-pull the month's numbers with the snapshotted rates + included lines. */
    public function regenerate(Request $request, Workspace $workspace, IncomeStatement $incomeStatement, UserIncomeStatementService $userStatements, ProductIncomeStatementService $productStatements)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        [$periodMonth, $from, $to] = $this->resolveMonth($incomeStatement->period_month->format('Y-m'));

        $codRate = (float) $incomeStatement->cod_fee_rate;
        $vatRate = (float) $incomeStatement->vat_rate;

        // Keep the user's saved line choices, but always (re)include every
        // cost-of-sales line. A transaction type newly tagged as cost of sales
        // (e.g. Ad Spent), or a cost-of-sales transaction added after the last
        // save, must flow into Gross Profit on regenerate rather than being
        // dropped for not being in the original set. OPEX toggles are preserved.
        $revenue = $this->deliveredRevenue($workspace, $from, $to);
        $costOfSalesKeys = $this->expenseLines($workspace, $from, $to, $revenue['delivered'], $codRate, $vatRate)
            ->where('section', 'cost_of_sales')
            ->map(fn ($l) => $l['type_key']);

        $includedKeys = $incomeStatement->breakdown
            ->map(fn ($b) => $this->keyForRow($b))
            ->merge($costOfSalesKeys)
            ->unique()
            ->values();

        $statement = $this->persist(
            $workspace,
            $periodMonth,
            $from,
            $to,
            $includedKeys,
            $codRate,
            $vatRate,
            (float) $incomeStatement->advisory_rate,
        );

        $userStatements->snapshot($statement);
        $productStatements->snapshot($statement);

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
            fputcsv($out, ['Cost of Sales', 'Amount']);
            foreach ($incomeStatement->breakdown->where('section', 'cost_of_sales') as $row) {
                fputcsv($out, [$row->type_name, $row->amount]);
            }
            fputcsv($out, ['Gross Profit', $incomeStatement->gross_profit]);
            fputcsv($out, []);
            fputcsv($out, ['OPEX', 'Amount']);
            foreach ($incomeStatement->breakdown->where('section', 'opex') as $row) {
                fputcsv($out, [$row->type_name, $row->amount]);
            }
            if ($incomeStatement->advisory_share > 0) {
                $label = 'Advisory Share ('.rtrim(rtrim(number_format((float) $incomeStatement->advisory_rate * 100, 2), '0'), '.').'%)';
                fputcsv($out, [$label, $incomeStatement->advisory_share]);
            }
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
     * Recompute the month's revenue, cost of sales, gross profit, advisory, OPEX
     * and net profit for the included lines, and (over)write the snapshot.
     */
    private function persist(Workspace $workspace, string $periodMonth, Carbon $from, Carbon $to, Collection $includedKeys, float $codRate, float $vatRate, float $advisoryRate): IncomeStatement
    {
        $revenue = $this->deliveredRevenue($workspace, $from, $to);

        $included = $this->expenseLines($workspace, $from, $to, $revenue['delivered'], $codRate, $vatRate)
            ->filter(fn ($l) => $includedKeys->contains($l['type_key']))
            ->values();

        $costOfSales = (float) $included->where('section', 'cost_of_sales')->sum('amount');
        $opex = (float) $included->where('section', 'opex')->sum('amount');

        $grossProfit = $revenue['delivered'] - $costOfSales;

        // Advisory share: a % of positive Gross Profit, gencys-partner only.
        $advisoryShare = ($workspace->is_gencys_partner && $grossProfit > 0)
            ? round($grossProfit * $advisoryRate, 2)
            : 0.0;

        $netProfit = $grossProfit - $opex - $advisoryShare;

        return DB::transaction(function () use ($workspace, $periodMonth, $revenue, $included, $costOfSales, $opex, $grossProfit, $netProfit, $codRate, $vatRate, $advisoryRate, $advisoryShare) {
            $statement = IncomeStatement::updateOrCreate(
                ['workspace_id' => $workspace->id, 'period_month' => $periodMonth],
                [
                    'total_delivered' => $revenue['delivered'],
                    'delivered_orders' => $revenue['orders'],
                    'total_expenses' => $costOfSales + $opex,
                    'gross_profit' => $grossProfit,
                    'net_profit' => $netProfit,
                    'cod_fee_rate' => $codRate,
                    'vat_rate' => $vatRate,
                    'advisory_rate' => $advisoryRate,
                    'advisory_share' => $advisoryShare,
                    'status' => 'final',
                    'generated_at' => now(),
                ],
            );

            $statement->breakdown()->delete();

            foreach ($included as $l) {
                $statement->breakdown()->create([
                    'source' => $l['source'],
                    'section' => $l['section'],
                    'transaction_type_id' => ($l['source'] === 'transaction_type' && $l['type_key'] !== 0)
                        ? $l['type_key']
                        : null,
                    'type_name' => $l['type_name'],
                    'amount' => $l['amount'],
                ]);
            }

            return $statement;
        });
    }

    /**
     * All selectable expense lines for the month, each tagged with its section.
     *
     * @return Collection<int, array{type_key:int, type_name:string, amount:float, source:string, section:string}>
     */
    private function expenseLines(Workspace $workspace, Carbon $from, Carbon $to, float $delivered, float $codRate, float $vatRate): Collection
    {
        $buckets = $this->transactionBuckets($workspace, $from, $to);

        $lines = collect();

        // Auto cost-of-sales lines.
        $shipping = $this->shippingFee($workspace, $from, $to);
        if ($shipping > 0) {
            $lines->push($this->line(self::SHIPPING_FEE_KEY, 'Shipping Fee', round($shipping, 2), 'shipping_fee', 'cost_of_sales'));
        }

        $codFee = round($delivered * $codRate, 2);
        if ($delivered > 0) {
            $lines->push($this->line(self::COD_FEE_KEY, 'COD Fee', $codFee, 'cod_fee', 'cost_of_sales'));
        }

        $vat = round($codFee * $vatRate, 2);
        if ($codFee > 0) {
            $lines->push($this->line(self::VAT_KEY, 'VAT', $vat, 'vat', 'cost_of_sales'));
        }

        // Split transaction types by their income-statement section (excluded
        // types were already dropped in transactionBuckets()).
        foreach ($buckets->where('section', 'cost_of_sales') as $b) {
            $lines->push($this->line($b['type_key'], $b['type_name'], $b['amount'], 'transaction_type', 'cost_of_sales'));
        }
        foreach ($buckets->where('section', 'opex') as $b) {
            $lines->push($this->line($b['type_key'], $b['type_name'], $b['amount'], 'transaction_type', 'opex'));
        }

        return $lines->values();
    }

    /** @return array{type_key:int, type_name:string, amount:float, source:string, section:string} */
    private function line(int $key, string $name, float $amount, string $source, string $section): array
    {
        return ['type_key' => $key, 'type_name' => $name, 'amount' => $amount, 'source' => $source, 'section' => $section];
    }

    /** Delivered gencys revenue + order count for the month (by parcel_updated_date). */
    private function deliveredRevenue(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        $row = GencysDailySalesOrder::where('workspace_id', $workspace->id)
            ->where('parcel_status', self::DELIVERED_STATUS)
            ->whereNotIn('platform', ['Shopee', 'TikTok'])
            ->whereNotLike('page', '%pikutin%')
            ->whereBetween('parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('COALESCE(SUM(price_final), 0) as delivered, COUNT(*) as orders')
            ->first();

        return [
            'delivered' => (float) $row->delivered,
            'orders' => (int) $row->orders,
        ];
    }

    /** Total shipping fee of gencys orders shipped out in the month. */
    private function shippingFee(Workspace $workspace, Carbon $from, Carbon $to): float
    {
        return (float) GencysDailySalesOrder::where('workspace_id', $workspace->id)
            ->whereBetween('shipped_out_date', [$from->toDateString(), $to->toDateString()])
            ->sum('shipping_fee');
    }

    /**
     * The month's outflow finance transactions grouped by transaction type, each
     * tagged with its income-statement section ('cost_of_sales' | 'opex'). Types
     * whose section is null (excluded) are dropped entirely. Key 0 is the
     * "Uncategorized" bucket (no transaction_type_id) which always falls to OPEX;
     * a deleted/unknown type also falls back to OPEX rather than being dropped.
     *
     * @return Collection<int, array{type_key:int, type_name:string, amount:float, section:string}>
     */
    private function transactionBuckets(Workspace $workspace, Carbon $from, Carbon $to): Collection
    {
        $types = TransactionType::where('workspace_id', $workspace->id)
            ->get(['id', 'name', 'income_statement_section'])
            ->keyBy('id');

        return Transaction::where('workspace_id', $workspace->id)
            ->where('type', 'out')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(transaction_type_id, 0) as type_key, SUM(amount) as total')
            ->groupBy('type_key')
            ->orderByDesc('total')
            ->get()
            ->map(function ($r) use ($types) {
                $key = (int) $r->type_key;
                $type = $key ? $types->get($key) : null;

                return [
                    'type_key' => $key,
                    'type_name' => $key ? ($type->name ?? 'Unknown') : 'Uncategorized',
                    'amount' => (float) $r->total,
                    // Existing type → its section (null = excluded); uncategorized
                    // or a deleted type → OPEX.
                    'section' => $type ? $type->income_statement_section : 'opex',
                ];
            })
            ->reject(fn ($b) => $b['section'] === null)
            ->values();
    }

    /** Map a saved breakdown row back to its preview type_key. */
    private function keyForRow(object $row): int
    {
        return match ($row->source) {
            'shipping_fee' => self::SHIPPING_FEE_KEY,
            'cod_fee' => self::COD_FEE_KEY,
            'vat' => self::VAT_KEY,
            default => (int) ($row->transaction_type_id ?? 0),
        };
    }

    /** Workspace default rates [cod, vat, advisory] as fractions, falling back to constants. */
    private function workspaceRates(Workspace $workspace): array
    {
        $settings = IncomeStatementSetting::where('workspace_id', $workspace->id)->first();

        return [
            (float) ($settings?->cod_fee_rate ?? IncomeStatementSetting::DEFAULT_COD_FEE_RATE),
            (float) ($settings?->vat_rate ?? IncomeStatementSetting::DEFAULT_VAT_RATE),
            (float) ($settings?->advisory_rate ?? IncomeStatementSetting::DEFAULT_ADVISORY_RATE),
        ];
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
