<?php

namespace Modules\Pancake\Jobs;

use App\Models\Page;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Pancake\Models\PancakeUserDailyEngagement;
use Modules\Pancake\Services\Pancake;

class FetchPageUserEngagements implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Page $page, public Carbon $date) {}

    public function handle(): void
    {
        $response = Pancake::getCustomerEngagements($this->page->id, $this->page->pancake_token, $this->date);

        if (! ($response['success'] ?? false)) {
            return;
        }

        foreach ($response['users_engagements'] ?? [] as $row) {
            if (empty($row['user_id'])) {
                continue;
            }

            PancakeUserDailyEngagement::updateOrCreate(
                [
                    'page_id' => $this->page->id,
                    'pancake_user_id' => $row['user_id'],
                    'date' => $this->date->toDateString(),
                ],
                [
                    'workspace_id' => $this->page->workspace_id,
                    'name' => $row['name'] ?? null,
                    'order_count' => $row['order_count'] ?? 0,
                    'old_order_count' => $row['old_order_count'] ?? 0,
                    'customer_engagement_new_inbox' => $row['customer_engagement_new_inbox'] ?? 0,
                    'inbox_count' => $row['inbox_count'] ?? 0,
                    'new_customer_replied_count' => $row['new_customer_replied_count'] ?? 0,
                    'total_engagement' => $row['total_engagement'] ?? 0,
                ]
            );
        }
    }
}
