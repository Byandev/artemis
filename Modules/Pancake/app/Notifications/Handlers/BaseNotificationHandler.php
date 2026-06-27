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
        $smsMessage = $this->renderer->render($this->workspace, 'sms', $activity, 'customer', $data);

        if ($smsMessage !== null) {
            // firstOrCreate keyed on the unique (journey, type, recipient) tuple
            // makes this idempotent: concurrent or retried syncs collapse to a
            // single row, so the model's `created` hook dispatches exactly one
            // send. The DB unique index backstops the race the select can't.
            ParcelJourneyNotification::firstOrCreate([
                'parcel_journey_id' => $parcelJourney->id,
                'type' => 'sms',
                'receiver_identity' => $order->shippingAddress->phone_number,
            ], [
                'order_id' => $order->id,
                'receiver_name' => $order->shippingAddress->full_name,
                'message' => $smsMessage,
            ]);
        }

        $chatMessage = $this->renderer->render($this->workspace, 'chat', $activity, 'customer', $data);

        // Webcake orders have no Messenger conversation (blank psid), so there is no
        // chat recipient to send to — skip the chat notification entirely for them.
        if ($chatMessage !== null && $psid !== '') {
            ParcelJourneyNotification::firstOrCreate([
                'parcel_journey_id' => $parcelJourney->id,
                'type' => 'chat',
                'receiver_identity' => $psid,
            ], [
                'order_id' => $order->id,
                'receiver_name' => $order->shippingAddress->full_name,
                'message' => $chatMessage,
            ]);
        }
    }
}
