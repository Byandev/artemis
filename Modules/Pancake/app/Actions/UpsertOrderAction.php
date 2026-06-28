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
        $divisor = $this->amountDivisor($order['order_currency'] ?? null);

        return Order::updateOrCreate(
            [
                'order_number' => $order['id'],
                'shop_id' => $order['shop_id'],
                'workspace_id' => $workspace->id,
            ],
            [
                'page_id' => $order['page_id'] ?? null,
                'order_source' => $order['order_sources'] ?? null,
                'order_source_name' => $order['order_sources_name'] ?? null,
                'status' => $order['status'],
                'status_name' => $order['status_name'],
                'total_amount' => $order['total_price'] / $divisor,
                'discount' => ($order['total_discount'] ?? 0) / $divisor,
                'final_amount' => $order['total_price_after_sub_discount'] / $divisor,
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

    /**
     * Pancake sometimes reports amounts in a scaled currency where the trailing
     * number is the scale factor (e.g. "PHP100" means values are multiplied by
     * 100). Return the divisor needed to normalise them back to the base
     * currency: "PHP" => 1, "PHP100" => 100.
     */
    private function amountDivisor(?string $currency): int
    {
        if ($currency === null) {
            return 1;
        }

        preg_match('/(\d+)$/', $currency, $matches);

        return isset($matches[1]) ? max((int) $matches[1], 1) : 1;
    }
}
