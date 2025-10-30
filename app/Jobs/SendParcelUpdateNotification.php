<?php

namespace App\Jobs;

use App\Models\ParcelJourneyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
        $this->parcelJourneyNotification->load('order.page');

        if ($this->parcelJourneyNotification->type === 'sms') {
            Http::post('https://api.myinfotxt.com/v2/send.php', [
                'SMS' => $this->parcelJourneyNotification->message,
                'ApiKey' => $this->parcelJourneyNotification->order->page->infotxt_token,
                'Mobile' => $this->parcelJourneyNotification->receiver_identity,
                'UserID' => $this->parcelJourneyNotification->order->page->infotxt_user_id,
            ])
                ->throw()
                ->json();

            $this->parcelJourneyNotification->update(['status' => 'sent', 'sent_at' => now()]);
        } elseif ($this->parcelJourneyNotification->type === 'chat') {
            $pageId = $this->parcelJourneyNotification->order->page->id;

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
                ])
                ->throw()
                ->json();

            if (! $response['success']) {
                $this->fail('Unable to send chat message');
            } else {
                $this->parcelJourneyNotification->update(['status' => 'sent', 'sent_at' => now()]);
            }
        }
    }
}
