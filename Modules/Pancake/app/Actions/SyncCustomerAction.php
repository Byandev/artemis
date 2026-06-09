<?php

namespace Modules\Pancake\Actions;

use Modules\Pancake\Models\Customer;
use Modules\Pancake\Models\Order;

class SyncCustomerAction
{
    public function execute(Order $savedOrder, array $order): void
    {
        $customer = $order['customer'] ?? null;

        if (empty($customer['id'])) {
            return;
        }

        Customer::updateOrCreate(
            ['id' => $customer['id']],
            [
                'shop_id' => $customer['shop_id'],
                'customer_id' => $customer['customer_id'],
                'name' => $customer['name'],
                'fb_id' => $customer['fb_id'],
                'returned_order_count' => $customer['returned_order_count'] ?? 0,
                'success_order_count' => $customer['succeed_order_count'] ?? 0,
                'gender' => $customer['gender'] ?? null,
                'date_of_birth' => $customer['date_of_birth'] ?? null,
                'purchased_amount' => $customer['purchased_amount'] ?? 0,
                'created_at' => $customer['inserted_at'],
                'updated_at' => $customer['updated_at'],
            ]
        );
    }
}
