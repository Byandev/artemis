<?php

namespace Modules\Pancake\Actions;

use App\Models\Page;
use App\Models\Workspace;
use App\Support\RmoAutoAssign;
use Carbon\Carbon;
use Modules\GencysERP\Support\RmoUpsellStamper;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\ParcelJourney;
use Modules\Pancake\Notifications\ParcelJourneyNotifier;
use Modules\Pancake\Support\JourneyUpdateNormalizer;
use Modules\Pancake\Support\MessageRenderer;

readonly class SyncParcelTrackingAction
{
    public function __construct(
        private JourneyUpdateNormalizer $normalizer,
        private MessageRenderer $renderer,
        private RmoUpsellStamper $upsellStamper,
    ) {}

    public function execute(Order $savedOrder, array $order, Page $page, Workspace $workspace): void
    {
        if (empty($order['partner']['extend_code'])) {
            return;
        }

        $savedOrder->update([
            'tracking_code' => $order['partner']['extend_code'],
            'parcel_status' => $order['partner']['partner_status'],
        ]);

        if (empty($order['partner']['extend_update'])) {
            return;
        }

        $notifier = new ParcelJourneyNotifier($page, $workspace, $this->renderer);

        // Sort oldest-first: ensures delivery_attempts count is sequential
        // and $latestNotifiable ends up being the most recent eligible entry.
        $updates = collect($order['partner']['extend_update'])
            ->map(fn ($item) => $this->normalizer->normalize($item))
            ->filter(fn ($item) => in_array($item['status'], ['On Delivery', 'Arrival', 'Departure']))
            ->filter(fn ($item) => $item['status'] === 'On Delivery'
                || Carbon::parse($item['updated_at'])->isToday())
            ->values();

        $deliveryAttempts = 0;
        $firstDeliveryAttempt = null;
        $latestNotifiable = null;

        foreach ($updates as $update) {
            if ($update['status'] === 'On Delivery') {
                $deliveryAttempts++;
                $firstDeliveryAttempt ??= $update['updated_at'];
            }

            // Normalize to the stored second-precision format so the lookup matches
            // the value Eloquent persists. Without this, a raw API string (ISO 8601,
            // tz offset, microseconds) never matches the stored datetime and a
            // duplicate row is created on every sync.
            $createdAt = Carbon::parse($update['updated_at'])->format('Y-m-d H:i:s');

            $journey = ParcelJourney::updateOrCreate(
                [
                    'order_id' => $savedOrder->id,
                    'created_at' => $createdAt,
                ],
                [
                    'status' => $update['status'],
                    'note' => $update['note'],
                    'rider_name' => $update['rider_name'],
                    'rider_mobile' => $update['rider_mobile'],
                ]
            );

            if ($update['status'] === 'On Delivery' && $update['rider_name'] && $update['rider_mobile'] && Carbon::parse($update['updated_at'])->isToday()) {
                $shippingAddress = $savedOrder->shippingAddress;

                $order_for_delivery = OrderForDelivery::firstOrCreate(
                    [
                        'order_id' => $savedOrder->id,
                        'page_id' => $savedOrder->page_id,
                        'shop_id' => $savedOrder->shop_id,
                        'workspace_id' => $savedOrder->workspace_id,
                        'rider_name' => $update['rider_name'],
                        'rider_phone' => $update['rider_mobile'],
                        'delivery_date' => Carbon::parse($update['updated_at'])->format('Y-m-d'),
                    ],
                    [
                        'conferrer_id' => $savedOrder->confirmed_by,
                        'status' => 'PENDING',
                        'customer_name' => $shippingAddress?->full_name,
                        'customer_phone' => $shippingAddress?->phone_number,
                        'created_at' => $update['updated_at'],
                    ]
                );

                // Hand the row to a CSR straight away when the workspace has
                // auto-assignment on. Called for every touched row, not just a
                // freshly created one: fetch-orders keeps re-touching a parcel
                // while it is out, so a row that predates the switch — or was
                // un-assigned since — gets picked up on a later pass. A row that
                // already has an assignee costs nothing, it returns before any
                // query, and a manual assignee is never overwritten.
                RmoAutoAssign::assignOne($workspace, $order_for_delivery);

                $parcel_status = $savedOrder->parcel_status;

                if ($savedOrder->status == 3) {
                    $parcel_status = 'delivered';
                } elseif ($savedOrder->status == 4) {
                    $parcel_status = 'returning';
                }

                $order_for_delivery->update([
                    'parcel_status' => $parcel_status,
                ]);

                // The RMO row exists now, so pull the Gencys upsell figures onto
                // it in the same pass. Fetch-orders runs on a schedule and keeps
                // re-touching this row while the parcel is out, so a row created
                // before its Gencys order landed gets stamped on a later pass —
                // no separate catch-up sweep needed. One indexed lookup, and only
                // for a Gencys workspace row still missing a value.
                if ($workspace->is_gencys_partner) {
                    $this->upsellStamper->stampRow($order_for_delivery);
                }
            }

            if ($this->isNotifiable($savedOrder, $journey) && $page->parcel_journey_enabled) {
                $latestNotifiable = $journey;

                $notifier->notify($savedOrder, $latestNotifiable);
            }
        }

        $savedOrder->update([
            'delivery_attempts' => $deliveryAttempts ?: null,
            'first_delivery_attempt' => $firstDeliveryAttempt,
        ]);
    }

    private function isNotifiable(Order $savedOrder, ParcelJourney $journey): bool
    {
        if ($savedOrder->status !== 2
            || ! in_array($journey->status, ['On Delivery', 'Departure', 'Arrival'], true)
            || ! Carbon::parse($journey->created_at)->isToday()
            || $journey->notifications()->exists()) {
            return false;
        }

        // Don't notify for a journey that's older than (or equal to) one we've
        // already sent a notification for on this order.
        $lastNotifiedAt = ParcelJourney::where('order_id', $savedOrder->id)
            ->whereHas('notifications')
            ->max('created_at');

        return ! $lastNotifiedAt || Carbon::parse($journey->created_at)->gt(Carbon::parse($lastNotifiedAt));
    }
}
