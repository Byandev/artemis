<?php

namespace Modules\Pancake\Jobs;

use App\Models\Page;
use App\Models\ParcelJourneyNotificationTemplate;
use App\Models\ShippingAddress;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\OrderItem;
use Modules\Pancake\Models\OrderPhoneNumberReport;
use Modules\Pancake\Models\ParcelJourney;
use Modules\Pancake\Models\ParcelJourneyNotification;
use Modules\Pancake\Models\PhoneNumberReport;

class SyncOrder implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Workspace $workspace, public Page $page, public array $data)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = $this->data;

        $insertedAtUtc = Carbon::createFromFormat('Y-m-d\TH:i:s.u', $order['inserted_at'], 'UTC');
        $insertedAt = $insertedAtUtc->setTimezone(config('app.timezone'));

        $confirmed_at = null;
        $returning_at = null;
        $returned_at = null;
        $delivered_at = null;
        $shipped_at = null;
        $conferrer_id = null;
        $confirmed_by = null;

        $confirmedHistory = collect($order['status_history'])->where('status', 1)->first();
        $shippedHistory = collect($order['status_history'])->where('status', 2)->first();
        $deliveredHistory = collect($order['status_history'])->where('status', 3)->first();
        $returningHistory = collect($order['status_history'])->where('status', 4)->first();
        $returnedHistory = collect($order['status_history'])->where('status', 5)->first();

        if ($confirmedHistory) {
            $confirmedAtUtc = Carbon::createFromFormat('Y-m-d\TH:i:s', $confirmedHistory['updated_at'], 'UTC');
            $confirmed_at = $confirmedAtUtc->setTimezone(config('app.timezone'))->toDateTimeString();

            $conferrer_id = $confirmedHistory['editor_fb'] ?: null;
            $confirmed_by = $confirmedHistory['editor_id'] ?: null;
        }

        if ($shippedHistory) {
            $shippedAtUtc = Carbon::createFromFormat('Y-m-d\TH:i:s', $shippedHistory['updated_at'], 'UTC');
            $shipped_at = $shippedAtUtc->setTimezone(config('app.timezone'))->toDateTimeString();
        }

        if ($deliveredHistory) {
            $deliveredAtUtc = Carbon::createFromFormat('Y-m-d\TH:i:s', $deliveredHistory['updated_at'], 'UTC');
            $delivered_at = $deliveredAtUtc->setTimezone(config('app.timezone'))->toDateTimeString();
        }

        if ($returningHistory) {
            $returningAtUtc = Carbon::createFromFormat('Y-m-d\TH:i:s', $returningHistory['updated_at'], 'UTC');
            $returning_at = $returningAtUtc->setTimezone(config('app.timezone'))->toDateTimeString();
        }

        if ($returnedHistory) {
            $returnedAtUtc = Carbon::createFromFormat('Y-m-d\TH:i:s', $returnedHistory['updated_at'], 'UTC');
            $returned_at = $returnedAtUtc->setTimezone(config('app.timezone'))->toDateTimeString();
        }

        $deliveryAttempts = null;
        $first_delivery_attempt = null;

        $savedOrder = Order::updateOrCreate([
            'order_number' => $order['id'],
            'shop_id' => $order['shop_id'],
            'workspace_id' => $this->workspace->id,
        ], [
            'page_id' => $order['page_id'],
            'status' => $order['status'],
            'status_name' => $order['status_name'],
            'total_amount' => $order['total_price'],
            'discount' => $order['total_discount'] ?: 0,
            'final_amount' => $order['total_price_after_sub_discount'],
            'ad_id' => $order['ad_id'] ?: null,
            'inserted_at' => $insertedAt->toDateTimeString(),
            'confirmed_at' => $confirmed_at,
            'fb_id' => $order['conversation_id'],
            'customer_id' => isset($order['customer']) ? $order['customer']['customer_id'] : null,
            'shipped_at' => $shipped_at,
            'delivered_at' => $delivered_at,
            'returning_at' => $returning_at,
            'returned_at' => $returned_at,
            'assignee_id' => isset($order['assigning_seller']) ? $order['assigning_seller']['fb_id'] : null,
            'last_editor_id' => isset($order['last_editor']) ? $order['last_editor']['fb_id'] : null,
            'customer_succeed_order_count' => $order['customer']['succeed_order_count'] ?: 0,
            'customer_returned_order_count' => $order['customer']['returned_order_count'] ?: 0,
            'conferrer_id' => $conferrer_id,
            'confirmed_by' => $confirmed_by,
        ]);

        if (isset($order['items'])) {
            foreach ($order['items'] as $item) {
                OrderItem::updateOrCreate([
                    'order_id' => $savedOrder->id,
                    'pancake_order_id' => $order['id'],
                    'pancake_id' => $item['id'],
                    'pancake_product_id' => $item['product_id'],
                    'pancake_variant_id' => $item['variation_id'],
                    'quantity' => $item['quantity'],
                    'name' => $item['variation_info']['display_id'],
                ]);
            }
        }

        if (isset($order['shipping_address'])) {
            ShippingAddress::updateOrCreate([
                'order_id' => $savedOrder->id,
            ], [
                'province_name' => $order['shipping_address']['province_name'],
                'district_name' => $order['shipping_address']['district_name'],
                'commune_name' => $order['shipping_address']['commune_name'],
                'address' => $order['shipping_address']['address'],
                'full_name' => $order['shipping_address']['full_name'],
                'full_address' => $order['shipping_address']['full_address'],
                'phone_number' => $order['shipping_address']['phone_number'],
            ]);
        }

        if (isset($order['partner'])) {
            if (isset($order['partner']['extend_code'])) {
                $tracking_code = $order['partner']['extend_code'];
                $parcel_status = $order['partner']['partner_status'];

                $savedOrder->update([
                    'tracking_code' => $tracking_code,
                    'parcel_status' => $parcel_status,
                ]);

                if (isset($order['partner']['extend_update'])) {
                    $isLatestUpdate = true;

                    $parcelJourneyHistory = collect($order['partner']['extend_update'])
                        ->map(function ($item) {
                            if ($item['note'] === '') {
                                $note = $item['status'];

                                if (str_contains($note, 'is sending')) {
                                    $item['status'] = 'On Delivery';
                                }

                                if (str_contains($note, 'send package')) {
                                    $item['status'] = 'Arrival';
                                }

                                $item['note'] = $note;
                            }

                            if (! isset($item['updated_at'])) {
                                $item['updated_at'] = $item['update_at'];
                            }

                            return $item;
                        })
                        ->toArray();

                    foreach ($parcelJourneyHistory as $update) {
                        if ($update['status'] == 'On Delivery') {
                            $deliveryAttempts = $deliveryAttempts + 1;

                            if ($first_delivery_attempt == null) {
                                $first_delivery_attempt = $update['updated_at'];
                            }
                        }

                        if (Carbon::parse($update['updated_at'])->isToday()) {
                            $parcelJourney = ParcelJourney::updateOrCreate([
                                'order_id' => $savedOrder->id,
                                'status' => $update['status'],
                                'note' => $update['note'],
                            ], [
                                'created_at' => $update['updated_at'],
                            ]);

                            if ($this->page->parcel_journey_enabled && $isLatestUpdate && $savedOrder->status == 2 && in_array($update['status'], ['On Delivery', 'Departure', 'Arrival'])) {
                                $isLatestUpdate = false;

                                if ($parcelJourney->notifications()->doesntExist()) {
                                    $this->sendParcelJourneyNotification($savedOrder, $parcelJourney);
                                }
                            }
                        }
                    }
                }
            }
        }

        if (isset($order['reports_by_phone'])) {
            foreach ($order['reports_by_phone'] as $phone => $report) {
                $orderFail = $report['order_fail'] ?? 0;
                $orderSuccess = $report['order_success'] ?? 0;
                $warning = $report['warning'] ?? 0;

                if ($orderFail > 0 || $orderSuccess > 0 || $warning > 0) {
                    $formatted_phone = '0'.substr($phone, -10);

                    PhoneNumberReport::updateOrCreate([
                        'phone_number' => $formatted_phone,
                    ], [
                        'order_fail' => $orderFail,
                        'order_success' => $orderSuccess,
                        'warning' => $warning,
                    ]);

                    OrderPhoneNumberReport::firstOrCreate([
                        'order_id' => $savedOrder->id,
                        'phone_number' => $formatted_phone,
                    ], [
                        'order_fail' => $orderFail,
                        'order_success' => $orderSuccess,
                        'warning' => $warning,
                    ]);
                }
            }
        }

        $savedOrder->update([
            'delivery_attempts' => $deliveryAttempts,
            'first_delivery_attempt' => $first_delivery_attempt,
        ]);
    }

    private function sendParcelJourneyNotification(Order $order, ParcelJourney $parcelJourney): void
    {
        $order->loadMissing(['shippingAddress', 'page']);

        [$page, $psid] = explode('_', $order->fb_id);
        $date = Carbon::parse($parcelJourney->created_at)->format('F d');

        $data = [
            'date' => $date,
            'page_name' => $order->page->name,
            'customer_name' => $order->shippingAddress->full_name,
            'tracking_code' => $order->tracking_code,
            'shipping_address' => $order->shippingAddress->full_address,
        ];

        if ($parcelJourney->status === 'Departure') {
            preg_match_all('/【(.*?)】/', $parcelJourney->note, $matches);

            $nextLocation = $matches[1][1];
            $data['next_location'] = $nextLocation;
            ParcelJourneyNotification::create([
                'order_id' => $order->id,
                'parcel_journey_id' => $parcelJourney->id,
                'message' => $this->renderMessage('sms', 'departure', 'customer', $data),
                'type' => 'sms',
                'receiver_name' => $order->shippingAddress->full_name,
                'receiver_identity' => $order->shippingAddress->phone_number,
            ]);

            ParcelJourneyNotification::create([
                'order_id' => $order->id,
                'parcel_journey_id' => $parcelJourney->id,
                'message' => $this->renderMessage('chat', 'departure', 'customer', $data),
                'type' => 'chat',
                'receiver_name' => $order->shippingAddress->full_name,
                'receiver_identity' => $psid,
            ]);
        }

        if ($parcelJourney->status === 'Arrival') {
            preg_match_all('/【(.*?)】/', $parcelJourney->note, $matches);
            $currentLocation = $matches[1][0];
            $data['current_location'] = $currentLocation;

            ParcelJourneyNotification::create([
                'order_id' => $order->id,
                'parcel_journey_id' => $parcelJourney->id,
                'message' => $this->renderMessage('sms', 'arrival', 'customer', $data),
                'type' => 'sms',
                'receiver_name' => $order->shippingAddress->full_name,
                'receiver_identity' => $order->shippingAddress->phone_number,
            ]);

            ParcelJourneyNotification::create([
                'order_id' => $order->id,
                'parcel_journey_id' => $parcelJourney->id,
                'message' => $this->renderMessage('chat', 'arrival', 'customer', $data),
                'type' => 'chat',
                'receiver_name' => $order->shippingAddress->full_name,
                'receiver_identity' => $psid,
            ]);
        }

        if ($parcelJourney->status === 'On Delivery') {
            $message = $parcelJourney->note;

            if (preg_match_all('/【(.*?)】/', $message, $matches) && isset($matches[1][1])) {
                $sprinterInfo = $matches[1][1]; // Second 【...】

                // Split name and mobile
                [$rider_name, $mobile] = array_map('trim', explode(':', $sprinterInfo));
                $last10 = substr(preg_replace('/\D/', '', $mobile), -10);

                $rider_mobile = "0{$last10}";
                $data['rider_name'] = $rider_name;
                $data['rider_mobile'] = $rider_mobile;

                OrderForDelivery::firstOrCreate([
                    'order_id' => $order->id,
                    'page_id' => $order->page_id,
                    'shop_id' => $order->shop_id,
                    'rider_phone' => $rider_mobile,
                    'rider_name' => $rider_name,
                    'workspace_id' => $order->workspace_id,
                    'conferrer_id' => $order->confirmed_by,
                    'delivery_date' => Carbon::parse($parcelJourney->created_at)->format('Y-m-d'),
                ], [
                    'status' => 'PENDING',
                    'created_at' => $parcelJourney->created_at,
                ]);

                ParcelJourneyNotification::create([
                    'order_id' => $order->id,
                    'parcel_journey_id' => $parcelJourney->id,
                    'message' => $this->renderMessage('sms', 'for-delivery', 'rider', $data),
                    'type' => 'sms',
                    'receiver_name' => $rider_name,
                    'receiver_identity' => $rider_mobile,
                ]);

                ParcelJourneyNotification::create([
                    'order_id' => $order->id,
                    'parcel_journey_id' => $parcelJourney->id,
                    'message' => $this->renderMessage('sms', 'for-delivery', 'customer', $data),
                    'type' => 'sms',
                    'receiver_name' => $order->shippingAddress->full_name,
                    'receiver_identity' => $order->shippingAddress->phone_number,
                ]);

                ParcelJourneyNotification::create([
                    'order_id' => $order->id,
                    'parcel_journey_id' => $parcelJourney->id,
                    'message' => $this->renderMessage('chat', 'for-delivery', 'customer', $data),
                    'type' => 'chat',
                    'receiver_name' => $order->shippingAddress->full_name,
                    'receiver_identity' => $psid,
                ]);
            }
        }
    }

    protected function getTemplateFor($type, $activity, $receiver)
    {
        $savedTemplate = $this->workspace->parcelJourneyNotificationTemplates()
            ->where('type', $type)
            ->where('receiver', $receiver)
            ->where('activity', $activity)
            ->first();

        if ($savedTemplate) {
            return $savedTemplate->message;
        }

        return ParcelJourneyNotificationTemplate::defaults()->where('type', $type)->where('type', $type)
            ->where('receiver', $receiver)
            ->where('activity', $activity)
            ->first()
            ?->message ?? '';
    }

    protected function renderMessage($type, $activity, $receiver, array $data): string
    {
        $template = $this->getTemplateFor($type, $activity, $receiver);

        return preg_replace_callback('/{{\s*(\w+)\s*}}/', function ($matches) use ($data) {
            $key = $matches[1]; // the variable name inside {{}}

            // Return the value if it exists, otherwise keep the original {{variable}}
            return array_key_exists($key, $data) ? (string) $data[$key] : $matches[0];
        }, $template);
    }
}
