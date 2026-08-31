<?php

namespace Modules\Finance\Statements;

/**
 * Sharing a product's costs between the sellers who moved it.
 *
 * The goods bought for a product, and the freight on them, are tagged to the
 * product and to nobody in particular. A product run by several people has one
 * such figure between them, so each takes the share matching their share of its
 * delivered orders.
 *
 * Lives here rather than inside one statement service because two of them need
 * the same answer: the per-user statement's cost columns are these allocations
 * added up along the product axis, and the user-and-product statement's are the
 * allocations themselves. Sharing the arithmetic is what stops the two pages
 * disagreeing about the same money.
 */
final class ProductCostAllocator
{
    /**
     * Split each product's costs across its sellers.
     *
     * A product with costs but no delivered orders at all keeps them whole on a
     * row with no user: there is no seller to credit them to, and dropping them
     * would leave the slice short of the product statement.
     *
     * @param  array<string, array<string, float>>  $perProduct  cost name => [product key => amount]
     * @param  array<string, OrderTotals>  $orders  keyed by {@see UserProductKey}
     * @return array<string, array<string, float>> user-product key => [cost name => amount]
     */
    public function allocate(array $perProduct, array $orders): array
    {
        // Delivered orders per seller, per product — the weights themselves.
        $weights = [];

        foreach ($orders as $key => $totals) {
            if ($totals->deliveredOrders > 0) {
                $weights[UserProductKey::productOf($key)][$key] = (float) $totals->deliveredOrders;
            }
        }

        $costs = [];

        foreach ($perProduct as $name => $amounts) {
            foreach ($amounts as $productKey => $amount) {
                $productKey = (string) $productKey;
                $shares = $weights[$productKey] ?? [];

                if ($shares === []) {
                    $costs[UserProductKey::of(null, $productKey)][$name] = round((float) $amount, 2);

                    continue;
                }

                foreach (ProportionalSplit::of((float) $amount, $shares) as $key => $share) {
                    $costs[$key][$name] = $share;
                }
            }
        }

        return $costs;
    }

    /**
     * The same allocations folded along the product axis — what each seller
     * carries in total, keyed by user id as a string ('' = credited to nobody,
     * which is where a product no one delivered leaves its costs).
     *
     * @param  array<string, array<string, float>>  $allocated  from {@see allocate()}
     * @return array<string, array<string, float>> cost name => [user key => amount]
     */
    public function byUser(array $allocated): array
    {
        $totals = [];

        foreach ($allocated as $key => $costs) {
            [$userId] = UserProductKey::split($key);
            $userKey = (string) ($userId ?? '');

            foreach ($costs as $name => $amount) {
                $totals[$name][$userKey] = round(($totals[$name][$userKey] ?? 0) + $amount, 2);
            }
        }

        return $totals;
    }
}
