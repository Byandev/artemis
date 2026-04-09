<?php

namespace Modules\Pancake\Actions;

use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderItem;

class SyncOrderItemsAction
{
    public function execute(Order $savedOrder, array $order): void
    {
        if (empty($order['items'])) {
            return;
        }

        foreach ($order['items'] as $item) {
            OrderItem::updateOrCreate(
                [
                    'order_id' => $savedOrder->id,
                    'pancake_order_id' => $order['id'],
                    'pancake_id' => $item['id'],
                    'pancake_product_id' => $item['product_id'],
                    'pancake_variant_id' => $item['variation_id'],
                ],
                [
                    'quantity' => $item['quantity'],
                    'name' => $item['variation_info']['display_id'],
                ]
            );
        }
    }
}
