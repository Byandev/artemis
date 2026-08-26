<?php

namespace Modules\Finance\Services;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Services\Concerns\ResolvesProductOrders;

/**
 * Builds, saves and reads the per-product slices of an income statement.
 *
 * A product row is workspace-wide: every intern's orders for that product, not
 * one person's. Each delivered order resolves to a product through its items —
 * `gencys_order_items.sku` is a unit-code label matching an
 * `inventory_unit_codes.unit_code`, whose `product_id` is the product — and
 * orders that resolve to nothing roll into a single "Unresolved" row.
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
    use ResolvesProductOrders;

    /** (Re)compute and store every per-product row for the statement's month. */
    public function snapshot(IncomeStatement $statement): void
    {
        $workspace = $statement->workspace;
        [$from, $to] = $this->range($statement);

        $delivered = $this->deliveredByProduct($workspace, null, $from, $to);
        $deliveredCogs = $this->deliveredCogsByProduct($workspace, $from, $to);
        $boughtCogs = $this->taggedTotalsForTypes($workspace, $from, $to, $this->costOfGoodsTypeIds($workspace));
        $boughtFreight = $this->taggedTotalsForTypes($workspace, $from, $to, $this->cogDeliveryTypeIds($workspace));

        // A product earns a row if anything happened to it this month, whether
        // that was a delivery or only a purchase.
        $keys = collect(array_keys($delivered))
            ->merge(array_keys($deliveredCogs))
            ->merge(array_keys($boughtCogs))
            ->merge(array_keys($boughtFreight))
            ->unique();

        $names = DB::table('products')
            ->whereIn('id', $keys->filter(fn ($k) => $k !== '')->map(fn ($k) => (int) $k)->all())
            ->pluck('name', 'id');

        $rows = $keys->map(function ($key) use ($delivered, $deliveredCogs, $boughtCogs, $boughtFreight, $names) {
            $productId = $key === '' ? null : (int) $key;
            $sold = $delivered[$key] ?? ['orders' => 0, 'revenue' => 0.0];

            return [
                'product_id' => $productId,
                'product_name' => $productId !== null ? ($names[$productId] ?? 'Unknown') : 'Unresolved',
                'delivered_count' => (int) $sold['orders'],
                'delivered_amount' => round((float) $sold['revenue'], 2),
                'total_bought_cogs' => round((float) ($boughtCogs[$key] ?? 0), 2),
                'total_bought_cogs_delivery_fee' => round((float) ($boughtFreight[$key] ?? 0), 2),
                'total_delivered_cogs' => round((float) ($deliveredCogs[$key] ?? 0), 2),
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
     * @return array{products: list<array<string, mixed>>, total: array<string, mixed>}
     */
    public function payload(IncomeStatement $statement): array
    {
        $this->ensureSnapshot($statement);

        $rows = $statement->productStatements()->get()->map(fn ($r) => [
            'product_id' => $r->product_id,
            'product' => $r->product_name ?: 'Unresolved',
            'delivered_count' => (int) $r->delivered_count,
            'delivered_amount' => (float) $r->delivered_amount,
            'total_bought_cogs' => (float) $r->total_bought_cogs,
            'total_bought_cogs_delivery_fee' => (float) $r->total_bought_cogs_delivery_fee,
            'total_delivered_cogs' => (float) $r->total_delivered_cogs,
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
            'delivered_count' => (int) $named->sum('delivered_count'),
            'delivered_amount' => $sum('delivered_amount'),
            'total_bought_cogs' => $sum('total_bought_cogs'),
            'total_bought_cogs_delivery_fee' => $sum('total_bought_cogs_delivery_fee'),
            'total_delivered_cogs' => $sum('total_delivered_cogs'),
        ];

        $unresolved = $rows->first(fn ($r) => $r['product_id'] === null);

        return [
            'products' => ($unresolved ? $named->push($unresolved) : $named)->values()->all(),
            'total' => $total,
        ];
    }

    private function ensureSnapshot(IncomeStatement $statement): void
    {
        if (! $statement->productStatements()->exists()) {
            $this->snapshot($statement);
        }
    }

    /**
     * The cost of the goods actually delivered in the month, per product id,
     * summed off the orders themselves ('' = orders resolving to no product).
     *
     * @return array<string, float>
     */
    private function deliveredCogsByProduct(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        $delivered = $this->deliveredOrders($workspace, $from, $to)
            ->selectRaw('COALESCE(total_cog, 0) as cog')
            ->selectSub($this->orderProductSubquery(), 'product_id');

        return DB::query()->fromSub($delivered, 't')
            ->selectRaw('product_id, COALESCE(SUM(cog), 0) as cog')
            ->groupBy('product_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$this->productKey($r->product_id) => round((float) $r->cog, 2)])
            ->all();
    }
}
