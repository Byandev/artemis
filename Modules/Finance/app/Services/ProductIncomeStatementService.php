<?php

namespace Modules\Finance\Services;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Services\Concerns\ClosesOutSlices;
use Modules\Finance\Statements\LossCarryovers;
use Modules\Finance\Statements\OrderTotals;
use Modules\Finance\Statements\StatementOrderSourceFactory;
use Modules\Finance\Statements\TransactionTotals;

/**
 * Builds, saves and reads the per-product slices of an income statement.
 *
 * A product row is workspace-wide: every seller's orders for that product, not
 * one person's. Where those orders come from and how they resolve to a product
 * is the order source's business — this class only knows what to do with the
 * totals once it has them.
 *
 * Goods bought and goods delivered are deliberately kept apart:
 * `total_bought_cogs` (with its freight alongside) is what was purchased this
 * month, while `total_delivered_cogs` is the cost of what actually shipped.
 * In any one month the two rarely match — the gap is stock moving in or out.
 *
 * The result is snapshotted into `finance_income_product_statements` when the
 * parent statement is saved or regenerated, so the page reads stored rows
 * instead of recomputing. A first view with no snapshot builds one lazily.
 */
class ProductIncomeStatementService
{
    use ClosesOutSlices;

    public function __construct(
        private readonly StatementOrderSourceFactory $sources,
        private readonly TransactionTotals $transactions,
        private readonly LossCarryovers $carryovers,
    ) {}

    /** (Re)compute and store every per-product row for the statement's month. */
    public function snapshot(IncomeStatement $statement): void
    {
        $workspace = $statement->workspace;
        [$from, $to] = $this->range($statement);

        // Struck at the rates saved on the parent statement, not today's.
        $codRate = (float) $statement->cod_fee_rate;
        $vatRate = (float) $statement->vat_rate;
        $advisoryRate = (float) $statement->advisory_rate;
        $gencysPartner = (bool) $workspace->is_gencys_partner;

        $source = $this->sources->for($workspace);

        $orders = $source->totalsByProduct($workspace, $from, $to);
        $adSpent = $source->adSpendByProduct($workspace, $from, $to);
        $boughtCogs = $this->transactions->byProductTag($workspace, $from, $to, TransactionTotals::COST_OF_GOODS, withUntagged: true);
        $boughtFreight = $this->transactions->byProductTag($workspace, $from, $to, TransactionTotals::COG_DELIVERY, withUntagged: true);

        // A product earns a row if anything happened to it this month — an
        // order, a purchase, or only an ad buy.
        $keys = collect(array_keys($orders))
            ->merge(array_keys($boughtCogs))
            ->merge(array_keys($boughtFreight))
            ->merge(array_keys($adSpent))
            ->unique();

        $names = DB::table('products')
            ->whereIn('id', $keys->filter(fn ($k) => $k !== '')->map(fn ($k) => (int) $k)->all())
            ->pluck('name', 'id');

        $rows = $keys->map(function ($key) use ($orders, $boughtCogs, $boughtFreight, $adSpent, $names, $codRate, $vatRate, $advisoryRate, $gencysPartner) {
            $productId = $key === '' ? null : (int) $key;
            $totals = $orders[$key] ?? OrderTotals::empty();

            $revenue = round($totals->deliveredAmount, 2);
            $codFee = round($revenue * $codRate, 2);
            $codVat = round($codFee * $vatRate, 2);

            $adSpend = round((float) ($adSpent[$key] ?? 0), 2);
            $productCogs = round((float) ($boughtCogs[$key] ?? 0), 2);
            $boughtFreightFee = round((float) ($boughtFreight[$key] ?? 0), 2);

            // Both margins take the same costs off delivered revenue and differ
            // only in which cost of goods they charge.
            $commonCosts = $adSpend + $totals->shippingFee + $codFee + $codVat;

            $grossDelivered = round($revenue - $commonCosts - $totals->deliveredCogs, 2);
            // Freight on a purchase is part of what the stock cost.
            $grossBought = round($revenue - $commonCosts - $productCogs - $boughtFreightFee, 2);

            // A share of gross profit for gencys partners. Only a positive gross
            // owes anything — a loss doesn't earn a rebate.
            $advisory = fn (float $gross) => ($gencysPartner && $gross > 0)
                ? round($gross * $advisoryRate, 2)
                : 0.0;

            return [
                'product_id' => $productId,
                'product_name' => $productId !== null ? ($names[$productId] ?? 'Unknown') : 'Unresolved',
                'delivered_orders' => $totals->deliveredOrders,
                'delivered_units' => $totals->deliveredUnits,
                'delivered_amount' => $revenue,
                'ad_spent' => $adSpend,
                'shipped_orders' => $totals->shippedOrders,
                'total_shipping_fee' => $totals->shippingFee,
                'cod_fee' => $codFee,
                'cod_fee_vat' => $codVat,
                'total_bought_cogs' => $productCogs,
                'total_bought_cogs_delivery_fee' => $boughtFreightFee,
                'total_delivered_cogs' => round($totals->deliveredCogs, 2),
                'gross_profit_delivered_cogs' => $grossDelivered,
                'gross_profit_delivered_cogs_advisory_share' => $advisory($grossDelivered),
                'gross_profit_delivered_cogs_after_advisory_share' => round($grossDelivered - $advisory($grossDelivered), 2),
                'gross_profit_bought_cogs' => $grossBought,
                'gross_profit_bought_cogs_advisory_share' => $advisory($grossBought),
                'gross_profit_bought_cogs_after_advisory_share' => round($grossBought - $advisory($grossBought), 2),
            ];
        })->values();

        // The advisory and OPEX are allocated from the parent statement once
        // every row's gross profit is settled — see ClosesOutSlices.
        $rows = $this->closeSlice($statement, $rows->all());

        // Then the deficit carried into the month, entered at the seller-and-
        // product grain and added up to whatever grain this slice reports at.
        $rows = collect($this->applyCarriedLoss(
            $rows,
            $this->carryovers->byProduct($workspace, $from),
            fn (array $row) => (string) ($row['product_id'] ?? ''),
        ));

        DB::transaction(function () use ($statement, $rows) {
            $statement->productStatements()->delete();

            foreach ($rows as $row) {
                $statement->productStatements()->create($row);
            }
        });
    }

    /**
     * The saved per-product rows — biggest delivered first, with the unresolved
     * row last — plus a Total across the named products. Built on first access.
     *
     * @return array{products: list<array<string, mixed>>, total: array<string, mixed>, unresolved: list<array<string, mixed>>, rates: array{cod:float, vat:float, advisory:float}, gencysPartner: bool}
     */
    public function payload(IncomeStatement $statement): array
    {
        $this->ensureSnapshot($statement);

        // The company's OPEX split by type, which each row's own share is
        // then divided along.
        $opexTypes = $this->opexTypes($statement);

        $rows = $statement->productStatements()->get()->map(fn ($r) => [
            'product_id' => $r->product_id,
            'product' => $r->product_name ?: 'Unresolved',
            'delivered_orders' => (int) $r->delivered_orders,
            'delivered_units' => (int) $r->delivered_units,
            'delivered_amount' => (float) $r->delivered_amount,
            'ad_spent' => (float) $r->ad_spent,
            'shipped_orders' => (int) $r->shipped_orders,
            'total_shipping_fee' => (float) $r->total_shipping_fee,
            'cod_fee' => (float) $r->cod_fee,
            'cod_fee_vat' => (float) $r->cod_fee_vat,
            'total_bought_cogs' => (float) $r->total_bought_cogs,
            'total_bought_cogs_delivery_fee' => (float) $r->total_bought_cogs_delivery_fee,
            'total_delivered_cogs' => (float) $r->total_delivered_cogs,
            'gross_profit_delivered_cogs' => (float) $r->gross_profit_delivered_cogs,
            'gross_profit_delivered_cogs_advisory_share' => (float) $r->gross_profit_delivered_cogs_advisory_share,
            'gross_profit_delivered_cogs_after_advisory_share' => (float) $r->gross_profit_delivered_cogs_after_advisory_share,
            'gross_profit_bought_cogs' => (float) $r->gross_profit_bought_cogs,
            'gross_profit_bought_cogs_advisory_share' => (float) $r->gross_profit_bought_cogs_advisory_share,
            'gross_profit_bought_cogs_after_advisory_share' => (float) $r->gross_profit_bought_cogs_after_advisory_share,
            'opex' => (float) $r->opex,
            'opex_share_percentage' => (float) $r->opex_share_percentage,
            'opex_breakdown' => $this->opexByType($opexTypes, (float) $r->opex),
            'loss_brought_forward' => (float) $r->loss_brought_forward,
            'cumulative_profit_delivered_cogs' => (float) $r->cumulative_profit_delivered_cogs,
            'cumulative_profit_bought_cogs' => (float) $r->cumulative_profit_bought_cogs,
            'net_profit_delivered_cogs' => (float) $r->net_profit_delivered_cogs,
            'net_profit_bought_cogs' => (float) $r->net_profit_bought_cogs,
        ]);

        $named = $rows->filter(fn ($r) => $r['product_id'] !== null)
            ->sortByDesc('delivered_amount')
            ->values();

        $sum = fn (string $key) => round($named->sum($key), 2);

        // The total covers the named products; unresolved revenue isn't a
        // product's, so counting it would overstate every column.
        $total = ['product_id' => null, 'product' => 'Total'];

        foreach (array_keys($named->first() ?? []) as $key) {
            if (in_array($key, ['product_id', 'product', 'opex_breakdown'], true)) {
                continue;
            }
            $total[$key] = str_contains($key, '_orders') || str_contains($key, '_units')
                ? (int) $named->sum($key)
                : $sum($key);
        }

        $total['opex_breakdown'] = $this->opexByType($opexTypes, $total['opex'] ?? 0.0);

        $unresolved = $rows->first(fn ($r) => $r['product_id'] === null);

        return [
            'products' => ($unresolved ? $named->push($unresolved) : $named)->values()->all(),
            'total' => $total,
            'unresolved' => $this->unresolvedBreakdown($statement),
            'rates' => [
                'cod' => (float) $statement->cod_fee_rate,
                'vat' => (float) $statement->vat_rate,
                'advisory' => (float) $statement->advisory_rate,
            ],
            'gencysPartner' => (bool) $statement->workspace->is_gencys_partner,
        ];
    }

    /**
     * What is sitting in the Unresolved row — whatever the source failed to
     * match a product on, biggest first.
     *
     * Computed live rather than snapshotted. It is a to-do list for whoever
     * maps products, and it should shrink as they work through it.
     *
     * @return list<array<string, mixed>>
     */
    public function unresolvedBreakdown(IncomeStatement $statement): array
    {
        $workspace = $statement->workspace;
        [$from, $to] = $this->range($statement);

        return $this->sources->for($workspace)->unresolvedProductDetail($workspace, $from, $to);
    }

    private function ensureSnapshot(IncomeStatement $statement): void
    {
        if (! $statement->productStatements()->exists()) {
            $this->snapshot($statement);
        }
    }

    /** @return array{0:Carbon, 1:Carbon} [from, to] for the statement's month. */
    private function range(IncomeStatement $statement): array
    {
        $start = $statement->period_month->copy()->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }
}
