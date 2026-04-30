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

        $newResponse = Pancake::listPageCustomers(
            pageId: (string) $this->page->id,
            pageAccessToken: $this->page->pancake_token,
            since: $since,
            until: $until,
            orderBy: 'inserted_at',
        );

        $allResponse = Pancake::listPageCustomers(
            pageId: (string) $this->page->id,
            pageAccessToken: $this->page->pancake_token,
            since: $since,
            until: $until,
            orderBy: 'updated_at',
        );

        $newCount = (int) ($newResponse['total'] ?? 0);
        $allCount = (int) ($allResponse['total'] ?? 0);
        $oldCount = max(0, $allCount - $newCount);

        WorkspacePageDailyMetric::updateOrCreate(
            [
                'workspace_id' => $this->page->workspace_id,
                'page_id' => $this->page->id,
                'date' => $day->toDateString(),
            ],
            [
                'new_customer_count' => $newCount,
                'all_customer_count' => $allCount,
                'old_customer_count' => $oldCount,
            ]
        );
    }
}
