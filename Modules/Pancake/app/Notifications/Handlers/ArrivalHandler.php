<?php

namespace Modules\Pancake\Notifications\Handlers;

use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\ParcelJourney;

class ArrivalHandler extends BaseNotificationHandler
{
    public function handle(Order $order, ParcelJourney $parcelJourney, string $psid, array $data): void
    {
        if (! $this->page->parcel_journey_enabled) return;

        preg_match_all('/【(.*?)】/', $parcelJourney->note, $matches);

        $data['current_location'] = $matches[1][0] ?? '';

        $this->notifyCustomer($order, $parcelJourney, $psid, 'arrival', $data);
    }
}
