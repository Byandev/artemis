<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
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
use Modules\Finance\Services\UserProductIncomeStatementService;
use Modules\Finance\Statements\LossCarryovers;
use Modules\Finance\Statements\StatementOrderSourceFactory;
use Modules\Finance\Statements\StatementRates;
use Modules\Finance\Statements\TransactionTotals;

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

    public function __construct(
        private readonly StatementOrderSourceFactory $sources,
        private readonly TransactionTotals $transactions,
        private readonly UserIncomeStatementService $userStatements,
        private readonly ProductIncomeStatementService $productStatements,
        private readonly UserProductIncomeStatementService $userProductStatements,
        private readonly LossCarryovers $carryovers,
    ) {}

    /**
     * Build or rebuild a month's statement and re-snapshot its per-user and
     * per-product slices.
     *
     * Creating and regenerating run this same path. They differ only in where
     * the rates come from and which expense lines are carried, which is what
     * the two arguments express — everything downstream is identical, so the
     * two can't drift apart.
     *
     * @param  Collection|null  $requestedKeys  the form's line choices; null = every line
     * @param  IncomeStatement|null  $regenerating  keep this statement's line choices
     */
    private function generate(
        Workspace $workspace,
        string $month,
        StatementRates $rates,
        ?Collection $requestedKeys = null,
        ?IncomeStatement $regenerating = null,
        ?float $manualLossBroughtForward = null,
    ): IncomeStatement {
        // A figure someone typed survives a regenerate, the way the rates do:
        // it is a statement about months this system never saw, and rebuilding
        // this one tells us nothing new about them.
        $manualLossBroughtForward ??= $regenerating?->manual_loss_brought_forward === null
            ? null
            : (float) $regenerating->manual_loss_brought_forward;

        [$periodMonth, $from, $to] = $this->resolveMonth($month);

        $lines = $this->expenseLines(
            $workspace, $from, $to,
            $this->deliveredRevenue($workspace, $from, $to)['delivered'],
            $rates->codFee, $rates->vat,
        );

        $includedKeys = match (true) {
            $requestedKeys !== null => $requestedKeys,
            // Keep the saved line choices, but always re-include every
            // cost-of-sales line. A type newly tagged as cost of sales, or a
            // cost-of-sales transaction added since the last save, has to flow
            // into gross profit rather than be dropped for not being in the
            // original set. OPEX toggles are preserved.
            // toBase() because mapping an Eloquent collection to ints only
            // downgrades it when the result is non-empty — map() checks whether
            // it still holds models, and an empty one trivially does. A
            // statement with no breakdown rows would otherwise keep an Eloquent
            // collection here and merge() would ask an int for its key.
            $regenerating !== null => $regenerating->breakdown
                ->map(fn ($b) => $this->keyForRow($b))
                ->toBase()
                ->merge($lines->where('section', 'cost_of_sales')->map(fn ($l) => $l['type_key']))
                ->unique()
                ->values(),
            default => $lines->map(fn ($l) => $l['type_key']),
        };

        $statement = $this->persist($workspace, $periodMonth, $from, $to, $includedKeys, $rates, $manualLossBroughtForward);

        $this->userStatements->snapshot($statement);
        $this->productStatements->snapshot($statement);
        $this->userProductStatements->snapshot($statement);

        return $statement;
    }

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
            ->get([
                'id', 'period_month', 'total_delivered', 'delivered_orders',
                'gross_profit_delivered_cogs', 'gross_profit_bought_cogs',
                'status', 'generated_at', 'locked_at',
            ]);

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

        $rates = StatementRates::resolve(
            $workspace,
            IncomeStatementSetting::where('workspace_id', $workspace->id)->first(),
            $this->sources->for($workspace),
            [
                'cod' => $request->input('cod_rate') ?? $existing?->cod_fee_rate,
                'vat' => $request->input('vat_rate') ?? $existing?->vat_rate,
                'advisory' => $request->input('advisory_rate') ?? $existing?->advisory_rate,
                'advisory_delivered' => $request->input('advisory_delivered_rate') ?? $existing?->advisory_delivered_rate,
            ],
        );

        ['codFee' => $codRate, 'vat' => $vatRate, 'advisory' => $advisoryRate, 'advisoryDelivered' => $advisoryDeliveredRate] = (array) $rates;

        $revenue = $this->deliveredRevenue($workspace, $from, $to);
        $lines = $this->expenseLines($workspace, $from, $to, $revenue['delivered'], $codRate, $vatRate);
        $figures = $this->figures($workspace, $from, $to, $codRate, $vatRate, $advisoryRate, $advisoryDeliveredRate);

        // Preview always defaults every line checked, so lines that appear after a
        // statement was last saved (e.g. a newly-flagged type, or new transactions)
        // are picked up on the next Save. Regenerate is the "keep my exact set" path.

        return Inertia::render('workspaces/finance/income-statements/show', [
            'workspace' => $workspace,
            'mode' => 'preview',
            // Computed for the month, not read back — nothing is stored yet.
            'figures' => [
                ...$figures,
                ...$this->carriedForward($workspace, $periodMonth, $figures),
                'delivered_amount' => $revenue['delivered'],
            ],
            'statement' => [
                'id' => $existing?->id,
                // A locked month already saved here can't be overwritten by a
                // save, so the preview says so rather than offering the button.
                'locked_at' => $existing?->locked_at?->toIso8601String(),
                'period_month' => $periodMonth,
                'cod_fee_rate' => $codRate,
                'vat_rate' => $vatRate,
                'advisory_rate' => $advisoryRate,
                'advisory_delivered_rate' => $advisoryDeliveredRate,
                'gencys_partner' => (bool) $workspace->is_gencys_partner,
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            // Omitted = every line. The figures don't depend on this, but the
            // OPEX ledger underneath does, and an absent choice means "all"
            // rather than "none".
            'included_keys' => ['nullable', 'array'],
            'included_keys.*' => ['integer'],
            'cod_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'advisory_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'advisory_delivered_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            // What last month left owing, when it was never closed here. Given
            // as a positive amount to deduct; 0 states the month broke even.
            'loss_brought_forward' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Saving writes over whatever the month already holds, so a locked
        // statement has to refuse it the same way regenerate does.
        $existing = IncomeStatement::where('workspace_id', $workspace->id)
            ->whereDate('period_month', $this->resolveMonth($validated['month'])[0])
            ->first();

        if ($existing?->isLocked()) {
            return redirect()
                ->route('workspaces.finance.income-statements.show', [$workspace->slug, $existing->id])
                ->with('error', 'This income statement is locked. Unlock it before saving over it.');
        }

        $rates = StatementRates::resolve(
            $workspace,
            IncomeStatementSetting::where('workspace_id', $workspace->id)->first(),
            $this->sources->for($workspace),
            [
                'cod' => $validated['cod_rate'] ?? null,
                'vat' => $validated['vat_rate'] ?? null,
                'advisory' => $validated['advisory_rate'] ?? null,
                'advisory_delivered' => $validated['advisory_delivered_rate'] ?? null,
            ],
        );

        $statement = $this->generate(
            $workspace,
            $validated['month'],
            $rates,
            requestedKeys: array_key_exists('included_keys', $validated)
                ? collect($validated['included_keys'])
                : null,
            manualLossBroughtForward: isset($validated['loss_brought_forward'])
                ? (float) $validated['loss_brought_forward']
                : null,
        );

        // Whatever was used becomes the workspace's default for next time.
        IncomeStatementSetting::updateOrCreate(
            ['workspace_id' => $workspace->id],
            $rates->toAttributes(),
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

        $incomeStatement->loadMissing('lockedBy:id,name');

        return Inertia::render('workspaces/finance/income-statements/show', [
            'workspace' => $workspace,
            'mode' => 'saved',
            // The figures this statement shares with its per-product and
            // per-user slices, read straight off the saved header.
            'figures' => [
                'delivered_orders' => (int) $incomeStatement->delivered_orders,
                'delivered_amount' => (float) $incomeStatement->total_delivered,
                'shipped_orders' => (int) $incomeStatement->shipped_orders,
                'total_shipping_fee' => (float) $incomeStatement->total_shipping_fee,
                'ad_spent' => (float) $incomeStatement->ad_spent,
                'cod_fee' => (float) $incomeStatement->cod_fee,
                'cod_fee_vat' => (float) $incomeStatement->cod_fee_vat,
                'total_bought_cogs' => (float) $incomeStatement->total_bought_cogs,
                'total_bought_cogs_delivery_fee' => (float) $incomeStatement->total_bought_cogs_delivery_fee,
                'total_delivered_cogs' => (float) $incomeStatement->total_delivered_cogs,
                'opex' => (float) $incomeStatement->opex,
                'net_profit_delivered_cogs' => (float) $incomeStatement->net_profit_delivered_cogs,
                'net_profit_bought_cogs' => (float) $incomeStatement->net_profit_bought_cogs,
                'loss_brought_forward_delivered_cogs' => (float) $incomeStatement->loss_brought_forward_delivered_cogs,
                'loss_brought_forward_bought_cogs' => (float) $incomeStatement->loss_brought_forward_bought_cogs,
                'cumulative_profit_delivered_cogs' => (float) $incomeStatement->cumulative_profit_delivered_cogs,
                'cumulative_profit_bought_cogs' => (float) $incomeStatement->cumulative_profit_bought_cogs,
                'gross_profit_delivered_cogs' => (float) $incomeStatement->gross_profit_delivered_cogs,
                'gross_profit_delivered_cogs_advisory_share' => (float) $incomeStatement->gross_profit_delivered_cogs_advisory_share,
                'gross_profit_delivered_cogs_after_advisory_share' => (float) $incomeStatement->gross_profit_delivered_cogs_after_advisory_share,
                'gross_profit_bought_cogs' => (float) $incomeStatement->gross_profit_bought_cogs,
                'gross_profit_bought_cogs_advisory_share' => (float) $incomeStatement->gross_profit_bought_cogs_advisory_share,
                'gross_profit_bought_cogs_after_advisory_share' => (float) $incomeStatement->gross_profit_bought_cogs_after_advisory_share,
                'advisory_share_on_delivered' => (float) $incomeStatement->advisory_share_on_delivered,
            ],
            'opexBreakdown' => $incomeStatement->opexBreakdown()
                ->with('transactionType:id,name')
                ->orderByDesc('amount')
                ->get()
                ->map(fn ($r) => [
                    'transaction_type_id' => $r->transaction_type_id,
                    'name' => $r->transactionType?->name ?? 'Unknown',
                    'amount' => (float) $r->amount,
                ]),
            'statement' => [
                'id' => $incomeStatement->id,
                'period_month' => $incomeStatement->period_month->toDateString(),
                'cod_fee_rate' => (float) $incomeStatement->cod_fee_rate,
                'vat_rate' => (float) $incomeStatement->vat_rate,
                'manual_loss_brought_forward' => $incomeStatement->manual_loss_brought_forward === null
                    ? null
                    : (float) $incomeStatement->manual_loss_brought_forward,
                'advisory_rate' => (float) $incomeStatement->advisory_rate,
                'advisory_delivered_rate' => (float) $incomeStatement->advisory_delivered_rate,
                'gencys_partner' => (bool) $workspace->is_gencys_partner,
                'generated_at' => $incomeStatement->generated_at?->toIso8601String(),
                'locked_at' => $incomeStatement->locked_at?->toIso8601String(),
                'locked_by_name' => $incomeStatement->lockedBy?->name,
            ],
        ]);
    }

    /** Re-pull the month's numbers with the rates and lines it was saved with. */
    public function regenerate(Request $request, Workspace $workspace, IncomeStatement $incomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        if ($incomeStatement->isLocked()) {
            return $this->refuseLocked('regenerated');
        }

        $this->generate(
            $workspace,
            $incomeStatement->period_month->format('Y-m'),
            StatementRates::fromStatement($incomeStatement),
            regenerating: $incomeStatement,
        );

        return redirect()->back()->with('success', 'Income statement regenerated.');
    }

    /**
     * Close the month: hold these figures as they were struck.
     *
     * The statement is a snapshot of data that keeps moving — a late delivery or
     * a backdated transaction changes what a regenerate would produce. Once the
     * month has been reported on, locking is what says "this is the number", and
     * anything that would rewrite it (regenerate, a save over the top, delete)
     * is refused until someone unlocks.
     */
    public function lock(Request $request, Workspace $workspace, IncomeStatement $incomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        // Already locked is the state asked for, so say so rather than moving
        // the timestamp and losing who closed it first.
        if ($incomeStatement->isLocked()) {
            return redirect()->back()->with('success', 'Income statement is already locked.');
        }

        $incomeStatement->update([
            'locked_at' => now(),
            'locked_by' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'Income statement locked.');
    }

    /** Reopen a closed month so it can be regenerated, re-saved or deleted. */
    public function unlock(Request $request, Workspace $workspace, IncomeStatement $incomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        $incomeStatement->update(['locked_at' => null, 'locked_by' => null]);

        return redirect()->back()->with('success', 'Income statement unlocked.');
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

        if ($incomeStatement->isLocked()) {
            return $this->refuseLocked('deleted');
        }

        $incomeStatement->delete();

        return redirect()
            ->route('workspaces.finance.income-statements.index', $workspace->slug)
            ->with('success', 'Income statement deleted.');
    }

    /**
     * Recompute the month's revenue, cost of sales, gross profit, advisory, OPEX
     * and net profit for the included lines, and (over)write the snapshot.
     */
    private function persist(Workspace $workspace, string $periodMonth, Carbon $from, Carbon $to, Collection $includedKeys, StatementRates $rates, ?float $manualLossBroughtForward = null): IncomeStatement
    {
        ['codFee' => $codRate, 'vat' => $vatRate, 'advisory' => $advisoryRate, 'advisoryDelivered' => $advisoryDeliveredRate] = (array) $rates;

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

        $figures = $this->figures($workspace, $from, $to, $codRate, $vatRate, $advisoryRate, $advisoryDeliveredRate);

        // What last month left owing, and what this month comes to once it is
        // paid off. Worked out before the write so it lands with everything
        // else rather than as a second save.
        $figures = [...$figures, ...$this->carriedForward($workspace, $periodMonth, $figures, $manualLossBroughtForward)];

        return DB::transaction(function () use ($workspace, $periodMonth, $from, $to, $revenue, $included, $costOfSales, $opex, $grossProfit, $netProfit, $rates, $advisoryShare, $figures) {
            $statement = IncomeStatement::updateOrCreate(
                ['workspace_id' => $workspace->id, 'period_month' => $periodMonth],
                [
                    ...$figures,
                    'total_delivered' => $revenue['delivered'],
                    'gross_profit' => $grossProfit,
                    'net_profit' => $netProfit,
                    'total_expenses' => $costOfSales + $opex,
                    ...$rates->toAttributes(),
                    'advisory_share' => $advisoryShare,
                    'status' => 'final',
                    'generated_at' => now(),
                ],
            );

            $statement->breakdown()->delete();

            // The OPEX split, rebuilt with the header it belongs to.
            $statement->opexBreakdown()->delete();

            foreach ($this->transactions->opexByTypeForWorkspace($workspace, $from, $to) as $typeId => $amount) {
                $statement->opexBreakdown()->create([
                    'transaction_type_id' => $typeId,
                    'amount' => $amount,
                ]);
            }

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

    /** Delivered revenue + order count for the month, from whichever source. */
    private function deliveredRevenue(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        $totals = $this->sources->for($workspace)->workspaceTotals($workspace, $from, $to);

        return ['delivered' => $totals->deliveredAmount, 'orders' => $totals->deliveredOrders];
    }

    /** Courier fee on the month's shipped-out parcels, from whichever source. */
    private function shippingFee(Workspace $workspace, Carbon $from, Carbon $to): float
    {
        return $this->sources->for($workspace)->workspaceTotals($workspace, $from, $to)->shippingFee;
    }

    /**
     * The figures the statement shares with its per-product and per-user slices,
     * measured across the whole workspace.
     *
     * Ad spend and bought goods are the month's whole transaction totals here,
     * not just the tagged or charged shares the slices can attribute — this is
     * the company figure, so nothing is left out.
     *
     * @return array<string, float|int>
     */
    private function figures(Workspace $workspace, Carbon $from, Carbon $to, float $codRate, float $vatRate, float $advisoryRate, float $advisoryDeliveredRate): array
    {
        $source = $this->sources->for($workspace);
        $totals = $source->workspaceTotals($workspace, $from, $to);

        $revenue = $totals->deliveredAmount;
        $orders = $totals->deliveredOrders;
        $units = $totals->deliveredUnits;
        $deliveredCogs = $totals->deliveredCogs;
        $shippedOrders = $totals->shippedOrders;
        $shippingFee = $totals->shippingFee;

        // Ad spend follows the workspace's source; goods and their freight are
        // booked through the ledger either way.
        $adSpent = $source->workspaceAdSpend($workspace, $from, $to);
        $boughtCogs = $this->transactions->forWorkspace($workspace, $from, $to, TransactionTotals::COST_OF_GOODS);
        $boughtFreight = $this->transactions->forWorkspace($workspace, $from, $to, TransactionTotals::COG_DELIVERY);
        $opexTotal = $this->transactions->opexForWorkspace($workspace, $from, $to);

        $codFee = round($revenue * $codRate, 2);
        $codVat = round($codFee * $vatRate, 2);

        // Both margins take the same costs off delivered revenue and differ only
        // in which cost of goods they charge.
        $commonCosts = $adSpent + $shippingFee + $codFee + $codVat;

        $grossDelivered = round($revenue - $commonCosts - $deliveredCogs, 2);
        // Freight on a purchase is part of what the stock cost.
        $grossBought = round($revenue - $commonCosts - $boughtCogs - $boughtFreight, 2);

        // A share of gross profit for gencys partners, taken on whichever
        // basis it sits beside. Only a positive gross owes anything — a
        // loss doesn't earn a rebate.
        // The share can be struck two ways — off the margin, or off delivered
        // revenue — and the agreement takes whichever comes out lower. Which one
        // that is flips with the month, so both are worked out and the cheaper
        // is the one actually charged.
        $advisoryOnDelivered = ($workspace->is_gencys_partner && $revenue > 0)
            ? round($revenue * $advisoryDeliveredRate, 2)
            : 0.0;

        $advisory = function (float $gross) use ($workspace, $advisoryRate, $advisoryOnDelivered) {
            if (! $workspace->is_gencys_partner || $gross <= 0) {
                return 0.0;
            }

            return min(round($gross * $advisoryRate, 2), $advisoryOnDelivered);
        };

        return [
            'delivered_orders' => $orders,
            'delivered_units' => $units,
            'shipped_orders' => $shippedOrders,
            'total_shipping_fee' => $shippingFee,
            'ad_spent' => $adSpent,
            'cod_fee' => $codFee,
            'cod_fee_vat' => $codVat,
            'total_bought_cogs' => $boughtCogs,
            'total_bought_cogs_delivery_fee' => $boughtFreight,
            'total_delivered_cogs' => $deliveredCogs,
            'opex' => $opexTotal,
            'gross_profit_delivered_cogs' => $grossDelivered,
            'gross_profit_delivered_cogs_advisory_share' => $advisory($grossDelivered),
            'gross_profit_delivered_cogs_after_advisory_share' => round($grossDelivered - $advisory($grossDelivered), 2),
            'gross_profit_bought_cogs' => $grossBought,
            'gross_profit_bought_cogs_advisory_share' => $advisory($grossBought),
            'gross_profit_bought_cogs_after_advisory_share' => round($grossBought - $advisory($grossBought), 2),
            'advisory_share_on_delivered' => $advisoryOnDelivered,
            // What is left once the running costs come off. Taken from gross
            // after the advisory, so the statement reads as one subtraction.
            'net_profit_delivered_cogs' => round($grossDelivered - $advisory($grossDelivered) - $opexTotal, 2),
            'net_profit_bought_cogs' => round($grossBought - $advisory($grossBought) - $opexTotal, 2),
        ];
    }

    /**
     * What last month left owing, and what this month comes to once it is paid
     * off.
     *
     * A month that ends in the red does not start the next one level: the
     * deficit is brought forward and only what is left after filling it counts
     * as profit. What carries is the previous month's *cumulative* figure, not
     * its net profit, so a run of bad months accumulates rather than each one
     * forgiving everything before it.
     *
     * Nought is carried from a month that ended in profit — a good month is not
     * a credit against a bad one, it has already been taken.
     *
     * @return array<string, float>
     */
    private function carriedForward(Workspace $workspace, string $periodMonth, array $figures, ?float $manual = null): array
    {
        $month = Carbon::parse($periodMonth)->startOfMonth();

        // Entries against a seller and a product are the finest statement of
        // the deficit there is, so where they exist the month's figure is
        // simply their sum — that is what makes the top of the statement agree
        // with the bottom of the slices.
        $entered = $this->carryovers->forWorkspace($workspace, $month);
        $hasEntries = $entered > 0;

        $previous = ($manual === null && ! $hasEntries)
            ? IncomeStatement::where('workspace_id', $workspace->id)
                ->whereDate('period_month', $month->copy()->subMonthNoOverflow())
                ->first()
            : null;

        $carried = ['manual_loss_brought_forward' => $manual];

        foreach (['delivered_cogs', 'bought_cogs'] as $basis) {
            // Precedence, finest first: what was entered per seller and
            // product, then a figure typed for the month as a whole, then last
            // month's own closing position. The first two apply to both bases,
            // coming from books that knew one bottom line.
            $before = (float) ($previous?->{"cumulative_profit_{$basis}"} ?? 0.0);
            $loss = match (true) {
                $hasEntries => $entered,
                $manual !== null => round(max($manual, 0.0), 2),
                default => $before < 0 ? round(abs($before), 2) : 0.0,
            };

            $carried["loss_brought_forward_{$basis}"] = $loss;
            $carried["cumulative_profit_{$basis}"] = round(
                (float) ($figures["net_profit_{$basis}"] ?? 0.0) - $loss,
                2,
            );
        }

        return $carried;
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

    /**
     * @return array{0:string, 1:Carbon, 2:Carbon} [periodMonth (Y-m-d, 1st), from, to]
     */
    private function resolveMonth(?string $month): array
    {
        // The `!` resets the unparsed fields. Without it Carbon fills the day
        // from today, so asking for February on the 31st lands on March 3rd and
        // startOfMonth() then reads March — a statement regenerated late in the
        // month would rewrite the wrong one.
        $start = $month
            ? Carbon::createFromFormat('!Y-m', $month)->startOfMonth()
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

    /** @param  string  $verb  what was refused, e.g. "regenerated" */
    private function refuseLocked(string $verb): RedirectResponse
    {
        return redirect()->back()
            ->with('error', "This income statement is locked and cannot be {$verb}. Unlock it first.");
    }
}
