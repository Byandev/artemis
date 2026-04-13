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
}
