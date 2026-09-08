<?php

namespace Modules\MetaAds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\CustomBreakdown;
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
            'members' => $this->workspaceMembers($workspace),
            'selectedAccounts' => $selectedAccountIds->values(),
            'dateRange' => ['since' => $since, 'until' => $until],
            'query' => [
                ...$request->only(['sort', 'page', 'since', 'until']),
                'groupBy' => $this->resolveGroupBy($request),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
                'metricFilters' => $this->parseMetricFilters($request),
            ],
            'objectives' => $this->availableObjectives($allAccountIds),
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

        $allAccountIds = $this->accountIdsForWorkspace($workspace, $request->user())->map(fn ($id) => (string) $id);
        $accountIds = $this->resolveSelectedAccounts($request, $allAccountIds);
        $metricFilters = $this->parseMetricFilters($request);
        $dateFilters = $this->parseDateFilters($request);
        $objectives = $this->parseObjectiveFilters($request);

        $scopeBy = (string) $request->query('scope_by', '');
        $scopeValue = (string) $request->query('scope', '');

        // Internal-creator filter (ad-level only). A member id, the literal
        // "unassigned", or empty for all. Applied only to ad-grained groupings.
        $creatorFilter = (string) $request->query('creator_id', '');
        $creatorFilter = $creatorFilter === '' ? null : $creatorFilter;

        // Custom breakdown: group ads into the saved, rule-defined buckets
        // instead of a fixed dimension. Encoded as `group_by=custom:{id}`.
        $groupByRaw = (string) $request->query('group_by', '');
        if ($scopeBy === '' && str_starts_with($groupByRaw, 'custom:')) {
            $breakdownId = (int) substr($groupByRaw, 7);
            $rows = $this->aggregateCustomBreakdown(
                $request, $workspace, $accountIds, $since, $until, $breakdownId, $metricFilters, $creatorFilter, $dateFilters
            );

            return response()->json(['rows' => $rows]);
        }

        if ($scopeBy !== '') {
            $groupBy = 'ad';
            $scope = $this->scopeFor($scopeBy, $scopeValue);
        } else {
            $groupBy = $this->resolveGroupBy($request);
            $scope = null;
        }

        $rows = $this->aggregate($request, $accountIds, $since, $until, $groupBy, $metricFilters, $scope, $creatorFilter, $dateFilters, $objectives);

        return response()->json(['rows' => $rows]);
    }

    /**
     * Narrows an ad-grained query to the ads under one row of a breakdown.
     * Shared by the drill-down table and the per-row timeline, so a chart always
     * covers exactly the ads its modal would have listed.
     */
    private function scopeFor(string $scopeBy, string $scopeValue): callable
    {
        return match ($scopeBy) {
            // A row of the `ad` breakdown is a single ad, charted on its own.
            'ad' => fn ($q) => $q->where('meta_ads_ads.id', $scopeValue),
            'campaign' => fn ($q) => $q->where('meta_ads_ads.meta_ads_campaign_id', $scopeValue),
            'ad_set' => fn ($q) => $q->where('meta_ads_ads.meta_ads_set_id', $scopeValue),
            'account' => fn ($q) => $q->where('meta_ads_ads.meta_ads_account_id', $scopeValue),
            'ad_name' => fn ($q) => $q->where('meta_ads_ads.name', $scopeValue),
            // Ads reach a page through their ad set; "0" is the unassigned
            // bucket the page breakdown emits for a null meta_page_id.
            'page' => fn ($q) => $q->whereIn(
                'meta_ads_ads.meta_ads_set_id',
                AdSet::query()->select('id')->when(
                    $scopeValue === '' || $scopeValue === '0',
                    fn ($sets) => $sets->whereNull('meta_page_id'),
                    fn ($sets) => $sets->where('meta_page_id', $scopeValue),
                )
            ),
            // Same chain one step further: the ad set's page, then that page's
            // owner. "0" is the bucket for ads whose ad set promotes no page,
            // or one that resolves to no owner.
            'page_owner' => fn ($q) => $q->whereIn(
                'meta_ads_ads.meta_ads_set_id',
                AdSet::query()->select('id')->when(
                    $scopeValue === '' || $scopeValue === '0',
                    fn ($sets) => $sets->where(fn ($w) => $w
                        ->whereNull('meta_page_id')
                        ->orWhereNotIn('meta_page_id', $this->ownedPageIds())),
                    fn ($sets) => $sets->whereIn('meta_page_id', $this->ownedPageIds($scopeValue)),
                )
            ),
            // The goal lives on the ad set, so a row's ads are those whose set
            // carries it. "0" is the bucket for ad sets with no goal.
            'optimization_goal' => fn ($q) => $q->whereIn(
                'meta_ads_ads.meta_ads_set_id',
                AdSet::query()->select('id')->when(
                    $scopeValue === '' || $scopeValue === '0',
                    fn ($sets) => $sets->whereNull('optimization_goal'),
                    fn ($sets) => $sets->where('optimization_goal', $scopeValue),
                )
            ),
            // The objective lives on the campaign, which ads reference directly,
            // so this is one hop rather than the ad-set chain above. "0" is the
            // bucket for campaigns Meta reported no objective for.
            'campaign_objective' => fn ($q) => $q->whereIn(
                'meta_ads_ads.meta_ads_campaign_id',
                Campaign::query()->select('id')->when(
                    $scopeValue === '' || $scopeValue === '0',
                    fn ($campaigns) => $campaigns->whereNull('objective'),
                    fn ($campaigns) => $campaigns->where('objective', $scopeValue),
                )
            ),
            default => abort(400, 'Unsupported scope_by'),
        };
    }

    /**
     * Day-by-day metrics for one breakdown row, for the per-row timeline chart.
     * Returns the same raw insight columns the grid does — one point per day
     * instead of one row per entity — so the frontend derives computed metrics
     * (ROAS, CTR, CPM…) per day with the very same formulas the table uses.
     *
     * Every day in the range is emitted, including days the ads didn't run, so
     * the line shows real gaps as zeroes rather than silently compressing time.
     */
    public function timeseries(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        [$since, $until] = $this->resolveDateRange($request);

        $allAccountIds = $this->accountIdsForWorkspace($workspace, $request->user())->map(fn ($id) => (string) $id);
        $accountIds = $this->resolveSelectedAccounts($request, $allAccountIds);

        $scopeBy = (string) $request->query('scope_by', '');
        $scopeValue = (string) $request->query('scope', '');

        if ($scopeBy === '') {
            abort(400, 'scope_by is required');
        }

        $selects = [DB::raw('meta_ads_insights.date AS date')];
        foreach (self::INSIGHTS_METRICS as $col) {
            $selects[] = DB::raw("COALESCE(SUM(meta_ads_insights.{$col}), 0) AS {$col}");
        }

        // Joined to the ads table so the shared scope closures — which all read
        // meta_ads_ads columns — apply unchanged.
        $query = DB::table('meta_ads_insights')
            ->join('meta_ads_ads', 'meta_ads_ads.id', '=', 'meta_ads_insights.meta_ads_ad_id')
            ->whereBetween('meta_ads_insights.date', [$since, $until])
            ->whereIn('meta_ads_ads.meta_ads_account_id', $accountIds);

        ($this->scopeFor($scopeBy, $scopeValue))($query);

        // Same creator filter the grid is under, so the chart matches the row.
        $creatorFilter = (string) $request->query('creator_id', '');
        $this->applyCreatorFilter($query, $creatorFilter === '' ? null : $creatorFilter);

        $byDate = $query->select($selects)
            ->groupBy('meta_ads_insights.date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $zero = array_fill_keys(self::INSIGHTS_METRICS, 0);
        $points = [];

        for ($day = Carbon::parse($since); $day->lte(Carbon::parse($until)); $day->addDay()) {
            $key = $day->toDateString();
            $row = $byDate->get($key);

            $points[] = [
                'date' => $key,
                ...($row ? array_map(
                    fn ($col) => (float) ($row->{$col} ?? 0),
                    array_combine(self::INSIGHTS_METRICS, self::INSIGHTS_METRICS),
                ) : $zero),
            ];
        }

        return response()->json(['points' => $points]);
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

    /** Same split as MEDIA_TYPE_SQL, but with the human label used as a group name. */
    private const AD_TYPE_LABEL_SQL = "CASE WHEN meta_ads_creatives.object_type = 'VIDEO' OR meta_ads_creatives.video_id IS NOT NULL THEN 'Video' ELSE 'Image' END";

    /**
     * Group name for the page breakdown. Ad sets whose promoted_object carried
     * no page id — and pages that were deleted on our side — collect in one
     * bucket rather than showing as a blank row.
     */
    private const PAGE_LABEL_SQL = "COALESCE(pages.name, 'Unassigned page')";

    /**
     * Group name for the page-owner breakdown. Ads whose page is unknown — no
     * promoted page, a page deleted on our side, or an owner with no user row —
     * share one bucket instead of showing as blank rows.
     */
    private const PAGE_OWNER_LABEL_SQL = "COALESCE(users.name, 'Unassigned owner')";

    /**
     * Group name for the optimization-goal breakdown. The raw Meta enum is kept
     * verbatim (that's how the creative drawer already shows it); ad sets Meta
     * reported no goal for share one bucket instead of showing as blank rows.
     */
    private const OPTIMIZATION_GOAL_LABEL_SQL = "COALESCE(meta_ads_sets.optimization_goal, 'Unassigned goal')";

    /**
     * Group name for the campaign-objective breakdown. Same treatment as the
     * optimization goal: Meta's raw enum verbatim (OUTCOME_SALES,
     * OUTCOME_ENGAGEMENT, ...), with campaigns Meta reported no objective for
     * sharing one bucket instead of showing as blank rows.
     */
    private const CAMPAIGN_OBJECTIVE_LABEL_SQL = "COALESCE(meta_ads_campaigns.objective, 'Unassigned objective')";

    /**
     * Returns Meta's signed ad-preview iframe src for a single ad. The raw video
     * source is permission-restricted, so this iframe is how video creatives are
     * watched (it also renders image ads). The `d=` token is short-lived, so we
     * resolve it on demand rather than storing it.
     */
    public function adPreview(Request $request, Workspace $workspace, string $ad): JsonResponse
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        $accountIds = $this->accountIdsForWorkspace($workspace, $request->user());
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

        $accountIds = $this->accountIdsForWorkspace($workspace, $request->user());

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
    /** Filter-builder field id for the campaign-objective dimension row. */
    private const OBJECTIVE_FILTER_FIELD = 'campaign_objective';

    private const GROUP_BY_KEYS = ['ad_name', 'ad', 'campaign', 'ad_set', 'account', 'ad_type', 'page', 'page_owner', 'optimization_goal', 'campaign_objective'];

    private function resolveGroupBy(Request $request): string
    {
        $value = (string) $request->query('group_by', 'ad_name');

        return in_array($value, self::GROUP_BY_KEYS, true) ? $value : 'ad_name';
    }

    /**
     * Operator for the breakdown-name filter. Defaults to `contains` so the
     * existing grid keeps its partial-match behaviour.
     */
    private const NAME_OPS = ['is', 'is_not', 'contains', 'not_contains'];

    private function resolveNameOp(Request $request): string
    {
        $value = (string) $request->query('name_op', 'contains');

        return in_array($value, self::NAME_OPS, true) ? $value : 'contains';
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
                'join' => fn ($q) => $q
                    ->leftJoin('meta_ads_creatives', 'meta_ads_creatives.id', '=', 'meta_ads_ads.meta_ads_creative_id')
                    // Internal creator tag (a workspace member). One row per ad, so
                    // grouping by the creator columns is safe.
                    ->leftJoin('users', 'users.id', '=', 'meta_ads_ads.creator_id'),
                'selects' => [
                    'meta_ads_ads.id',
                    'meta_ads_ads.name',
                    'meta_ads_ads.status',
                    'meta_ads_ads.effective_status',
                    'meta_ads_ads.creator_id',
                    DB::raw('users.name AS creator_name'),
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
                    'meta_ads_ads.creator_id',
                    'users.name',
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
            // Buckets ads into Video / Image by their creative's media type.
            'ad_type' => [
                'model' => Ad::class,
                'insightKey' => 'meta_ads_ad_id',
                'joinOn' => 'meta_ads_ads.id',
                'accountColumn' => 'meta_ads_ads.meta_ads_account_id',
                'join' => fn ($q) => $q->leftJoin('meta_ads_creatives', 'meta_ads_creatives.id', '=', 'meta_ads_ads.meta_ads_creative_id'),
                'selects' => [
                    DB::raw('MIN(meta_ads_ads.id) AS id'),
                    DB::raw(self::AD_TYPE_LABEL_SQL.' AS name'),
                ],
                'groupBy' => [DB::raw(self::AD_TYPE_LABEL_SQL)],
                'search' => 'meta_ads_ads.name',
                'adsCountExpr' => 'COUNT(DISTINCT meta_ads_ads.id)',
            ],
            // Buckets ads by the Facebook page their ad set promotes. Ad-grained
            // like ad_name: the page id lives on meta_ads_sets, and a Pancake
            // page's primary key IS that FB page id, so `pages` joins directly.
            'page' => [
                'model' => Ad::class,
                'insightKey' => 'meta_ads_ad_id',
                'joinOn' => 'meta_ads_ads.id',
                'accountColumn' => 'meta_ads_ads.meta_ads_account_id',
                'join' => fn ($q) => $q
                    ->leftJoin('meta_ads_sets', 'meta_ads_sets.id', '=', 'meta_ads_ads.meta_ads_set_id')
                    // A raw join skips the model's SoftDeletes, so deleted pages
                    // are excluded here and fall into the unassigned bucket.
                    ->leftJoin('pages', function ($join) {
                        $join->on('pages.id', '=', 'meta_ads_sets.meta_page_id')
                            ->whereNull('pages.deleted_at');
                    }),
                'selects' => [
                    // 0, not null, so the unassigned row survives the string
                    // cast and can be passed back as a scope value.
                    DB::raw('COALESCE(meta_ads_sets.meta_page_id, 0) AS id'),
                    DB::raw(self::PAGE_LABEL_SQL.' AS name'),
                ],
                'groupBy' => ['meta_ads_sets.meta_page_id', 'pages.name'],
                // Searches real page names; the unassigned bucket has none.
                'search' => 'pages.name',
                'adsCountExpr' => 'COUNT(DISTINCT meta_ads_ads.id)',
            ],
            // Buckets ads by the workspace member who owns the Facebook page
            // their ad set promotes. Same ad-grained chain as `page`, one join
            // further: ad -> ad set -> page -> owning user.
            'page_owner' => [
                'model' => Ad::class,
                'insightKey' => 'meta_ads_ad_id',
                'joinOn' => 'meta_ads_ads.id',
                'accountColumn' => 'meta_ads_ads.meta_ads_account_id',
                'join' => fn ($q) => $q
                    ->leftJoin('meta_ads_sets', 'meta_ads_sets.id', '=', 'meta_ads_ads.meta_ads_set_id')
                    // A raw join skips the model's SoftDeletes, so deleted pages
                    // are excluded here and fall into the unassigned bucket.
                    ->leftJoin('pages', function ($join) {
                        $join->on('pages.id', '=', 'meta_ads_sets.meta_page_id')
                            ->whereNull('pages.deleted_at');
                    })
                    ->leftJoin('users', 'users.id', '=', 'pages.owner_id'),
                'selects' => [
                    // Keyed off the joined user, not pages.owner_id, so an owner
                    // whose user row is gone lands in the unassigned bucket (id
                    // 0) rather than in a nameless row of its own.
                    DB::raw('COALESCE(users.id, 0) AS id'),
                    DB::raw(self::PAGE_OWNER_LABEL_SQL.' AS name'),
                ],
                'groupBy' => ['users.id', 'users.name'],
                // Searches real owner names; the unassigned bucket has none.
                'search' => 'users.name',
                'adsCountExpr' => 'COUNT(DISTINCT meta_ads_ads.id)',
            ],
            // Buckets ads by the optimization goal of the ad set they run under
            // (MESSAGING_PURCHASE_CONVERSION, CONVERSATIONS, VALUE, ...).
            // Ad-grained like `page`: the goal lives on meta_ads_sets, so the
            // ad set is joined and the metrics stay summed over ads.
            'optimization_goal' => [
                'model' => Ad::class,
                'insightKey' => 'meta_ads_ad_id',
                'joinOn' => 'meta_ads_ads.id',
                'accountColumn' => 'meta_ads_ads.meta_ads_account_id',
                'join' => fn ($q) => $q
                    ->leftJoin('meta_ads_sets', 'meta_ads_sets.id', '=', 'meta_ads_ads.meta_ads_set_id'),
                'selects' => [
                    // '0', not null, so the unassigned row survives the string
                    // cast and can be passed back as a scope value. No real Meta
                    // goal is '0', so the sentinel can't collide with one.
                    DB::raw("COALESCE(meta_ads_sets.optimization_goal, '0') AS id"),
                    DB::raw(self::OPTIMIZATION_GOAL_LABEL_SQL.' AS name'),
                ],
                'groupBy' => ['meta_ads_sets.optimization_goal'],
                // Searches real goals; the unassigned bucket has none.
                'search' => 'meta_ads_sets.optimization_goal',
                'adsCountExpr' => 'COUNT(DISTINCT meta_ads_ads.id)',
            ],
            // Buckets ads by the objective of the campaign they run under
            // (OUTCOME_SALES, OUTCOME_ENGAGEMENT, ...). Ad-grained like
            // `optimization_goal`, but the objective lives on the campaign and
            // ads carry meta_ads_campaign_id directly, so this joins campaigns
            // in one hop rather than going through the ad set.
            'campaign_objective' => [
                'model' => Ad::class,
                'insightKey' => 'meta_ads_ad_id',
                'joinOn' => 'meta_ads_ads.id',
                'accountColumn' => 'meta_ads_ads.meta_ads_account_id',
                'join' => fn ($q) => $q
                    ->leftJoin('meta_ads_campaigns', 'meta_ads_campaigns.id', '=', 'meta_ads_ads.meta_ads_campaign_id'),
                'selects' => [
                    // '0', not null, so the unassigned row survives the string
                    // cast and can be passed back as a scope value. No real Meta
                    // objective is '0', so the sentinel can't collide with one.
                    DB::raw("COALESCE(meta_ads_campaigns.objective, '0') AS id"),
                    DB::raw(self::CAMPAIGN_OBJECTIVE_LABEL_SQL.' AS name'),
                ],
                'groupBy' => ['meta_ads_campaigns.objective'],
                // Searches real objectives; the unassigned bucket has none.
                'search' => 'meta_ads_campaigns.objective',
                'adsCountExpr' => 'COUNT(DISTINCT meta_ads_ads.id)',
            ],
        };
    }

    /**
     * Page ids that resolve to a real owner, optionally a single one. Mirrors
     * the page_owner breakdown's joins — soft-deleted pages and owners with no
     * user row drop out — so a drill-down buckets ads exactly as the grid did.
     */
    private function ownedPageIds(?string $ownerId = null)
    {
        return Page::query()
            ->join('users', 'users.id', '=', 'pages.owner_id')
            ->when($ownerId !== null, fn ($q) => $q->where('users.id', $ownerId))
            ->select('pages.id');
    }

    private function aggregate(Request $request, $accountIds, string $since, string $until, string $groupBy, array $metricFilters = [], ?callable $scope = null, ?string $creatorFilter = null, array $dateFilters = [], array $objectives = []): array
    {
        $config = $this->groupByConfig($groupBy);

        $insights = $this->insightsSubquery($config['insightKey'], $since, $until, $objectives);

        $base = $config['model']::query();

        if (isset($config['join'])) {
            ($config['join'])($base);
        }

        $base->leftJoinSub($insights, 'i', 'i.'.$config['insightKey'], '=', $config['joinOn']);

        // Optional extra constraint (e.g. limit ads to a single campaign/ad set).
        if ($scope) {
            $scope($base);
        }

        // Creator filter is ad-level only — apply it just for ad-grained
        // groupings (ad / ad_name / ad_type), whose base table is meta_ads_ads.
        if ($config['model'] === Ad::class) {
            $this->applyCreatorFilter($base, $creatorFilter);
        }

        // Row-level lifecycle dates — a WHERE, so it runs before aggregation.
        $this->applyDateFilters($base, $dateFilters, $groupBy);

        // Campaign objective — narrows which rows survive; the insights
        // subquery above already narrowed what they're allowed to sum.
        $this->applyObjectiveFilter($base, $config, $objectives);

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

        // The breakdown-name filter. Defaults to a partial "contains" match (the
        // existing grid behaviour); the report builder can pass name_op to switch
        // to is / is not / does-not-contain — matching the SuperAds filter bar.
        $nameOp = $this->resolveNameOp($request);
        $searchColumn = $config['search'];

        $rows = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) use ($searchColumn, $nameOp) {
                    $value = trim((string) $value);
                    if ($value === '') {
                        return;
                    }

                    match ($nameOp) {
                        'is' => $query->where($searchColumn, '=', $value),
                        'is_not' => $query->where($searchColumn, '!=', $value),
                        'not_contains' => $query->where($searchColumn, 'not like', '%'.$value.'%'),
                        default => $query->where($searchColumn, 'like', '%'.$value.'%'),
                    };
                }),
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
     * Group ads into a custom breakdown's named, rule-defined buckets. Each row
     * is one group; ads matching no group are excluded. The bucketing is a CASE
     * expression over the ad name, and metrics aggregate exactly like the fixed
     * dimensions (LEFT JOINed insights, summed per group).
     */
    private function aggregateCustomBreakdown(
        Request $request,
        Workspace $workspace,
        $accountIds,
        string $since,
        string $until,
        int $breakdownId,
        array $metricFilters = [],
        ?string $creatorFilter = null,
        array $dateFilters = []
    ): array {
        $breakdown = CustomBreakdown::where('workspace_id', $workspace->id)->findOrFail($breakdownId);

        $case = $this->customBreakdownCaseSql($breakdown->groups ?? []);
        if ($case === null) {
            $perPage = $request->integer('per_page', 25);

            return (new LengthAwarePaginator([], 0, $perPage, 1))->toArray();
        }

        $insights = $this->insightsSubquery('meta_ads_ad_id', $since, $until);

        $base = Ad::query()
            ->leftJoinSub($insights, 'i', 'i.meta_ads_ad_id', '=', 'meta_ads_ads.id')
            ->whereIn('meta_ads_ads.meta_ads_account_id', $accountIds)
            ->whereRaw("({$case}) IS NOT NULL")
            ->tap(fn ($q) => $this->applyCreatorFilter($q, $creatorFilter))
            ->select(array_merge(
                [
                    DB::raw("({$case}) AS name"),
                    DB::raw('COUNT(DISTINCT meta_ads_ads.id) AS ads_count'),
                ],
                $this->metricSelects(),
            ))
            ->groupBy(DB::raw($case));

        // The base here is meta_ads_ads, so the ad-grained date rules apply.
        $this->applyDateFilters($base, $dateFilters, 'ad');

        $this->applyMetricFilters($base, $metricFilters);

        $rows = QueryBuilder::for($base)
            ->allowedSorts($this->allowedSorts(true))
            ->defaultSort('-spend')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        // Each row is a group; key it by its (unique) name for the frontend.
        $rows->getCollection()->transform(function ($row) {
            $row->id = (string) ($row->name ?? '');

            return $row;
        });

        return $rows->toArray();
    }

    /**
     * Build the CASE expression that buckets an ad into the first matching
     * group. Values are quoted via PDO (these are workspace-defined rules, not
     * request input), so no bindings are threaded through the grouped query.
     */
    private function customBreakdownCaseSql(array $groups): ?string
    {
        $pdo = DB::connection()->getPdo();
        $whens = [];

        foreach ($groups as $group) {
            $name = $group['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            $conds = [];
            foreach ($group['rules'] ?? [] as $rule) {
                $frag = $this->customRuleSql($rule, $pdo);
                if ($frag !== null) {
                    $conds[] = $frag;
                }
            }

            if (empty($conds)) {
                continue;
            }

            $joiner = (($group['match'] ?? 'all') === 'any') ? ' OR ' : ' AND ';
            $whens[] = 'WHEN ('.implode($joiner, $conds).') THEN '.$pdo->quote($name);
        }

        return empty($whens) ? null : 'CASE '.implode(' ', $whens).' END';
    }

    /** A single name rule → safe SQL fragment, or null when invalid. */
    private function customRuleSql(array $rule, \PDO $pdo): ?string
    {
        if (($rule['field'] ?? null) !== 'name') {
            return null;
        }

        $value = $rule['value'] ?? null;
        if (! is_string($value) || $value === '') {
            return null;
        }

        $col = 'meta_ads_ads.name';
        $eq = $pdo->quote($value);
        $like = $pdo->quote('%'.$value.'%');

        return match ($rule['op'] ?? null) {
            'is' => "{$col} = {$eq}",
            'is_not' => "{$col} <> {$eq}",
            'contains' => "{$col} LIKE {$like}",
            'not_contains' => "{$col} NOT LIKE {$like}",
            default => null,
        };
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

    private const DATE_FILTER_FIELDS = ['created_date', 'started_date'];

    private const DATE_FILTER_OPS = ['on', 'before', 'after', 'between'];

    /**
     * Row-level lifecycle date filters (`date_filters`), a JSON array mirroring
     * `metric_filters`. These constrain WHICH rows are included by the entity's
     * own created/start date — unrelated to `since`/`until`, which pick which
     * insight days get summed. Malformed entries are dropped, not rejected.
     */
    private function parseDateFilters(Request $request): array
    {
        $raw = $request->query('date_filters');
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
            $value = $this->asDate($f['value'] ?? null);

            if (! in_array($field, self::DATE_FILTER_FIELDS, true)) {
                continue;
            }
            if (! in_array($op, self::DATE_FILTER_OPS, true) || $value === null) {
                continue;
            }

            if ($op === 'between') {
                $value2 = $this->asDate($f['value2'] ?? null);
                if ($value2 === null) {
                    continue;
                }
                // Tolerate a reversed range rather than returning nothing.
                [$from, $to] = $value <= $value2 ? [$value, $value2] : [$value2, $value];
                $valid[] = ['field' => $field, 'op' => 'between', 'value' => $from, 'value2' => $to];

                continue;
            }

            $valid[] = ['field' => $field, 'op' => $op, 'value' => $value];
        }

        return $valid;
    }

    /**
     * A `Y-m-d` string, or null when the input isn't one.
     */
    private function asDate($value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Which column answers each date filter for a given breakdown. Ads carry no
     * `start_time` of their own, so an ad-grained "started" is answered through
     * the owning ad set (handled in applyDateFilters). Accounts have neither
     * date, so both filters are dropped there.
     */
    private function dateFilterColumn(string $field, string $groupBy): ?string
    {
        if ($field === 'created_date') {
            return match ($groupBy) {
                'campaign' => 'meta_ads_campaigns.created_time',
                'ad_set' => 'meta_ads_sets.created_time',
                'ad', 'ad_name', 'ad_type', 'page', 'page_owner', 'optimization_goal', 'campaign_objective' => 'meta_ads_ads.created_time',
                default => null,
            };
        }

        return match ($groupBy) {
            // campaign_objective joins the campaign too, so it reads the same column.
            'campaign', 'campaign_objective' => 'meta_ads_campaigns.start_time',
            // The page breakdowns already join the ad set, so they read the
            // column directly instead of the subquery fallback below.
            'ad_set', 'page', 'page_owner', 'optimization_goal' => 'meta_ads_sets.start_time',
            default => null,
        };
    }

    /**
     * Date filters constrain the rows themselves, so they're plain WHERE
     * clauses on the pre-aggregation query.
     */
    private function applyDateFilters($query, array $filters, string $groupBy): void
    {
        foreach ($filters as $f) {
            $column = $this->dateFilterColumn($f['field'], $groupBy);

            if ($column !== null) {
                $this->applyDateCondition($query, $column, $f);

                continue;
            }

            // Ads have no start_time — scope by the ad set that owns them.
            if ($f['field'] === 'started_date' && in_array($groupBy, ['ad', 'ad_name', 'ad_type'], true)) {
                $query->whereIn('meta_ads_ads.meta_ads_set_id', function ($sub) use ($f) {
                    $sub->select('id')->from('meta_ads_sets');
                    $this->applyDateCondition($sub, 'meta_ads_sets.start_time', $f);
                });
            }

            // Anything else (e.g. account breakdowns) has no such date: skip.
        }
    }

    /**
     * The columns are timestamps, so a whole-day comparison spans 00:00:00 to
     * 23:59:59 rather than matching the bare date.
     */
    private function applyDateCondition($query, string $column, array $f): void
    {
        $from = $f['value'].' 00:00:00';
        $to = ($f['value2'] ?? $f['value']).' 23:59:59';

        match ($f['op']) {
            'before' => $query->where($column, '<', $from),
            'after' => $query->where($column, '>', $f['value'].' 23:59:59'),
            default => $query->whereBetween($column, [$from, $to]),
        };
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

    /**
     * Constrain an ads query by internal creator. Expects the query to have the
     * meta_ads_ads table available. Values: a numeric member id, the literal
     * "unassigned" (untagged ads), or null (no constraint).
     */
    private function applyCreatorFilter($query, ?string $creatorFilter): void
    {
        if ($creatorFilter === null || $creatorFilter === '') {
            return;
        }

        if ($creatorFilter === 'unassigned') {
            $query->whereNull('meta_ads_ads.creator_id');

            return;
        }

        if (is_numeric($creatorFilter)) {
            $query->where('meta_ads_ads.creator_id', (int) $creatorFilter);
        }
    }

    /**
     * Workspace members (users + owner) assignable as an ad's internal creator,
     * for the inline selector and creator filter. Mirrors the owner list.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function workspaceMembers(Workspace $workspace): array
    {
        $ids = $workspace->users()->pluck('users.id')
            ->push($workspace->owner_id)
            ->filter()
            ->unique();

        return User::whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
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
    private function insightsSubquery(string $keyColumn, string $since, string $until, array $objectives = [])
    {
        $selects = [$keyColumn];
        foreach (self::INSIGHTS_METRICS as $col) {
            $selects[] = DB::raw("SUM({$col}) AS {$col}");
        }

        return DB::table('meta_ads_insights')
            ->whereBetween('date', [$since, $until])
            // Insight rows carry their campaign, so the objective filter is
            // applied here too, not just to the grouped entity. Without this an
            // account row would keep summing spend from campaigns the filter
            // excluded, and report a total the filter says you're not looking at.
            ->tap(fn ($q) => $this->constrainByObjectives($q, 'meta_ads_campaign_id', $objectives))
            ->select($selects)
            ->groupBy($keyColumn);
    }

    /**
     * Campaigns carrying one objective. The "0" sentinel is the unassigned
     * bucket the campaign_objective breakdown emits, so it maps back to
     * campaigns Meta reported no objective for.
     */
    private function campaignIdsForObjective(string $objective)
    {
        return Campaign::query()
            ->select('id')
            ->when(
                $objective === '0',
                fn ($q) => $q->whereNull('objective'),
                fn ($q) => $q->where('objective', $objective),
            );
    }

    /**
     * Applies each objective row to a query, on the column that reaches the
     * campaign from that table. "is" keeps matching campaigns, "is not" drops
     * them; several rows AND together, as everywhere else in the builder.
     */
    private function constrainByObjectives($query, string $campaignColumn, array $filters): void
    {
        foreach ($filters as $f) {
            $ids = $this->campaignIdsForObjective($f['value']);

            $f['op'] === 'is_not'
                ? $query->whereNotIn($campaignColumn, $ids)
                : $query->whereIn($campaignColumn, $ids);
        }
    }

    /**
     * Narrows a breakdown to ads/campaigns carrying the selected objectives.
     * Applied per base model, since each grouping reaches the campaign from a
     * different table — mirroring how dateFilterColumn resolves per grouping.
     */
    private function applyObjectiveFilter($query, array $config, array $objectives): void
    {
        if ($objectives === []) {
            return;
        }

        $column = match ($config['model']) {
            Ad::class => 'meta_ads_ads.meta_ads_campaign_id',
            AdSet::class => 'meta_ads_sets.meta_ads_campaign_id',
            Campaign::class => 'meta_ads_campaigns.id',
            default => null,
        };

        if ($column !== null) {
            $this->constrainByObjectives($query, $column, $objectives);

            return;
        }

        // An account has no objective of its own, so it is kept when it ran at
        // least one campaign the rows allow. Its metrics are already limited to
        // those campaigns by the insights subquery.
        if ($config['model'] === AdAccount::class) {
            $query->whereIn('meta_ads_accounts.id', function ($sub) use ($objectives) {
                $sub->select('meta_ads_account_id')->from('meta_ads_campaigns');
                $this->constrainByObjectives($sub, 'id', $objectives);
            });
        }
    }

    /**
     * Campaign-objective rows from the filter builder. They ride in the same
     * `metric_filters` payload as the numeric filters but are dimensions, not
     * aggregates, so they become WHERE clauses here instead of the HAVING
     * clauses parseMetricFilters() builds — which ignores them, since
     * metricSqlExpression() has no expression for the field.
     *
     * @return array<int, array{op: string, value: string}>
     */
    private function parseObjectiveFilters(Request $request): array
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

        $filters = [];
        foreach ($decoded as $f) {
            if (($f['field'] ?? null) !== self::OBJECTIVE_FILTER_FIELD) {
                continue;
            }

            $value = $f['value'] ?? null;
            $op = $f['op'] ?? 'is';

            if (! is_string($value) || $value === '' || ! in_array($op, ['is', 'is_not'], true)) {
                continue;
            }

            $filters[] = ['op' => $op, 'value' => $value];
        }

        return $filters;
    }

    /**
     * Distinct campaign objectives across the workspace's accounts, for the
     * filter picker. Campaigns with none collapse to the same "0" sentinel the
     * breakdown uses, so picker and grid agree on the unassigned bucket.
     */
    private function availableObjectives($accountIds): array
    {
        return Campaign::query()
            ->whereIn('meta_ads_account_id', $accountIds)
            ->select('objective')
            ->distinct()
            ->orderByRaw('objective IS NULL, objective')
            ->pluck('objective')
            ->map(fn ($o) => [
                'value' => $o ?? '0',
                'label' => $o ?? 'Unassigned objective',
            ])
            ->values()
            ->all();
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
