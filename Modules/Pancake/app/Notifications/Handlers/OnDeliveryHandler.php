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
            // Idempotent on the (journey, type, recipient) tuple — see
            // BaseNotificationHandler::notifyCustomer. The rider SMS shares the
            // 'sms' type with the customer SMS but has a distinct recipient, so
            // it never collides with it.
            ParcelJourneyNotification::firstOrCreate([
                'parcel_journey_id' => $parcelJourney->id,
                'type' => 'sms',
                'receiver_identity' => $riderMobile,
            ], [
                'order_id' => $order->id,
                'receiver_name' => $riderName,
                'message' => $riderMessage,
            ]);
        }

        $this->notifyCustomer($order, $parcelJourney, $psid, 'for-delivery', $data);
    }
}
