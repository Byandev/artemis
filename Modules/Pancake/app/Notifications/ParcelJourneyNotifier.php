<?php

namespace Modules\Pancake\Notifications;

use App\Models\Page;
use App\Models\Workspace;
use Carbon\Carbon;
use Modules\Pancake\Contracts\NotifiesParcelJourney;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\ParcelJourney;
use Modules\Pancake\Notifications\Handlers\ArrivalHandler;
use Modules\Pancake\Notifications\Handlers\DepartureHandler;
use Modules\Pancake\Notifications\Handlers\OnDeliveryHandler;
use Modules\Pancake\Support\MessageRenderer;

class ParcelJourneyNotifier
{
    public function __construct(
        private readonly Page $page,
        private readonly Workspace $workspace,
        private readonly MessageRenderer $renderer,
    ) {}

    public function notify(Order $order, ParcelJourney $parcelJourney): void
    {
        // Webcake orders have no Messenger conversation, so fb_id can be null/blank.
        // The customer SMS keys off the shipping phone, not the psid, so a missing psid
        // only means there's no chat recipient — it must not break the SMS path.
        $psid = $order->fb_id ? (explode('_', $order->fb_id)[1] ?? '') : '';

        $data = $this->buildData($order, $parcelJourney);
        $handler = $this->resolveHandler($parcelJourney->status);

        $handler?->handle($order, $parcelJourney, $psid, $data);
    }

    private function buildData(Order $order, ParcelJourney $parcelJourney): array
    {
        $data = [
            'date' => Carbon::parse($parcelJourney->created_at)->format('F d'),
            'tracking_code' => $order->tracking_code,
        ];

        if ($this->page->parcel_journey_enabled) {
            $order->loadMissing(['shippingAddress', 'page']);

            $data = array_merge($data, [
                'page_name' => $order->page->name,
                'customer_name' => $order->shippingAddress?->full_name,
                'shipping_address' => $order->shippingAddress?->full_address,
                'province' => $order->shippingAddress?->province_name,
                'city' => $order->shippingAddress?->district_name,
                'barangay' => $order->shippingAddress?->commune_name,
            ]);
        }

        return $data;
    }

    private function resolveHandler(string $status): ?NotifiesParcelJourney
    {
        return match ($status) {
            'Departure' => new DepartureHandler($this->page, $this->workspace, $this->renderer),
            'Arrival' => new ArrivalHandler($this->page, $this->workspace, $this->renderer),
            'On Delivery' => new OnDeliveryHandler($this->page, $this->workspace, $this->renderer),
            default => null,
        };
    }
}
