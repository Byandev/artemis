<?php

namespace Modules\Pancake\Notifications\Handlers;

use Modules\Pancake\Models\Order;
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

        $riderMessage = $this->renderer->render($this->workspace, 'sms', 'for-delivery', 'rider', $data);

        if ($riderMessage !== null) {
            ParcelJourneyNotification::create([
                'order_id' => $order->id,
                'parcel_journey_id' => $parcelJourney->id,
                'type' => 'sms',
                'receiver_name' => $riderName,
                'receiver_identity' => $riderMobile,
                'message' => $riderMessage,
            ]);
        }

        $this->notifyCustomer($order, $parcelJourney, $psid, 'for-delivery', $data);
    }
}
