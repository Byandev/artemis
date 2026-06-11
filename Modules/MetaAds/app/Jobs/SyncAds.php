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
use Modules\MetaAds\Models\Creative;
use Modules\MetaAds\Models\SyncRun;
use Throwable;

class SyncAds implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels, SerializesPerAdAccount;

    public int $timeout = 600;

    public int $tries = 5;

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
            $this->sinceTimestamp = $this->resolveLastSuccessAt(SyncRun::ENTITY_ADS);
        }

        $run = $this->syncRunId !== null
            ? SyncRun::findOrFail($this->syncRunId)
            : SyncRun::start(
                entityType: SyncRun::ENTITY_ADS,
                scopeType: AdAccount::class,
                scopeId: $this->adAccount->id,
                meta: $this->sinceTimestamp ? ['since_timestamp' => $this->sinceTimestamp] : [],
            );
        $this->syncRunId = $run->id;

        try {
            $client = $this->adAccount->graphClient();

            $fields = 'id,name,adset_id,campaign_id,creative{id,thumbnail_url,image_url},status,effective_status,created_time,updated_time,created_by';

            $query = ['fields' => $fields];
            if ($this->afterCursor !== null) {
                $query['after'] = $this->afterCursor;
            }
            if ($this->sinceTimestamp !== null) {
                $query['filtering'] = json_encode([
                    ['field' => 'updated_time', 'operator' => 'GREATER_THAN', 'value' => $this->sinceTimestamp],
                ]);
            }

            $page = $client->getPage("{$this->adAccount->graphAccountId()}/ads", $query);

            $count = $this->runningCount;

            foreach ($page['data'] ?? [] as $row) {
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

                // Capture the creative's thumbnail straight from the ad so the
                // Ads Manager always has an image to show, even when the
                // account-level /adcreatives listing doesn't return this
                // creative. Only the media fields are written, so a fuller row
                // synced by SyncCreatives is never clobbered.
                $creative = $row['creative'] ?? null;
                if (is_array($creative) && ($creative['id'] ?? null)) {
                    $media = array_filter([
                        'thumbnail_url' => $creative['thumbnail_url'] ?? null,
                        'image_url' => $creative['image_url'] ?? null,
                    ], fn ($v) => $v !== null);

                    Creative::updateOrCreate(
                        ['id' => $creative['id']],
                        ['meta_ads_account_id' => $this->adAccount->id, ...$media],
                    );
                }

                $count++;
            }

            // `paging.cursors.after` is present on every page (even the last);
            // only `paging.next` signals more results. Gating on the cursor
            // re-dispatches a continuation past the final page on every sync.
            $hasNextPage = isset($page['paging']['next']);
            $afterCursor = $page['paging']['cursors']['after'] ?? null;

            if ($hasNextPage && $afterCursor !== null) {
                static::dispatch($this->adAccount, $afterCursor, $count, $run->id, $this->sinceTimestamp)->onQueue('meta-ads');
            } else {
                $run->succeed($count, ['ad_count' => $count]);
            }
        } catch (Throwable $e) {
            $this->handleSyncError($run, $e);
        }
    }
}
