<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\MetaAds\Jobs\Concerns\HandlesMetaSyncErrors;
use Modules\MetaAds\Jobs\Concerns\SerializesPerAdAccount;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\SyncRun;
use Throwable;

class SyncInsights implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels, SerializesPerAdAccount;

    public int $timeout = 300;

    public int $tries = 8;

    /**
     * Single-source action chains: walk in priority order, take first match.
     * Used when several action_types describe the SAME underlying event
     * (e.g. omni_purchase preferred over fb_pixel_purchase).
     */
    private const FIRST_MATCH_CHAINS = [
        'page_photo_views' => ['photo_view'],
        'page_engagement' => ['page_engagement'],
        'page_likes' => ['like'],
        'post_engagement' => ['post_engagement'],
        'post_comments' => ['comment'],
        'post_shares' => ['post'],
        'post_saves' => ['onsite_conversion.post_save', 'post_save'],
        'post_reactions' => ['post_reaction'],
        'messaging_first_replies' => ['onsite_conversion.messaging_first_reply'],
        'messaging_conversations_started' => ['onsite_conversion.messaging_conversation_started_7d'],
        'initiate_checkout' => [
            'omni_initiated_checkout',
            'offsite_conversion.fb_pixel_initiate_checkout',
            'initiate_checkout',
        ],
        'purchases' => [
            'omni_purchase',
            'purchase',
            'offsite_conversion.fb_pixel_purchase',
            'app_custom_event.fb_mobile_purchase',
            'onsite_conversion.purchase',
            'web_in_store_purchase',
        ],
        'on_facebook_leads' => ['onsite_conversion.lead_grouped'],
    ];

    /**
     * Additive action chains: sum values across ALL matching action_types.
     * Used when the metric is genuinely a total (e.g. leads = offsite + onsite).
     */
    private const SUM_CHAINS = [
        'leads' => [
            'offsite_conversion.fb_pixel_lead',
            'onsite_conversion.lead_grouped',
            'leadgen.other',
        ],
    ];

    /**
     * Map of count column → currency value column. Both share the same chain
     * but read from `actions` vs `action_values` respectively.
     */
    private const VALUE_COLUMNS = [
        'page_photo_views' => 'photo_views_value',
        'page_engagement' => 'page_engagement_value',
        'page_likes' => 'page_likes_value',
        'post_engagement' => 'post_engagement_value',
        'post_comments' => 'post_comments_value',
        'post_shares' => 'post_shares_value',
        'post_saves' => 'post_saves_value',
        'post_reactions' => 'post_reactions_value',
        'messaging_first_replies' => 'messaging_first_replies_value',
        'messaging_conversations_started' => 'messaging_conversations_started_value',
        'initiate_checkout' => 'initiate_checkout_value',
        'purchases' => 'purchase_value',
        'on_facebook_leads' => 'on_facebook_leads_value',
        'leads' => 'lead_value',
    ];

    public function __construct(
        public AdAccount $adAccount,
        public string $date,
        public ?string $nextUrl = null,
        public int $runningCount = 0,
        public ?int $syncRunId = null,
    ) {}

    public function handle(): void
    {
        $run = $this->syncRunId !== null
            ? SyncRun::findOrFail($this->syncRunId)
            : SyncRun::start(
                entityType: SyncRun::ENTITY_AD_INSIGHTS,
                scopeType: AdAccount::class,
                scopeId: $this->adAccount->id,
                meta: ['date' => $this->date],
            );

        try {
            $client = $this->adAccount->graphClient();

            $fields = implode(',', [
                'ad_id', 'adset_id', 'campaign_id', 'date_start', 'date_stop',
                // Delivery & Traffic
                'impressions', 'reach', 'clicks', 'inline_link_clicks',
                'outbound_clicks', 'spend', 'estimated_ad_recallers',
                'inline_post_engagement',
                // Action breakdowns (consumed below; not persisted as JSON)
                'actions', 'action_values', 'conversions',
                // Video (each is an array we sum to a scalar)
                'video_play_actions',
                'video_thruplay_watched_actions',
                'video_p25_watched_actions', 'video_p50_watched_actions',
                'video_p75_watched_actions', 'video_p100_watched_actions',
            ]);

            $path = $this->nextUrl ?? "{$this->adAccount->graphAccountId()}/insights";
            $query = $this->nextUrl ? [] : [
                'level' => 'ad',
                'time_increment' => 1,
                'time_range' => json_encode([
                    'since' => $this->date,
                    'until' => $this->date,
                ]),
                'fields' => $fields,
            ];

            $page = $client->getPage($path, $query);

            $count = $this->runningCount;

            foreach ($page['data'] ?? [] as $row) {
                if (! ($row['ad_id'] ?? null) || ! ($row['date_start'] ?? null)) {
                    continue;
                }

                $actionsArr = $row['actions'] ?? [];
                $valuesArr = $row['action_values'] ?? [];

                $denormCounts = [];
                $denormValues = [];

                foreach (self::FIRST_MATCH_CHAINS as $col => $types) {
                    $denormCounts[$col] = $this->intOrNull($this->firstAction($actionsArr, $types));
                    if (isset(self::VALUE_COLUMNS[$col])) {
                        $denormValues[self::VALUE_COLUMNS[$col]] = $this->floatOrNull(
                            $this->firstAction($valuesArr, $types),
                        );
                    }
                }

                foreach (self::SUM_CHAINS as $col => $types) {
                    $denormCounts[$col] = $this->intOrNull($this->sumActionTypes($actionsArr, $types));
                    if (isset(self::VALUE_COLUMNS[$col])) {
                        $denormValues[self::VALUE_COLUMNS[$col]] = $this->floatOrNull(
                            $this->sumActionTypes($valuesArr, $types),
                        );
                    }
                }

                $impressions = (int) ($row['impressions'] ?? 0);
                $spend = (float) ($row['spend'] ?? 0);

                // Skip rows with no real activity to keep the table tight.
                $hasActivity = $impressions > 0
                    || $spend > 0
                    || array_sum(array_map(fn ($v) => (int) ($v ?? 0), $denormCounts)) > 0
                    || array_sum(array_map(fn ($v) => (float) ($v ?? 0), $denormValues)) > 0;

                if (! $hasActivity) {
                    continue;
                }

                Insight::updateOrCreate(
                    [
                        'meta_ads_ad_id' => $row['ad_id'],
                        'date' => $row['date_start'],
                    ],
                    [
                        'meta_ads_account_id' => $this->adAccount->id,
                        'meta_ads_campaign_id' => $row['campaign_id'] ?? null,
                        'meta_ads_set_id' => $row['adset_id'] ?? null,

                        // Delivery & Traffic
                        'spend' => $spend,
                        'impressions' => $impressions,
                        'reach' => (int) ($row['reach'] ?? 0),
                        'clicks' => (int) ($row['clicks'] ?? 0),
                        'link_clicks' => $this->intOrNull($row['inline_link_clicks'] ?? null),
                        'outbound_clicks' => $this->sumScalar($row['outbound_clicks'] ?? null),
                        'estimated_ad_recallers' => $this->intOrNull($row['estimated_ad_recallers'] ?? null),

                        // Engagement (counts merged with denormCounts; post_engagement
                        // overridden to use Meta's scalar `inline_post_engagement` if
                        // present, otherwise fall back to action_type sum).
                        ...$denormCounts,
                        'post_engagement' => $this->intOrNull($row['inline_post_engagement'] ?? $denormCounts['post_engagement'] ?? null),

                        // Values
                        ...$denormValues,

                        // Video
                        'video_3sec_views' => $this->sumScalar($row['video_play_actions'] ?? null),
                        'video_thruplay_views' => $this->sumScalar($row['video_thruplay_watched_actions'] ?? null),
                        'video_p25_views' => $this->sumScalar($row['video_p25_watched_actions'] ?? null),
                        'video_p50_views' => $this->sumScalar($row['video_p50_watched_actions'] ?? null),
                        'video_p75_views' => $this->sumScalar($row['video_p75_watched_actions'] ?? null),
                        'video_p100_views' => $this->sumScalar($row['video_p100_watched_actions'] ?? null),

                        // Conversions (top-level field; sum all entries)
                        'conversions' => $this->sumScalar($row['conversions'] ?? null),
                    ],
                );

                $count++;
            }

            $nextUrl = $page['paging']['next'] ?? null;

            if ($nextUrl !== null) {
                static::dispatch($this->adAccount, $this->date, $nextUrl, $count, $run->id);
            } else {
                $run->succeed($count, ['insight_row_count' => $count]);
            }
        } catch (Throwable $e) {
            $this->handleSyncError($run, $e);
        }
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function floatOrNull(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    /**
     * First-matching `value` from a list of `[{action_type, value}, ...]` rows.
     * Walks $candidateTypes in order; returns the first hit. Used when types
     * are interchangeable representations of the same metric.
     */
    private function firstAction(array $rows, array $candidateTypes): ?string
    {
        foreach ($candidateTypes as $type) {
            foreach ($rows as $entry) {
                if (($entry['action_type'] ?? null) === $type) {
                    return $entry['value'] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Sum `value` across ALL rows whose action_type is in $types. Used when
     * the metric is genuinely a total of multiple distinct events (e.g.
     * leads = offsite + onsite).
     */
    private function sumActionTypes(array $rows, array $types): ?float
    {
        $total = null;
        foreach ($rows as $entry) {
            if (in_array($entry['action_type'] ?? null, $types, true)) {
                $total = ($total ?? 0) + (float) ($entry['value'] ?? 0);
            }
        }

        return $total;
    }

    /**
     * Meta wraps several scalar metrics (video views, outbound_clicks,
     * conversions) as `[{action_type, value}, ...]` arrays. Sum the values
     * regardless of action_type; return null if absent or empty.
     */
    private function sumScalar(mixed $rows): ?int
    {
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $total = 0.0;
        foreach ($rows as $entry) {
            $total += (float) ($entry['value'] ?? 0);
        }

        return (int) $total;
    }
}
