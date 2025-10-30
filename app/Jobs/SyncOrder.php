<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ParcelJourney;
use App\Models\ParcelJourneyNotification;
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

                    foreach ($order['partner']['extend_update'] as $update) {
                        if ($update['status'] == 'On Delivery') {
                            $deliveryAttempts = $deliveryAttempts + 1;

                            if ($first_delivery_attempt == null) {
                                $first_delivery_attempt = $update['updated_at'];
                            }
                        }

                        $parcelJourney = ParcelJourney::updateOrCreate([
                            'order_id' => $savedOrder->id,
                            'status' => $update['status'],
                            'note' => $update['note'],
                        ], [
                            'created_at' => $update['updated_at'],
                        ]);

                        if ($isLatestUpdate && $savedOrder->status == 2 && in_array($parcelJourney->status, ['On Delivery', 'Departure', 'Arrival']) && Carbon::parse($parcelJourney->created_at)->isToday()) {
                            $isLatestUpdate = false;

                            if ($parcelJourney->notifications()->doesntExist()) {
                                $this->sendParcelJourneyNotification($savedOrder, $parcelJourney);
                            }
                        }
                    }
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

        if ($parcelJourney->status === 'Departure') {
            preg_match_all('/【(.*?)】/', $parcelJourney->note, $matches);

            $nextLocation = $matches[1][1];
            $message = "{$order->shippingAddress->full_name}, update lang po sa parcel nyo from JNT.\n\nAs of {$date}, papunta na po ito sa {$nextLocation}.\nDadaan muna ito sa warehouse na ito bilang bahagi ng ruta papunta sa inyong address: {$order->shippingAddress->full_address}.\n\nMaraming salamat sa tiwala!\n\n{$order->page->name}";

            ParcelJourneyNotification::create([
                'order_id' => $order->id,
                'parcel_journey_id' => $parcelJourney->id,
                'message' => $message,
                'type' => 'sms',
                'receiver_name' => $order->shippingAddress->full_name,
                'receiver_identity' => $order->shippingAddress->phone_number,
            ]);

            ParcelJourneyNotification::create([
                'order_id' => $order->id,
                'parcel_journey_id' => $parcelJourney->id,
                'message' => $message,
                'type' => 'chat',
                'receiver_name' => $order->shippingAddress->full_name,
                'receiver_identity' => $psid,
            ]);
        }

        if ($parcelJourney->status === 'Arrival') {
            preg_match_all('/【(.*?)】/', $parcelJourney->note, $matches);
            $currentLocation = $matches[1][0];
            $message = "{$order->shippingAddress->full_name}, update lang po sa parcel nyo from JNT.\n\nAs of {$date}, nakarating na po ito sa {$currentLocation}.\nDadaan muna ito sa warehouse na ito bilang bahagi ng ruta papunta sa inyong address: {$order->shippingAddress->full_address}.\n\nMaraming salamat sa tiwala!\n\n{$order->page->name}";

            ParcelJourneyNotification::create([
                'order_id' => $order->id,
                'parcel_journey_id' => $parcelJourney->id,
                'message' => $message,
                'type' => 'sms',
                'receiver_name' => $order->shippingAddress->full_name,
                'receiver_identity' => $order->shippingAddress->phone_number,
            ]);

            ParcelJourneyNotification::create([
                'order_id' => $order->id,
                'parcel_journey_id' => $parcelJourney->id,
                'message' => $message,
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
                $rider_message = "Boss $rider_name, Ito po ang seller ng parcel na may tracking no. {$order->tracking_code}.\nPakiusap po, ingatan at siguraduhing maideliver agad ito kay {$order->shippingAddress->full_name}.\nMatagal na pong hinihintay ito at kailangan na kailangan na po talaga ngayon.\n\nNabanggit din po ng customer na mahina ang signal sa kanilang lugar, kaya kung hindi po matawagan, pakideliver na lang po direkta — siguradong tatanggapin daw po nila.\nNakahanda na rin po ang bayad.\n\nSalamat po sa inyong pag-unawa at ingat po kayo palagi sa biyahe!";
                $receiver_message = "{$order->shippingAddress->full_name}. Padating na po ang order nyo mula sa {$order->page->name}. Siguraduhing matatawagan po ang cp nyo ng rider. Paki-ready nalang po ng pang bayad.\n Parcel Tracking No. {$order->tracking_code}\n Rider Name: $rider_name\nRider No: $rider_mobile";

                ParcelJourneyNotification::create([
                    'order_id' => $order->id,
                    'parcel_journey_id' => $parcelJourney->id,
                    'message' => $rider_message,
                    'type' => 'sms',
                    'receiver_name' => $rider_name,
                    'receiver_identity' => $rider_mobile,
                ]);

                $message = "{$order->shippingAddress->full_name}, Magandang Araw po. Kamusta po ? \nNgayong araw po matatanggap ang parcel nyo. Pwede nyo dn po sila tawagan ang rider na magdedeliver sayo. \n\n$rider_name\n$rider_mobile\n\n Make sure po na matatawagan ang cp number nyo po. Pakibantayan dn po. Salamat po";

                ParcelJourneyNotification::create([
                    'order_id' => $order->id,
                    'parcel_journey_id' => $parcelJourney->id,
                    'message' => $receiver_message,
                    'type' => 'sms',
                    'receiver_name' => $order->shippingAddress->full_name,
                    'receiver_identity' => $order->shippingAddress->phone_number,
                ]);

                ParcelJourneyNotification::create([
                    'order_id' => $order->id,
                    'parcel_journey_id' => $parcelJourney->id,
                    'message' => $message,
                    'type' => 'chat',
                    'receiver_name' => $order->shippingAddress->full_name,
                    'receiver_identity' => $psid,
                ]);
            }
        }
    }
}
