<?php

namespace Modules\Pancake\Jobs;

use App\Models\Page;
use App\Models\WorkspacePageDailyMetric;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Pancake\Services\Pancake;

class FetchPageCustomersDaily implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public Page $page, public string $date) {}

    public function handle(): void
    {
        $day = CarbonImmutable::parse($this->date, 'Asia/Manila')->startOfDay();
        $since = $day->getTimestamp();
        $until = $day->endOfDay()->getTimestamp();

        $response = Pancake::listPageCustomers(
            pageId: (string) $this->page->id,
            pageAccessToken: $this->page->pancake_token,
            since: $since,
            until: $until,
        );

        $total = (int) ($response['total'] ?? 0);

        WorkspacePageDailyMetric::updateOrCreate(
            [
                'workspace_id' => $this->page->workspace_id,
                'page_id' => $this->page->id,
                'date' => $day->toDateString(),
            ],
            [
                'new_customer_count' => $total,
            ]
        );
    }
}
