<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ParcelJourney;
use App\Models\ShippingAddress;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncOrder implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Workspace $workspace, public array $data)
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
        $delivered_at = null;
        $shipped_at = null;

        $confirmedHistory = collect($order['status_history'])->where('status', 1)->first();
        $shippedHistory = collect($order['status_history'])->where('status', 2)->first();
        $deliveredHistory = collect($order['status_history'])->where('status', 3)->first();
        $returningHistory = collect($order['status_history'])->where('status', 4)->first();

        if ($confirmedHistory) {
            $confirmedAtUtc = Carbon::createFromFormat('Y-m-d\TH:i:s', $confirmedHistory['updated_at'], 'UTC');
            $confirmed_at = $confirmedAtUtc->setTimezone(config('app.timezone'))->toDateTimeString();
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

        $deliveryAttempts = null;
        $first_delivery_attempt = null;
        $tracking_code = null;
        $parcel_status = null;

        $savedOrder = Order::updateOrCreate([
            'order_number' => $order['id'],
            'shop_id' => $order['shop_id'],
            'workspace_id' => $this->workspace->id,
        ], [
            'page_id' => $order['page_id'],
            'status' => $order['status'],
            'status_name' => $order['status_name'],
            'total_amount' => $order['total_price'],
            'ad_id' => $order['ad_id'],
            'inserted_at' => $insertedAt->toDateTimeString(),
            'confirmed_at' => $confirmed_at,
            'fb_id' => $order['conversation_id'],
            'shipped_at' => $shipped_at,
            'delivered_at' => $delivered_at,
            'returning_at' => $returning_at,
        ]);

        if (isset($order['partner'])) {
            if (isset($order['partner']['extend_code'])) {
                $tracking_code = $order['partner']['extend_code'];
                $parcel_status = $order['partner']['partner_status'];

                if (isset($order['partner']['extend_update'])) {
                    foreach ($order['partner']['extend_update'] as $update) {
                        if ($update['status'] == 'On Delivery') {
                            $deliveryAttempts = $deliveryAttempts + 1;

                            if ($first_delivery_attempt == null) {
                                $first_delivery_attempt = $update['updated_at'];
                            }
                        }

                        ParcelJourney::updateOrCreate([
                            'order_id' => $savedOrder->id,
                            'status' => $update['status'],
                            'note' => $update['note'],
                        ], [
                            'created_at' => $update['updated_at'],
                        ]);
                    }
                }
            }
        }

        $savedOrder->update([
            'tracking_code' => $tracking_code,
            'parcel_status' => $parcel_status,
            'delivery_attempts' => $deliveryAttempts,
            'first_delivery_attempt' => $first_delivery_attempt,
        ]);

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
    }
}
