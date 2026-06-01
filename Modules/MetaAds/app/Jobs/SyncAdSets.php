<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Jobs\Concerns\HandlesMetaSyncErrors;
use Modules\MetaAds\Jobs\Concerns\SerializesPerAdAccount;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\SyncRun;
use Throwable;

class SyncAdSets implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels, SerializesPerAdAccount;

    public int $timeout = 600;

    public int $tries = 8;

    public function __construct(
        public AdAccount $adAccount,
        public ?string $nextUrl = null,
        public int $runningCount = 0,
        public ?int $syncRunId = null,
    ) {}

    public function handle(): void
    {
        sleep(2);

        $run = $this->syncRunId !== null
            ? SyncRun::findOrFail($this->syncRunId)
            : SyncRun::start(
                entityType: SyncRun::ENTITY_AD_SETS,
                scopeType: AdAccount::class,
                scopeId: $this->adAccount->id,
            );

        try {
            $client = $this->adAccount->graphClient();

            $fields = 'id,name,campaign_id,status,effective_status,daily_budget,lifetime_budget,bid_strategy,optimization_goal,billing_event,targeting,promoted_object,start_time,end_time,created_time,updated_time';

            $path = $this->nextUrl ?? "{$this->adAccount->graphAccountId()}/adsets";
            $query = $this->nextUrl ? [] : ['fields' => $fields];

            $page = $client->getPage($path, $query);

            $count = $this->runningCount;

            foreach ($page['data'] ?? [] as $row) {
                if (! ($row['campaign_id'] ?? null)) {
                    continue;
                }

                $pageId = $row['promoted_object']['page_id'] ?? null;

                AdSet::updateOrCreate(
                    ['id' => $row['id']],
                    [
                        'meta_ads_account_id' => $this->adAccount->id,
                        'meta_ads_campaign_id' => $row['campaign_id'],
                        'meta_page_id' => $pageId,
                        'name' => $row['name'] ?? $row['id'],
                        'status' => $row['status'] ?? null,
                        'effective_status' => $row['effective_status'] ?? null,
                        'daily_budget' => $this->minorToMajor($row['daily_budget'] ?? null),
                        'lifetime_budget' => $this->minorToMajor($row['lifetime_budget'] ?? null),
                        'bid_strategy' => $row['bid_strategy'] ?? null,
                        'optimization_goal' => $row['optimization_goal'] ?? null,
                        'billing_event' => $row['billing_event'] ?? null,
                        'targeting' => $row['targeting'] ?? null,
                        'start_time' => $row['start_time'] ?? null,
                        'end_time' => $row['end_time'] ?? null,
                        'created_time' => $row['created_time'] ?? null,
                        'updated_time' => $row['updated_time'] ?? null,
                        'last_synced_at' => Carbon::now(),
                    ],
                );

                $count++;
            }

            $nextUrl = $page['paging']['next'] ?? null;

            if ($nextUrl !== null) {
                static::dispatch($this->adAccount, $nextUrl, $count, $run->id);
            } else {
                $run->succeed($count, ['ad_set_count' => $count]);
            }
        } catch (Throwable $e) {
            $this->handleSyncError($run, $e);
        }
    }

    private function minorToMajor(mixed $minor): ?float
    {
        if ($minor === null || $minor === '') {
            return null;
        }

        return ((int) $minor) / 100;
    }
}
