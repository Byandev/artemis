<?php

namespace Modules\Inventory\Exports;

use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\Inventory\Support\ItemReportFacts;

/**
 * The wide planning report: one row per item group, carrying demand over three
 * windows, cover, movement dates and the state of the group's purchase orders.
 *
 * Separate from InventoryItemExport, which stays a faithful dump of the list
 * view. This one answers a different question — not "what does the table say"
 * but "for each thing we sell, is supply keeping up" — so it adds columns the
 * list has never shown and would not have room for.
 *
 * Rolled up to the group only. A parent and its children share supply and share
 * a reorder decision, so a per-SKU row would split one decision across several
 * lines and invite double-counting the group's demand.
 */
class InventoryItemReportExport implements FromGenerator, WithHeadings
{
    /**
     * Cover below this share of the lead time is critical: stock will run out
     * before a replacement order could possibly land, so there is no ordering
     * decision left to make — only an expediting one.
     */
    private const CRITICAL_LEAD_TIME_SHARE = 0.5;

    /**
     * @param  Builder  $query  the grouped roll-up from buildSummaryQuery(), or its
     *                          snapshot equivalent for a past date
     * @param  (callable(object): array<string, mixed>)  $factsFor  where a row's
     *                                                              group figures come from
     *
     * Two sources, one shape. Live, the figures are computed now and looked up by
     * group; for a past date they were frozen into the snapshot row itself and
     * are read straight off it. The keys are identical either way — see
     * ItemReportFacts::SNAPSHOT_COLUMNS — so nothing below this line knows or
     * cares which date it is rendering.
     */
    public function __construct(private Builder $query, private $factsFor) {}

    /** Read the figures live, computing them once for the whole workspace. */
    public static function live(Builder $query, ItemReportFacts $facts): self
    {
        return new self($query, fn (object $row) => $facts->for((int) $row->id));
    }

    /**
     * Read the figures as they were frozen on a past date.
     *
     * Missing keys default to null rather than zero: a snapshot taken before
     * these columns existed recorded no demand, which is not the same as having
     * recorded that demand was nil.
     */
    public static function asOf(Builder $query): self
    {
        return new self($query, fn (object $row) => ((array) $row) + array_fill_keys(ItemReportFacts::SNAPSHOT_COLUMNS, null));
    }

    public function headings(): array
    {
        return [
            'Item',
            '3 Days Average Orders',
            '3 Days Average Units',
            '7 Days Average Orders',
            '7 Days Average Units',
            '14 Days Average Orders',
            '14 Days Average Units',
            'Demand Trend (3d vs 14d)',
            'Unfulfilled Order Quantity',
            'Unfulfilled Item Needed',
            'Current Stocks',
            'Current Stocks Can Last (days)',
            'Lead Time',
            'Stockout Risk',
            'Last Stocks IN Date',
            'Last Stocks IN Count',
            'Last Stocks OUT Date',
            'Last Stocks OUT Count',
            'Waiting Stocks',
            'Remaining + Waiting Can Last (days)',
            'PO Needed',
            'Last PO Date',
            'Last PO Count',
            'Days PO Raised But No PO Created',
            'PO Raised Count But No PO Created',
            'Earliest PO Expected Delivery Date',
            'Earliest PO Expected Delivery Count',
            'Longest Waiting PO Date',
            'Longest Waiting PO Count',
            'Delayed PO',
            'Bottleneck Stage',
        ];
    }

    public function generator(): \Generator
    {
        foreach ($this->query->lazy(200) as $row) {
            $facts = ($this->factsFor)($row);

            $average = (float) $row->three_days_average;
            $stocks = (int) round((float) $row->current_stocks);
            $unfulfilled = (int) round((float) $row->unfulfilled_count);
            $leadTime = (int) ($row->lead_time ?? 0);
            $stocksCover = $average > 0 ? round($stocks / $average, 1) : null;

            yield [
                $row->sku,
                ...$this->demandColumns($facts),
                $this->trend($facts),
                $unfulfilled,
                // The part of what we owe with no stock behind it — real orders
                // waiting on supply rather than on picking.
                max(0, $unfulfilled - $stocks),
                $stocks,
                $stocksCover,
                $leadTime,
                $this->stockoutRisk($stocksCover, $leadTime),
                $facts['last_in_date'],
                $facts['last_in_count'],
                $facts['last_out_date'],
                $facts['last_out_count'],
                (int) round((float) $row->waiting_for_delivery_stocks),
                // days_it_can_last already runs on remaining_after_fulfillment,
                // which is current stock plus what suppliers owe, less unfulfilled.
                round((float) $row->days_it_can_last, 1),
                (int) round((float) $row->po_needed),
                $facts['last_po_date'],
                $facts['last_po_count'],
                $facts['raised_not_created_days'],
                $facts['raised_not_created_units'],
                $facts['earliest_expected_date'],
                $facts['earliest_expected_count'],
                $facts['longest_waiting_date'],
                $facts['longest_waiting_count'],
                $facts['delayed_po'],
                $facts['bottleneck_stage'],
            ];
        }
    }

    /**
     * Orders and units per window, as daily rates rather than window totals so
     * the three are comparable side by side: 30 units over 3 days and 70 over 14
     * are 10/day against 5/day, which the raw totals hide.
     *
     * @param  array<string, mixed>  $facts
     * @return array<int, float|int>
     */
    private function demandColumns(array $facts): array
    {
        $columns = [];

        foreach (ItemReportFacts::WINDOWS as $days) {
            foreach (["orders_{$days}d", "units_{$days}d"] as $key) {
                // Null survives as null. A snapshot taken before these columns
                // existed did not record nil demand, it recorded nothing, and a
                // 0 in a report someone plans against is a lie either way.
                $columns[] = $facts[$key] === null ? null : round($facts[$key] / $days, 2);
            }
        }

        return $columns;
    }

    /**
     * Recent demand against the fortnight behind it, as a percentage. Over 100
     * means demand is accelerating and the reorder maths is reading low.
     *
     * Null rather than zero when there is no fortnight to compare against: a new
     * item has no trend, which is not the same as a flat one.
     *
     * @param  array<string, mixed>  $facts
     */
    private function trend(array $facts): ?int
    {
        if ($facts['units_3d'] === null || $facts['units_14d'] === null) {
            return null;
        }

        $recent = $facts['units_3d'] / 3;
        $baseline = $facts['units_14d'] / 14;

        return $baseline > 0 ? (int) round(100 * $recent / $baseline) : null;
    }

    /**
     * How exposed the group is, read as cover against its own lead time.
     *
     * Judged against lead time rather than a fixed number of days because that
     * is what decides whether ordering now still helps: 5 days of cover is
     * comfortable on a 3-day lead time and hopeless on a 30-day one.
     */
    private function stockoutRisk(?float $cover, int $leadTime): string
    {
        if ($cover === null) {
            // No demand recorded, so nothing is being consumed to run out of.
            return 'No demand';
        }

        if ($leadTime <= 0) {
            return $cover > 0 ? 'OK' : 'Out of stock';
        }

        if ($cover <= 0) {
            return 'Out of stock';
        }

        return match (true) {
            $cover < $leadTime * self::CRITICAL_LEAD_TIME_SHARE => 'Critical',
            $cover < $leadTime => 'At risk',
            default => 'OK',
        };
    }
}
