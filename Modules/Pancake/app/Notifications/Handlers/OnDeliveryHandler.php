<?php

namespace Modules\Pancake\Notifications\Handlers;

use Carbon\Carbon;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\ParcelJourney;
use Modules\Pancake\Models\ParcelJourneyNotification;

class OnDeliveryHandler extends BaseNotificationHandler
{
    public function handle(Order $order, ParcelJourney $parcelJourney, string $psid, array $data): void
    {
        $riderName = $parcelJourney->rider_name;
        $riderMobile = $parcelJourney->rider_mobile;

        if (! $riderName || ! $riderMobile) {
            return;
        }

        $this->createOrderForDelivery($order, $parcelJourney, $riderName, $riderMobile);

        if (! $this->page->parcel_journey_enabled) {
            return;
        }

        $data = array_merge($data, ['rider_name' => $riderName, 'rider_mobile' => $riderMobile]);

        ParcelJourneyNotification::create([
            'order_id' => $order->id,
            'parcel_journey_id' => $parcelJourney->id,
            'type' => 'sms',
            'receiver_name' => $riderName,
            'receiver_identity' => $riderMobile,
            'message' => $this->renderer->render($this->workspace, 'sms', 'for-delivery', 'rider', $data),
        ]);

        $this->notifyCustomer($order, $parcelJourney, $psid, 'for-delivery', $data);
    }

    private function createOrderForDelivery(
        Order $order,
        ParcelJourney $parcelJourney,
        string $riderName,
        string $riderMobile,
    ): void {
        OrderForDelivery::firstOrCreate(
            [
                'order_id' => $order->id,
                'page_id' => $order->page_id,
                'shop_id' => $order->shop_id,
                'rider_phone' => $riderMobile,
                'rider_name' => $riderName,
                'workspace_id' => $order->workspace_id,
                'conferrer_id' => $order->confirmed_by,
                'delivery_date' => Carbon::parse($parcelJourney->created_at)->format('Y-m-d'),
            ],
            [
                'status' => 'PENDING',
                'created_at' => $parcelJourney->created_at,
            ]
        );
    }
}
