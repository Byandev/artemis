<?php

namespace App\Jobs;

use App\Models\VideoCreative;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class GenerateVideoCreative implements ShouldQueue
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
        $this->videoCreative->update(['status' => 'started']);

        try {
            $response = Http::withToken('26348f412b846a1f743febd93532b3fc')
                ->post('https://api.kie.ai/api/v1/jobs/createTask', [
                    'model' => 'sora-2-image-to-video',
                    'input' => [
                        'prompt' => $this->videoCreative->prompt,
                        'aspect_ratio' => 'portrait',
                        'n_frames' => '15',
                        'remove_watermark' => true,
                        'image_urls' => [
                            'https://ecom-assets-flvebyxl.s3.ap-southeast-1.amazonaws.com/Product+Image.png',
                        ],
                        'cover_image_mode' => 'first_frame_face',
                    ],
                ])
                ->throw()
                ->json();

            $this->videoCreative->update(['status' => 'generating', 'task_id' => $response['data']['taskId']]);

            dispatch(new CheckVideoCreativeStatus($this->videoCreative))->delay(now()->addMinutes(5));
        } catch (\Exception $exception) {
            $this->videoCreative->update(['status' => 'failed']);

            $this->fail($exception->getMessage());
        }
    }
}
