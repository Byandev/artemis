<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class AdsManagerController extends Controller
{
    /**
     * Ads Manager shell. The grid data itself is fetched client-side from
     * data() — this only renders the page with the account list and the initial
     * query state (parsed from the URL so refreshes / shared links restore it).
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        [$since, $until] = $this->resolveDateRange($request);

        $allAccountIds = $this->accountIdsForWorkspace($workspace, $request->user())
            ->map(fn ($id) => (string) $id);
        $selectedAccountIds = $this->resolveSelectedAccounts($request, $allAccountIds);

        $accounts = AdAccount::forWorkspace($workspace)
            ->where('meta_ads_accounts.active_sync', true)
            ->visibleTo($request->user(), $workspace)
            ->select('meta_ads_accounts.id', 'meta_ads_accounts.name')
            ->orderBy('meta_ads_accounts.name')
            ->get()
            ->map(fn ($a) => ['id' => (string) $a->id, 'name' => $a->name]);

        return Inertia::render('workspaces/integrations/meta-ads/index', [
            'workspace' => $workspace,
            'accounts' => $accounts,
            'selectedAccounts' => $selectedAccountIds->values(),
            'dateRange' => ['since' => $since, 'until' => $until],
            'query' => [
                ...$request->only(['sort', 'page', 'since', 'until']),
                'groupBy' => $this->resolveGroupBy($request),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
                'metricFilters' => $this->parseMetricFilters($request),
            ],
        ]);
    }

    /**
     * Shared JSON data endpoint for the grid AND the "ads in this group" modal.
     * Without a scope it returns rows grouped by the requested dimension; with a
     * `scope_by` + `scope` it returns the `ad` aggregation limited to that parent
     * group, so the modal renders the same columns/metrics as the main table.
     */
    public function data(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        [$since, $until] = $this->resolveDateRange($request);

        $allAccountIds = $this->accountIdsForWorkspace($workspace)->map(fn ($id) => (string) $id);
        $accountIds = $this->resolveSelectedAccounts($request, $allAccountIds);
        $metricFilters = $this->parseMetricFilters($request);

        $scopeBy = (string) $request->query('scope_by', '');
        $scopeValue = (string) $request->query('scope', '');

        if ($scopeBy !== '') {
            $groupBy = 'ad';
            $scope = match ($scopeBy) {
                'campaign' => fn ($q) => $q->where('meta_ads_ads.meta_ads_campaign_id', $scopeValue),
                'ad_set' => fn ($q) => $q->where('meta_ads_ads.meta_ads_set_id', $scopeValue),
                'account' => fn ($q) => $q->where('meta_ads_ads.meta_ads_account_id', $scopeValue),
                'ad_name' => fn ($q) => $q->where('meta_ads_ads.name', $scopeValue),
                default => abort(400, 'Unsupported scope_by'),
            };
        } else {
            $groupBy = $this->resolveGroupBy($request);
            $scope = null;
        }

        $rows = $this->aggregate($request, $accountIds, $since, $until, $groupBy, $metricFilters, $scope);

        return response()->json(['rows' => $rows]);
    }

    /**
     * Ad-format whitelist for the creative preview (avoid passing arbitrary
     * values straight to the Graph API).
     */
    private const PREVIEW_FORMATS = [
        'MOBILE_FEED_STANDARD',
        'DESKTOP_FEED_STANDARD',
        'INSTAGRAM_STANDARD',
        'INSTAGRAM_STORY',
        'FACEBOOK_STORY_MOBILE',
    ];

    /**
     * A creative is a video when Meta tags it object_type=VIDEO or it carries a
     * top-level video_id (a few video creatives have no video_id but are still
     * VIDEO). Everything else is treated as an image. object_type alone is
     * unreliable (PHOTO/SHARE/STATUS/PRIVACY_CHECK_FAIL all appear), so both
     * signals are combined.
     */
    private const MEDIA_TYPE_SQL = "CASE WHEN meta_ads_creatives.object_type = 'VIDEO' OR meta_ads_creatives.video_id IS NOT NULL THEN 'video' ELSE 'image' END";

    /**
     * Returns Meta's signed ad-preview iframe src for a single ad. The raw video
     * source is permission-restricted, so this iframe is how video creatives are
     * watched (it also renders image ads). The `d=` token is short-lived, so we
     * resolve it on demand rather than storing it.
     */
    public function adPreview(Request $request, Workspace $workspace, string $ad): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $accountIds = $this->accountIdsForWorkspace($workspace);
        $adModel = Ad::whereIn('meta_ads_account_id', $accountIds)->findOrFail($ad);
        $account = AdAccount::findOrFail($adModel->meta_ads_account_id);

        return response()->json([
            'src' => $this->resolvePreviewSrc($account, $adModel->id, $this->resolveFormat($request)),
        ]);
    }

    /**
     * Creative detail for the drawer: the dimensions panel and the preview
     * iframe src.
     */
    public function adDetail(Request $request, Workspace $workspace, string $ad): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $accountIds = $this->accountIdsForWorkspace($workspace);

        $row = Ad::query()
            ->whereIn('meta_ads_ads.meta_ads_account_id', $accountIds)
            ->where('meta_ads_ads.id', $ad)
            ->leftJoin('meta_ads_sets', 'meta_ads_sets.id', '=', 'meta_ads_ads.meta_ads_set_id')
            ->leftJoin('meta_ads_campaigns', 'meta_ads_campaigns.id', '=', 'meta_ads_ads.meta_ads_campaign_id')
            ->leftJoin('meta_ads_accounts', 'meta_ads_accounts.id', '=', 'meta_ads_ads.meta_ads_account_id')
            ->leftJoin('meta_ads_creatives', 'meta_ads_creatives.id', '=', 'meta_ads_ads.meta_ads_creative_id')
            ->select([
                'meta_ads_ads.id',
                'meta_ads_ads.name',
                'meta_ads_ads.status',
                'meta_ads_ads.effective_status',
                'meta_ads_ads.meta_ads_account_id',
                DB::raw('meta_ads_sets.name AS adset_name'),
                DB::raw('meta_ads_sets.optimization_goal AS optimization_goal'),
                DB::raw('meta_ads_campaigns.name AS campaign_name'),
                DB::raw('meta_ads_accounts.name AS account_name'),
                DB::raw(self::MEDIA_TYPE_SQL.' AS media_type'),
                DB::raw('meta_ads_creatives.call_to_action_type AS call_to_action'),
            ])
            ->firstOrFail();

        $account = AdAccount::findOrFail($row->meta_ads_account_id);

        return response()->json([
            'dimensions' => [
                'ad_status' => $row->effective_status ?? $row->status,
                'optimization_goal' => $row->optimization_goal,
                'ad_name' => $row->name,
                'ad_id' => (string) $row->id,
                'adset_name' => $row->adset_name,
                'campaign_name' => $row->campaign_name,
                'account_name' => $row->account_name,
                'ad_type' => $row->media_type === 'video' ? 'Video' : 'Image',
                'media_type' => $row->media_type,
                'call_to_action' => $row->call_to_action,
            ],
            'preview' => [
                'src' => $this->resolvePreviewSrc($account, $row->id, $this->resolveFormat($request)),
            ],
        ]);
    }

    private function resolveFormat(Request $request): string
    {
        $format = (string) $request->query('format', 'MOBILE_FEED_STANDARD');

        return in_array($format, self::PREVIEW_FORMATS, true) ? $format : 'MOBILE_FEED_STANDARD';
    }

    /**
     * Pull Meta's signed preview iframe src out of the /previews response. The
     * raw video source is permission-restricted, so this iframe is how video
     * creatives are watched (it renders image ads too). The `d=` token is
     * short-lived, so callers resolve it on demand rather than storing it.
     */
    private function resolvePreviewSrc(AdAccount $account, int|string $adId, string $format): ?string
    {
        $response = $account->graphClient()->get($adId.'/previews', ['ad_format' => $format]);
        $body = $response['data'][0]['body'] ?? null;

        if ($body && preg_match('/src="([^"]+)"/', $body, $matches)) {
            return html_entity_decode($matches[1]);
        }

        return null;
    }

    /**
     * Allowed group-by dimensions. Keys are the public `group_by` values; the
     * default is `ad_name`.
     */
    private const GROUP_BY_KEYS = ['ad_name', 'ad', 'campaign', 'ad_set', 'account'];

    private function resolveGroupBy(Request $request): string
    {
        $value = (string) $request->query('group_by', 'ad_name');

        return in_array($value, self::GROUP_BY_KEYS, true) ? $value : 'ad_name';
    }

    /**
     * Intersect the requested account IDs with the accounts the workspace may
     * see, so a member can only query their own accounts. Empty / missing
     * selection defaults to every account.
     */
    private function resolveSelectedAccounts(Request $request, $allAccountIds)
    {
        $requested = $request->query('accounts');

        if (! is_array($requested) || count($requested) === 0) {
            return $allAccountIds->values();
        }

        $requested = array_map('strval', $requested);

        return $allAccountIds->intersect($requested)->values();
    }

    /**
     * Per-dimension query shape. The ENTITY table is the base (so campaigns /
     * ad sets / ads / accounts with no insights in the date range still show,
     * with zeroed metrics); the aggregated insights subquery is LEFT JOINed as
     * `i`. Each config gives: the base Eloquent model, the insights column to
     * group/join on, the entity column to join it to, the entity column to scope
     * by selected account, any extra join (creatives for ads), the non-aggregated
     * select columns, the GROUP BY columns, and the search column.
     */
    private function groupByConfig(string $key): array
    {
        return match ($key) {
            'ad' => [
                'model' => Ad::class,
                'insightKey' => 'meta_ads_ad_id',
                'joinOn' => 'meta_ads_ads.id',
                'accountColumn' => 'meta_ads_ads.meta_ads_account_id',
                'join' => fn ($q) => $q->leftJoin('meta_ads_creatives', 'meta_ads_creatives.id', '=', 'meta_ads_ads.meta_ads_creative_id'),
                'selects' => [
                    'meta_ads_ads.id',
                    'meta_ads_ads.name',
                    'meta_ads_ads.status',
                    'meta_ads_ads.effective_status',
                    DB::raw('meta_ads_creatives.thumbnail_url AS thumbnail_url'),
                    DB::raw('meta_ads_creatives.image_url AS image_url'),
                    DB::raw('meta_ads_creatives.video_id AS video_id'),
                    DB::raw(self::MEDIA_TYPE_SQL.' AS media_type'),
                ],
                'groupBy' => [
                    'meta_ads_ads.id',
                    'meta_ads_ads.name',
                    'meta_ads_ads.status',
                    'meta_ads_ads.effective_status',
                    'meta_ads_creatives.thumbnail_url',
                    'meta_ads_creatives.image_url',
                    'meta_ads_creatives.video_id',
                    'meta_ads_creatives.object_type',
                ],
                'search' => 'meta_ads_ads.name',
            ],
            'ad_name' => [
                'model' => Ad::class,
                'insightKey' => 'meta_ads_ad_id',
                'joinOn' => 'meta_ads_ads.id',
                'accountColumn' => 'meta_ads_ads.meta_ads_account_id',
                'selects' => [
                    DB::raw('MIN(meta_ads_ads.id) AS id'),
                    DB::raw('meta_ads_ads.name AS name'),
                ],
                'groupBy' => ['meta_ads_ads.name'],
                'search' => 'meta_ads_ads.name',
                // Ads sharing this name (within the selected accounts).
                'adsCountExpr' => 'COUNT(DISTINCT meta_ads_ads.id)',
            ],
            'campaign' => [
                'model' => Campaign::class,
                'insightKey' => 'meta_ads_campaign_id',
                'joinOn' => 'meta_ads_campaigns.id',
                'accountColumn' => 'meta_ads_campaigns.meta_ads_account_id',
                'selects' => [
                    'meta_ads_campaigns.id',
                    'meta_ads_campaigns.name',
                    'meta_ads_campaigns.status',
                    'meta_ads_campaigns.effective_status',
                    'meta_ads_campaigns.daily_budget',
                    'meta_ads_campaigns.lifetime_budget',
                ],
                'groupBy' => [
                    'meta_ads_campaigns.id',
                    'meta_ads_campaigns.name',
                    'meta_ads_campaigns.status',
                    'meta_ads_campaigns.effective_status',
                    'meta_ads_campaigns.daily_budget',
                    'meta_ads_campaigns.lifetime_budget',
                ],
                'search' => 'meta_ads_campaigns.name',
                'adsCount' => ['key' => 'meta_ads_campaign_id', 'joinOn' => 'meta_ads_campaigns.id'],
            ],
            'ad_set' => [
                'model' => AdSet::class,
                'insightKey' => 'meta_ads_set_id',
                'joinOn' => 'meta_ads_sets.id',
                'accountColumn' => 'meta_ads_sets.meta_ads_account_id',
                'selects' => [
                    'meta_ads_sets.id',
                    'meta_ads_sets.name',
                    'meta_ads_sets.status',
                    'meta_ads_sets.effective_status',
                    'meta_ads_sets.daily_budget',
                    'meta_ads_sets.lifetime_budget',
                ],
                'groupBy' => [
                    'meta_ads_sets.id',
                    'meta_ads_sets.name',
                    'meta_ads_sets.status',
                    'meta_ads_sets.effective_status',
                    'meta_ads_sets.daily_budget',
                    'meta_ads_sets.lifetime_budget',
                ],
                'search' => 'meta_ads_sets.name',
                'adsCount' => ['key' => 'meta_ads_set_id', 'joinOn' => 'meta_ads_sets.id'],
            ],
            'account' => [
                'model' => AdAccount::class,
                'insightKey' => 'meta_ads_account_id',
                'joinOn' => 'meta_ads_accounts.id',
                'accountColumn' => 'meta_ads_accounts.id',
                'selects' => [
                    'meta_ads_accounts.id',
                    'meta_ads_accounts.name',
                ],
                'groupBy' => [
                    'meta_ads_accounts.id',
                    'meta_ads_accounts.name',
                ],
                'search' => 'meta_ads_accounts.name',
                'adsCount' => ['key' => 'meta_ads_account_id', 'joinOn' => 'meta_ads_accounts.id'],
            ],
        };
    }

    private function aggregate(Request $request, $accountIds, string $since, string $until, string $groupBy, array $metricFilters = [], ?callable $scope = null): array
    {
        $config = $this->groupByConfig($groupBy);

        $insights = $this->insightsSubquery($config['insightKey'], $since, $until);

        $base = $config['model']::query();

        if (isset($config['join'])) {
            ($config['join'])($base);
        }

        $base->leftJoinSub($insights, 'i', 'i.'.$config['insightKey'], '=', $config['joinOn']);

        // Optional extra constraint (e.g. limit ads to a single campaign/ad set).
        if ($scope) {
            $scope($base);
        }

        // Number of ads in each group — shown for every dimension except `ad`
        // (where each row is already a single ad). `ad_name` counts distinct ads
        // sharing the name; the rest join a per-entity ad count subquery.
        $selects = $config['selects'];
        $hasAdsCount = false;

        if (isset($config['adsCountExpr'])) {
            $selects[] = DB::raw($config['adsCountExpr'].' AS ads_count');
            $hasAdsCount = true;
        } elseif (isset($config['adsCount'])) {
            $adsCount = $this->adsCountSubquery($config['adsCount']['key']);
            $base->leftJoinSub($adsCount, 'ac', 'ac.'.$config['adsCount']['key'], '=', $config['adsCount']['joinOn']);
            $selects[] = DB::raw('COALESCE(MAX(ac.ads_count), 0) AS ads_count');
            $hasAdsCount = true;
        }

        $base->whereIn($config['accountColumn'], $accountIds)
            ->select(array_merge($selects, $this->metricSelects()))
            ->groupBy($config['groupBy']);

        $this->applyMetricFilters($base, $metricFilters);

        $rows = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', $config['search']),
            ])
            ->allowedSorts($this->allowedSorts($hasAdsCount))
            ->defaultSort('-spend')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        // Meta entity ids are unsigned bigints (~1e17) that exceed JS's safe
        // integer range (2^53). Serialize them as strings so the frontend
        // doesn't silently round the value — otherwise the per-row preview /
        // detail lookups hit a corrupted id and 404.
        $rows->getCollection()->transform(function ($row) {
            if (isset($row->id)) {
                $row->id = (string) $row->id;
            }
            if (isset($row->video_id)) {
                $row->video_id = (string) $row->video_id;
            }

            return $row;
        });

        return $rows->toArray();
    }

    /**
     * One row per entity key with the count of ads belonging to it. LEFT JOINed
     * as `ac` so the count never fans out the per-entity insight aggregates.
     */
    private function adsCountSubquery(string $keyColumn)
    {
        return DB::table('meta_ads_ads')
            ->select($keyColumn, DB::raw('COUNT(*) AS ads_count'))
            ->groupBy($keyColumn);
    }

    /**
     * Sortable columns: the label, the optional ad count, the direct SUM()
     * metrics (sorted by their select alias), and every computed metric (sorted
     * by its SUM()-based SQL expression via orderByRaw so derived columns like
     * CTR / ROAS / CPC sort server-side too).
     */
    private function allowedSorts(bool $hasAdsCount = false): array
    {
        $sorts = array_merge(['name'], self::INSIGHTS_METRICS);

        if ($hasAdsCount) {
            $sorts[] = 'ads_count';
        }

        foreach ($this->computedMetricMap() as $id => $expr) {
            $sorts[] = AllowedSort::callback(
                $id,
                fn ($query, bool $descending) => $query->orderByRaw($expr.' '.($descending ? 'desc' : 'asc')),
            );
        }

        return $sorts;
    }

    private static array $OP_MAP = [
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<=',
        'eq' => '=',
    ];

    /**
     * Returns a safe SQL expression for any filterable metric (direct or computed),
     * built from SUM() aggregates so it can be used in a HAVING clause against the
     * grouped query. Direct metrics resolve to COALESCE(SUM(i.`col`), 0). Computed
     * metrics mirror the safeDiv() frontend logic: result is 0 when the denominator
     * is 0, matching what the table displays.
     */
    private function metricSqlExpression(string $id): ?string
    {
        if (in_array($id, self::INSIGHTS_METRICS, true)) {
            return "COALESCE(SUM(i.`{$id}`), 0)";
        }

        return $this->computedMetricMap()[$id] ?? null;
    }

    /**
     * Computed (derived) metrics keyed by id => SUM()-based SQL expression.
     * Shared by HAVING metric filters and ORDER BY sorting so both stay in sync
     * with the frontend safeDiv() display logic.
     *
     * @return array<string, string>
     */
    private function computedMetricMap(): array
    {
        $c = fn (string $col) => "COALESCE(SUM(i.`{$col}`), 0)";
        $div = fn (string $a, string $b) => "COALESCE({$c($a)} / NULLIF({$c($b)}, 0), 0)";

        return [
            // Delivery & Traffic
            'frequency' => $div('impressions', 'reach'),
            'ctr' => $div('clicks', 'impressions'),
            'cpc' => $div('spend', 'clicks'),
            'cpm' => "COALESCE({$c('spend')} / NULLIF({$c('impressions')}, 0), 0) * 1000",
            'link_ctr' => $div('link_clicks', 'impressions'),
            'cost_per_link_click' => $div('spend', 'link_clicks'),
            'outbound_ctr' => $div('outbound_clicks', 'impressions'),
            'cost_per_outbound_click' => $div('spend', 'outbound_clicks'),
            'cost_per_estimated_ad_recaller' => $div('spend', 'estimated_ad_recallers'),
            // Video
            'hook_rate' => $div('video_3sec_views', 'impressions'),
            'cost_per_3s_view' => $div('spend', 'video_3sec_views'),
            'hold_rate' => $div('video_thruplay_views', 'video_3sec_views'),
            'cost_per_thruplay' => $div('spend', 'video_thruplay_views'),
            'body_rate_25' => $div('video_p25_views', 'video_3sec_views'),
            'cost_per_p25' => $div('spend', 'video_p25_views'),
            'body_rate_50' => $div('video_p50_views', 'video_3sec_views'),
            'cost_per_p50' => $div('spend', 'video_p50_views'),
            'body_rate_75' => $div('video_p75_views', 'video_3sec_views'),
            'cost_per_p75' => $div('spend', 'video_p75_views'),
            'body_rate_100' => $div('video_p100_views', 'video_3sec_views'),
            'cost_per_p100' => $div('spend', 'video_p100_views'),
            // Engagement
            'cost_per_page_engagement' => $div('spend', 'page_engagement'),
            'page_engagement_rate' => $div('page_engagement', 'clicks'),
            'cost_per_page_like' => $div('spend', 'page_likes'),
            'page_likes_rate' => $div('page_likes', 'clicks'),
            'cost_per_photo_view' => $div('spend', 'page_photo_views'),
            'photo_views_rate' => $div('page_photo_views', 'clicks'),
            'cost_per_post_engagement' => $div('spend', 'post_engagement'),
            'cost_per_post_comment' => $div('spend', 'post_comments'),
            'post_comments_rate' => $div('post_comments', 'clicks'),
            'cost_per_post_share' => $div('spend', 'post_shares'),
            'post_shares_rate' => $div('post_shares', 'clicks'),
            'cost_per_post_save' => $div('spend', 'post_saves'),
            'post_saves_rate' => $div('post_saves', 'clicks'),
            'post_reactions_rate' => $div('post_reactions', 'clicks'),
            // Messaging
            'cost_per_messaging_first_reply' => $div('spend', 'messaging_first_replies'),
            'messaging_first_reply_rate' => $div('messaging_first_replies', 'clicks'),
            'cost_per_messaging_conversation_started' => $div('spend', 'messaging_conversations_started'),
            'messaging_conversation_started_rate' => $div('messaging_conversations_started', 'clicks'),
            // Commerce & Leads
            'cost_per_initiated_checkout' => $div('spend', 'initiate_checkout'),
            'initiated_checkout_rate' => $div('initiate_checkout', 'clicks'),
            'conversion_rate' => $div('conversions', 'clicks'),
            'avg_purchase_value' => $div('purchase_value', 'purchases'),
            'cost_per_purchase' => $div('spend', 'purchases'),
            'purchase_conv_rate' => $div('purchases', 'clicks'),
            'roas' => $div('purchase_value', 'spend'),
            'gross_profit_per_transaction' => "COALESCE(({$c('purchase_value')} - {$c('spend')}) / NULLIF({$c('purchases')}, 0), 0)",
            'cost_per_on_facebook_lead' => $div('spend', 'on_facebook_leads'),
            'on_facebook_lead_rate' => $div('on_facebook_leads', 'clicks'),
            'cost_per_lead' => $div('spend', 'leads'),
            'lead_conv_rate' => $div('leads', 'clicks'),
        ];
    }

    private function parseMetricFilters(Request $request): array
    {
        $raw = $request->query('metric_filters');
        if (! $raw) {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 5, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $valid = [];
        foreach ($decoded as $f) {
            $field = $f['field'] ?? null;
            $op = $f['op'] ?? null;
            $value = $f['value'] ?? null;

            if (! is_string($field) || $this->metricSqlExpression($field) === null) {
                continue;
            }

            if ($op === 'range') {
                $v2 = $f['value2'] ?? null;
                if (! is_numeric($value) || ! is_numeric($v2)) {
                    continue;
                }
                $valid[] = ['field' => $field, 'op' => 'range', 'value' => (float) $value, 'value2' => (float) $v2];
            } else {
                if (! isset(self::$OP_MAP[$op]) || ! is_numeric($value)) {
                    continue;
                }
                $valid[] = ['field' => $field, 'op' => $op, 'value' => (float) $value];
            }
        }

        return $valid;
    }

    /**
     * Metric filters apply to SUM() aggregates, so they're emitted as HAVING
     * clauses on the grouped query.
     */
    private function applyMetricFilters($query, array $filters): void
    {
        foreach ($filters as $f) {
            $expr = $this->metricSqlExpression($f['field']);
            if ($f['op'] === 'range') {
                $query->havingRaw("({$expr}) BETWEEN ? AND ?", [$f['value'], $f['value2']]);
            } else {
                $sqlOp = self::$OP_MAP[$f['op']];
                $query->havingRaw("({$expr}) {$sqlOp} ?", [$f['value']]);
            }
        }
    }

    private function accountIdsForWorkspace(Workspace $workspace, ?User $user = null)
    {
        return AdAccount::forWorkspace($workspace)
            ->where('meta_ads_accounts.active_sync', true)
            ->when($user, fn ($q) => $q->visibleTo($user, $workspace))
            ->pluck('meta_ads_accounts.id');
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
     * metric here surfaces it to the Ads Manager automatically.
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

    /**
     * Insights aggregated to one row per entity key (ad / campaign / set /
     * account id) for the date range. LEFT JOINed onto the entity base as `i`,
     * so entities with no insights in the window simply get NULL → 0 metrics.
     */
    private function insightsSubquery(string $keyColumn, string $since, string $until)
    {
        $selects = [$keyColumn];
        foreach (self::INSIGHTS_METRICS as $col) {
            $selects[] = DB::raw("SUM({$col}) AS {$col}");
        }

        return DB::table('meta_ads_insights')
            ->whereBetween('date', [$since, $until])
            ->select($selects)
            ->groupBy($keyColumn);
    }

    /**
     * Outer SUM() over the joined insights subquery, aliased to the bare metric
     * name so missing-insight rows fold to 0 and sorting can target the alias.
     * The outer SUM collapses many ads into one row when grouping by ad name.
     */
    private function metricSelects(): array
    {
        return array_map(
            fn (string $col) => DB::raw("COALESCE(SUM(i.{$col}), 0) AS {$col}"),
            self::INSIGHTS_METRICS,
        );
    }
}
