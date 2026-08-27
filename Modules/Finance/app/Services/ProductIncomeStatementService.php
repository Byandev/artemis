<?php

namespace Modules\Finance\Services;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\TransactionType;
use Modules\GencysERP\Models\GencysDailySalesOrder;

/**
 * Builds, saves and reads the per-product slices of an income statement.
 *
 * A product row is workspace-wide: every intern's orders for that product, not
 * one person's. Attribution runs at the line-item level — an order can carry
 * several different products, so `gencys_order_items.sku` is matched to an
 * `inventory_unit_codes.unit_code` and the order's money divided between the
 * products it actually holds. Items matching no unit code, and orders with no
 * items at all, roll into a single "Unresolved" row.
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
    /** gencys_orders.parcel_status value that counts as delivered revenue. */
    private const DELIVERED_STATUS = 'DELIVERED';

    /** Platforms whose orders never belong on the statement. */
    private const EXCLUDED_PLATFORMS = ['Shopee', 'TikTok'];

    /** Pages whose orders never belong on the statement. */
    private const EXCLUDED_PAGE_LIKE = '%pikutin%';

    /** (Re)compute and store every per-product row for the statement's month. */
    public function snapshot(IncomeStatement $statement): void
    {
        $workspace = $statement->workspace;
        [$from, $to] = $this->range($statement);

        // Struck at the rates saved on the parent statement, not today's.
        $codRate = (float) $statement->cod_fee_rate;
        $vatRate = (float) $statement->vat_rate;

        $delivered = $this->deliveredByProduct($workspace, $from, $to);
        $shipped = $this->shippedByProduct($workspace, $from, $to);
        $boughtCogs = $this->taggedTotalsForTypes($workspace, $from, $to, $this->costOfGoodsTypeIds($workspace));
        $boughtFreight = $this->taggedTotalsForTypes($workspace, $from, $to, $this->cogDeliveryTypeIds($workspace));
        $adSpent = $this->taggedTotalsForTypes($workspace, $from, $to, $this->adSpentTypeIds($workspace));

        // A product earns a row if anything happened to it this month — a
        // delivery, a purchase, or only an ad buy.
        $keys = collect(array_keys($delivered))
            ->merge(array_keys($boughtCogs))
            ->merge(array_keys($boughtFreight))
            ->merge(array_keys($adSpent))
            ->merge(array_keys($shipped))
            ->unique();

        $names = DB::table('products')
            ->whereIn('id', $keys->filter(fn ($k) => $k !== '')->map(fn ($k) => (int) $k)->all())
            ->pluck('name', 'id');

        $rows = $keys->map(function ($key) use ($delivered, $shipped, $boughtCogs, $boughtFreight, $adSpent, $names, $codRate, $vatRate) {
            $productId = $key === '' ? null : (int) $key;
            $sold = $delivered[$key] ?? ['orders' => 0, 'units' => 0, 'revenue' => 0.0, 'cog' => 0.0];

            $sentOut = $shipped[$key] ?? ['orders' => 0, 'shipping' => 0.0];
            $revenue = round((float) $sold['revenue'], 2);
            $codFee = round($revenue * $codRate, 2);
            $codVat = round($codFee * $vatRate, 2);

            $adSpend = round((float) ($adSpent[$key] ?? 0), 2);
            $shippingFee = round((float) $sentOut['shipping'], 2);
            $deliveredCogs = round((float) $sold['cog'], 2);
            $productCogs = round((float) ($boughtCogs[$key] ?? 0), 2);
            $boughtFreightFee = round((float) ($boughtFreight[$key] ?? 0), 2);

            // Both margins take the same costs off delivered revenue and differ
            // only in which cost of goods they charge.
            $commonCosts = $adSpend + $shippingFee + $codFee + $codVat;

            return [
                'product_id' => $productId,
                'product_name' => $productId !== null ? ($names[$productId] ?? 'Unknown') : 'Unresolved',
                'delivered_orders' => (int) $sold['orders'],
                'delivered_units' => (int) $sold['units'],
                'delivered_amount' => $revenue,
                'ad_spent' => $adSpend,
                'shipped_orders' => (int) $sentOut['orders'],
                'total_shipping_fee' => $shippingFee,
                'cod_fee' => $codFee,
                // VAT is charged on the fee, not on the revenue.
                'cod_fee_vat' => $codVat,
                'total_bought_cogs' => $productCogs,
                'total_bought_cogs_delivery_fee' => $boughtFreightFee,
                'total_delivered_cogs' => $deliveredCogs,
                'gross_profit_delivered_cogs' => round($revenue - $commonCosts - $deliveredCogs, 2),
                // Freight on a purchase is part of what the stock cost.
                'gross_profit_bought_cogs' => round($revenue - $commonCosts - $productCogs - $boughtFreightFee, 2),
            ];
        })->values();

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
     * @return array{products: list<array<string, mixed>>, total: array<string, mixed>, rates: array{cod:float, vat:float}}
     */
    public function payload(IncomeStatement $statement): array
    {
        $this->ensureSnapshot($statement);

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
            'gross_profit_bought_cogs' => (float) $r->gross_profit_bought_cogs,
        ]);

        $named = $rows->filter(fn ($r) => $r['product_id'] !== null)
            ->sortByDesc('delivered_amount')
            ->values();

        $sum = fn (string $key) => round($named->sum($key), 2);

        // The total covers the named products; unresolved revenue isn't a
        // product's, so counting it would overstate every column.
        $total = [
            'product_id' => null,
            'product' => 'Total',
            'delivered_orders' => (int) $named->sum('delivered_orders'),
            'delivered_units' => (int) $named->sum('delivered_units'),
            'delivered_amount' => $sum('delivered_amount'),
            'ad_spent' => $sum('ad_spent'),
            'shipped_orders' => (int) $named->sum('shipped_orders'),
            'total_shipping_fee' => $sum('total_shipping_fee'),
            'cod_fee' => $sum('cod_fee'),
            'cod_fee_vat' => $sum('cod_fee_vat'),
            'total_bought_cogs' => $sum('total_bought_cogs'),
            'total_bought_cogs_delivery_fee' => $sum('total_bought_cogs_delivery_fee'),
            'total_delivered_cogs' => $sum('total_delivered_cogs'),
            'gross_profit_delivered_cogs' => $sum('gross_profit_delivered_cogs'),
            'gross_profit_bought_cogs' => $sum('gross_profit_bought_cogs'),
        ];

        $unresolved = $rows->first(fn ($r) => $r['product_id'] === null);

        return [
            'products' => ($unresolved ? $named->push($unresolved) : $named)->values()->all(),
            'total' => $total,
            // The rates these rows were struck at, for the column labels.
            'rates' => [
                'cod' => (float) $statement->cod_fee_rate,
                'vat' => (float) $statement->vat_rate,
            ],
        ];
    }

    private function ensureSnapshot(IncomeStatement $statement): void
    {
        if (! $statement->productStatements()->exists()) {
            $this->snapshot($statement);
        }
    }

    /**
     * Delivered parcels, units, revenue and cost of goods per product id for the
     * month, keyed by product id as a string ('' = items resolving to no
     * product).
     *
     * Revenue and COGS are split across the products an order carries, while the
     * parcel count is of whole orders — a parcel holding two products is one
     * parcel for each of them, so those can add up to more than the month's
     * orders. Units are the pieces themselves, so they never double-count.
     *
     * @return array<string, array{orders:int, units:int, revenue:float, cog:float}>
     */
    private function deliveredByProduct(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        return DB::query()->fromSub($this->deliveredItems($workspace, $from, $to), 't')
            ->selectRaw('product_id')
            ->selectRaw('COUNT(DISTINCT order_id) as orders')
            ->selectRaw('COALESCE(SUM(units), 0) as units')
            ->selectRaw('COALESCE(SUM(revenue * share), 0) as revenue')
            ->selectRaw('COALESCE(SUM(cog * share), 0) as cog')
            ->groupBy('product_id')
            ->get()
            ->mapWithKeys(fn ($r) => [
                ($r->product_id === null ? '' : (string) (int) $r->product_id) => [
                    'orders' => (int) $r->orders,
                    'units' => (int) $r->units,
                    'revenue' => round((float) $r->revenue, 2),
                    'cog' => round((float) $r->cog, 2),
                ],
            ])
            ->all();
    }

    /**
     * One row per (delivered order, line item), each carrying the item's product
     * and the fraction of the order it accounts for.
     *
     * An order can hold several different products, so the money on it has to be
     * divided rather than pinned to one. `price_final` and `total_cog` are only
     * recorded per order, and the items carry no price of their own, so quantity
     * is the only split available: an item is worth its quantity over the
     * order's total quantity.
     *
     * Left joins throughout, so an order with no items — or with items whose sku
     * matches no unit code — still comes through, on a null product, rather than
     * dropping out of the month entirely.
     */
    private function deliveredItems(Workspace $workspace, Carbon $from, Carbon $to)
    {
        return $this->withItemShares(
            $this->orders($workspace)
                ->where('gencys_orders.parcel_status', self::DELIVERED_STATUS)
                ->whereBetween('gencys_orders.parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
        );
    }

    /**
     * The month's shipped-out orders, whatever became of them afterwards — the
     * courier is paid for a return just the same.
     */
    private function shippedItems(Workspace $workspace, Carbon $from, Carbon $to)
    {
        return $this->withItemShares(
            $this->orders($workspace)
                ->whereBetween('gencys_orders.shipped_out_date', [$from->toDateString(), $to->toDateString()])
        );
    }

    /** Orders that belong on the statement at all, before any date scoping. */
    private function orders(Workspace $workspace)
    {
        // Columns are qualified: the item and unit-code joins added on top put
        // `workspace_id` on more than one table.
        return GencysDailySalesOrder::where('gencys_orders.workspace_id', $workspace->id)
            ->whereNotIn('gencys_orders.platform', self::EXCLUDED_PLATFORMS)
            ->whereNotLike('gencys_orders.page', self::EXCLUDED_PAGE_LIKE);
    }

    /**
     * Parcels shipped out and the courier fee on them, per product id for the
     * month ('' = items resolving to no product). A different set of orders from
     * the delivered columns — dated by shipping, and counting returns too.
     *
     * @return array<string, array{orders:int, shipping:float}>
     */
    private function shippedByProduct(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        return DB::query()->fromSub($this->shippedItems($workspace, $from, $to), 't')
            ->selectRaw('product_id')
            ->selectRaw('COUNT(DISTINCT order_id) as orders')
            ->selectRaw('COALESCE(SUM(shipping_fee * share), 0) as shipping')
            ->groupBy('product_id')
            ->get()
            ->mapWithKeys(fn ($r) => [
                ($r->product_id === null ? '' : (string) (int) $r->product_id) => [
                    'orders' => (int) $r->orders,
                    'shipping' => round((float) $r->shipping, 2),
                ],
            ])
            ->all();
    }

    /**
     * One row per (order, line item), each carrying the item's product and the
     * fraction of the order it accounts for. `price_final`, `total_cog` and
     * `shipping_fee` are only recorded per order and the items carry no price of
     * their own, so quantity is the only split available.
     */
    private function withItemShares($query)
    {
        $orderQuantities = DB::table('gencys_order_items')
            ->selectRaw('order_id, SUM(GREATEST(COALESCE(quantity, 1), 1)) as total_qty')
            ->groupBy('order_id');

        return $query
            ->leftJoin('gencys_order_items as goi', 'goi.order_id', '=', 'gencys_orders.id')
            ->leftJoin('inventory_unit_codes as uc', fn ($join) => $join
                ->on('uc.unit_code', '=', 'goi.sku')
                ->whereColumn('uc.workspace_id', 'gencys_orders.workspace_id'))
            ->leftJoinSub($orderQuantities, 'q', 'q.order_id', '=', 'gencys_orders.id')
            ->selectRaw('gencys_orders.id as order_id')
            ->selectRaw('uc.product_id as product_id')
            ->selectRaw('COALESCE(gencys_orders.price_final, 0) as revenue')
            ->selectRaw('COALESCE(gencys_orders.total_cog, 0) as cog')
            ->selectRaw('COALESCE(gencys_orders.shipping_fee, 0) as shipping_fee')
            // An order with no items is treated as a single unit of nothing in
            // particular, which is also what makes its share the whole order.
            ->selectRaw('GREATEST(COALESCE(goi.quantity, 1), 1) as units')
            // No items (or a zero quantity) means the whole order is one share.
            ->selectRaw('CASE WHEN COALESCE(q.total_qty, 0) = 0 THEN 1 ELSE GREATEST(COALESCE(goi.quantity, 1), 1) / q.total_qty END as share');
    }

    /**
     * Product-tagged outflow totals for the given transaction types, keyed by
     * product id as a string ('' = a tag matching no product).
     *
     * @param  list<int>  $typeIds
     * @return array<string, float>
     */
    private function taggedTotalsForTypes(Workspace $workspace, Carbon $from, Carbon $to, array $typeIds): array
    {
        if (empty($typeIds)) {
            return [];
        }

        $rows = DB::table('finance_transaction_products as tp')
            ->join('finance_transactions as t', 't.id', '=', 'tp.transaction_id')
            ->where('t.workspace_id', $workspace->id)
            ->where('t.type', 'out')
            ->whereIn('t.transaction_type_id', $typeIds)
            ->whereBetween('t.date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('tp.product')
            ->selectRaw('tp.product as name, SUM(tp.amount) as amount')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $idByName = DB::table('products')
            ->where('workspace_id', $workspace->id)
            ->whereIn('name', $rows->pluck('name')->unique()->all())
            ->pluck('id', 'name');

        $totals = [];

        foreach ($rows as $r) {
            $key = isset($idByName[$r->name]) ? (string) $idByName[$r->name] : '';
            $totals[$key] = ($totals[$key] ?? 0) + (float) $r->amount;
        }

        return $totals;
    }

    /**
     * Ids of the workspace's "Ad Spent" types.
     *
     * @return list<int>
     */
    private function adSpentTypeIds(Workspace $workspace): array
    {
        return $this->typeIdsMatching($workspace, [
            '%adspent%',
            '%ad spent%',
            '%ad spend%',
        ]);
    }

    /**
     * Ids of the workspace's "Cost of Goods" types — the bulk goods purchases.
     *
     * @return list<int>
     */
    private function costOfGoodsTypeIds(Workspace $workspace): array
    {
        return $this->typeIdsMatching($workspace, ['%cost of goods%']);
    }

    /**
     * Ids of the "Delivery of COG" types — the freight on a bulk goods purchase,
     * tracked apart from the goods themselves.
     *
     * @return list<int>
     */
    private function cogDeliveryTypeIds(Workspace $workspace): array
    {
        return $this->typeIdsMatching($workspace, [
            '%delivery of cog%',
            '%delivery of goods%',
            '%cog delivery%',
        ]);
    }

    /**
     * Ids of the workspace's transaction types whose name matches any of the
     * given (lowercased) LIKE patterns.
     *
     * @param  list<string>  $patterns
     * @return list<int>
     */
    private function typeIdsMatching(Workspace $workspace, array $patterns): array
    {
        return TransactionType::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $q->orWhereRaw('LOWER(name) LIKE ?', [$pattern]);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array{0:Carbon, 1:Carbon} [from, to] for the statement's month. */
    private function range(IncomeStatement $statement): array
    {
        $start = $statement->period_month->copy()->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }
}
