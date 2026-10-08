<?php

namespace Modules\Pancake\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Modules\Pancake\Exceptions\OrderAddressPushFailed;
use Modules\Pancake\Models\Commune;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Services\Pancake;
use Modules\Pancake\Support\PancakeShippingAddress;

/**
 * Write a reviewed address — a commune (which fixes its district and province)
 * plus the address line — to the order in Pancake, then to our own copy so the
 * Orders list shows it before the next sync comes round.
 */
class PushOrderAddressAction
{
    /**
     * @return array<string, mixed> the shipping_address sent to Pancake
     *
     * @throws OrderAddressPushFailed
     */
    public function execute(Order $order, Commune $commune, string $address): array
    {
        $order->loadMissing(['shop:id,pos_token', 'shippingAddress']);
        $commune->loadMissing('district.province');

        if (blank($order->shop?->pos_token)) {
            throw OrderAddressPushFailed::noPosToken();
        }

        $shippingAddress = PancakeShippingAddress::build(
            [],
            $address,
            $commune->province_id,
            $commune->district_id,
            $commune->id,
        );

        try {
            $response = (new Pancake($order->shop->id, $order->shop->pos_token))
                ->updateOrder((string) $order->order_number, ['shipping_address' => $shippingAddress]);
        } catch (ConnectionException $e) {
            throw OrderAddressPushFailed::unreachable();
        } catch (RequestException $e) {
            Log::warning('Push order address: Pancake refused the update.', [
                'order_id' => $order->id,
                'status' => $e->response->status(),
                'body' => mb_substr($e->response->body(), 0, 500),
            ]);

            throw OrderAddressPushFailed::refused();
        }

        // A 200 can still carry success: false.
        if (($response['success'] ?? true) === false) {
            Log::warning('Push order address: Pancake answered success=false.', [
                'order_id' => $order->id,
                'body' => mb_substr(json_encode($response), 0, 500),
            ]);

            throw OrderAddressPushFailed::refused();
        }

        $order->shippingAddress?->update([
            'address' => $address,
            'province_id' => $commune->province_id,
            'district_id' => $commune->district_id,
            'commune_id' => $commune->id,
            'province_name' => $commune->district->province->name,
            'district_name' => $commune->district->name,
            'commune_name' => $commune->name,
            'full_address' => implode(', ', [$address, $commune->name, $commune->district->name, $commune->district->province->name]),
        ]);

        return $shippingAddress;
    }
}
