<?php

namespace Modules\Botcake\Jobs;

use App\Models\Page;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Modules\Botcake\Models\Sequence;

class FetchSequences implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Page $page) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $response = Http::withHeaders([
            'access-token' => $this->page->botcake_token,
        ])
            ->get("https://botcake.io/api/public_api/v1/pages/{$this->page->id}/sequences/")
            ->throw()
            ->json();

        $items = collect($response['data'])
            ->map(function ($item) {
                return [
                    'page_id' => $this->page->id,
                    'sequence_id' => $item['id'],
                    'name' => $item['name'] ?? $item['id'],
                ];
            })
            ->toArray();

        Sequence::upsert($items, ['page_id', 'sequence_id']);

    }
}
