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
        $restrictOwnerIds = self::ids($filter, 'restrict_owner_ids');

        if (! $forceJoin && ! $pageIds && ! $shopIds && ! $userIds && ! $productIds && ! $teamIds && ! $restrictOwnerIds) {
            return;
        }

        $query->join('pages', 'pages.id', '=', "{$ordersAlias}.page_id");

        self::applyPageColumnFilters($query, $pageIds, $shopIds, $userIds, $productIds, $teamIds, $restrictOwnerIds);
    }

    /**
     * Apply page_ids / shop_ids / user_ids / product_ids / team_ids filters when pages is already joined.
     * Filters reference pages.id, pages.shop_id, pages.owner_id, pages.product_id, and team_user→pages.owner_id.
     */
    public static function applyToJoined(Builder $query, array $filter): void
    {
        self::applyPageColumnFilters(
            $query,
            self::ids($filter, 'page_ids'),
            self::ids($filter, 'shop_ids'),
            self::ids($filter, 'user_ids'),
            self::ids($filter, 'product_ids'),
            self::ids($filter, 'team_ids'),
            self::ids($filter, 'restrict_owner_ids'),
        );
    }

    private static function applyPageColumnFilters(
        Builder $query,
        ?array $pageIds,
        ?array $shopIds,
        ?array $userIds,
        ?array $productIds,
        ?array $teamIds,
        ?array $restrictOwnerIds = null,
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
            $query->whereIn('pages.product_id', $productIds);
        }

        if ($teamIds) {
            $query->whereIn('pages.owner_id', function ($sub) use ($teamIds) {
                $sub->from('team_user')
                    ->select('user_id')
                    ->whereIn('team_id', $teamIds);
            });
        }

        // Data-scope restriction: limit to pages owned by the given users
        // (the viewer's teammates) regardless of the other filters above.
        if ($restrictOwnerIds) {
            $query->whereIn('pages.owner_id', $restrictOwnerIds);
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
