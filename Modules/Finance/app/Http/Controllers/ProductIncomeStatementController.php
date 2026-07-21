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
use Modules\Finance\Models\ProductIncomeStatement;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionType;
use Modules\GencysERP\Models\GencysDailySalesOrder;

/**
 * Monthly per-product income statement for gencys-partner workspaces.
 *
 * "Product" is the normalized gencys order_details name (quantity prefix
 * stripped). Revenue scopes to orders whose normalized order_details = product;
 * expenses scope to finance transactions tagged with that product (`product`).
 * Otherwise identical to the workspace statement (two-tier P&L + advisory) and it
 * reuses the same ledger page (workspaces/finance/income-statements/show).
 */
class ProductIncomeStatementController extends Controller
{
    use AuthorizesRequests;

    private const DELIVERED_STATUS = 'DELIVERED';

    private const SHIPPING_FEE_KEY = -1;

    private const COD_FEE_KEY = -2;

    private const VAT_KEY = -3;

    /** SQL that normalizes order_details to a product name (strips "1x1X"/"2X" prefixes). */
    private const PRODUCT_EXPR = "UPPER(TRIM(REGEXP_REPLACE(order_details, '^[0-9]+[xX][0-9]*[xX]? *', '')))";

    /** Base route path for the shared ledger page. */
    private function base(Workspace $workspace): string
    {
        return "/workspaces/{$workspace->slug}/finance/product-income-statements";
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        $statements = ProductIncomeStatement::where('workspace_id', $workspace->id)
            ->orderByDesc('period_month')
            ->orderByDesc('total_delivered')
            ->get(['id', 'product', 'period_month', 'total_delivered', 'gross_profit', 'total_expenses', 'net_profit', 'status', 'generated_at']);

        return Inertia::render('workspaces/finance/product-income-statements/index', [
            'workspace' => $workspace,
            'statements' => $statements,
            'products' => $this->productOptions($workspace),
            'currentMonth' => Carbon::now()->format('Y-m'),
        ]);
    }

    public function preview(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        $product = $this->resolveProduct($request->input('product'));
        [$periodMonth, $from, $to] = $this->resolveMonth($request->input('month'));

        $existing = ProductIncomeStatement::with('breakdown')
            ->where('workspace_id', $workspace->id)
            ->where('product', $product)
            ->whereDate('period_month', $periodMonth)
            ->first();

        [$defaultCod, $defaultVat, $defaultAdvisory] = $this->workspaceRates($workspace);
        $codRate = (float) ($request->input('cod_rate') ?? $existing?->cod_fee_rate ?? $defaultCod);
        $vatRate = (float) ($request->input('vat_rate') ?? $existing?->vat_rate ?? $defaultVat);
        $advisoryRate = (float) ($request->input('advisory_rate') ?? $existing?->advisory_rate ?? $defaultAdvisory);

        $revenue = $this->deliveredRevenue($workspace, $from, $to, $product);
        $lines = $this->expenseLines($workspace, $from, $to, $revenue['delivered'], $codRate, $vatRate, $product);

        return Inertia::render('workspaces/finance/income-statements/show', [
            'workspace' => $workspace,
            'mode' => 'preview',
            'base' => $this->base($workspace),
            'scope' => ['label' => $product, 'params' => ['product' => $product]],
            'statement' => [
                'id' => $existing?->id,
                'period_month' => $periodMonth,
                'delivered' => $revenue['delivered'],
                'orders' => $revenue['orders'],
                'cod_fee_rate' => $codRate,
                'vat_rate' => $vatRate,
                'advisory_rate' => $advisoryRate,
                'gencys_partner' => (bool) $workspace->is_gencys_partner,
                'expenses' => $lines->map(fn ($l) => [...$l, 'included' => true])->values(),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'product' => ['required', 'string', 'max:191'],
            'included_keys' => ['array'],
            'included_keys.*' => ['integer'],
            'cod_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'advisory_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $product = $this->resolveProduct($validated['product']);
        [$periodMonth, $from, $to] = $this->resolveMonth($validated['month']);

        [$defaultCod, $defaultVat, $defaultAdvisory] = $this->workspaceRates($workspace);
        $codRate = (float) ($validated['cod_rate'] ?? $defaultCod);
        $vatRate = (float) ($validated['vat_rate'] ?? $defaultVat);
        $advisoryRate = (float) ($validated['advisory_rate'] ?? $defaultAdvisory);

        $statement = $this->persist($workspace, $product, $periodMonth, $from, $to, collect($validated['included_keys'] ?? []), $codRate, $vatRate, $advisoryRate);

        return redirect()
            ->route('workspaces.finance.product-income-statements.show', [$workspace->slug, $statement->id])
            ->with('success', 'Product income statement saved.');
    }

    public function show(Request $request, Workspace $workspace, ProductIncomeStatement $productIncomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $productIncomeStatement);

        $productIncomeStatement->load('breakdown');

        return Inertia::render('workspaces/finance/income-statements/show', [
            'workspace' => $workspace,
            'mode' => 'saved',
            'base' => $this->base($workspace),
            'scope' => ['label' => $productIncomeStatement->product],
            'statement' => [
                'id' => $productIncomeStatement->id,
                'period_month' => $productIncomeStatement->period_month->toDateString(),
                'delivered' => (float) $productIncomeStatement->total_delivered,
                'orders' => (int) $productIncomeStatement->delivered_orders,
                'gross_profit' => (float) $productIncomeStatement->gross_profit,
                'total_expenses' => (float) $productIncomeStatement->total_expenses,
                'net_profit' => (float) $productIncomeStatement->net_profit,
                'cod_fee_rate' => (float) $productIncomeStatement->cod_fee_rate,
                'vat_rate' => (float) $productIncomeStatement->vat_rate,
                'advisory_rate' => (float) $productIncomeStatement->advisory_rate,
                'advisory_share' => (float) $productIncomeStatement->advisory_share,
                'gencys_partner' => (bool) $workspace->is_gencys_partner,
                'generated_at' => $productIncomeStatement->generated_at?->toIso8601String(),
                'expenses' => $productIncomeStatement->breakdown->map(fn ($b) => [
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

    public function regenerate(Request $request, Workspace $workspace, ProductIncomeStatement $productIncomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $productIncomeStatement);

        [$periodMonth, $from, $to] = $this->resolveMonth($productIncomeStatement->period_month->format('Y-m'));
        $includedKeys = $productIncomeStatement->breakdown->map(fn ($b) => $this->keyForRow($b));

        $this->persist(
            $workspace,
            $productIncomeStatement->product,
            $periodMonth,
            $from,
            $to,
            $includedKeys,
            (float) $productIncomeStatement->cod_fee_rate,
            (float) $productIncomeStatement->vat_rate,
            (float) $productIncomeStatement->advisory_rate,
        );

        return redirect()->back()->with('success', 'Product income statement regenerated.');
    }

    public function export(Request $request, Workspace $workspace, ProductIncomeStatement $productIncomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $productIncomeStatement);

        $productIncomeStatement->load('breakdown');
        $label = $productIncomeStatement->period_month->format('Y-m');
        $slug = str($productIncomeStatement->product)->slug();
        $fileName = "income-statement-{$slug}-{$label}.csv";

        return response()->streamDownload(function () use ($productIncomeStatement) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Product Income Statement', $productIncomeStatement->period_month->format('F Y')]);
            fputcsv($out, ['Product', $productIncomeStatement->product]);
            fputcsv($out, []);
            fputcsv($out, ['Total Delivered', $productIncomeStatement->total_delivered]);
            fputcsv($out, ['Delivered Orders', $productIncomeStatement->delivered_orders]);
            fputcsv($out, []);
            fputcsv($out, ['Cost of Sales', 'Amount']);
            foreach ($productIncomeStatement->breakdown->where('section', 'cost_of_sales') as $row) {
                fputcsv($out, [$row->type_name, $row->amount]);
            }
            fputcsv($out, ['Gross Profit', $productIncomeStatement->gross_profit]);
            fputcsv($out, []);
            fputcsv($out, ['OPEX', 'Amount']);
            foreach ($productIncomeStatement->breakdown->where('section', 'opex') as $row) {
                fputcsv($out, [$row->type_name, $row->amount]);
            }
            if ($productIncomeStatement->advisory_share > 0) {
                fputcsv($out, ['Advisory Share', $productIncomeStatement->advisory_share]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Net Profit', $productIncomeStatement->net_profit]);

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    public function destroy(Request $request, Workspace $workspace, ProductIncomeStatement $productIncomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $productIncomeStatement);

        $productIncomeStatement->delete();

        return redirect()
            ->route('workspaces.finance.product-income-statements.index', $workspace->slug)
            ->with('success', 'Product income statement deleted.');
    }

    private function persist(Workspace $workspace, string $product, string $periodMonth, Carbon $from, Carbon $to, Collection $includedKeys, float $codRate, float $vatRate, float $advisoryRate): ProductIncomeStatement
    {
        $revenue = $this->deliveredRevenue($workspace, $from, $to, $product);

        $included = $this->expenseLines($workspace, $from, $to, $revenue['delivered'], $codRate, $vatRate, $product)
            ->filter(fn ($l) => $includedKeys->contains($l['type_key']))
            ->values();

        $costOfSales = (float) $included->where('section', 'cost_of_sales')->sum('amount');
        $opex = (float) $included->where('section', 'opex')->sum('amount');

        $grossProfit = $revenue['delivered'] - $costOfSales;
        $advisoryShare = ($workspace->is_gencys_partner && $grossProfit > 0) ? round($grossProfit * $advisoryRate, 2) : 0.0;
        $netProfit = $grossProfit - $opex - $advisoryShare;

        // Link to the parent monthly workspace income statement, if one exists.
        $parentId = IncomeStatement::where('workspace_id', $workspace->id)
            ->whereDate('period_month', $periodMonth)
            ->value('id');

        return DB::transaction(function () use ($workspace, $parentId, $product, $periodMonth, $revenue, $included, $costOfSales, $opex, $grossProfit, $netProfit, $codRate, $vatRate, $advisoryRate, $advisoryShare) {
            $statement = ProductIncomeStatement::updateOrCreate(
                ['workspace_id' => $workspace->id, 'product' => $product, 'period_month' => $periodMonth],
                [
                    'income_statement_id' => $parentId,
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
                    'transaction_type_id' => ($l['source'] === 'transaction_type' && $l['type_key'] !== 0) ? $l['type_key'] : null,
                    'type_name' => $l['type_name'],
                    'amount' => $l['amount'],
                ]);
            }

            return $statement;
        });
    }

    /** @return Collection<int, array{type_key:int, type_name:string, amount:float, source:string, section:string}> */
    private function expenseLines(Workspace $workspace, Carbon $from, Carbon $to, float $delivered, float $codRate, float $vatRate, string $product): Collection
    {
        $buckets = $this->transactionBuckets($workspace, $from, $to, $product);
        $lines = collect();

        $shipping = $this->shippingFee($workspace, $from, $to, $product);
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

        foreach ($buckets->where('flagged', true) as $b) {
            $lines->push($this->line($b['type_key'], $b['type_name'], $b['amount'], 'transaction_type', 'cost_of_sales'));
        }
        foreach ($buckets->where('flagged', false) as $b) {
            $lines->push($this->line($b['type_key'], $b['type_name'], $b['amount'], 'transaction_type', 'opex'));
        }

        return $lines->values();
    }

    /** @return array{type_key:int, type_name:string, amount:float, source:string, section:string} */
    private function line(int $key, string $name, float $amount, string $source, string $section): array
    {
        return ['type_key' => $key, 'type_name' => $name, 'amount' => $amount, 'source' => $source, 'section' => $section];
    }

    private function deliveredRevenue(Workspace $workspace, Carbon $from, Carbon $to, string $product): array
    {
        $row = GencysDailySalesOrder::where('workspace_id', $workspace->id)
            ->where('parcel_status', self::DELIVERED_STATUS)
            ->whereBetween('parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereRaw(self::PRODUCT_EXPR.' = ?', [$product])
            ->selectRaw('COALESCE(SUM(price_final), 0) as delivered, COUNT(*) as orders')
            ->first();

        return ['delivered' => (float) $row->delivered, 'orders' => (int) $row->orders];
    }

    private function shippingFee(Workspace $workspace, Carbon $from, Carbon $to, string $product): float
    {
        return (float) GencysDailySalesOrder::where('workspace_id', $workspace->id)
            ->whereBetween('shipped_out_date', [$from->toDateString(), $to->toDateString()])
            ->whereRaw(self::PRODUCT_EXPR.' = ?', [$product])
            ->sum('shipping_fee');
    }

    /** @return Collection<int, array{type_key:int, type_name:string, amount:float, flagged:bool}> */
    private function transactionBuckets(Workspace $workspace, Carbon $from, Carbon $to, string $product): Collection
    {
        $types = TransactionType::where('workspace_id', $workspace->id)
            ->get(['id', 'name', 'is_gross_profit_deduction'])
            ->keyBy('id');

        return Transaction::where('workspace_id', $workspace->id)
            ->where('type', 'out')
            ->where('product', $product)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(transaction_type_id, 0) as type_key, SUM(amount) as total')
            ->groupBy('type_key')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'type_key' => (int) $r->type_key,
                'type_name' => $r->type_key ? ($types[$r->type_key]->name ?? 'Unknown') : 'Uncategorized',
                'amount' => (float) $r->total,
                'flagged' => $r->type_key ? (bool) ($types[$r->type_key]->is_gross_profit_deduction ?? false) : false,
            ]);
    }

    /** Distinct normalized products for this workspace, ordered by order volume. */
    private function productOptions(Workspace $workspace): Collection
    {
        return GencysDailySalesOrder::where('workspace_id', $workspace->id)
            ->whereNotNull('order_details')
            ->where('order_details', '!=', '')
            ->selectRaw(self::PRODUCT_EXPR.' as product, COUNT(*) as orders')
            ->groupByRaw(self::PRODUCT_EXPR)
            ->orderByDesc('orders')
            ->limit(200)
            ->get()
            ->map(fn ($r) => ['product' => $r->product, 'orders' => (int) $r->orders])
            ->filter(fn ($r) => $r['product'] !== '')
            ->values();
    }

    private function resolveProduct($raw): string
    {
        $product = trim((string) $raw);
        if ($product === '') {
            abort(422, 'A product is required.');
        }

        return $product;
    }

    private function keyForRow(object $row): int
    {
        return match ($row->source) {
            'shipping_fee' => self::SHIPPING_FEE_KEY,
            'cod_fee' => self::COD_FEE_KEY,
            'vat' => self::VAT_KEY,
            default => (int) ($row->transaction_type_id ?? 0),
        };
    }

    private function workspaceRates(Workspace $workspace): array
    {
        $settings = IncomeStatementSetting::where('workspace_id', $workspace->id)->first();

        return [
            (float) ($settings?->cod_fee_rate ?? IncomeStatementSetting::DEFAULT_COD_FEE_RATE),
            (float) ($settings?->vat_rate ?? IncomeStatementSetting::DEFAULT_VAT_RATE),
            (float) ($settings?->advisory_rate ?? IncomeStatementSetting::DEFAULT_ADVISORY_RATE),
        ];
    }

    /** @return array{0:string, 1:Carbon, 2:Carbon} */
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

    private function ensureOwns(Workspace $workspace, ProductIncomeStatement $statement): void
    {
        if ($statement->workspace_id !== $workspace->id) {
            abort(404);
        }
    }
}
