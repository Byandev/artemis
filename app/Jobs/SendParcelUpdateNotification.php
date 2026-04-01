<?php

namespace App\Jobs;

use App\Services\Botcake;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Http;
use Modules\Pancake\Models\ParcelJourneyNotification;

class SendParcelUpdateNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public ParcelJourneyNotification $parcelJourneyNotification) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! config('settings.parcel_journey_notification_enabled')) {
            return;
        }

        sleep(0.5);

        $this->parcelJourneyNotification->load('order.page');

        if ($this->parcelJourneyNotification->type === 'sms') {
            $response = Http::get('https://api.myinfotxt.com/v2/send.php', [
                'SMS' => $this->parcelJourneyNotification->message,
                'ApiKey' => $this->parcelJourneyNotification->order->page->infotxt_token,
                'Mobile' => $this->parcelJourneyNotification->receiver_identity,
                'UserID' => $this->parcelJourneyNotification->order->page->infotxt_user_id,
            ]);

            if ($response->successful()) {
                $response = $response->json();

                if (isset($response['status']) && $response['status'] === '00') {
                    $this->parcelJourneyNotification->update(['sms_id' => $response['smsid']]);

                    dispatch(new CheckParcelUpdateNotification($this->parcelJourneyNotification))->delay(now()->addMinutes(5))->onQueue('parcel-notifications');
                } else {
                    $this->parcelJourneyNotification->update(['remarks' => json_encode($response)]);
                }
            } else {
                $this->parcelJourneyNotification->update(['status' => 'failed', 'remarks' => 'Request failed']);
            }

        } elseif ($this->parcelJourneyNotification->type === 'chat') {
            [$pageId, $psid] = explode('_', $this->parcelJourneyNotification->order->fb_id);

            try {
                $botcake = new Botcake($pageId, $this->parcelJourneyNotification->order->page->botcake_token);

                $botcake->updateCustomField($psid, $this->parcelJourneyNotification->order->page->parcel_journey_custom_field_id, $this->parcelJourneyNotification->message);

                $botcake->sendFlow($psid, $this->parcelJourneyNotification->order->page->parcel_journey_flow_id);

                $this->parcelJourneyNotification->update(['status' => 'sent']);
            } catch (\Exception $e) {
                $this->parcelJourneyNotification->update(['status' => 'failed', 'remarks' => $e->getMessage()]);
            }
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new ThrottlesExceptions(3, 5 * 60)];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): DateTime
    {
        return now()->addHour();
    }
}
