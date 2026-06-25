<?php

namespace Modules\MetaAds\Services;

use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\MonitorStatusRule;

/**
 * Builds the per-entity monitor rows: each entity's first-7-day spend/ROAS
 * series, the per-window metric sums, and the suggested status + reason. Shared
 * by EntityMonitorController (live board) and EvaluateEntityMonitorCommand (daily
 * snapshot), so both compute identical numbers.
 */
class EntityMonitorService
{
    public function __construct(private readonly MonitorStatusEvaluator $evaluator) {}

    /** Length of a test window, in days. */
    public const TEST_DAYS = 7;

    /** Insight columns summed for metric computation. */
    private const METRIC_COLUMNS = [
        'spend', 'impressions', 'reach', 'clicks', 'link_clicks',
        'purchases', 'purchase_value', 'leads', 'conversions',
        'messaging_conversations_started',
    ];

    /**
     * Per-level entity table + the insights column that points back to it.
     *
     * @return array{table: string, insight_key: string}
     */
    private function levelConfig(string $level): array
    {
        return match ($level) {
            'campaign' => ['table' => 'meta_ads_campaigns', 'insight_key' => 'meta_ads_campaign_id'],
            'ad_set' => ['table' => 'meta_ads_sets', 'insight_key' => 'meta_ads_set_id'],
            'ad' => ['table' => 'meta_ads_ads', 'insight_key' => 'meta_ads_ad_id'],
            default => throw new \InvalidArgumentException("Unsupported level: {$level}"),
        };
    }

    /**
     * One row per entity (at $level, in $accountIds) whose first spend day falls
     * within [$startedFrom, $startedTo].
     *
     * @param  array<int, string>  $accountIds
     * @param  Collection<int, MonitorStatusRule>  $rules
     * @return array<int, array<string, mixed>>
     */
    public function buildRows(Workspace $workspace, string $level, array $accountIds, string $startedFrom, string $startedTo, Collection $rules): array
    {
        if (empty($accountIds)) {
            return [];
        }

        $config = $this->levelConfig($level);
        $insightKey = $config['insight_key'];

        // First spend day per entity, limited to entities that started in range.
        $starts = DB::table('meta_ads_insights')
            ->whereIn('meta_ads_account_id', $accountIds)
            ->where('spend', '>', 0)
            ->groupBy($insightKey)
            ->havingRaw('MIN(date) between ? and ?', [$startedFrom, $startedTo])
            ->pluck(DB::raw('MIN(date)'), $insightKey);

        if ($starts->isEmpty()) {
            return [];
        }

        $entityIds = $starts->keys()->all();

        // Entity metadata + account name.
        $entities = DB::table($config['table'].' as e')
            ->leftJoin('meta_ads_accounts as a', 'a.id', '=', 'e.meta_ads_account_id')
            ->whereIn('e.id', $entityIds)
            ->get(['e.id', 'e.name', 'e.status', 'e.effective_status', 'e.meta_ads_account_id', 'a.name as account_name'])
            ->keyBy(fn ($e) => (string) $e->id);

        // Daily insights, summed to one row per entity per date. Insights are
        // stored per-ad, so a campaign / ad set has many rows per date (one per
        // ad) — collapse them here so the day series and day counts are correct.
        $select = array_merge(
            [$insightKey.' as entity_id', 'date'],
            array_map(fn ($c) => DB::raw("SUM({$c}) as {$c}"), self::METRIC_COLUMNS),
        );

        $dailyByEntity = DB::table('meta_ads_insights')
            ->whereIn($insightKey, $entityIds)
            ->groupBy($insightKey, 'date')
            ->orderBy('date')
            ->get($select)
            ->groupBy(fn ($r) => (string) $r->entity_id);

        $rows = [];

        foreach ($starts as $entityId => $startDate) {
            $key = (string) $entityId;
            $entity = $entities->get($key);

            if (! $entity) {
                continue;
            }

            $start = Carbon::parse($startDate);
            $daily = $dailyByEntity->get($key, collect());

            $rows[] = $this->buildRow($workspace, $level, $key, $entity, $start, $daily, $rules);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, object>  $daily
     * @param  Collection<int, MonitorStatusRule>  $rules
     * @return array<string, mixed>
     */
    private function buildRow(Workspace $workspace, string $level, string $entityId, object $entity, Carbon $start, Collection $daily, Collection $rules): array
    {
        $end3 = $start->copy()->addDays(2)->toDateString();
        $end7 = $start->copy()->addDays(self::TEST_DAYS - 1)->toDateString();
        $startStr = $start->toDateString();

        $in = fn (object $r, string $from, string $to): bool => $r->date >= $from && $r->date <= $to;

        $first7 = $daily->filter(fn ($r) => $in($r, $startStr, $end7));

        $windowSums = [
            'first_3_days' => $this->sumColumns($daily->filter(fn ($r) => $in($r, $startStr, $end3))),
            'first_7_days' => $this->sumColumns($first7),
            'lifetime' => $this->sumColumns($daily),
        ];

        // Day 1–7 spend + ROAS series.
        $series = [];
        for ($i = 0; $i < self::TEST_DAYS; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $row = $daily->firstWhere('date', $date);
            $spend = $row ? (float) $row->spend : 0.0;
            $revenue = $row ? (float) $row->purchase_value : 0.0;

            $series[] = [
                'day' => $i + 1,
                'date' => $date,
                'spend' => $spend,
                'roas' => $spend > 0 ? round($revenue / $spend, 2) : null,
            ];
        }

        $daysWithSpend = $first7->filter(fn ($r) => (float) $r->spend > 0)->count();

        $evaluation = $this->evaluator->evaluate($windowSums, $rules, $daysWithSpend);

        $totalSpend = (float) $windowSums['first_7_days']['spend'];
        $overallRoas = $this->evaluator->metricValue('roas', $windowSums['first_7_days']);

        return [
            'level' => $level,
            'entity_id' => $entityId, // string — Meta ids exceed JS safe int
            'name' => $entity->name,
            'account_name' => $entity->account_name,
            'status' => $entity->status,
            'effective_status' => $entity->effective_status,
            'start_date' => $startStr,
            'day_number' => (int) $start->copy()->startOfDay()->diffInDays(Carbon::today()) + 1,
            'series' => $series,
            'total_spend' => round($totalSpend, 2),
            'overall_roas' => $overallRoas !== null ? round($overallRoas, 2) : null,
            'days_with_spend' => $daysWithSpend,
            'suggested_status' => $evaluation['status'],
            'reason' => $evaluation['reason'],
            'metrics' => $windowSums,
        ];
    }

    /**
     * Sum the tracked metric columns across a set of daily rows.
     *
     * @param  Collection<int, object>  $rows
     * @return array<string, float>
     */
    private function sumColumns(Collection $rows): array
    {
        $sums = array_fill_keys(self::METRIC_COLUMNS, 0.0);

        foreach ($rows as $row) {
            foreach (self::METRIC_COLUMNS as $col) {
                $sums[$col] += (float) ($row->{$col} ?? 0);
            }
        }

        return $sums;
    }
}
