<?php

namespace App\Support\Metrics;

use Illuminate\Database\Query\Builder;

class OrdersFilter
{
    /**
     * Conditionally JOIN pages and apply page_ids / shop_ids / user_ids filters.
     * Use this when a metric's base query may or may not need the pages join.
     *
     * Pass $forceJoin = true when the caller (breakdown/perPage/perShop/perUser)
     * needs pages joined regardless of whether a filter is set.
     *
     * Filters reference pages.id (page_ids), pages.shop_id (shop_ids),
     * pages.owner_id (user_ids).
     */
    public static function joinAndApply(Builder $query, array $filter, bool $forceJoin = false, string $ordersAlias = 'pancake_orders'): void
    {
        $pageIds = self::ids($filter, 'page_ids');
        $shopIds = self::ids($filter, 'shop_ids');
        $userIds = self::ids($filter, 'user_ids');
        $productIds = self::ids($filter, 'product_ids');
        $teamIds = self::ids($filter, 'team_ids');

        // order_source_name lives on the orders table, so apply it before the pages
        // join (and without forcing one). Webcake orders have a null page_id; an inner
        // join on pages would drop them, defeating an "order source" filter.
        self::applySourceNames($query, $filter, $ordersAlias);

        if (! $forceJoin && ! $pageIds && ! $shopIds && ! $userIds && ! $productIds && ! $teamIds) {
            return;
        }

        $query->join('pages', 'pages.id', '=', "{$ordersAlias}.page_id");

        self::applyPageColumnFilters($query, $pageIds, $shopIds, $userIds, $productIds, $teamIds);
    }

    /**
     * Apply page_ids / shop_ids / user_ids / product_ids / team_ids filters when pages is already joined.
     * Filters reference pages.id, pages.shop_id, pages.owner_id, pages.shop_id→shops.product_id, and team_user→pages.owner_id.
     */
    public static function applyToJoined(Builder $query, array $filter, string $ordersAlias = 'po'): void
    {
        self::applyPageColumnFilters(
            $query,
            self::ids($filter, 'page_ids'),
            self::ids($filter, 'shop_ids'),
            self::ids($filter, 'user_ids'),
            self::ids($filter, 'product_ids'),
            self::ids($filter, 'team_ids'),
        );

        self::applySourceNames($query, $filter, $ordersAlias);
    }

    /**
     * Filter by order_source_name (e.g. "Facebook", "Webcake"). Applied directly on
     * the orders table so it works for page-less Webcake orders too.
     */
    private static function applySourceNames(Builder $query, array $filter, string $ordersAlias): void
    {
        $sourceNames = self::ids($filter, 'order_source_names');

        if ($sourceNames) {
            $query->whereIn("{$ordersAlias}.order_source_name", $sourceNames);
        }
    }

    private static function applyPageColumnFilters(
        Builder $query,
        ?array $pageIds,
        ?array $shopIds,
        ?array $userIds,
        ?array $productIds,
        ?array $teamIds,
    ): void {
        if ($pageIds) {
            $query->whereIn('pages.id', $pageIds);
        }

        if ($shopIds) {
            $query->whereIn('pages.shop_id', $shopIds);
        }

        if ($userIds) {
            $query->whereIn('pages.owner_id', $userIds);
        }

        if ($productIds) {
            // The product link now lives on the shop; match pages whose shop is
            // assigned to one of the selected products.
            $query->whereIn('pages.shop_id', function ($sub) use ($productIds) {
                $sub->from('shops')->select('id')->whereIn('product_id', $productIds);
            });
        }

        if ($teamIds) {
            $query->whereIn('pages.owner_id', function ($sub) use ($teamIds) {
                $sub->from('team_user')
                    ->select('user_id')
                    ->whereIn('team_id', $teamIds);
            });
        }
    }

    /**
     * Normalize a filter value into a clean array of IDs, or null if empty.
     * Accepts arrays, comma-separated strings, or null.
     */
    private static function ids(array $filter, string $key): ?array
    {
        $value = $filter[$key] ?? null;

        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $list = is_array($value)
            ? $value
            : explode(',', (string) $value);

        $list = array_values(array_filter($list, fn ($v) => $v !== null && $v !== ''));

        return $list ?: null;
    }
}
