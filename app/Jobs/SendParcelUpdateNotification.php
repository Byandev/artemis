<?php

namespace App\Jobs;

use App\Models\ParcelJourneyNotification;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Http;

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

                    dispatch(new CheckParcelUpdateNotification($this->parcelJourneyNotification))->delay(now()->addMinutes(5));
                } else {
                    $this->parcelJourneyNotification->update(['remarks' => json_encode($response)]);
                }
            } else {
                $this->parcelJourneyNotification->update(['status' => 'failed', 'remarks' => 'Request failed']);
            }

        } elseif ($this->parcelJourneyNotification->type === 'chat') {
            $pageId = $this->parcelJourneyNotification->order->page->id;
            $convoId = $this->parcelJourneyNotification->order->fb_id;

            $usingPancake = (bool) $this->parcelJourneyNotification->order->page->pancake_token;

            if ($usingPancake) {
                $pancakeToken = $this->parcelJourneyNotification->order->page->pancake_token;

                $response = Http::post("https://pages.fm/api/public_api/v1/pages/$pageId/conversations/$convoId/messages?page_access_token=$pancakeToken", [
                    'action' => 'reply_inbox',
                    'message' => $this->parcelJourneyNotification->message,
                ]);
            } else {
                $response = Http::withHeaders(['access-token' => $this->parcelJourneyNotification->order->page->botcake_token])
                    ->post("https://botcake.io/api/public_api/v1/pages/$pageId/flows/send_content", [
                        'psid' => $this->parcelJourneyNotification->receiver_identity,
                        'message_tag' => 'POST_PURCHASE_UPDATE',
                        'data' => [
                            'version' => 'v2',
                            'content' => [
                                'messages' => [
                                    [
                                        'type' => 'text',
                                        'text' => $this->parcelJourneyNotification->message,
                                    ],
                                ],
                            ],
                        ],
                    ]);
            }

            if ($response->ok()) {
                $response = $response->json();

                if (! $response['success']) {
                    $this->parcelJourneyNotification->update(['status' => 'failed', 'remarks' => $response['message']
                        ?? 'Unable to send chat message',
                    ]);
                } else {
                    $this->parcelJourneyNotification->update(['status' => 'sent']);
                }
            } else {
                $this->parcelJourneyNotification->update(['status' => 'failed', 'remarks' => 'Request failed']);
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
