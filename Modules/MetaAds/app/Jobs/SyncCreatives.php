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
use Modules\MetaAds\Models\Creative;
use Modules\MetaAds\Models\SyncRun;
use Throwable;

class SyncCreatives implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels, SerializesPerAdAccount;

    public int $timeout = 600;

    public int $tries = 8;

    public function __construct(
        public AdAccount $adAccount,
        public ?string $afterCursor = null,
        public int $runningCount = 0,
        public ?int $syncRunId = null,
    ) {}

    public function handle(): void
    {
        $run = $this->syncRunId !== null
            ? SyncRun::findOrFail($this->syncRunId)
            : SyncRun::start(
                entityType: SyncRun::ENTITY_AD_CREATIVES,
                scopeType: AdAccount::class,
                scopeId: $this->adAccount->id,
            );
        $this->syncRunId = $run->id;

        try {
            $client = $this->adAccount->graphClient();

            $fields = 'id,name,title,body,object_type,call_to_action_type,image_url,image_hash,video_id,thumbnail_url,object_story_spec,effective_object_story_id,instagram_permalink_url,status';

            // Creatives carry a large `object_story_spec` JSON; cap page size
            // so the response doesn't blow Meta's per-request size limit.
            $query = ['fields' => $fields, 'limit' => 25];
            if ($this->afterCursor !== null) {
                $query['after'] = $this->afterCursor;
            }

            $page = $client->getPage("{$this->adAccount->graphAccountId()}/adcreatives", $query);

            $count = $this->runningCount;

            foreach ($page['data'] ?? [] as $row) {
                Creative::updateOrCreate(
                    ['id' => $row['id']],
                    [
                        'meta_ads_account_id' => $this->adAccount->id,
                        'meta_page_id' => $this->extractPageId($row),
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

            // `paging.cursors.after` is present on every page (even the last);
            // only `paging.next` signals more results. Gating on the cursor
            // re-dispatches a continuation past the final page on every sync.
            $hasNextPage = isset($page['paging']['next']);
            $afterCursor = $page['paging']['cursors']['after'] ?? null;

            if ($hasNextPage && $afterCursor !== null) {
                static::dispatch($this->adAccount, $afterCursor, $count, $run->id);
            } else {
                $run->succeed($count, ['creative_count' => $count]);
            }
        } catch (Throwable $e) {
            $this->handleSyncError($run, $e);
        }
    }

    /**
     * Pull the FB page id off a creative row. Prefer `object_story_spec.page_id`
     * (most reliable). Fall back to the leading numeric portion of
     * `effective_object_story_id` (which is `<page_id>_<post_id>`).
     */
    private function extractPageId(array $row): ?int
    {
        $fromSpec = $row['object_story_spec']['page_id'] ?? null;
        if ($fromSpec) {
            return (int) $fromSpec;
        }

        $story = $row['effective_object_story_id'] ?? null;
        if ($story && str_contains($story, '_')) {
            $head = explode('_', $story, 2)[0];
            if (ctype_digit($head)) {
                return (int) $head;
            }
        }

        return null;
    }
}
