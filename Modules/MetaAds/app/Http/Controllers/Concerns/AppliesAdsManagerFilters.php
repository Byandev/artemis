<?php

namespace Modules\MetaAds\Http\Controllers\Concerns;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Http\Controllers\AdsManagerController;
use Modules\MetaAds\Models\Campaign;

/**
 * The Ads Manager's filters — metric conditions, lifecycle dates, campaign
 * objective and internal creator — parsed and applied the same way wherever
 * they're offered, so a filter means one thing across Meta Ads pages.
 *
 * Metric expressions read the aggregated insights as alias `i`; the using
 * query decides which insight rows `i` covers.
 */
trait AppliesAdsManagerFilters
{
    /**
     * Allowed group-by dimensions. Keys are the public `group_by` values; the
     * default is `ad_name`.
     */
    /** Filter-builder field id for the campaign-objective dimension row. */
    private const OBJECTIVE_FILTER_FIELD = 'campaign_objective';

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
        if (in_array($id, AdsManagerController::INSIGHTS_METRICS, true)) {
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
}
