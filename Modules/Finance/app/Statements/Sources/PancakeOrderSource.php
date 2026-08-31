<?php

namespace Modules\Finance\Statements\Sources;

use App\Models\Page;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Statements\Contracts\StatementOrderSource;
use Modules\Finance\Statements\OrderTotals;
use Modules\Finance\Statements\UserProductKey;

/**
 * Orders as pancake records them, for workspaces that aren't gencys partners.
 *
 * Delivery and shipping are dated by `delivered_at` and `shipped_at`, and
 * revenue is `final_amount` — net of discount, matching what the gencys side
 * calls `price_final`. Pancake records no cost of goods against an order, so
 * the delivered-COGS figure is absent rather than zero-because-nothing-shipped.
 *
 * Attribution is per order, not per line item: an order belongs to one shop and
 * one page, so its product and its user follow directly and nothing is split.
 */
final class PancakeOrderSource implements StatementOrderSource
{
    /** The courier on this side charges more than the gencys one. */
    public const COD_FEE_RATE = 0.0275;

    public function label(): string
    {
        return 'pancake orders';
    }

    public function defaultCodFeeRate(): float
    {
        return self::COD_FEE_RATE;
    }

    /**
     * A pancake workspace has its spend counted per page per day by the ads
     * sync, so it is read from there rather than from the finance ledger.
     *
     * Every row counts, whatever wrote it. The table is unique on workspace,
     * page and date — `source` is not part of that key — so a page cannot hold
     * two rows for one day, and filtering by source would only risk dropping
     * spend that happened to be written by the other importer.
     *
     * A page belongs to a shop and a shop to a product, which is how spend
     * reaches a product; a page has an owner, which is how it reaches a person.
     */
    public function workspaceAdSpend(Workspace $workspace, Carbon $from, Carbon $to): float
    {
        return round((float) $this->adSpend($workspace, $from, $to)->sum('r.ad_spent'), 2);
    }

    public function adSpendByProduct(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        return $this->adSpendKeyedBy($workspace, $from, $to, 'sh.product_id', fn ($q) => $q
            ->leftJoin('shops as sh', 'sh.id', '=', 'pg.shop_id'));
    }

    public function adSpendByUser(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        return $this->adSpendKeyedBy($workspace, $from, $to, 'pg.owner_id', fn ($q) => $q);
    }

    /** @return array<string, float> */
    private function adSpendKeyedBy(Workspace $workspace, Carbon $from, Carbon $to, string $column, callable $join): array
    {
        return $join($this->adSpend($workspace, $from, $to))
            ->selectRaw($column.' as grouping_key, COALESCE(SUM(r.ad_spent), 0) as amount')
            ->groupBy(DB::raw($column))
            ->get()
            ->mapWithKeys(fn ($r) => [$this->key($r->grouping_key) => round((float) $r->amount, 2)])
            ->all();
    }

    private function adSpend(Workspace $workspace, Carbon $from, Carbon $to)
    {
        return DB::table('page_daily_records as r')
            ->join('pages as pg', 'pg.id', '=', 'r.page_id')
            ->where('r.workspace_id', $workspace->id)
            ->where('r.page_type', (new Page)->getMorphClass())
            ->whereBetween('r.date', [$from->toDateString(), $to->toDateString()]);
    }

    public function workspaceTotals(Workspace $workspace, Carbon $from, Carbon $to): OrderTotals
    {
        $delivered = $this->delivered($workspace, $from, $to)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(final_amount), 0) as revenue')
            ->first();

        $shipped = $this->shipped($workspace, $from, $to)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(shipping_fee), 0) as shipping')
            ->first();

        return new OrderTotals(
            deliveredOrders: (int) $delivered->orders,
            deliveredUnits: $this->units($workspace, $from, $to),
            deliveredAmount: round((float) $delivered->revenue, 2),
            deliveredCogs: 0.0,
            shippedOrders: (int) $shipped->orders,
            shippingFee: round((float) $shipped->shipping, 2),
        );
    }

    public function totalsByProduct(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        // An order's product is its shop's, so the grouping is a plain join.
        return $this->groupedTotals(
            $workspace, $from, $to,
            fn ($q) => $q->leftJoin('shops as sh', 'sh.id', '=', 'pancake_orders.shop_id')
                ->selectRaw('sh.product_id as grouping_key'),
        );
    }

    public function unresolvedProductDetail(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        return $this->unattributedDetail(
            $workspace, $from, $to,
            fn ($q) => $q->leftJoin('shops as sh', 'sh.id', '=', 'pancake_orders.shop_id')
                ->selectRaw('sh.product_id as grouping_key, sh.name as label'),
        );
    }

    public function totalsByUser(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        // An order is credited to whoever owns the page it came in on.
        return $this->groupedTotals(
            $workspace, $from, $to,
            fn ($q) => $q->leftJoin('pages as pg', 'pg.id', '=', 'pancake_orders.page_id')
                ->selectRaw('pg.owner_id as grouping_key'),
        );
    }

    public function unassignedUserDetail(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        return $this->unattributedDetail(
            $workspace, $from, $to,
            fn ($q) => $q->leftJoin('pages as pg', 'pg.id', '=', 'pancake_orders.page_id')
                ->selectRaw('pg.owner_id as grouping_key, pg.name as label'),
        );
    }

    /**
     * An order carries one page and one shop, so it lands on exactly one
     * user/product pair — the same rows as the two readings above, grouped by
     * both keys at once instead of one.
     */
    public function totalsByUserProduct(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        return $this->groupedTotals(
            $workspace, $from, $to,
            fn ($q) => $q->leftJoin('pages as pg', 'pg.id', '=', 'pancake_orders.page_id')
                ->leftJoin('shops as sh', 'sh.id', '=', 'pancake_orders.shop_id')
                ->selectRaw("CONCAT(COALESCE(pg.owner_id, ''), '|', COALESCE(sh.product_id, '')) as grouping_key"),
            // Already composite from SQL; re-made here so both halves are
            // canonical ints whatever the driver returned them as.
            key: fn ($v) => UserProductKey::of(...array_pad(explode('|', (string) $v, 2), 2, '')),
        );
    }

    /**
     * Delivered and shipped figures per grouping key, folded together. The two
     * are separate queries because they cover different orders.
     *
     * `$key` normalises what the grouping expression selected; it defaults to a
     * single id and is overridden where the key is composite.
     *
     * @return array<string, OrderTotals>
     */
    private function groupedTotals(Workspace $workspace, Carbon $from, Carbon $to, callable $group, ?callable $key = null): array
    {
        $key ??= fn ($value) => $this->key($value);
        $totals = [];

        $delivered = $group($this->delivered($workspace, $from, $to))
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(pancake_orders.final_amount), 0) as revenue')
            ->groupBy('grouping_key')
            ->get();

        foreach ($delivered as $row) {
            $rowKey = $key($row->grouping_key);
            $totals[$rowKey] = ($totals[$rowKey] ?? OrderTotals::empty())->plus(new OrderTotals(
                deliveredOrders: (int) $row->orders,
                deliveredAmount: round((float) $row->revenue, 2),
            ));
        }

        foreach ($this->unitsByKey($workspace, $from, $to, $group, $key) as $rowKey => $units) {
            $totals[$rowKey] = ($totals[$rowKey] ?? OrderTotals::empty())
                ->plus(new OrderTotals(deliveredUnits: $units));
        }

        $shipped = $group($this->shipped($workspace, $from, $to))
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(pancake_orders.shipping_fee), 0) as shipping')
            ->groupBy('grouping_key')
            ->get();

        foreach ($shipped as $row) {
            $rowKey = $key($row->grouping_key);
            $totals[$rowKey] = ($totals[$rowKey] ?? OrderTotals::empty())->plus(new OrderTotals(
                shippedOrders: (int) $row->orders,
                shippingFee: round((float) $row->shipping, 2),
            ));
        }

        return $totals;
    }

    /**
     * The rows behind an unattributed bucket, labelled by whatever the source
     * failed to match on, biggest first.
     *
     * @return list<array{label:?string, delivered_orders:int, delivered_amount:float, shipped_orders:int, shipping_fee:float}>
     */
    private function unattributedDetail(Workspace $workspace, Carbon $from, Carbon $to, callable $group): array
    {
        $rows = [];

        $delivered = $group($this->delivered($workspace, $from, $to))
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(pancake_orders.final_amount), 0) as revenue')
            ->groupBy('grouping_key', 'label')
            ->get();

        foreach ($delivered as $row) {
            if ($this->key($row->grouping_key) !== '') {
                continue;
            }
            $rows[(string) $row->label] = [
                'label' => $row->label ?: null,
                'delivered_orders' => (int) $row->orders,
                'delivered_amount' => round((float) $row->revenue, 2),
                'shipped_orders' => 0,
                'shipping_fee' => 0.0,
            ];
        }

        $shipped = $group($this->shipped($workspace, $from, $to))
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(pancake_orders.shipping_fee), 0) as shipping')
            ->groupBy('grouping_key', 'label')
            ->get();

        foreach ($shipped as $row) {
            if ($this->key($row->grouping_key) !== '') {
                continue;
            }
            $rows[(string) $row->label] ??= [
                'label' => $row->label ?: null,
                'delivered_orders' => 0,
                'delivered_amount' => 0.0,
                'shipped_orders' => 0,
                'shipping_fee' => 0.0,
            ];
            $rows[(string) $row->label]['shipped_orders'] = (int) $row->orders;
            $rows[(string) $row->label]['shipping_fee'] = round((float) $row->shipping, 2);
        }

        usort($rows, fn ($a, $b) => [$b['delivered_amount'], $b['shipping_fee']] <=> [$a['delivered_amount'], $a['shipping_fee']]);

        return $rows;
    }

    /** @return array<string, int> */
    private function unitsByKey(Workspace $workspace, Carbon $from, Carbon $to, callable $group, callable $key): array
    {
        return $group($this->delivered($workspace, $from, $to))
            ->join('pancake_order_items as poi', 'poi.order_id', '=', 'pancake_orders.id')
            ->selectRaw('COALESCE(SUM(GREATEST(COALESCE(poi.quantity, 1), 1)), 0) as units')
            ->groupBy('grouping_key')
            ->get()
            ->mapWithKeys(fn ($r) => [$key($r->grouping_key) => (int) $r->units])
            ->all();
    }

    private function units(Workspace $workspace, Carbon $from, Carbon $to): int
    {
        return (int) $this->delivered($workspace, $from, $to)
            ->join('pancake_order_items as poi', 'poi.order_id', '=', 'pancake_orders.id')
            ->sum(DB::raw('GREATEST(COALESCE(poi.quantity, 1), 1)'));
    }

    private function delivered(Workspace $workspace, Carbon $from, Carbon $to)
    {
        return DB::table('pancake_orders')
            ->where('pancake_orders.workspace_id', $workspace->id)
            ->whereBetween('pancake_orders.delivered_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    private function shipped(Workspace $workspace, Carbon $from, Carbon $to)
    {
        return DB::table('pancake_orders')
            ->where('pancake_orders.workspace_id', $workspace->id)
            ->whereBetween('pancake_orders.shipped_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    /** '' for anything that didn't resolve, matching the contract. */
    private function key(mixed $value): string
    {
        return $value === null ? '' : (string) (int) $value;
    }
}
