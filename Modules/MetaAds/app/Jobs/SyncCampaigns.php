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

    public function __construct(
        public AdAccount $adAccount,
        public ?string $afterCursor = null,
        public int $runningCount = 0,
        public ?int $syncRunId = null,
        public ?int $sinceTimestamp = null,
    ) {}

    public function handle(): void
    {
        sleep(2);

        if ($this->syncRunId === null) {
            $this->sinceTimestamp = $this->resolveLastSuccessAt(SyncRun::ENTITY_CAMPAIGNS);
        }

        $run = $this->syncRunId !== null
            ? SyncRun::findOrFail($this->syncRunId)
            : SyncRun::start(
                entityType: SyncRun::ENTITY_CAMPAIGNS,
                scopeType: AdAccount::class,
                scopeId: $this->adAccount->id,
                meta: $this->sinceTimestamp ? ['since_timestamp' => $this->sinceTimestamp] : [],
            );
        $this->syncRunId = $run->id;

        try {
            $client = $this->adAccount->graphClient();

            $fields = 'id,name,objective,status,effective_status,buying_type,bid_strategy,daily_budget,lifetime_budget,start_time,stop_time,created_time,updated_time';

            $query = ['fields' => $fields];
            if ($this->afterCursor !== null) {
                $query['after'] = $this->afterCursor;
            }
            if ($this->sinceTimestamp !== null) {
                $query['filtering'] = json_encode([
                    ['field' => 'updated_time', 'operator' => 'GREATER_THAN', 'value' => $this->sinceTimestamp],
                ]);
            }

            $page = $client->getPage("{$this->adAccount->graphAccountId()}/campaigns", $query);

            $count = $this->runningCount;

            foreach ($page['data'] ?? [] as $row) {
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

            $afterCursor = $page['paging']['cursors']['after'] ?? null;

            if ($afterCursor !== null) {
                static::dispatch($this->adAccount, $afterCursor, $count, $run->id, $this->sinceTimestamp);
            } else {
                $run->succeed($count, ['campaign_count' => $count]);
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
