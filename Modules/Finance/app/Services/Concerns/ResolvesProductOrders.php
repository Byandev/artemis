<?php

namespace Modules\Finance\Services\Concerns;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\TransactionType;
use Modules\GencysERP\Models\GencysDailySalesOrder;

/**
 * Shared ground between the per-user and per-product statement services:
 * resolving a gencys order to the product it carries, and the delivered figures
 * that fall out of that.
 */
trait ResolvesProductOrders
{
    /** gencys_orders.parcel_status value that counts as delivered revenue. */
    private const DELIVERED_STATUS = 'DELIVERED';

    /** Platforms whose orders never belong on the statement. */
    private const EXCLUDED_PLATFORMS = ['Shopee', 'TikTok'];

    /** Pages whose orders never belong on the statement. */
    private const EXCLUDED_PAGE_LIKE = '%pikutin%';

    /** @return array{0:Carbon, 1:Carbon} [from, to] for the statement's month. */
    private function range(IncomeStatement $statement): array
    {
        $start = $statement->period_month->copy()->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }

    /** The month's delivered orders, with the statement-wide exclusions applied. */
    private function deliveredOrders(Workspace $workspace, Carbon $from, Carbon $to)
    {
        return GencysDailySalesOrder::where('workspace_id', $workspace->id)
            ->where('parcel_status', self::DELIVERED_STATUS)
            ->whereNotIn('platform', self::EXCLUDED_PLATFORMS)
            ->whereNotLike('page', self::EXCLUDED_PAGE_LIKE)
            ->whereBetween('parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    /**
     * Delivered parcel count and revenue per product id for the month, keyed by
     * product id as a string ('' = orders that resolve to no product). Passing
     * null for $cells covers every intern; a cell list scopes it to one.
     *
     * @return array<string, array{orders:int, revenue:float}>
     */
    private function deliveredByProduct(Workspace $workspace, ?array $cells, Carbon $from, Carbon $to): array
    {
        if ($cells !== null && empty($cells)) {
            return [];
        }

        $delivered = $this->deliveredOrders($workspace, $from, $to)
            ->when($cells !== null, fn ($q) => $q->whereIn('intern_brands_name', $cells))
            ->selectRaw('COALESCE(price_final, 0) as revenue')
            ->selectSub($this->orderProductSubquery(), 'product_id');

        return DB::query()->fromSub($delivered, 't')
            ->selectRaw('product_id, COUNT(*) as orders, COALESCE(SUM(revenue), 0) as revenue')
            ->groupBy('product_id')
            ->get()
            ->mapWithKeys(fn ($r) => [
                $this->productKey($r->product_id) => [
                    'orders' => (int) $r->orders,
                    'revenue' => round((float) $r->revenue, 2),
                ],
            ])
            ->all();
    }

    /** The array key a resolved product id is stored under ('' = unresolved). */
    private function productKey(mixed $productId): string
    {
        return $productId === null ? '' : (string) (int) $productId;
    }

    /**
     * A correlated subquery resolving the outer `gencys_orders` row to a single
     * product id via its items: `gencys_order_items.sku` matches an
     * `inventory_unit_codes.unit_code` whose `product_id` is the product. MIN
     * picks one when an order carries several unit codes (all of one product).
     */
    private function orderProductSubquery(): Builder
    {
        return DB::table('inventory_unit_codes as uc')
            ->join('gencys_order_items as goi', 'goi.sku', '=', 'uc.unit_code')
            ->whereColumn('uc.workspace_id', 'gencys_orders.workspace_id')
            ->whereColumn('goi.order_id', 'gencys_orders.id')
            ->whereNotNull('uc.product_id')
            ->selectRaw('MIN(uc.product_id)');
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
}
