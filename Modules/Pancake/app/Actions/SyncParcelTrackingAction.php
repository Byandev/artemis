<?php

namespace Modules\Pancake\Actions;

use App\Models\Page;
use App\Models\Workspace;
use Carbon\Carbon;
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

            $journey = ParcelJourney::updateOrCreate(
                [
                    'order_id' => $savedOrder->id,
                    'status' => $update['status'],
                    'note' => $update['note'],
                    'created_at' => $update['updated_at'],
                ],
                [
                    'rider_name' => $update['rider_name'],
                    'rider_mobile' => $update['rider_mobile'],
                ]
            );

            if ($update['status'] === 'On Delivery' && $update['rider_name'] && $update['rider_mobile'] && Carbon::parse($update['updated_at'])->isToday()) {
                OrderForDelivery::firstOrCreate(
                    [
                        'order_id'      => $savedOrder->id,
                        'page_id'       => $savedOrder->page_id,
                        'shop_id'       => $savedOrder->shop_id,
                        'workspace_id'  => $savedOrder->workspace_id,
                        'rider_name'    => $update['rider_name'],
                        'rider_phone'   => $update['rider_mobile'],
                        'delivery_date' => Carbon::parse($update['updated_at'])->format('Y-m-d'),
                    ],
                    [
                        'conferrer_id' => $savedOrder->confirmed_by,
                        'status'       => 'PENDING',
                        'created_at'   => $update['updated_at'],
                    ]
                );
            }

            if ($this->isNotifiable($savedOrder, $journey)) {
                $latestNotifiable = $journey;
            }
        }

        if ($latestNotifiable) {
            $notifier->notify($savedOrder, $latestNotifiable);
        }

        $savedOrder->update([
            'delivery_attempts' => $deliveryAttempts ?: null,
            'first_delivery_attempt' => $firstDeliveryAttempt,
        ]);
    }

    private function isNotifiable(Order $savedOrder, ParcelJourney $journey): bool
    {
        return $savedOrder->status === 2
            && in_array($journey->status, ['On Delivery', 'Departure', 'Arrival'], true)
            && Carbon::parse($journey->created_at)->isToday()
            && $journey->notifications()->doesntExist();
    }
}
