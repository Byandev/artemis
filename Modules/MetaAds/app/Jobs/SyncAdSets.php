<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\SyncRun;
use RuntimeException;
use Throwable;

class SyncAdSets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(public AdAccount $adAccount) {}

    public function handle(): void
    {
        $run = SyncRun::start(
            entityType: SyncRun::ENTITY_AD_SETS,
            scopeType: AdAccount::class,
            scopeId: $this->adAccount->id,
        );

        try {
            $metaUser = $this->adAccount->metaUsers()->first();

            if (! $metaUser) {
                throw new RuntimeException("No MetaUser linked to AdAccount {$this->adAccount->meta_account_id}");
            }

            $client = $metaUser->graphClient();

            $campaignMap = Campaign::where('meta_ads_account_id', $this->adAccount->id)
                ->pluck('id', 'meta_campaign_id')
                ->all();

            $fields = 'id,name,campaign_id,status,effective_status,daily_budget,lifetime_budget,bid_strategy,optimization_goal,billing_event,targeting,start_time,end_time,created_time,updated_time';

            $count = 0;
            $stubbedCampaigns = 0;

            foreach ($client->paginated("{$this->adAccount->meta_account_id}/adsets", ['fields' => $fields]) as $row) {
                $metaCampaignId = $row['campaign_id'] ?? null;

                if (! $metaCampaignId) {
                    continue;
                }

                if (! isset($campaignMap[$metaCampaignId])) {
                    $stub = Campaign::firstOrCreate(
                        ['meta_campaign_id' => $metaCampaignId],
                        [
                            'meta_ads_account_id' => $this->adAccount->id,
                            'name' => $metaCampaignId,
                        ],
                    );
                    $campaignMap[$metaCampaignId] = $stub->id;
                    $stubbedCampaigns++;
                }

                AdSet::updateOrCreate(
                    ['meta_ad_set_id' => $row['id']],
                    [
                        'meta_ads_account_id' => $this->adAccount->id,
                        'meta_ads_campaign_id' => $campaignMap[$metaCampaignId],
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

            $run->succeed($count, [
                'ad_set_count' => $count,
                'stubbed_campaigns' => $stubbedCampaigns,
            ]);
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
