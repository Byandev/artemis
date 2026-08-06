<?php

namespace Modules\Inventory\Exports;

use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryItemExport implements FromGenerator, WithHeadings
{
    /**
     * @param  QueryBuilder|Builder  $query  the flat per-SKU query (Eloquent models, product
     *                                       eager-loaded) or the grouped roll-up from
     *                                       buildSummaryQuery() (plain stdClass rows)
     * @param  bool  $grouped  which of the two shapes $query is
     */
    public function __construct(private QueryBuilder|Builder $query, private bool $grouped = false) {}

    public function headings(): array
    {
        return [
            'SKU',
            'Product',
            // Only meaningful for the roll-up: how many SKUs the row sums.
            ...($this->grouped ? ['SKUs in Group'] : []),
            'Status',
            'Lead Time (days)',
            'Unfulfilled',
            'Remaining Qty',
            'Discrepancy',
            'Remaining After Fulfillment',
            'Waiting for Delivery',
            'Requested (not yet with supplier)',
            'Stocks Needed for Lead Time',
            '3-Day Avg',
            'PO QTY',
            'Days It Can Last',
            'PO Needed',
        ];
    }

    public function generator(): \Generator
    {
        // Columns mirror the list view: stored fields plus the computed
        // selectRaw columns (current_stocks, remaining_after_fulfillment, etc.)
        // attached by InventoryItemController::buildQuery()/buildSummaryQuery().
        foreach ($this->query->lazy(200) as $item) {
            yield [
                $item->sku,
                // The roll-up joins the product name in as a column, and so does a
                // snapshot row; only the live flat query carries the relation.
                $this->grouped ? $item->product_name : ($item->product_name ?? $item->product?->name),
                // child_count excludes the parent placeholder, so a standalone item
                // (no children) is a group of one.
                ...($this->grouped ? [$item->child_count ?: 1] : []),
                $item->is_active ? 'Active' : 'Inactive',
                $item->lead_time ?? 0,
                $item->unfulfilled_count,
                $item->current_stocks,
                $item->discrepancy,
                $item->remaining_after_fulfillment,
                $item->waiting_for_delivery_stocks,
                $item->requested_stocks,
                $item->stocks_needed_for_lead_time,
                $item->three_days_average,
                $item->po_qty,
                $item->days_it_can_last,
                $item->po_needed,
            ];
        }
    }
}
