<?php

namespace Modules\Pancake\Actions;

use App\Models\ShippingAddress;
use Modules\Pancake\Models\Order;

class SyncShippingAddressAction
{
    public function execute(Order $savedOrder, array $order): void
    {
        if (empty($order['shipping_address'])) {
            return;
        }

        $address = $order['shipping_address'];

        ShippingAddress::updateOrCreate(
            ['order_id' => $savedOrder->id],
            [
                'province_name' => $address['province_name'],
                'district_name' => $address['district_name'],
                'commune_name' => $address['commune_name'],
                'address' => $address['address'],
                'full_name' => $address['full_name'],
                'full_address' => $address['full_address'],
                'phone_number' => $address['phone_number'],
                'province_id' => $address['province_id'] ?? null,
                'new_province_id' => $address['new_province_id'] ?? null,
                'district_id' => $address['district_id'] ?? null,
                'commune_id' => $address['commune_id'] ?? null,
            ]
        );
    }
}
