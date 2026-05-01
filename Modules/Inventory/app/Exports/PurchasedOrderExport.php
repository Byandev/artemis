<?php

namespace Modules\Inventory\Exports;

use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\Inventory\Models\PurchasedOrder;
use Spatie\QueryBuilder\QueryBuilder;

class PurchasedOrderExport implements FromGenerator, WithHeadings
{
    public function __construct(private QueryBuilder $query) {}

    public function headings(): array
    {
        return [
            'Issue Date',
            'Delivery No.',
            'Cust PO No.',
            'Control No.',
            'Status',
            'Item SKU',
            'Item Product',
            'Item Count',
            'Item Unit Amount',
            'Item Total Amount',
            'Order Subtotal',
            'Order Delivery Fee',
            'Order Total Amount',
        ];
    }

    public function generator(): \Generator
    {
        $orders = $this->query
            ->with(['items.inventoryItem.product'])
            ->lazy(200);

        foreach ($orders as $order) {
            $base = [
                $order->issue_date?->format('Y-m-d'),
                $order->delivery_no,
                $order->cust_po_no,
                $order->control_no,
                PurchasedOrder::STATUSES[$order->status] ?? 'Unknown',
            ];

            $subtotal = (float) $order->total_amount - (float) $order->delivery_fee;

            if ($order->items->isEmpty()) {
                yield array_merge($base, [null, null, null, null, null, $subtotal, $order->delivery_fee, $order->total_amount]);

                continue;
            }

            foreach ($order->items as $item) {
                yield array_merge($base, [
                    $item->inventoryItem?->sku,
                    $item->inventoryItem?->product?->name,
                    $item->count,
                    $item->amount,
                    $item->total_amount,
                    $subtotal,
                    $order->delivery_fee,
                    $order->total_amount,
                ]);
            }
        }
    }
}
