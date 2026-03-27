<?php

namespace Modules\Pancake\Contracts;

use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\ParcelJourney;

interface NotifiesParcelJourney
{
    public function handle(Order $order, ParcelJourney $parcelJourney, string $psid, array $data): void;
}
