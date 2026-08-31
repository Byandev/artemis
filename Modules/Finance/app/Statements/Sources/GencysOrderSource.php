<?php

namespace Modules\Finance\Statements\Sources;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\IncomeStatementSetting;
use Modules\Finance\Statements\Contracts\StatementOrderSource;
use Modules\Finance\Statements\OrderTotals;
use Modules\Finance\Statements\TransactionTotals;
use Modules\Finance\Statements\UserProductKey;
use Modules\GencysERP\Models\Intern;
use Modules\GencysERP\Support\InternResolver;

/**
 * Orders as the gencys ERP records them, for gencys-partner workspaces.
 *
 * Delivery is dated by `parcel_updated_date` on a DELIVERED parcel and shipping
 * by `shipped_out_date`, whatever became of the parcel afterwards.
 *
 * Attribution differs by grain. A product is per line item — one parcel can
 * carry several — so the order's money is divided between them by quantity. A
 * user is per order: the intern name written on it resolves to one person, and
 * nothing is split.
 */
final class GencysOrderSource implements StatementOrderSource
{
    private const DELIVERED_STATUS = 'DELIVERED';

    private const EXCLUDED_PLATFORMS = ['Shopee', 'TikTok'];

    private const EXCLUDED_PAGE_LIKE = '%pikutin%';

    public function __construct(private readonly TransactionTotals $transactions) {}

    public function label(): string
    {
        return 'gencys orders';
    }

    public function defaultCodFeeRate(): float
    {
        return IncomeStatementSetting::DEFAULT_COD_FEE_RATE;
    }

    /**
     * A gencys workspace books its advertising through the finance ledger, so
     * the figures come from the Ad Spent transactions: the whole month for the
     * workspace, the product tags per product, and the charge-to shares per
     * person.
     */
    public function workspaceAdSpend(Workspace $workspace, Carbon $from, Carbon $to): float
    {
        return $this->transactions->forWorkspace($workspace, $from, $to, TransactionTotals::AD_SPENT);
    }

    public function adSpendByProduct(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        return $this->transactions->byProductTag($workspace, $from, $to, TransactionTotals::AD_SPENT);
    }

    public function adSpendByUser(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        return $this->transactions->byChargedUser($workspace, $from, $to, TransactionTotals::AD_SPENT);
    }

    public function workspaceTotals(Workspace $workspace, Carbon $from, Carbon $to): OrderTotals
    {
        $delivered = $this->delivered($workspace, $from, $to)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(price_final), 0) as revenue, COALESCE(SUM(total_cog), 0) as cog')
            ->first();

        $shipped = $this->shipped($workspace, $from, $to)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(shipping_fee), 0) as shipping')
            ->first();

        $units = (int) $this->delivered($workspace, $from, $to)
            ->join('gencys_order_items as goi', 'goi.order_id', '=', 'gencys_orders.id')
            ->sum(DB::raw('GREATEST(COALESCE(goi.quantity, 1), 1)'));

        return new OrderTotals(
            deliveredOrders: (int) $delivered->orders,
            deliveredUnits: $units,
            deliveredAmount: round((float) $delivered->revenue, 2),
            deliveredCogs: round((float) $delivered->cog, 2),
            shippedOrders: (int) $shipped->orders,
            shippingFee: round((float) $shipped->shipping, 2),
        );
    }

    public function totalsByProduct(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        $totals = [];

        foreach ($this->itemRows($this->delivered($workspace, $from, $to)) as $row) {
            $key = $this->key($row->product_id);
            $totals[$key] = ($totals[$key] ?? OrderTotals::empty())->plus(new OrderTotals(
                deliveredOrders: (int) $row->orders,
                deliveredUnits: (int) $row->units,
                deliveredAmount: round((float) $row->revenue, 2),
                deliveredCogs: round((float) $row->cog, 2),
            ));
        }

        foreach ($this->itemRows($this->shipped($workspace, $from, $to)) as $row) {
            $key = $this->key($row->product_id);
            $totals[$key] = ($totals[$key] ?? OrderTotals::empty())->plus(new OrderTotals(
                shippedOrders: (int) $row->orders,
                shippingFee: round((float) $row->shipping, 2),
            ));
        }

        return $totals;
    }

    public function unresolvedProductDetail(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        $rows = [];

        foreach ($this->itemRows($this->delivered($workspace, $from, $to), bySku: true) as $row) {
            if ($row->product_id !== null) {
                continue;
            }
            $rows[(string) $row->sku] = [
                'label' => $row->sku ?: null,
                'delivered_orders' => (int) $row->orders,
                'delivered_amount' => round((float) $row->revenue, 2),
                'shipped_orders' => 0,
                'shipping_fee' => 0.0,
            ];
        }

        foreach ($this->itemRows($this->shipped($workspace, $from, $to), bySku: true) as $row) {
            if ($row->product_id !== null) {
                continue;
            }
            $rows[(string) $row->sku] ??= [
                'label' => $row->sku ?: null,
                'delivered_orders' => 0,
                'delivered_amount' => 0.0,
                'shipped_orders' => 0,
                'shipping_fee' => 0.0,
            ];
            $rows[(string) $row->sku]['shipped_orders'] = (int) $row->orders;
            $rows[(string) $row->sku]['shipping_fee'] = round((float) $row->shipping, 2);
        }

        usort($rows, fn ($a, $b) => [$b['delivered_amount'], $b['shipping_fee']] <=> [$a['delivered_amount'], $a['shipping_fee']]);

        return $rows;
    }

    public function totalsByUser(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        $resolve = $this->cellUserResolver($workspace);
        $totals = [];

        foreach ($this->cellRows($workspace, $from, $to) as $cell => $row) {
            $key = (string) ($resolve($cell) ?? '');
            $totals[$key] = ($totals[$key] ?? OrderTotals::empty())->plus($row);
        }

        return $totals;
    }

    public function unassignedUserDetail(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        $resolve = $this->cellUserResolver($workspace);
        $rows = [];

        foreach ($this->cellRows($workspace, $from, $to) as $cell => $totals) {
            if ($resolve($cell) !== null) {
                continue;
            }
            $rows[] = [
                'label' => $cell === '' ? null : $cell,
                'delivered_orders' => $totals->deliveredOrders,
                'delivered_amount' => $totals->deliveredAmount,
                'shipped_orders' => $totals->shippedOrders,
                'shipping_fee' => $totals->shippingFee,
            ];
        }

        usort($rows, fn ($a, $b) => [$b['delivered_amount'], $b['shipping_fee']] <=> [$a['delivered_amount'], $a['shipping_fee']]);

        return $rows;
    }

    /**
     * Both grains at once: the per-item product split, kept apart by the intern
     * cell the order carries, with each cell then resolved to a user.
     *
     * The cell is grouped in SQL and resolved in PHP for the same reason
     * totalsByUser() does it — the cell-to-intern match is fuzzy and lives in
     * InternResolver, not in a join. Several cells can land on one user, so the
     * rows are folded rather than assigned.
     */
    public function totalsByUserProduct(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        $resolve = $this->cellUserResolver($workspace);
        $totals = [];

        $fold = function (string $key, OrderTotals $add) use (&$totals) {
            $totals[$key] = ($totals[$key] ?? OrderTotals::empty())->plus($add);
        };

        foreach ($this->itemRows($this->delivered($workspace, $from, $to), byCell: true) as $row) {
            $fold(UserProductKey::of($resolve($row->cell), $row->product_id), new OrderTotals(
                deliveredOrders: (int) $row->orders,
                deliveredUnits: (int) $row->units,
                deliveredAmount: round((float) $row->revenue, 2),
                deliveredCogs: round((float) $row->cog, 2),
            ));
        }

        foreach ($this->itemRows($this->shipped($workspace, $from, $to), byCell: true) as $row) {
            $fold(UserProductKey::of($resolve($row->cell), $row->product_id), new OrderTotals(
                shippedOrders: (int) $row->orders,
                shippingFee: round((float) $row->shipping, 2),
            ));
        }

        return $totals;
    }

    /**
     * Per intern cell, delivered and shipped folded together. An order carries
     * one cell, so nothing is split here.
     *
     * @return array<string, OrderTotals>
     */
    private function cellRows(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        $totals = [];

        $delivered = $this->delivered($workspace, $from, $to)
            ->selectRaw('gencys_orders.intern_brands_name as cell, COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(gencys_orders.price_final), 0) as revenue')
            ->selectRaw('COALESCE(SUM(gencys_orders.total_cog), 0) as cog')
            ->groupBy('cell')->get();

        foreach ($delivered as $row) {
            $totals[(string) $row->cell] = ($totals[(string) $row->cell] ?? OrderTotals::empty())->plus(new OrderTotals(
                deliveredOrders: (int) $row->orders,
                deliveredAmount: round((float) $row->revenue, 2),
                deliveredCogs: round((float) $row->cog, 2),
            ));
        }

        $units = $this->delivered($workspace, $from, $to)
            ->join('gencys_order_items as goi', 'goi.order_id', '=', 'gencys_orders.id')
            ->selectRaw('gencys_orders.intern_brands_name as cell')
            ->selectRaw('COALESCE(SUM(GREATEST(COALESCE(goi.quantity, 1), 1)), 0) as units')
            ->groupBy('cell')->get();

        foreach ($units as $row) {
            $totals[(string) $row->cell] = ($totals[(string) $row->cell] ?? OrderTotals::empty())
                ->plus(new OrderTotals(deliveredUnits: (int) $row->units));
        }

        $shipped = $this->shipped($workspace, $from, $to)
            ->selectRaw('gencys_orders.intern_brands_name as cell, COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(gencys_orders.shipping_fee), 0) as shipping')
            ->groupBy('cell')->get();

        foreach ($shipped as $row) {
            $totals[(string) $row->cell] = ($totals[(string) $row->cell] ?? OrderTotals::empty())->plus(new OrderTotals(
                shippedOrders: (int) $row->orders,
                shippingFee: round((float) $row->shipping, 2),
            ));
        }

        return $totals;
    }

    /**
     * One row per product off a set of orders, with the order's money divided
     * between the products it carries by item quantity. `$bySku` splits the
     * rows by unit code as well (for explaining the unresolved bucket) and
     * `$byCell` by the intern the order was written to.
     */
    private function itemRows($orders, bool $bySku = false, bool $byCell = false)
    {
        $quantities = DB::table('gencys_order_items')
            ->selectRaw('order_id, SUM(GREATEST(COALESCE(quantity, 1), 1)) as total_qty')
            ->groupBy('order_id');

        $items = $orders
            ->leftJoin('gencys_order_items as goi', 'goi.order_id', '=', 'gencys_orders.id')
            ->leftJoin('inventory_unit_codes as uc', fn ($join) => $join
                ->on('uc.unit_code', '=', 'goi.sku')
                ->whereColumn('uc.workspace_id', 'gencys_orders.workspace_id'))
            ->leftJoinSub($quantities, 'q', 'q.order_id', '=', 'gencys_orders.id')
            ->selectRaw('gencys_orders.id as order_id')
            ->selectRaw('uc.product_id as product_id')
            ->selectRaw('goi.sku as sku')
            ->selectRaw("COALESCE(gencys_orders.intern_brands_name, '') as cell")
            ->selectRaw('COALESCE(gencys_orders.price_final, 0) as revenue')
            ->selectRaw('COALESCE(gencys_orders.total_cog, 0) as cog')
            ->selectRaw('COALESCE(gencys_orders.shipping_fee, 0) as shipping_fee')
            ->selectRaw('GREATEST(COALESCE(goi.quantity, 1), 1) as units')
            // No items, or a zero quantity, means the whole order is one share.
            ->selectRaw('CASE WHEN COALESCE(q.total_qty, 0) = 0 THEN 1 ELSE GREATEST(COALESCE(goi.quantity, 1), 1) / q.total_qty END as share');

        $group = 'product_id'
            .($bySku ? ', sku' : '')
            .($byCell ? ', cell' : '');

        return DB::query()->fromSub($items, 't')
            ->selectRaw($group)
            ->selectRaw('COUNT(DISTINCT order_id) as orders')
            ->selectRaw('COALESCE(SUM(units), 0) as units')
            ->selectRaw('COALESCE(SUM(revenue * share), 0) as revenue')
            ->selectRaw('COALESCE(SUM(cog * share), 0) as cog')
            ->selectRaw('COALESCE(SUM(shipping_fee * share), 0) as shipping')
            ->groupBy(DB::raw($group))
            ->get();
    }

    /**
     * A memoized intern-cell → user_id resolver (null = the cell matches no
     * intern, or that intern has no linked user).
     */
    private function cellUserResolver(Workspace $workspace): callable
    {
        $resolver = new InternResolver($workspace->id);
        $interns = Intern::where('workspace_id', $workspace->id)->get(['id', 'user_id'])->keyBy('id');
        $cache = [];

        return function (?string $cell) use ($resolver, $interns, &$cache): ?int {
            $key = (string) $cell;

            if (! array_key_exists($key, $cache)) {
                $internId = $resolver->resolve($cell);
                $cache[$key] = $internId ? ($interns[$internId]->user_id ?? null) : null;
            }

            return $cache[$key];
        };
    }

    /**
     * Orders that belong on the statement at all.
     *
     * The exclusions are written null-safely on purpose: `platform NOT IN (…)`
     * and `page NOT LIKE …` are both NULL — not true — when the column is null,
     * so an order missing either would drop out of the statement entirely.
     */
    private function orders(Workspace $workspace): Builder
    {
        return DB::table('gencys_orders')
            ->where('gencys_orders.workspace_id', $workspace->id)
            ->where(fn ($q) => $q->whereNull('gencys_orders.platform')
                ->orWhereNotIn('gencys_orders.platform', self::EXCLUDED_PLATFORMS))
            ->where(fn ($q) => $q->whereNull('gencys_orders.page')
                ->orWhere('gencys_orders.page', 'not like', self::EXCLUDED_PAGE_LIKE));
    }

    private function delivered(Workspace $workspace, Carbon $from, Carbon $to): Builder
    {
        return $this->orders($workspace)
            ->where('gencys_orders.parcel_status', self::DELIVERED_STATUS)
            ->whereBetween('gencys_orders.parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    private function shipped(Workspace $workspace, Carbon $from, Carbon $to): Builder
    {
        return $this->orders($workspace)
            ->whereBetween('gencys_orders.shipped_out_date', [$from->toDateString(), $to->toDateString()]);
    }

    private function key(mixed $value): string
    {
        return $value === null ? '' : (string) (int) $value;
    }
}
