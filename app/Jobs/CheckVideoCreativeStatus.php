<?php

namespace App\Jobs;

use App\Models\VideoCreative;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class CheckVideoCreativeStatus implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public VideoCreative $videoCreative)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $response = Http::withToken('26348f412b846a1f743febd93532b3fc')
                ->get('https://api.kie.ai/api/v1/jobs/recordInfo', [
                    'taskId' => $this->videoCreative->task_id,
                ])
                ->throw()
                ->json();

            if ($response['data']['state'] === 'success') {
                $url = json_decode($response['data']['resultJson'], true)['resultUrls'][0];
                \Log::error($url);

                $this->videoCreative->addMediaFromUrl($url)->toMediaCollection('VIDEO_CREATIVE');

                $this->videoCreative->update([
                    'status' => 'success',
                ]);
            } else {
                dispatch(new CheckVideoCreativeStatus($this->videoCreative))->delay(now()->addMinutes(3));
            }
        } catch (\Exception $exception) {
            $this->videoCreative->update(['status' => 'failed']);
        }
    }
}
