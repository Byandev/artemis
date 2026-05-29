<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AdsManagerController extends Controller
{
    public function campaignsPage(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        [$since, $until] = $this->resolveDateRange($request);
        $accountIds = $this->accountIdsForWorkspace($workspace);
        $metricFilters = $this->parseMetricFilters($request);

        $payload = $this->campaigns($request, $accountIds, $since, $until, $metricFilters);

        return Inertia::render('workspaces/integrations/meta-ads/campaigns', [
            'workspace' => $workspace,
            'rows' => $payload['rows'],
            'dateRange' => ['since' => $since, 'until' => $until],
            'query' => [
                ...$request->only(['sort', 'page', 'since', 'until']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
                'metricFilters' => $metricFilters,
            ],
        ]);
    }

    public function adSetsPage(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        [$since, $until] = $this->resolveDateRange($request);
        $accountIds = $this->accountIdsForWorkspace($workspace);
        $campaignId = $request->query('campaign');
        $metricFilters = $this->parseMetricFilters($request);

        $payload = $this->adSets($request, $accountIds, $since, $until, $campaignId, $metricFilters);

        return Inertia::render('workspaces/integrations/meta-ads/ad-sets', [
            'workspace' => $workspace,
            'rows' => $payload['rows'],
            'context' => [
                'campaign' => $campaignId
                    ? Campaign::select('id', 'name')->find($campaignId)
                    : null,
            ],
            'dateRange' => ['since' => $since, 'until' => $until],
            'query' => [
                ...$request->only(['sort', 'page', 'campaign', 'since', 'until']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
                'metricFilters' => $metricFilters,
            ],
        ]);
    }

    public function adsPage(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        [$since, $until] = $this->resolveDateRange($request);
        $accountIds = $this->accountIdsForWorkspace($workspace);
        $campaignId = $request->query('campaign');
        $adSetId = $request->query('ad_set');
        $metricFilters = $this->parseMetricFilters($request);

        $payload = $this->ads($request, $accountIds, $since, $until, $campaignId, $adSetId, $metricFilters);

        return Inertia::render('workspaces/integrations/meta-ads/ads', [
            'workspace' => $workspace,
            'rows' => $payload['rows'],
            'context' => [
                'campaign' => $campaignId
                    ? Campaign::select('id', 'name')->find($campaignId)
                    : null,
                'ad_set' => $adSetId
                    ? AdSet::select('id', 'name')->find($adSetId)
                    : null,
            ],
            'dateRange' => ['since' => $since, 'until' => $until],
            'query' => [
                ...$request->only(['sort', 'page', 'campaign', 'ad_set', 'since', 'until']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
                'metricFilters' => $metricFilters,
            ],
        ]);
    }

    private static array $OP_MAP = [
        'gt'    => '>',
        'gte'   => '>=',
        'lt'    => '<',
        'lte'   => '<=',
        'eq'    => '=',
    ];

    /**
     * Returns a safe SQL expression for any filterable metric (direct or computed).
     * Direct metrics resolve to COALESCE(i.`col`, 0).
     * Computed metrics mirror the safeDiv() frontend logic: result is 0 when
     * the denominator is 0, matching what the table displays.
     */
    private function metricSqlExpression(string $id): ?string
    {
        if (in_array($id, self::INSIGHTS_METRICS, true)) {
            return "COALESCE(i.`{$id}`, 0)";
        }

        $c   = fn (string $col) => "COALESCE(i.`{$col}`, 0)";
        $div = fn (string $a, string $b) => "COALESCE({$c($a)} / NULLIF({$c($b)}, 0), 0)";

        $map = [
            // Delivery & Traffic
            'frequency'                               => $div('impressions', 'reach'),
            'ctr'                                     => $div('clicks', 'impressions'),
            'cpc'                                     => $div('spend', 'clicks'),
            'cpm'                                     => "COALESCE({$c('spend')} / NULLIF({$c('impressions')}, 0), 0) * 1000",
            'link_ctr'                                => $div('link_clicks', 'impressions'),
            'cost_per_link_click'                     => $div('spend', 'link_clicks'),
            'outbound_ctr'                            => $div('outbound_clicks', 'impressions'),
            'cost_per_outbound_click'                 => $div('spend', 'outbound_clicks'),
            'cost_per_estimated_ad_recaller'          => $div('spend', 'estimated_ad_recallers'),
            // Video
            'hook_rate'                               => $div('video_3sec_views', 'impressions'),
            'cost_per_3s_view'                        => $div('spend', 'video_3sec_views'),
            'hold_rate'                               => $div('video_thruplay_views', 'video_3sec_views'),
            'cost_per_thruplay'                       => $div('spend', 'video_thruplay_views'),
            'body_rate_25'                            => $div('video_p25_views', 'video_3sec_views'),
            'cost_per_p25'                            => $div('spend', 'video_p25_views'),
            'body_rate_50'                            => $div('video_p50_views', 'video_3sec_views'),
            'cost_per_p50'                            => $div('spend', 'video_p50_views'),
            'body_rate_75'                            => $div('video_p75_views', 'video_3sec_views'),
            'cost_per_p75'                            => $div('spend', 'video_p75_views'),
            'body_rate_100'                           => $div('video_p100_views', 'video_3sec_views'),
            'cost_per_p100'                           => $div('spend', 'video_p100_views'),
            // Engagement
            'cost_per_page_engagement'                => $div('spend', 'page_engagement'),
            'page_engagement_rate'                    => $div('page_engagement', 'clicks'),
            'cost_per_page_like'                      => $div('spend', 'page_likes'),
            'page_likes_rate'                         => $div('page_likes', 'clicks'),
            'cost_per_photo_view'                     => $div('spend', 'page_photo_views'),
            'photo_views_rate'                        => $div('page_photo_views', 'clicks'),
            'cost_per_post_engagement'                => $div('spend', 'post_engagement'),
            'cost_per_post_comment'                   => $div('spend', 'post_comments'),
            'post_comments_rate'                      => $div('post_comments', 'clicks'),
            'cost_per_post_share'                     => $div('spend', 'post_shares'),
            'post_shares_rate'                        => $div('post_shares', 'clicks'),
            'cost_per_post_save'                      => $div('spend', 'post_saves'),
            'post_saves_rate'                         => $div('post_saves', 'clicks'),
            'post_reactions_rate'                     => $div('post_reactions', 'clicks'),
            // Messaging
            'cost_per_messaging_first_reply'          => $div('spend', 'messaging_first_replies'),
            'messaging_first_reply_rate'              => $div('messaging_first_replies', 'clicks'),
            'cost_per_messaging_conversation_started' => $div('spend', 'messaging_conversations_started'),
            'messaging_conversation_started_rate'     => $div('messaging_conversations_started', 'clicks'),
            // Commerce & Leads
            'cost_per_initiated_checkout'             => $div('spend', 'initiate_checkout'),
            'initiated_checkout_rate'                 => $div('initiate_checkout', 'clicks'),
            'conversion_rate'                         => $div('conversions', 'clicks'),
            'avg_purchase_value'                      => $div('purchase_value', 'purchases'),
            'cost_per_purchase'                       => $div('spend', 'purchases'),
            'purchase_conv_rate'                      => $div('purchases', 'clicks'),
            'roas'                                    => $div('purchase_value', 'spend'),
            'gross_profit_per_transaction'            => "COALESCE(({$c('purchase_value')} - {$c('spend')}) / NULLIF({$c('purchases')}, 0), 0)",
            'cost_per_on_facebook_lead'               => $div('spend', 'on_facebook_leads'),
            'on_facebook_lead_rate'                   => $div('on_facebook_leads', 'clicks'),
            'cost_per_lead'                           => $div('spend', 'leads'),
            'lead_conv_rate'                          => $div('leads', 'clicks'),
        ];

        return $map[$id] ?? null;
    }

    private function parseMetricFilters(Request $request): array
    {
        $raw = $request->query('metric_filters');
        if (! $raw) return [];

        try {
            $decoded = json_decode($raw, true, 5, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) return [];

        $valid = [];
        foreach ($decoded as $f) {
            $field = $f['field'] ?? null;
            $op    = $f['op']    ?? null;
            $value = $f['value'] ?? null;

            if (! is_string($field) || $this->metricSqlExpression($field) === null) continue;

            if ($op === 'range') {
                $v2 = $f['value2'] ?? null;
                if (! is_numeric($value) || ! is_numeric($v2)) continue;
                $valid[] = ['field' => $field, 'op' => 'range', 'value' => (float) $value, 'value2' => (float) $v2];
            } else {
                if (! isset(self::$OP_MAP[$op]) || ! is_numeric($value)) continue;
                $valid[] = ['field' => $field, 'op' => $op, 'value' => (float) $value];
            }
        }

        return $valid;
    }

    private function applyMetricFilters($query, array $filters): void
    {
        foreach ($filters as $f) {
            $expr = $this->metricSqlExpression($f['field']);
            if ($f['op'] === 'range') {
                $query->whereRaw("({$expr}) BETWEEN ? AND ?", [$f['value'], $f['value2']]);
            } else {
                $sqlOp = self::$OP_MAP[$f['op']];
                $query->whereRaw("({$expr}) {$sqlOp} ?", [$f['value']]);
            }
        }
    }

    private function accountIdsForWorkspace(Workspace $workspace)
    {
        return DB::table('meta_ads_user_account')
            ->join('meta_ads_workspace_user', 'meta_ads_workspace_user.meta_ads_user_id', '=', 'meta_ads_user_account.meta_ads_user_id')
            ->where('meta_ads_workspace_user.workspace_id', $workspace->id)
            ->pluck('meta_ads_user_account.meta_ads_account_id');
    }

    /**
     * Default to last 7 days; honour explicit `since`/`until` from the
     * DatePicker on the frontend.
     */
    private function resolveDateRange(Request $request): array
    {
        $since = $request->query('since');
        $until = $request->query('until');

        if ($since && $until) {
            return [$since, $until];
        }

        $today = Carbon::today();

        return [
            $today->copy()->subDays(6)->toDateString(),
            $today->toDateString(),
        ];
    }

    /**
     * Full list of meta_ads_insights metric columns we aggregate. Adding a new
     * metric here surfaces it to all three Ads Manager pages automatically.
     */
    private const INSIGHTS_METRICS = [
        // Delivery & Traffic
        'spend', 'impressions', 'reach', 'clicks',
        'link_clicks', 'outbound_clicks', 'estimated_ad_recallers',
        'page_photo_views',
        // Video
        'video_3sec_views', 'video_thruplay_views',
        'video_p25_views', 'video_p50_views', 'video_p75_views', 'video_p100_views',
        // Engagement
        'page_engagement', 'page_engagement_value',
        'page_likes', 'page_likes_value', 'photo_views_value',
        'post_engagement', 'post_engagement_value',
        'post_comments', 'post_comments_value',
        'post_shares', 'post_shares_value',
        'post_saves', 'post_saves_value',
        'post_reactions', 'post_reactions_value',
        // Messaging
        'messaging_first_replies', 'messaging_first_replies_value',
        'messaging_conversations_started', 'messaging_conversations_started_value',
        // Commerce & Leads
        'initiate_checkout', 'initiate_checkout_value',
        'conversions', 'purchases', 'purchase_value',
        'on_facebook_leads', 'on_facebook_leads_value',
        'leads', 'lead_value',
    ];

    private function insightsSubquery(string $entityColumn, string $since, string $until)
    {
        $selects = [$entityColumn];
        foreach (self::INSIGHTS_METRICS as $col) {
            $selects[] = DB::raw("SUM({$col}) AS {$col}");
        }

        return DB::table('meta_ads_insights')
            ->whereBetween('date', [$since, $until])
            ->select($selects)
            ->groupBy($entityColumn);
    }

    /**
     * COALESCE expressions for every aggregated metric — folded into the main
     * Eloquent select so missing-insight rows return 0 instead of null.
     */
    private function metricSelects(): array
    {
        return array_map(
            fn (string $col) => DB::raw("COALESCE(i.{$col}, 0) AS {$col}"),
            self::INSIGHTS_METRICS,
        );
    }

    private function campaigns(Request $request, $accountIds, string $since, string $until, array $metricFilters = []): array
    {
        $insights = $this->insightsSubquery('meta_ads_campaign_id', $since, $until);

        $base = Campaign::query()
            ->whereIn('meta_ads_account_id', $accountIds)
            ->leftJoinSub($insights, 'i', 'i.meta_ads_campaign_id', '=', 'meta_ads_campaigns.id')
            ->select(array_merge([
                'meta_ads_campaigns.id',
                'meta_ads_campaigns.name',
                'meta_ads_campaigns.status',
                'meta_ads_campaigns.effective_status',
                'meta_ads_campaigns.objective',
                'meta_ads_campaigns.buying_type',
                'meta_ads_campaigns.bid_strategy',
                'meta_ads_campaigns.daily_budget',
                'meta_ads_campaigns.lifetime_budget',
                'meta_ads_campaigns.start_time',
                'meta_ads_campaigns.stop_time',
                'meta_ads_campaigns.updated_time',
            ], $this->metricSelects()));

        $this->applyMetricFilters($base, $metricFilters);

        $rows = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'meta_ads_campaigns.name'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('effective_status'),
            ])
            ->allowedSorts(array_merge(['name', 'status', 'effective_status', 'daily_budget', 'updated_time'], self::INSIGHTS_METRICS))
            ->defaultSort('-spend')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return ['rows' => $rows];
    }

    private function adSets(Request $request, $accountIds, string $since, string $until, ?string $campaignId, array $metricFilters = []): array
    {
        $insights = $this->insightsSubquery('meta_ads_set_id', $since, $until);

        $base = AdSet::query()
            ->whereIn('meta_ads_sets.meta_ads_account_id', $accountIds)
            ->when($campaignId, fn ($q) => $q->where('meta_ads_sets.meta_ads_campaign_id', $campaignId))
            ->leftJoin('meta_ads_campaigns', 'meta_ads_campaigns.id', '=', 'meta_ads_sets.meta_ads_campaign_id')
            ->leftJoinSub($insights, 'i', 'i.meta_ads_set_id', '=', 'meta_ads_sets.id')
            ->select(array_merge([
                'meta_ads_sets.id',
                'meta_ads_sets.name',
                'meta_ads_sets.status',
                'meta_ads_sets.effective_status',
                'meta_ads_sets.daily_budget',
                'meta_ads_sets.lifetime_budget',
                'meta_ads_sets.bid_strategy',
                'meta_ads_sets.optimization_goal',
                'meta_ads_sets.billing_event',
                'meta_ads_sets.start_time',
                'meta_ads_sets.end_time',
                'meta_ads_sets.updated_time',
                'meta_ads_sets.meta_ads_campaign_id',
                DB::raw('meta_ads_campaigns.name AS campaign_name'),
            ], $this->metricSelects()));

        $this->applyMetricFilters($base, $metricFilters);

        $rows = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'meta_ads_sets.name'),
                AllowedFilter::exact('status', 'meta_ads_sets.status'),
                AllowedFilter::exact('effective_status', 'meta_ads_sets.effective_status'),
            ])
            ->allowedSorts(array_merge(['name', 'status', 'effective_status', 'daily_budget', 'updated_time'], self::INSIGHTS_METRICS))
            ->defaultSort('-spend')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return ['rows' => $rows];
    }

    private function ads(Request $request, $accountIds, string $since, string $until, ?string $campaignId, ?string $adSetId, array $metricFilters = []): array
    {
        $insights = $this->insightsSubquery('meta_ads_ad_id', $since, $until);

        $base = Ad::query()
            ->whereIn('meta_ads_ads.meta_ads_account_id', $accountIds)
            ->when($campaignId, fn ($q) => $q->where('meta_ads_ads.meta_ads_campaign_id', $campaignId))
            ->when($adSetId, fn ($q) => $q->where('meta_ads_ads.meta_ads_set_id', $adSetId))
            ->leftJoin('meta_ads_campaigns', 'meta_ads_campaigns.id', '=', 'meta_ads_ads.meta_ads_campaign_id')
            ->leftJoin('meta_ads_sets', 'meta_ads_sets.id', '=', 'meta_ads_ads.meta_ads_set_id')
            ->leftJoin('meta_ads_creatives', 'meta_ads_creatives.id', '=', 'meta_ads_ads.meta_ads_creative_id')
            ->leftJoinSub($insights, 'i', 'i.meta_ads_ad_id', '=', 'meta_ads_ads.id')
            ->select(array_merge([
                'meta_ads_ads.id',
                'meta_ads_ads.name',
                'meta_ads_ads.status',
                'meta_ads_ads.effective_status',
                'meta_ads_ads.created_time',
                'meta_ads_ads.updated_time',
                'meta_ads_ads.meta_ads_campaign_id',
                'meta_ads_ads.meta_ads_set_id',
                'meta_ads_ads.meta_ads_creative_id',
                DB::raw('meta_ads_campaigns.name AS campaign_name'),
                DB::raw('meta_ads_sets.name AS ad_set_name'),
                DB::raw('meta_ads_creatives.thumbnail_url AS thumbnail_url'),
                DB::raw('meta_ads_creatives.image_url AS image_url'),
            ], $this->metricSelects()));

        $this->applyMetricFilters($base, $metricFilters);

        $rows = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'meta_ads_ads.name'),
                AllowedFilter::exact('status', 'meta_ads_ads.status'),
                AllowedFilter::exact('effective_status', 'meta_ads_ads.effective_status'),
            ])
            ->allowedSorts(array_merge(['name', 'status', 'effective_status', 'updated_time'], self::INSIGHTS_METRICS))
            ->defaultSort('-spend')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return ['rows' => $rows];
    }
}
