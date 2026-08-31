<?php

namespace Modules\Finance\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\UserProductIncomeStatement;
use Modules\Finance\Services\Concerns\ClosesOutSlices;
use Modules\Finance\Statements\OrderTotals;
use Modules\Finance\Statements\ProductCostAllocator;
use Modules\Finance\Statements\StatementOrderSourceFactory;
use Modules\Finance\Statements\TransactionTotals;
use Modules\Finance\Statements\UserProductKey;

/**
 * Builds, saves and reads the per-user-per-product slices of an income
 * statement — one row for each seller/product pair, the grain its two siblings
 * each collapse one axis of.
 *
 * The two kinds of figure reach a row differently.
 *
 * Everything carried on the orders themselves — delivered orders and units,
 * revenue, shipping, the cost of goods that shipped — already knows both a
 * seller and a product, so it is read straight at that grain and nothing is
 * apportioned.
 *
 * The goods bought for a product, and the freight on them, know only a product.
 * A product run by several people has one such figure between them, so each
 * seller takes the share matching their share of that product's delivered
 * orders. A product whose costs no one delivered against this month keeps them
 * on a row with no user rather than having them silently dropped or spread over
 * sellers of other products.
 *
 * Ad spend is apportioned only where it has to be. Where the source ties spend
 * to a page — which has both an owner and a product — the pair is already known
 * and a seller carries the spend from their own pages exactly.
 *
 * That choice is what the rows reconcile to: summed over users, a product here
 * equals its row on the product statement. It deliberately does NOT reconcile to
 * the user statement, which reads the same costs from the charge-to shares on
 * transactions instead — a different attribution of the same money, and the two
 * are not expected to agree.
 *
 * Snapshotted into `finance_income_user_product_statements` when the parent
 * statement is saved or regenerated. A first view with no snapshot builds one.
 */
class UserProductIncomeStatementService
{
    use ClosesOutSlices;

    public function __construct(
        private readonly StatementOrderSourceFactory $sources,
        private readonly TransactionTotals $transactions,
        private readonly ProductCostAllocator $allocator,
    ) {}

    /** (Re)compute and store every user/product row for the statement's month. */
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

        $orders = $source->totalsByUserProduct($workspace, $from, $to);

        // Ad spend can be exact at this grain: where a page has both an owner
        // and a product behind it, the spend already knows the pair, so a
        // seller carries what ran on their own pages and nothing else. Only a
        // source that cannot tie the two together falls back to apportioning
        // the product's figure.
        $exactAdSpend = $source->adSpendByUserProduct($workspace, $from, $to);

        // Costs that know a product but not a seller, shared out below.
        $perProduct = [
            'total_bought_cogs' => $this->transactions->byProductTag($workspace, $from, $to, TransactionTotals::COST_OF_GOODS, withUntagged: true),
            'total_bought_cogs_delivery_fee' => $this->transactions->byProductTag($workspace, $from, $to, TransactionTotals::COG_DELIVERY, withUntagged: true),
        ];

        if ($exactAdSpend === null) {
            $perProduct['ad_spent'] = $source->adSpendByProduct($workspace, $from, $to);
        }

        $costs = $this->allocator->allocate($perProduct, $orders);

        foreach ($exactAdSpend ?? [] as $key => $amount) {
            $costs[$key]['ad_spent'] = round((float) $amount, 2);
        }

        // A pair earns a row for a cost even with no orders behind it.
        foreach (array_keys($costs) as $key) {
            $orders[$key] ??= OrderTotals::empty();
        }

        $names = $this->names(array_keys($orders));

        $rows = collect($orders)->map(function (OrderTotals $t, string $key) use ($costs, $names, $codRate, $vatRate, $advisoryRate, $gencysPartner) {
            [$userId, $productId] = UserProductKey::split($key);

            $revenue = round($t->deliveredAmount, 2);
            $codFee = round($revenue * $codRate, 2);
            $codVat = round($codFee * $vatRate, 2);

            $adSpent = round($costs[$key]['ad_spent'] ?? 0, 2);
            $shippingFee = round($t->shippingFee, 2);
            $deliveredCogs = round($t->deliveredCogs, 2);
            $boughtCogs = round($costs[$key]['total_bought_cogs'] ?? 0, 2);
            $boughtFreight = round($costs[$key]['total_bought_cogs_delivery_fee'] ?? 0, 2);

            // Both margins take the same costs off delivered revenue and differ
            // only in which cost of goods they charge.
            $commonCosts = $adSpent + $shippingFee + $codFee + $codVat;

            $grossDelivered = round($revenue - $commonCosts - $deliveredCogs, 2);
            // Freight on a purchase is part of what the stock cost.
            $grossBought = round($revenue - $commonCosts - $boughtCogs - $boughtFreight, 2);

            // A share of gross profit for gencys partners, taken on whichever
            // basis it sits beside. Only a positive gross owes anything — a
            // loss doesn't earn a rebate.
            $advisory = fn (float $gross) => ($gencysPartner && $gross > 0)
                ? round($gross * $advisoryRate, 2)
                : 0.0;

            return [
                'user_id' => $userId,
                'user_name' => $userId !== null ? ($names['users'][$userId] ?? 'Unknown') : 'Unassigned',
                'product_id' => $productId,
                'product_name' => $productId !== null ? ($names['products'][$productId] ?? 'Unknown') : 'Unresolved',
                'delivered_orders' => $t->deliveredOrders,
                'delivered_units' => $t->deliveredUnits,
                'delivered_amount' => $revenue,
                'shipped_orders' => $t->shippedOrders,
                'total_shipping_fee' => $shippingFee,
                'ad_spent' => $adSpent,
                'cod_fee' => $codFee,
                'cod_fee_vat' => $codVat,
                'total_bought_cogs' => $boughtCogs,
                'total_bought_cogs_delivery_fee' => $boughtFreight,
                'total_delivered_cogs' => $deliveredCogs,
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
        $rows = collect($this->closeSlice($statement, $rows->all()));

        DB::transaction(function () use ($statement, $rows) {
            $statement->userProductStatements()->delete();

            foreach ($rows as $row) {
                $statement->userProductStatements()->create($row);
            }
        });
    }

    /**
     * Snapshot names for every id on the rows, so a renamed or deleted user or
     * product still reads back.
     *
     * @param  list<string>  $keys
     * @return array{users: array<int, string>, products: array<int, string>}
     */
    private function names(array $keys): array
    {
        $userIds = [];
        $productIds = [];

        foreach ($keys as $key) {
            [$userId, $productId] = UserProductKey::split($key);
            if ($userId !== null) {
                $userIds[$userId] = true;
            }
            if ($productId !== null) {
                $productIds[$productId] = true;
            }
        }

        return [
            'users' => User::whereIn('id', array_keys($userIds))->pluck('name', 'id')->all(),
            'products' => DB::table('products')->whereIn('id', array_keys($productIds))->pluck('name', 'id')->all(),
        ];
    }

    /**
     * The saved rows, grouped so a product's sellers sit together: products by
     * delivered revenue, sellers within a product likewise, with the unresolved
     * product last and each product's own no-user row last within it. Plus a
     * Total across the fully-attributed rows. Built on first access.
     *
     * @return array{rows: list<array<string, mixed>>, total: array<string, mixed>, rates: array{cod:float, vat:float, advisory:float}, gencysPartner: bool}
     */
    public function payload(IncomeStatement $statement): array
    {
        $this->ensureSnapshot($statement);

        $rows = $statement->userProductStatements()->get()->map(fn ($r) => [
            'user_id' => $r->user_id,
            'user' => $r->user_name ?: 'Unassigned',
            'product_id' => $r->product_id,
            'product' => $r->product_name ?: 'Unresolved',
            'delivered_orders' => (int) $r->delivered_orders,
            'delivered_units' => (int) $r->delivered_units,
            'delivered_amount' => (float) $r->delivered_amount,
            'shipped_orders' => (int) $r->shipped_orders,
            'total_shipping_fee' => (float) $r->total_shipping_fee,
            'ad_spent' => (float) $r->ad_spent,
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
            'net_profit_delivered_cogs' => (float) $r->net_profit_delivered_cogs,
            'net_profit_bought_cogs' => (float) $r->net_profit_bought_cogs,
        ]);

        // A product's own revenue, so its sellers stay together and the biggest
        // product leads — sorting each row on its own would interleave them.
        $byProduct = $rows->groupBy(fn ($r) => (string) $r['product_id']);
        $productRevenue = $byProduct->map(fn ($group) => $group->sum('delivered_amount'));

        $ordered = $rows->sortBy([
            // The unresolved product last, whatever it sold.
            fn ($a, $b) => ($a['product_id'] === null) <=> ($b['product_id'] === null),
            fn ($a, $b) => $productRevenue[(string) $b['product_id']] <=> $productRevenue[(string) $a['product_id']],
            fn ($a, $b) => $a['product'] <=> $b['product'],
            // Within a product, the no-user row last.
            fn ($a, $b) => ($a['user_id'] === null) <=> ($b['user_id'] === null),
            fn ($a, $b) => $b['delivered_amount'] <=> $a['delivered_amount'],
        ])->values();

        // The total covers rows attributed on both axes; revenue nobody is
        // credited with, or that belongs to no product, is not anyone's.
        $attributed = $rows->filter(fn ($r) => $r['user_id'] !== null && $r['product_id'] !== null);

        $total = ['user_id' => null, 'user' => 'Total', 'product_id' => null, 'product' => ''];

        foreach (array_keys($rows->first() ?? []) as $key) {
            if (in_array($key, ['user_id', 'user', 'product_id', 'product'], true)) {
                continue;
            }
            $total[$key] = str_contains($key, '_orders') || str_contains($key, '_units')
                ? (int) $attributed->sum($key)
                : round($attributed->sum($key), 2);
        }

        return [
            'rows' => $ordered->all(),
            'total' => $total,
            'rates' => [
                'cod' => (float) $statement->cod_fee_rate,
                'vat' => (float) $statement->vat_rate,
                'advisory' => (float) $statement->advisory_rate,
            ],
            'gencysPartner' => (bool) $statement->workspace->is_gencys_partner,
        ];
    }

    /**
     * One seller's products: the same saved rows narrowed to a single user, for
     * the drill-down off the per-user list.
     *
     * Each column also carries the whole product (`whole_*`) and this seller's
     * `share` of its delivered orders, so the page can show what the allocated
     * costs were a share of.
     *
     * Null when the statement has no rows for that user at all — there is
     * nothing to drill into.
     *
     * @return array{user: array{id:?int, name:string}, products: list<array<string, mixed>>, total: array<string, mixed>, rates: array{cod:float, vat:float, advisory:float}, gencysPartner: bool}|null
     */
    public function userPayload(IncomeStatement $statement, ?int $userId): ?array
    {
        $this->ensureSnapshot($statement);

        // Every seller's rows, not just this one's: the product's own totals are
        // these rows added up along the user axis, so the page can show what
        // each figure was a share OF without reading another table (and without
        // the two ever disagreeing).
        $all = $statement->userProductStatements()->get();

        $rows = $all->filter(fn ($r) => $userId === null
            ? $r->user_id === null
            : (int) $r->user_id === $userId);

        if ($rows->isEmpty()) {
            return null;
        }

        $wholeProduct = $all->groupBy(fn ($r) => (string) $r->product_id)
            ->map(fn ($group) => $this->foldFigures($group));

        $products = $rows->map(fn ($r) => [
            ...$this->wholeAndShare($wholeProduct[(string) $r->product_id] ?? null, (int) $r->delivered_orders),
            'product_id' => $r->product_id,
            'product' => $r->product_name ?: 'Unresolved',
            'delivered_orders' => (int) $r->delivered_orders,
            'delivered_units' => (int) $r->delivered_units,
            'delivered_amount' => (float) $r->delivered_amount,
            'shipped_orders' => (int) $r->shipped_orders,
            'total_shipping_fee' => (float) $r->total_shipping_fee,
            'ad_spent' => (float) $r->ad_spent,
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
            'net_profit_delivered_cogs' => (float) $r->net_profit_delivered_cogs,
            'net_profit_bought_cogs' => (float) $r->net_profit_bought_cogs,
        ]);

        $named = $products->filter(fn ($r) => $r['product_id'] !== null)
            ->sortByDesc('delivered_amount')
            ->values();

        $unresolved = $products->first(fn ($r) => $r['product_id'] === null);

        // The total covers the named products; revenue that belongs to no
        // product isn't any product's, so counting it would overstate every
        // column — the same rule the sibling statements use.
        $total = ['product_id' => null, 'product' => 'Total'];

        foreach (array_keys($products->first() ?? []) as $key) {
            if (in_array($key, ['product_id', 'product', 'share'], true) || str_starts_with($key, 'whole_')) {
                continue;
            }
            $total[$key] = str_contains($key, '_orders') || str_contains($key, '_units')
                ? (int) $named->sum($key)
                : round($named->sum($key), 2);
        }

        // Across the products they run, taken together: what those products did
        // in total, and how much of it was theirs.
        $namedIds = $named->pluck('product_id')->map(fn ($id) => (string) $id)->all();

        $total += $this->wholeAndShare(
            $this->foldFigures($all->filter(fn ($r) => in_array((string) $r->product_id, $namedIds, true))),
            (int) $named->sum('delivered_orders'),
        );

        return [
            'user' => [
                'id' => $userId,
                'name' => $rows->first()->user_name ?: 'Unassigned',
            ],
            'products' => ($unresolved ? $named->push($unresolved) : $named)->values()->all(),
            'total' => $total,
            'rates' => [
                'cod' => (float) $statement->cod_fee_rate,
                'vat' => (float) $statement->vat_rate,
                'advisory' => (float) $statement->advisory_rate,
            ],
            'gencysPartner' => (bool) $statement->workspace->is_gencys_partner,
        ];
    }

    /**
     * The figures a set of rows adds up to — a product across every seller who
     * ran it, or a group of products taken together.
     *
     * @param  Collection<int, UserProductIncomeStatement>  $rows
     * @return array<string, float|int>
     */
    private function foldFigures($rows): array
    {
        return [
            'delivered_orders' => (int) $rows->sum('delivered_orders'),
            'delivered_amount' => round((float) $rows->sum('delivered_amount'), 2),
            'ad_spent' => round((float) $rows->sum('ad_spent'), 2),
            'total_bought_cogs' => round((float) $rows->sum('total_bought_cogs'), 2),
            'total_bought_cogs_delivery_fee' => round((float) $rows->sum('total_bought_cogs_delivery_fee'), 2),
            'total_delivered_cogs' => round((float) $rows->sum('total_delivered_cogs'), 2),
        ];
    }

    /**
     * The whole-product context for one column: what the product did across
     * every seller (`whole_*`), and this seller's `share` of its delivered
     * orders — the very weight the allocated costs were cut by, so the page can
     * show `whole × share` landing on the figure beside it.
     *
     * Null share when the product delivered nothing this month: there is no
     * ratio, and 0/0 is not 0%.
     *
     * @param  array<string, float|int>|null  $whole
     * @return array<string, mixed>
     */
    private function wholeAndShare(?array $whole, int $mineDelivered): array
    {
        $whole ??= $this->foldFigures(collect());
        $out = ['share' => $whole['delivered_orders'] > 0
            ? round($mineDelivered / $whole['delivered_orders'], 6)
            : null];

        foreach ($whole as $key => $value) {
            $out['whole_'.$key] = $value;
        }

        return $out;
    }

    private function ensureSnapshot(IncomeStatement $statement): void
    {
        if (! $statement->userProductStatements()->exists()) {
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
