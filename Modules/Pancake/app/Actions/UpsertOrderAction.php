<?php

namespace Modules\Pancake\Actions;

use App\Models\Workspace;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Support\OrderTimestampResolver;

class UpsertOrderAction
{
    public function __construct(private readonly OrderTimestampResolver $timestampResolver) {}

    public function execute(Workspace $workspace, array $order): Order
    {
        return Order::updateOrCreate(
            [
                'order_number' => $order['id'],
                'shop_id' => $order['shop_id'],
                'workspace_id' => $workspace->id,
            ],
            [
                'page_id' => $order['page_id'],
                'status' => $order['status'],
                'status_name' => $order['status_name'],
                'total_amount' => $order['total_price'],
                'discount' => $order['total_discount'] ?? 0,
                'final_amount' => $order['total_price_after_sub_discount'],
                'ad_id' => $order['ad_id'] ?: null,
                'fb_id' => $order['conversation_id'],
                'customer_id' => $order['customer']['customer_id'] ?? null,
                'assignee_id' => $order['assigning_seller']['fb_id'] ?? null,
                'last_editor_id' => $order['last_editor']['fb_id'] ?? null,
                'customer_succeed_order_count' => $order['customer']['succeed_order_count'] ?? 0,
                'customer_returned_order_count' => $order['customer']['returned_order_count'] ?? 0,
                ...$this->timestampResolver->resolve($order),
            ]
        );
    }
}
