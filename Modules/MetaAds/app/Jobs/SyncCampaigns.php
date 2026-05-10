<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\SyncRun;
use RuntimeException;
use Throwable;

class SyncCampaigns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(public AdAccount $adAccount) {}

    public function handle(): void
    {
        $run = SyncRun::start(
            entityType: SyncRun::ENTITY_CAMPAIGNS,
            scopeType: AdAccount::class,
            scopeId: $this->adAccount->id,
        );

        try {
            $metaUser = $this->adAccount->metaUsers()->first();

            if (! $metaUser) {
                throw new RuntimeException("No MetaUser linked to AdAccount {$this->adAccount->meta_account_id}");
            }

            $client = $metaUser->graphClient();

            $fields = 'id,name,objective,status,effective_status,buying_type,bid_strategy,daily_budget,lifetime_budget,start_time,stop_time,created_time,updated_time';

            $count = 0;

            foreach ($client->paginated("{$this->adAccount->meta_account_id}/campaigns", ['fields' => $fields]) as $row) {
                Campaign::updateOrCreate(
                    ['meta_campaign_id' => $row['id']],
                    [
                        'meta_ads_account_id' => $this->adAccount->id,
                        'name' => $row['name'] ?? $row['id'],
                        'objective' => $row['objective'] ?? null,
                        'status' => $row['status'] ?? null,
                        'effective_status' => $row['effective_status'] ?? null,
                        'buying_type' => $row['buying_type'] ?? null,
                        'bid_strategy' => $row['bid_strategy'] ?? null,
                        'daily_budget' => $this->minorToMajor($row['daily_budget'] ?? null),
                        'lifetime_budget' => $this->minorToMajor($row['lifetime_budget'] ?? null),
                        'start_time' => $row['start_time'] ?? null,
                        'stop_time' => $row['stop_time'] ?? null,
                        'created_time' => $row['created_time'] ?? null,
                        'updated_time' => $row['updated_time'] ?? null,
                        'last_synced_at' => Carbon::now(),
                    ],
                );

                $count++;
            }

            $run->succeed($count, ['campaign_count' => $count]);
        } catch (Throwable $e) {
            $run->fail($e);
            throw $e;
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
