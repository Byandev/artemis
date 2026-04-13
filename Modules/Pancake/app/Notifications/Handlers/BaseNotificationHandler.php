<?php

namespace Modules\Pancake\Notifications\Handlers;

use App\Models\Page;
use App\Models\Workspace;
use Modules\Pancake\Contracts\NotifiesParcelJourney;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\ParcelJourney;
use Modules\Pancake\Models\ParcelJourneyNotification;
use Modules\Pancake\Support\MessageRenderer;

abstract class BaseNotificationHandler implements NotifiesParcelJourney
{
    public function __construct(
        protected readonly Page $page,
        protected readonly Workspace $workspace,
        protected readonly MessageRenderer $renderer,
    ) {}

    abstract public function handle(Order $order, ParcelJourney $parcelJourney, string $psid, array $data): void;

    /**
     * Create both SMS and chat notifications for the customer.
     */
    protected function notifyCustomer(
        Order $order,
        ParcelJourney $parcelJourney,
        string $psid,
        string $activity,
        array $data,
    ): void {
        ParcelJourneyNotification::create([
            'order_id' => $order->id,
            'parcel_journey_id' => $parcelJourney->id,
            'type' => 'sms',
            'receiver_name' => $order->shippingAddress->full_name,
            'receiver_identity' => $order->shippingAddress->phone_number,
            'message' => $this->renderer->render($this->workspace, 'sms', $activity, 'customer', $data),
        ]);

        ParcelJourneyNotification::create([
            'order_id' => $order->id,
            'parcel_journey_id' => $parcelJourney->id,
            'type' => 'chat',
            'receiver_name' => $order->shippingAddress->full_name,
            'receiver_identity' => $psid,
            'message' => $this->renderer->render($this->workspace, 'chat', $activity, 'customer', $data),
        ]);
    }
}
