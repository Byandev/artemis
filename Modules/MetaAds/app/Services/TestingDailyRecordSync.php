<?php

namespace Modules\MetaAds\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\TestingItem;

/**
 * Rolls meta_ads_insights up into meta_ads_testing_daily_records — one row per
 * tested campaign / ad set per day.
 *
 * Insights are stored per ad per day, so a campaign's or ad set's figures are
 * the sum of its ads' rows for that date. ROAS follows the Ads Manager's own
 * definition: purchase_value / spend.
 *
 * `day` is the test day, counted from the item's own start_time — day 1 is the
 * day the campaign / ad set started, whatever calendar date that was. Items
 * that started on different dates therefore line up: everyone's day 1 is their
 * own first day.
 *
 * A test runs for MAX_DAY days and then stops: nothing past day 7 is recorded,
 * and anything already recorded past it is cleared out. An item with no
 * start_time has no day to measure, so it is left alone entirely rather than
 * being recorded against an unknown window.
 */
class TestingDailyRecordSync
{
    /** A test is 7 days long; day 8 onwards is not part of it. */
    public const MAX_DAY = 7;

    /**
     * Sync every tested item in the system.
     */
    public function all(): int
    {
        // Paused items keep the history they have and stop accruing days.
        return $this->sync(TestingItem::active()->get());
    }

    /**
     * Sync the given items. Returns how many daily rows were written.
     *
     * @param  Collection<int, TestingItem>  $items
     */
    public function sync(Collection $items): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        $written = 0;

        foreach ($items->groupBy('item_type') as $type => $group) {
            $written += $this->syncType($type, $group);
        }

        return $written;
    }

    /**
     * One item type at a time: the insights column to match on differs, and
     * grouping lets a single query cover every item of that type.
     *
     * @param  Collection<int, TestingItem>  $items
     */
    private function syncType(string $type, Collection $items): int
    {
        $column = $type === 'campaign' ? 'meta_ads_campaign_id' : 'meta_ads_set_id';
        $itemIds = $items->pluck('item_id')->all();

        // Where each item's day 1 falls, as a Y-m-d string keyed by item id.
        $startDates = $this->startDates($type, $itemIds);

        $rows = DB::table('meta_ads_insights')
            ->whereIn($column, $itemIds)
            ->groupBy($column, 'date')
            ->selectRaw(
                "{$column} AS item_id, date, ".
                'COALESCE(SUM(spend), 0) AS ad_spent, '.
                'COALESCE(SUM(purchase_value), 0) AS sales'
            )
            ->get();

        // Insight rows are keyed by the Meta id; map back to our own row ids.
        $recordIds = $items->keyBy(fn (TestingItem $item) => (string) $item->item_id);

        $payload = [];

        foreach ($rows as $row) {
            $item = $recordIds->get((string) $row->item_id);

            if (! $item) {
                continue;
            }

            $day = $this->dayNumber($startDates[(string) $row->item_id] ?? null, $row->date);

            // Outside the 7-day window — or before the item even started, or
            // with no start date to measure from — so not part of the test.
            if ($day === null || $day > self::MAX_DAY) {
                continue;
            }

            $adSpent = (float) $row->ad_spent;
            $sales = (float) $row->sales;

            $payload[] = [
                'meta_ads_testing_item_id' => $item->id,
                'date' => $row->date,
                'day' => $day,
                'sales' => round($sales, 2),
                'ad_spent' => round($adSpent, 2),
                // No spend means no ROAS to speak of — null reads as "n/a"
                // rather than a misleading zero.
                'roas' => $adSpent > 0 ? round($sales / $adSpent, 2) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Clear anything recorded outside the window by an earlier run, so
        // shortening the test length actually shortens the stored history.
        DB::table('meta_ads_testing_daily_records')
            ->whereIn('meta_ads_testing_item_id', $items->pluck('id'))
            ->where(fn ($q) => $q->where('day', '>', self::MAX_DAY)->orWhereNull('day'))
            ->delete();

        if (empty($payload)) {
            return 0;
        }

        // Upsert: a day already recorded is refreshed in place, so re-running
        // after Meta settles late-attributed conversions corrects the numbers.
        foreach (array_chunk($payload, 500) as $chunk) {
            DB::table('meta_ads_testing_daily_records')->upsert(
                $chunk,
                ['meta_ads_testing_item_id', 'date'],
                ['day', 'sales', 'ad_spent', 'roas', 'updated_at'],
            );
        }

        return count($payload);
    }

    /**
     * Each item's start date as Y-m-d, keyed by its Meta id.
     *
     * @param  array<int, mixed>  $itemIds
     * @return array<string, string|null>
     */
    private function startDates(string $type, array $itemIds): array
    {
        $table = $type === 'campaign' ? 'meta_ads_campaigns' : 'meta_ads_sets';

        return DB::table($table)
            ->whereIn('id', $itemIds)
            ->pluck('start_time', 'id')
            ->map(fn ($start) => $start ? Carbon::parse($start)->toDateString() : null)
            ->all();
    }

    /**
     * Which day of the test a date is. Day 1 is the start date itself; a date
     * before the start (Meta occasionally back-dates a row) counts as null
     * rather than zero or a negative day.
     */
    private function dayNumber(?string $start, string $date): ?int
    {
        if ($start === null) {
            return null;
        }

        $days = Carbon::parse($start)->diffInDays(Carbon::parse($date), false);

        return $days >= 0 ? (int) $days + 1 : null;
    }
}
