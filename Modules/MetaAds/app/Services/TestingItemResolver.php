<?php

namespace Modules\MetaAds\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\TestingItem;

/**
 * Works out which ad account a tracked item runs on and which product it sells.
 *
 * The account is a column on the campaign / ad set — no work to do.
 *
 * The product is derived, along the path the rest of the app already uses (see
 * BudgetTrackerController and CaptureBudgetSnapshotsCommand):
 *
 *     ad set --meta_page_id--> pages.id --shop_id--> shops.product_id
 *
 * A Pancake page's id *is* the Facebook page id, which is what makes the first
 * hop a direct lookup. A campaign carries no page of its own, so it resolves
 * through its ad sets; MIN() keeps the answer stable when a campaign spans more
 * than one page, matching how the budget snapshot picks a page.
 *
 * Every hop is allowed to come up empty — an unsynced page or a shop with no
 * product leaves product_id null rather than failing. Resolution runs again on
 * each sync, so an item fills itself in once the missing link lands.
 */
class TestingItemResolver
{
    /**
     * Fill in account and product for the given items, writing only rows that
     * actually change. Returns how many were updated.
     *
     * @param  Collection<int, TestingItem>  $items
     */
    public function resolve(Collection $items): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        $updated = 0;

        foreach ($items->groupBy('item_type') as $type => $group) {
            $ids = $group->pluck('item_id')->all();

            $accounts = $this->accountIds($type, $ids);
            $pages = $this->pageIds($type, $ids);
            $products = $this->productsForPages(array_filter($pages));

            foreach ($group as $item) {
                $key = (string) $item->item_id;
                $pageId = $pages[$key] ?? null;

                $attributes = [
                    'meta_ads_account_id' => $accounts[$key] ?? null,
                    'product_id' => $pageId !== null ? ($products[(string) $pageId] ?? null) : null,
                ];

                // Never overwrite a resolved value with null — a page that has
                // stopped syncing should not wipe an item's known product.
                $attributes = array_filter($attributes, fn ($value) => $value !== null);

                if ($attributes === [] || ! $this->changes($item, $attributes)) {
                    continue;
                }

                $item->forceFill($attributes)->save();
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param  array<int, mixed>  $attributes
     */
    private function changes(TestingItem $item, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            if ((string) $item->{$key} !== (string) $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * The ad account each item belongs to, keyed by Meta id.
     *
     * @param  array<int, mixed>  $ids
     * @return array<string, int|null>
     */
    private function accountIds(string $type, array $ids): array
    {
        $table = $type === 'campaign' ? 'meta_ads_campaigns' : 'meta_ads_sets';

        return DB::table($table)
            ->whereIn('id', $ids)
            ->pluck('meta_ads_account_id', 'id')
            ->all();
    }

    /**
     * The Facebook page each item promotes, keyed by Meta id. Ad sets carry it
     * directly; campaigns borrow it from their ad sets.
     *
     * @param  array<int, mixed>  $ids
     * @return array<string, int|null>
     */
    private function pageIds(string $type, array $ids): array
    {
        if ($type === 'ad_set') {
            return DB::table('meta_ads_sets')
                ->whereIn('id', $ids)
                ->pluck('meta_page_id', 'id')
                ->all();
        }

        return DB::table('meta_ads_sets')
            ->whereIn('meta_ads_campaign_id', $ids)
            ->whereNotNull('meta_page_id')
            ->groupBy('meta_ads_campaign_id')
            ->pluck(DB::raw('MIN(meta_page_id)'), 'meta_ads_campaign_id')
            ->all();
    }

    /**
     * product_id for each Facebook page id, via the page's shop.
     *
     * @param  array<string, int>  $pageIds
     * @return array<string, int|null>
     */
    private function productsForPages(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }

        return DB::table('pages')
            ->join('shops', 'shops.id', '=', 'pages.shop_id')
            ->whereIn('pages.id', array_values($pageIds))
            ->whereNotNull('shops.product_id')
            ->pluck('shops.product_id', 'pages.id')
            ->all();
    }
}
