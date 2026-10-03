<?php

namespace App\Support\Metrics;

use Illuminate\Database\Query\Builder;

class OrdersFilter
{
    /**
     * Page-keyed rollups with no shop_id column; their shop filter has to go through pages.
     */
    private const TABLES_WITHOUT_SHOP_ID = ['workspace_page_daily_metrics'];

    /**
     * Conditionally JOIN pages and apply page_ids / shop_ids / user_ids filters.
     * Use this when a metric's base query may or may not need the pages join.
     *
     * Pass $forceJoin = true when the caller (breakdown/perPage/perShop/perUser)
     * needs pages joined regardless of whether a filter is set.
     *
     * Filters reference pages.id (page_ids), the orders table's own shop_id
     * (shop_ids; pages.shop_id for page-keyed rollups), pages.owner_id (user_ids).
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

        // The order's own shop_id is what Pancake reports, so filter on it directly
        // when the table has one. Going through pages.shop_id drops page-less
        // (Webcake) orders and orders whose page was re-synced into another shop.
        if ($shopIds && ! in_array($ordersAlias, self::TABLES_WITHOUT_SHOP_ID, true)) {
            $query->whereIn("{$ordersAlias}.shop_id", $shopIds);
            $shopIds = null;
        }

        if (! $forceJoin && ! $pageIds && ! $shopIds && ! $userIds && ! $productIds && ! $teamIds) {
            return;
        }

        $query->join('pages', 'pages.id', '=', "{$ordersAlias}.page_id");

        self::applyPageColumnFilters($query, $pageIds, $shopIds, $userIds, $productIds, $teamIds);
    }

    /**
     * Apply page_ids / shop_ids / user_ids / product_ids / team_ids filters when pages is already joined.
     * Filters reference pages.id, the orders table's shop_id, pages.owner_id,
     * pages.shop_id→shops.product_id, and team_user→pages.owner_id.
     *
     * Pass $ordersAlias = null for queries with no orders table (e.g. users ⋈ pages);
     * shop_ids then falls back to pages.shop_id and order sources are skipped.
     */
    public static function applyToJoined(Builder $query, array $filter, ?string $ordersAlias = 'po'): void
    {
        $shopIds = self::ids($filter, 'shop_ids');

        if ($shopIds && $ordersAlias !== null) {
            $query->whereIn("{$ordersAlias}.shop_id", $shopIds);
            $shopIds = null;
        }

        self::applyPageColumnFilters(
            $query,
            self::ids($filter, 'page_ids'),
            $shopIds,
            self::ids($filter, 'user_ids'),
            self::ids($filter, 'product_ids'),
            self::ids($filter, 'team_ids'),
        );

        if ($ordersAlias !== null) {
            self::applySourceNames($query, $filter, $ordersAlias);
        }
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
