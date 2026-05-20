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
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\SyncRun;
use Throwable;

class SyncAds implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels, SerializesPerAdAccount;

    public int $timeout = 600;

    public int $tries = 8;

    public function __construct(public AdAccount $adAccount) {}

    public function handle(): void
    {
        sleep(2);

        $run = SyncRun::start(
            entityType: SyncRun::ENTITY_ADS,
            scopeType: AdAccount::class,
            scopeId: $this->adAccount->id,
        );

        try {
            $client = $this->adAccount->graphClient();

            $fields = 'id,name,adset_id,campaign_id,creative,status,effective_status,created_time,updated_time,created_by';

            $count = 0;

            foreach ($client->paginated("{$this->adAccount->graphAccountId()}/ads", ['fields' => $fields]) as $row) {
                if (! ($row['campaign_id'] ?? null) || ! ($row['adset_id'] ?? null)) {
                    continue;
                }

                Ad::updateOrCreate(
                    ['id' => $row['id']],
                    [
                        'meta_ads_account_id' => $this->adAccount->id,
                        'meta_ads_campaign_id' => $row['campaign_id'],
                        'meta_ads_set_id' => $row['adset_id'],
                        'meta_ads_creative_id' => $row['creative']['id'] ?? null,
                        'name' => $row['name'] ?? $row['id'],
                        'status' => $row['status'] ?? null,
                        'effective_status' => $row['effective_status'] ?? null,
                        'created_by_meta_user_id' => $row['created_by']['id'] ?? null,
                        'created_time' => $row['created_time'] ?? null,
                        'updated_time' => $row['updated_time'] ?? null,
                        'last_synced_at' => Carbon::now(),
                    ],
                );

                $count++;
            }

            $run->succeed($count, ['ad_count' => $count]);
        } catch (Throwable $e) {
            $this->handleSyncError($run, $e);
        }
    }
}
