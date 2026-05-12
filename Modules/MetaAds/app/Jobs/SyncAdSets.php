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
use RuntimeException;
use Throwable;

class SyncAdSets implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels, SerializesPerAdAccount;

    public int $timeout = 600;

    public int $tries = 8;

    public function __construct(public AdAccount $adAccount) {}

    public function handle(): void
    {
        sleep(2);

        $run = SyncRun::start(
            entityType: SyncRun::ENTITY_AD_SETS,
            scopeType: AdAccount::class,
            scopeId: $this->adAccount->id,
        );

        try {
            $metaUser = $this->adAccount->metaUsers()->first();

            if (! $metaUser) {
                throw new RuntimeException("No MetaUser linked to AdAccount {$this->adAccount->id}");
            }

            $client = $metaUser->graphClient();

            $fields = 'id,name,campaign_id,status,effective_status,daily_budget,lifetime_budget,bid_strategy,optimization_goal,billing_event,targeting,promoted_object,start_time,end_time,created_time,updated_time';

            $count = 0;

            foreach ($client->paginated("{$this->adAccount->graphAccountId()}/adsets", ['fields' => $fields]) as $row) {
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

            $run->succeed($count, ['ad_set_count' => $count]);
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
