<?php

namespace Modules\Pancake\Notifications\Handlers;

use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\ParcelJourney;

class DepartureHandler extends BaseNotificationHandler
{
    public function handle(Order $order, ParcelJourney $parcelJourney, string $psid, array $data): void
    {
        if (! $this->page->parcel_journey_enabled) return;

        preg_match_all('/【(.*?)】/', $parcelJourney->note, $matches);

        $data['next_location'] = $matches[1][1] ?? '';

        $this->notifyCustomer($order, $parcelJourney, $psid, 'departure', $data);
    }
}
