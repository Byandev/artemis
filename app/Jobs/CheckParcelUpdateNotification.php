<?php

namespace App\Jobs;


use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Modules\Pancake\Models\ParcelJourneyNotification;

class CheckParcelUpdateNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public ParcelJourneyNotification $parcelJourneyNotification)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $response = Http::get('https://api.myinfotxt.com/v2/status.php', [
            'smsid' => $this->parcelJourneyNotification->sms_id,
        ]);

        if ($response->successful()) {
            $response = $response->json();

            if (isset($response['status']) && $response['status'] === '1') {
                $this->parcelJourneyNotification->update(['status' => 'sent']);
            } elseif (isset($response['status']) && $response['status'] === '0') {
                dispatch(new CheckParcelUpdateNotification($this->parcelJourneyNotification))->delay(now()->addMinutes(5))->onQueue('parcel-notifications');
            } elseif (isset($response['status']) && $response['status'] === '2') {
                $this->parcelJourneyNotification->update(['status' => 'failed', 'remarks' => json_encode($response)]);
            } else {
                $this->parcelJourneyNotification->update(['status' => 'failed', 'remarks' => json_encode($response)]);
            }
        } else {
            $this->parcelJourneyNotification->update(['status' => 'failed', 'remarks' => 'Checking Request failed']);
        }
    }
}
