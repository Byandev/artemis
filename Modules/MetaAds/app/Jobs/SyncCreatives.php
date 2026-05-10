<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Jobs\Concerns\HandlesMetaSyncErrors;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\Creative;
use Modules\MetaAds\Models\SyncRun;
use RuntimeException;
use Throwable;

class SyncCreatives implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 8;

    public function __construct(public AdAccount $adAccount) {}

    public function handle(): void
    {
        $run = SyncRun::start(
            entityType: SyncRun::ENTITY_AD_CREATIVES,
            scopeType: AdAccount::class,
            scopeId: $this->adAccount->id,
        );

        try {
            $metaUser = $this->adAccount->metaUsers()->first();

            if (! $metaUser) {
                throw new RuntimeException("No MetaUser linked to AdAccount {$this->adAccount->id}");
            }

            $client = $metaUser->graphClient();

            $fields = 'id,name,title,body,object_type,call_to_action_type,image_url,image_hash,video_id,thumbnail_url,object_story_spec,effective_object_story_id,instagram_permalink_url,status';

            $count = 0;

            // Creatives carry a large `object_story_spec` JSON; cap page size
            // so the response doesn't blow Meta's per-request size limit.
            foreach ($client->paginated(
                "{$this->adAccount->graphAccountId()}/adcreatives",
                ['fields' => $fields, 'limit' => 25],
            ) as $row) {
                Creative::updateOrCreate(
                    ['id' => $row['id']],
                    [
                        'meta_ads_account_id' => $this->adAccount->id,
                        'name' => $row['name'] ?? null,
                        'title' => $row['title'] ?? null,
                        'body' => $row['body'] ?? null,
                        'object_type' => $row['object_type'] ?? null,
                        'call_to_action_type' => $row['call_to_action_type'] ?? null,
                        'image_url' => $row['image_url'] ?? null,
                        'image_hash' => $row['image_hash'] ?? null,
                        'video_id' => $row['video_id'] ?? null,
                        'thumbnail_url' => $row['thumbnail_url'] ?? null,
                        'object_story_spec' => $row['object_story_spec'] ?? null,
                        'effective_object_story_id' => $row['effective_object_story_id'] ?? null,
                        'instagram_permalink_url' => $row['instagram_permalink_url'] ?? null,
                        'status' => $row['status'] ?? null,
                        'last_synced_at' => Carbon::now(),
                    ],
                );

                $count++;
            }

            $run->succeed($count, ['creative_count' => $count]);
        } catch (Throwable $e) {
            $this->handleSyncError($run, $e);
        }
    }
}
