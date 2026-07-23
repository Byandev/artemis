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
use Modules\Finance\Models\UserIncomeStatement;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\Intern;
use Modules\GencysERP\Support\InternResolver;

/**
 * Monthly per-user (per-intern) income statement for gencys-partner workspaces.
 *
 * Revenue is the intern's delivered gencys orders, broken down by product. Each
 * product carries its own fully-attributable cost:
 *
 *   Product Gross = Revenue − (COGS + Shipping + COD + VAT + product-tagged txns)
 *
 * where the tagged txns are finance transactions charged to the intern AND tagged
 * to that product. Σ product gross = Gross Profit. The intern's remaining charged
 * transactions (untagged) are user-level OPEX. Net = Gross − OPEX − Advisory.
 */
class UserIncomeStatementController extends Controller
{
    use AuthorizesRequests;

    private const DELIVERED_STATUS = 'DELIVERED';

    /** SQL that normalizes order_details to a product name (strips "1x1X"/"2X" prefixes). */
    private const PRODUCT_EXPR = "UPPER(TRIM(REGEXP_REPLACE(order_details, '^[0-9]+[xX][0-9]*[xX]? *', '')))";

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        $statements = UserIncomeStatement::where('workspace_id', $workspace->id)
            ->orderByDesc('period_month')
            ->orderByDesc('net_profit')
            ->get(['id', 'intern_name', 'period_month', 'total_delivered', 'gross_profit', 'total_opex', 'net_profit', 'status', 'generated_at']);

        return Inertia::render('workspaces/finance/user-income-statements/index', [
            'workspace' => $workspace,
            'statements' => $statements,
            'interns' => $this->internOptions($workspace),
            'currentMonth' => Carbon::now()->format('Y-m'),
        ]);
    }

    public function preview(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        $intern = $this->resolveIntern($workspace, $request->input('intern_id'));
        [$periodMonth, $from, $to] = $this->resolveMonth($request->input('month'));

        $existing = UserIncomeStatement::where('workspace_id', $workspace->id)
            ->where('gencys_intern_id', $intern->id)
            ->whereDate('period_month', $periodMonth)
            ->first();

        [$codRate, $vatRate, $advisoryRate] = $this->workspaceRates($workspace, $existing);

        $b = $this->build($workspace, $intern, $from, $to, $codRate, $vatRate, $advisoryRate);

        return Inertia::render('workspaces/finance/user-income-statements/show', [
            'workspace' => $workspace,
            'mode' => 'preview',
            'statement' => $this->presentComputed($workspace, $intern, $periodMonth, $existing?->id, $codRate, $vatRate, $advisoryRate, $b),
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        $validated = $request->validate([
            'intern_id' => ['required', 'integer'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $intern = $this->resolveIntern($workspace, $validated['intern_id']);
        [$periodMonth, $from, $to] = $this->resolveMonth($validated['month']);
        [$codRate, $vatRate, $advisoryRate] = $this->workspaceRates($workspace, null);

        $statement = $this->persist($workspace, $intern, $periodMonth, $from, $to, $codRate, $vatRate, $advisoryRate);

        return redirect()
            ->route('workspaces.finance.user-income-statements.show', [$workspace->slug, $statement->id])
            ->with('success', 'User income statement saved.');
    }

    public function show(Request $request, Workspace $workspace, UserIncomeStatement $userIncomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $userIncomeStatement);

        $userIncomeStatement->load(['productBreakdown', 'expenseBreakdown']);

        return Inertia::render('workspaces/finance/user-income-statements/show', [
            'workspace' => $workspace,
            'mode' => 'saved',
            'statement' => $this->presentSaved($workspace, $userIncomeStatement),
        ]);
    }

    /** Re-pull the month's numbers with the snapshotted rates. */
    public function regenerate(Request $request, Workspace $workspace, UserIncomeStatement $userIncomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $userIncomeStatement);

        $intern = $this->resolveIntern($workspace, $userIncomeStatement->gencys_intern_id);
        [$periodMonth, $from, $to] = $this->resolveMonth($userIncomeStatement->period_month->format('Y-m'));

        $this->persist(
            $workspace,
            $intern,
            $periodMonth,
            $from,
            $to,
            (float) $userIncomeStatement->cod_fee_rate,
            (float) $userIncomeStatement->vat_rate,
            (float) $userIncomeStatement->advisory_rate,
        );

        return redirect()->back()->with('success', 'User income statement regenerated.');
    }

    public function export(Request $request, Workspace $workspace, UserIncomeStatement $userIncomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $userIncomeStatement);

        $userIncomeStatement->load(['productBreakdown', 'expenseBreakdown']);
        $label = $userIncomeStatement->period_month->format('Y-m');
        $slug = str($userIncomeStatement->intern_name)->slug();
        $fileName = "user-income-statement-{$slug}-{$label}.csv";

        return response()->streamDownload(function () use ($userIncomeStatement) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['User Income Statement', $userIncomeStatement->period_month->format('F Y')]);
            fputcsv($out, ['Intern', $userIncomeStatement->intern_name]);
            fputcsv($out, []);
            fputcsv($out, ['Product', 'Revenue', 'Orders', 'COGS', 'Shipping', 'COD', 'VAT', 'Tagged', 'Gross Profit']);
            foreach ($userIncomeStatement->productBreakdown as $p) {
                fputcsv($out, [$p->product, $p->revenue, $p->orders, $p->cogs, $p->shipping, $p->cod, $p->vat, $p->tagged_expense, $p->gross_profit]);
            }
            fputcsv($out, ['Gross Profit', $userIncomeStatement->gross_profit]);
            fputcsv($out, []);
            fputcsv($out, ['OPEX', 'Amount']);
            foreach ($userIncomeStatement->expenseBreakdown as $e) {
                fputcsv($out, [$e->type_name, $e->amount]);
            }
            if ($userIncomeStatement->advisory_share > 0) {
                fputcsv($out, ['Advisory Share', $userIncomeStatement->advisory_share]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Net Profit', $userIncomeStatement->net_profit]);

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    public function destroy(Request $request, Workspace $workspace, UserIncomeStatement $userIncomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $userIncomeStatement);

        $userIncomeStatement->delete();

        return redirect()
            ->route('workspaces.finance.user-income-statements.index', $workspace->slug)
            ->with('success', 'User income statement deleted.');
    }

    /**
     * Compute the month's per-product rows, OPEX buckets and the derived totals.
     *
     * @return array{products:Collection, opex:Collection, delivered:float, orders:int, cogs:float, shipping:float, cod:float, vat:float, tagged:float, gross:float, opexTotal:float, advisory:float, net:float}
     */
    private function build(Workspace $workspace, Intern $intern, Carbon $from, Carbon $to, float $codRate, float $vatRate, float $advisoryRate): array
    {
        $cells = $this->internCells($workspace, $intern);
        $products = $this->productRows($workspace, $intern, $cells, $from, $to, $codRate, $vatRate);
        $opex = $this->opexBuckets($workspace, $intern, $from, $to);

        // Round the summed totals so the snapshot and the live payload agree to
        // the cent (float sums like 5120.80 + 2660.40 drift otherwise).
        $delivered = round((float) $products->sum('revenue'), 2);
        $gross = round((float) $products->sum('gross_profit'), 2);
        $opexTotal = round((float) $opex->sum('amount'), 2);
        $advisory = ($workspace->is_gencys_partner && $gross > 0) ? round($gross * $advisoryRate, 2) : 0.0;

        return [
            'products' => $products,
            'opex' => $opex,
            'delivered' => $delivered,
            'orders' => (int) $products->sum('orders'),
            'cogs' => round((float) $products->sum('cogs'), 2),
            'shipping' => round((float) $products->sum('shipping'), 2),
            'cod' => round((float) $products->sum('cod'), 2),
            'vat' => round((float) $products->sum('vat'), 2),
            'tagged' => round((float) $products->sum('tagged_expense'), 2),
            'gross' => $gross,
            'opexTotal' => $opexTotal,
            'advisory' => $advisory,
            'net' => round($gross - $opexTotal - $advisory, 2),
        ];
    }

    private function persist(Workspace $workspace, Intern $intern, string $periodMonth, Carbon $from, Carbon $to, float $codRate, float $vatRate, float $advisoryRate): UserIncomeStatement
    {
        $b = $this->build($workspace, $intern, $from, $to, $codRate, $vatRate, $advisoryRate);

        $parentId = IncomeStatement::where('workspace_id', $workspace->id)
            ->whereDate('period_month', $periodMonth)
            ->value('id');

        return DB::transaction(function () use ($workspace, $intern, $parentId, $periodMonth, $b, $codRate, $vatRate, $advisoryRate) {
            $statement = UserIncomeStatement::updateOrCreate(
                ['workspace_id' => $workspace->id, 'gencys_intern_id' => $intern->id, 'period_month' => $periodMonth],
                [
                    'income_statement_id' => $parentId,
                    'user_id' => $intern->user_id,
                    'intern_name' => $intern->full_name ?: ('Intern #'.$intern->id),
                    'total_delivered' => $b['delivered'],
                    'delivered_orders' => $b['orders'],
                    'total_cogs' => $b['cogs'],
                    'total_shipping' => $b['shipping'],
                    'total_cod' => $b['cod'],
                    'total_vat' => $b['vat'],
                    'total_tagged' => $b['tagged'],
                    'gross_profit' => $b['gross'],
                    'total_opex' => $b['opexTotal'],
                    'advisory_rate' => $advisoryRate,
                    'advisory_share' => $b['advisory'],
                    'cod_fee_rate' => $codRate,
                    'vat_rate' => $vatRate,
                    'net_profit' => $b['net'],
                    'status' => 'final',
                    'generated_at' => now(),
                ],
            );

            $statement->productBreakdown()->delete();
            foreach ($b['products'] as $p) {
                $statement->productBreakdown()->create($p);
            }

            $statement->expenseBreakdown()->delete();
            foreach ($b['opex'] as $e) {
                $statement->expenseBreakdown()->create($e);
            }

            return $statement;
        });
    }

    /** Distinct intern_brands_name cells in the workspace that resolve to this intern. */
    private function internCells(Workspace $workspace, Intern $intern): array
    {
        $resolver = new InternResolver($workspace->id);

        return GencysDailySalesOrder::where('workspace_id', $workspace->id)
            ->whereNotNull('intern_brands_name')
            ->where('intern_brands_name', '!=', '')
            ->distinct()
            ->pluck('intern_brands_name')
            ->filter(fn ($cell) => $resolver->resolve($cell) === $intern->id)
            ->values()
            ->all();
    }

    /**
     * Per-product gross P&L rows for the month.
     *
     * @param  array<int, string>  $cells
     */
    private function productRows(Workspace $workspace, Intern $intern, array $cells, Carbon $from, Carbon $to, float $codRate, float $vatRate): Collection
    {
        $rev = collect();
        $ship = collect();

        if (! empty($cells)) {
            $rev = GencysDailySalesOrder::where('workspace_id', $workspace->id)
                ->where('parcel_status', self::DELIVERED_STATUS)
                ->whereBetween('parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->whereIn('intern_brands_name', $cells)
                ->selectRaw(self::PRODUCT_EXPR.' as product, COALESCE(SUM(price_final), 0) as revenue, COALESCE(SUM(total_cog), 0) as cogs, COUNT(*) as orders')
                ->groupByRaw(self::PRODUCT_EXPR)
                ->get()
                ->keyBy('product');

            $ship = GencysDailySalesOrder::where('workspace_id', $workspace->id)
                ->whereBetween('shipped_out_date', [$from->toDateString(), $to->toDateString()])
                ->whereIn('intern_brands_name', $cells)
                ->selectRaw(self::PRODUCT_EXPR.' as product, COALESCE(SUM(shipping_fee), 0) as shipping')
                ->groupByRaw(self::PRODUCT_EXPR)
                ->pluck('shipping', 'product');
        }

        $tagged = $this->taggedByProduct($workspace, $intern, $from, $to);

        $products = collect($rev->keys())
            ->merge($tagged->keys())
            ->filter(fn ($p) => filled($p))
            ->unique()
            ->values();

        return $products->map(function ($p) use ($rev, $ship, $tagged, $codRate, $vatRate) {
            $row = $rev->get($p);
            $revenue = $row ? (float) $row->revenue : 0.0;
            $cogs = $row ? (float) $row->cogs : 0.0;
            $orders = $row ? (int) $row->orders : 0;
            $shipping = round((float) ($ship[$p] ?? 0), 2);
            $cod = round($revenue * $codRate, 2);
            $vat = round($cod * $vatRate, 2);
            $taggedExp = round((float) ($tagged[$p] ?? 0), 2);

            return [
                'product' => $p,
                'revenue' => $revenue,
                'orders' => $orders,
                'cogs' => $cogs,
                'shipping' => $shipping,
                'cod' => $cod,
                'vat' => $vat,
                'tagged_expense' => $taggedExp,
                'gross_profit' => round($revenue - ($cogs + $shipping + $cod + $vat + $taggedExp), 2),
            ];
        })->sortByDesc('revenue')->values();
    }

    /** Product-tagged transactions charged to the intern, summed per product. */
    private function taggedByProduct(Workspace $workspace, Intern $intern, Carbon $from, Carbon $to): Collection
    {
        if (! $intern->user_id) {
            return collect();
        }

        return Transaction::where('workspace_id', $workspace->id)
            ->where('type', 'out')
            ->where('charge_to', $intern->user_id)
            ->whereNotNull('product')
            ->where('product', '!=', '')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('product, COALESCE(SUM(amount), 0) as total')
            ->groupBy('product')
            ->pluck('total', 'product');
    }

    /** Untagged transactions charged to the intern, bucketed by transaction type. */
    private function opexBuckets(Workspace $workspace, Intern $intern, Carbon $from, Carbon $to): Collection
    {
        if (! $intern->user_id) {
            return collect();
        }

        $types = TransactionType::where('workspace_id', $workspace->id)->get(['id', 'name'])->keyBy('id');

        return Transaction::where('workspace_id', $workspace->id)
            ->where('type', 'out')
            ->where('charge_to', $intern->user_id)
            ->where(fn ($q) => $q->whereNull('product')->orWhere('product', ''))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(transaction_type_id, 0) as type_key, COALESCE(SUM(amount), 0) as total')
            ->groupBy('type_key')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'transaction_type_id' => $r->type_key ? (int) $r->type_key : null,
                'type_name' => $r->type_key ? ($types[$r->type_key]->name ?? 'Unknown') : 'Uncategorized',
                'amount' => (float) $r->total,
            ]);
    }

    /** Shape a freshly-computed statement for the ledger page. */
    private function presentComputed(Workspace $workspace, Intern $intern, string $periodMonth, ?int $existingId, float $codRate, float $vatRate, float $advisoryRate, array $b): array
    {
        return [
            'id' => $existingId,
            'intern_id' => $intern->id,
            'intern_name' => $intern->full_name ?: ('Intern #'.$intern->id),
            'period_month' => $periodMonth,
            'delivered' => $b['delivered'],
            'orders' => $b['orders'],
            'gross_profit' => $b['gross'],
            'total_opex' => $b['opexTotal'],
            'advisory_rate' => $advisoryRate,
            'advisory_share' => $b['advisory'],
            'net_profit' => $b['net'],
            'cod_fee_rate' => $codRate,
            'vat_rate' => $vatRate,
            'gencys_partner' => (bool) $workspace->is_gencys_partner,
            'products' => $b['products']->values(),
            'opex' => $b['opex']->map(fn ($e) => ['type_name' => $e['type_name'], 'amount' => $e['amount']])->values(),
        ];
    }

    /** Shape a saved snapshot for the ledger page. */
    private function presentSaved(Workspace $workspace, UserIncomeStatement $s): array
    {
        return [
            'id' => $s->id,
            'intern_id' => $s->gencys_intern_id,
            'intern_name' => $s->intern_name,
            'period_month' => $s->period_month->toDateString(),
            'delivered' => (float) $s->total_delivered,
            'orders' => (int) $s->delivered_orders,
            'gross_profit' => (float) $s->gross_profit,
            'total_opex' => (float) $s->total_opex,
            'advisory_rate' => (float) $s->advisory_rate,
            'advisory_share' => (float) $s->advisory_share,
            'net_profit' => (float) $s->net_profit,
            'cod_fee_rate' => (float) $s->cod_fee_rate,
            'vat_rate' => (float) $s->vat_rate,
            'gencys_partner' => (bool) $workspace->is_gencys_partner,
            'generated_at' => $s->generated_at?->toIso8601String(),
            'products' => $s->productBreakdown->map(fn ($p) => [
                'product' => $p->product,
                'revenue' => (float) $p->revenue,
                'orders' => (int) $p->orders,
                'cogs' => (float) $p->cogs,
                'shipping' => (float) $p->shipping,
                'cod' => (float) $p->cod,
                'vat' => (float) $p->vat,
                'tagged_expense' => (float) $p->tagged_expense,
                'gross_profit' => (float) $p->gross_profit,
            ])->values(),
            'opex' => $s->expenseBreakdown->map(fn ($e) => [
                'type_name' => $e->type_name,
                'amount' => (float) $e->amount,
            ])->values(),
        ];
    }

    /** Active interns for the picker. */
    private function internOptions(Workspace $workspace): Collection
    {
        return Intern::where('workspace_id', $workspace->id)
            ->where('active', true)
            ->whereNotNull('full_name')
            ->orderBy('full_name')
            ->get(['id', 'full_name'])
            ->map(fn ($i) => ['id' => $i->id, 'name' => $i->full_name]);
    }

    private function resolveIntern(Workspace $workspace, $internId): Intern
    {
        $intern = Intern::where('workspace_id', $workspace->id)->find($internId);

        if (! $intern) {
            abort(404, 'Intern not found in this workspace.');
        }

        return $intern;
    }

    /** [cod, vat, advisory] rates — snapshotted on the statement, else workspace default. */
    private function workspaceRates(Workspace $workspace, ?UserIncomeStatement $existing): array
    {
        $settings = IncomeStatementSetting::where('workspace_id', $workspace->id)->first();

        return [
            (float) ($existing?->cod_fee_rate ?? $settings?->cod_fee_rate ?? IncomeStatementSetting::DEFAULT_COD_FEE_RATE),
            (float) ($existing?->vat_rate ?? $settings?->vat_rate ?? IncomeStatementSetting::DEFAULT_VAT_RATE),
            (float) ($existing?->advisory_rate ?? $settings?->advisory_rate ?? IncomeStatementSetting::DEFAULT_ADVISORY_RATE),
        ];
    }

    /**
     * @return array{0:string, 1:Carbon, 2:Carbon} [periodMonth (Y-m-d, 1st), from, to]
     */
    private function resolveMonth(?string $month): array
    {
        // `!Y-m` resets day/time to the 1st at 00:00:00, avoiding the month-overflow
        // footgun where a short month generated late in the month rolls forward.
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

    private function ensureOwns(Workspace $workspace, UserIncomeStatement $statement): void
    {
        if ($statement->workspace_id !== $workspace->id) {
            abort(404);
        }
    }
}
