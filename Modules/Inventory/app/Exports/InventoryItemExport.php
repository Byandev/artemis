<?php

namespace Modules\Inventory\Exports;

use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryItemExport implements FromGenerator, WithHeadings
{
    public function __construct(private QueryBuilder $query) {}

    public function headings(): array
    {
        return [
            'SKU',
            'Product',
            'Status',
            'Lead Time (days)',
            'Unfulfilled',
            'Remaining Qty',
            'Discrepancy',
            'Remaining After Fulfillment',
            'Waiting for Delivery',
            'Stocks Needed for Lead Time',
            '3-Day Avg',
            'Days It Can Last',
            'PO Needed',
        ];
    }

    public function generator(): \Generator
    {
        // Columns mirror the list view: stored fields plus the computed
        // selectRaw columns (current_stocks, remaining_after_fulfillment, etc.)
        // attached by InventoryItemController::buildQuery().
        foreach ($this->query->lazy(200) as $item) {
            yield [
                $item->sku,
                $item->product?->name,
                $item->is_active ? 'Active' : 'Inactive',
                $item->lead_time ?? 0,
                $item->unfulfilled_count,
                $item->current_stocks,
                $item->discrepancy,
                $item->remaining_after_fulfillment,
                $item->waiting_for_delivery_stocks,
                $item->stocks_needed_for_lead_time,
                $item->three_days_average,
                $item->days_it_can_last,
                $item->po_needed,
            ];
        }
    }
}
