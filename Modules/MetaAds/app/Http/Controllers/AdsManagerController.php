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

        $payload = $this->campaigns($request, $accountIds, $since, $until);

        return Inertia::render('workspaces/integrations/meta-ads/campaigns', [
            'workspace' => $workspace,
            'rows' => $payload['rows'],
            'dateRange' => ['since' => $since, 'until' => $until],
            'query' => [
                ...$request->only(['sort', 'page', 'since', 'until']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function adSetsPage(Request $request, Workspace $workspace): Response
    {
        abort_unless($request->user()->isMemberOf($workspace), 403);

        [$since, $until] = $this->resolveDateRange($request);
        $accountIds = $this->accountIdsForWorkspace($workspace);
        $campaignId = $request->query('campaign');

        $payload = $this->adSets($request, $accountIds, $since, $until, $campaignId);

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

        $payload = $this->ads($request, $accountIds, $since, $until, $campaignId, $adSetId);

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
            ],
        ]);
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

    private function insightsSubquery(string $entityColumn, string $since, string $until)
    {
        return DB::table('meta_ads_insights')
            ->whereBetween('date', [$since, $until])
            ->select(
                $entityColumn,
                DB::raw('SUM(spend) AS spend'),
                DB::raw('SUM(impressions) AS impressions'),
                DB::raw('SUM(reach) AS reach'),
                DB::raw('SUM(clicks) AS clicks'),
                DB::raw('SUM(link_clicks) AS link_clicks'),
                DB::raw('SUM(purchases) AS purchases'),
                DB::raw('SUM(purchase_value) AS purchase_value'),
            )
            ->groupBy($entityColumn);
    }

    private function campaigns(Request $request, $accountIds, string $since, string $until): array
    {
        $insights = $this->insightsSubquery('meta_ads_campaign_id', $since, $until);

        $base = Campaign::query()
            ->whereIn('meta_ads_account_id', $accountIds)
            ->leftJoinSub($insights, 'i', 'i.meta_ads_campaign_id', '=', 'meta_ads_campaigns.id')
            ->select(
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
                DB::raw('COALESCE(i.spend, 0) AS spend'),
                DB::raw('COALESCE(i.impressions, 0) AS impressions'),
                DB::raw('COALESCE(i.reach, 0) AS reach'),
                DB::raw('COALESCE(i.clicks, 0) AS clicks'),
                DB::raw('COALESCE(i.link_clicks, 0) AS link_clicks'),
                DB::raw('COALESCE(i.purchases, 0) AS purchases'),
                DB::raw('COALESCE(i.purchase_value, 0) AS purchase_value'),
            );

        $rows = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'meta_ads_campaigns.name'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('effective_status'),
            ])
            ->allowedSorts(['name', 'status', 'effective_status', 'daily_budget', 'spend', 'impressions', 'reach', 'purchases', 'updated_time'])
            ->defaultSort('-spend')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return ['rows' => $rows];
    }

    private function adSets(Request $request, $accountIds, string $since, string $until, ?string $campaignId): array
    {
        $insights = $this->insightsSubquery('meta_ads_set_id', $since, $until);

        $base = AdSet::query()
            ->whereIn('meta_ads_sets.meta_ads_account_id', $accountIds)
            ->when($campaignId, fn ($q) => $q->where('meta_ads_sets.meta_ads_campaign_id', $campaignId))
            ->leftJoin('meta_ads_campaigns', 'meta_ads_campaigns.id', '=', 'meta_ads_sets.meta_ads_campaign_id')
            ->leftJoinSub($insights, 'i', 'i.meta_ads_set_id', '=', 'meta_ads_sets.id')
            ->select(
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
                'meta_ads_campaigns.name AS campaign_name',
                DB::raw('COALESCE(i.spend, 0) AS spend'),
                DB::raw('COALESCE(i.impressions, 0) AS impressions'),
                DB::raw('COALESCE(i.reach, 0) AS reach'),
                DB::raw('COALESCE(i.clicks, 0) AS clicks'),
                DB::raw('COALESCE(i.link_clicks, 0) AS link_clicks'),
                DB::raw('COALESCE(i.purchases, 0) AS purchases'),
                DB::raw('COALESCE(i.purchase_value, 0) AS purchase_value'),
            );

        $rows = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'meta_ads_sets.name'),
                AllowedFilter::exact('status', 'meta_ads_sets.status'),
                AllowedFilter::exact('effective_status', 'meta_ads_sets.effective_status'),
            ])
            ->allowedSorts(['name', 'status', 'effective_status', 'daily_budget', 'spend', 'impressions', 'reach', 'purchases', 'updated_time'])
            ->defaultSort('-spend')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return ['rows' => $rows];
    }

    private function ads(Request $request, $accountIds, string $since, string $until, ?string $campaignId, ?string $adSetId): array
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
            ->select(
                'meta_ads_ads.id',
                'meta_ads_ads.name',
                'meta_ads_ads.status',
                'meta_ads_ads.effective_status',
                'meta_ads_ads.created_time',
                'meta_ads_ads.updated_time',
                'meta_ads_ads.meta_ads_campaign_id',
                'meta_ads_ads.meta_ads_set_id',
                'meta_ads_ads.meta_ads_creative_id',
                'meta_ads_campaigns.name AS campaign_name',
                'meta_ads_sets.name AS ad_set_name',
                'meta_ads_creatives.thumbnail_url AS thumbnail_url',
                'meta_ads_creatives.image_url AS image_url',
                DB::raw('COALESCE(i.spend, 0) AS spend'),
                DB::raw('COALESCE(i.impressions, 0) AS impressions'),
                DB::raw('COALESCE(i.reach, 0) AS reach'),
                DB::raw('COALESCE(i.clicks, 0) AS clicks'),
                DB::raw('COALESCE(i.link_clicks, 0) AS link_clicks'),
                DB::raw('COALESCE(i.purchases, 0) AS purchases'),
                DB::raw('COALESCE(i.purchase_value, 0) AS purchase_value'),
            );

        $rows = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::partial('search', 'meta_ads_ads.name'),
                AllowedFilter::exact('status', 'meta_ads_ads.status'),
                AllowedFilter::exact('effective_status', 'meta_ads_ads.effective_status'),
            ])
            ->allowedSorts(['name', 'status', 'effective_status', 'spend', 'impressions', 'reach', 'purchases', 'updated_time'])
            ->defaultSort('-spend')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return ['rows' => $rows];
    }
}
