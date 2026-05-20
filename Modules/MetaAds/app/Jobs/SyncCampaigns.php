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
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\SyncRun;
use Throwable;

class SyncCampaigns implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels, SerializesPerAdAccount;

    public int $timeout = 600;

    public int $tries = 8;

    public function __construct(public AdAccount $adAccount) {}

    public function handle(): void
    {
        sleep(2);

        $run = SyncRun::start(
            entityType: SyncRun::ENTITY_CAMPAIGNS,
            scopeType: AdAccount::class,
            scopeId: $this->adAccount->id,
        );

        try {
            $client = $this->adAccount->graphClient();

            $fields = 'id,name,objective,status,effective_status,buying_type,bid_strategy,daily_budget,lifetime_budget,start_time,stop_time,created_time,updated_time';

            $count = 0;

            foreach ($client->paginated("{$this->adAccount->graphAccountId()}/campaigns", ['fields' => $fields]) as $row) {
                Campaign::updateOrCreate(
                    ['id' => $row['id']],
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
                        'start_time' => $this->normalizeTimestamp($row['start_time'] ?? null),
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

    private function normalizeTimestamp(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (Carbon::parse($value)->getTimestamp() <= 0) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return $value;
    }
}
