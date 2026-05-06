<?php

namespace Modules\Botcake\Jobs;

use App\Models\Page;
use App\Services\Botcake;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Botcake\Models\Flow;

class FetchFlows implements ShouldQueue
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
        $page = $this->page;

        $flows = (new Botcake($page->id, $page->botcake_token))->fetchFlows();

        collect($flows)
            ->map(function ($item) use ($page) {
                return [
                    'page_id' => $page->id,
                    'flow_id' => $item['id'],
                    'parent_id' => $item['parent_id'],
                    'is_removed' => $item['is_removed'],
                    'name' => $item['name'] ?? $item['id'],
                ];
            })
            ->chunk(100)
            ->each(function ($chunk) {
                Flow::upsert($chunk->toArray(), ['page_id', 'flow_id']);
            });
    }
}
