<?php

namespace App\Jobs\Analytics;

use App\Support\Analytics\PageDailyMetricsBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RebuildPageDailyMetricsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $workspaceId,
        public int $pageId,
        public string $date,
    ) {}

    public function handle(PageDailyMetricsBuilder $builder): void
    {
        $builder->rebuild($this->workspaceId, $this->pageId, $this->date);
    }

    public function uniqueId(): string
    {
        return "{$this->workspaceId}:{$this->pageId}:{$this->date}";
    }
}
