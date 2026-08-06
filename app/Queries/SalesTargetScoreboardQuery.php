<?php

namespace App\Queries;

use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\SalesTarget;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Scores a dated sales target against what its teams actually did that day —
 * the numbers behind the public gameboard's KPI row.
 *
 * The target supplies the goal (per-team amounts); the actuals come from the
 * unified advertiser_performance_daily_records, rolled up to the team the same
 * source-aware way as {@see TeamAdSpendGoalStatusQuery}:
 *   - gencys:  advertiser is an Intern → gencys_interns.user_id → team_user → team
 *   - artemis: advertiser_id IS the user id → team_user → team
 *
 * A user on two teams contributes to BOTH teams' rows (the pivot is
 * many-to-many), so the company totals are read from the deduplicated advertiser
 * set rather than by summing the team rows — otherwise that user's sales would
 * be counted twice in the headline figure.
 *
 * This is the workspace-wide board: no team visibility scoping is applied, which
 * is why it only backs the password-gated public page and not a per-user view.
 */
class SalesTargetScoreboardQuery
{
    /**
     * The ROAS a team has to clear when the target itself doesn't say — the
     * board still needs a bar to count teams against.
     */
    public const DEFAULT_QUALIFYING_ROAS = 5.0;

    /** Which unified source this workspace reads ('gencys' | 'artemis'). */
    private string $source;

    public function __construct(private readonly Workspace $workspace)
    {
        $this->source = $workspace->is_gencys_partner
            ? AdvertiserPerformanceDailyRecord::SOURCE_GENCYS
            : AdvertiserPerformanceDailyRecord::SOURCE_ARTEMIS;
    }

    /**
     * The KPI payload for one target, with day-over-day trends taken from the
     * previous day's target when there is one.
     *
     * Pass $teamId to score the board on a single team — the totals, the counts
     * and the trends all narrow to it.
     *
     * @return array<string, mixed>
     */
    public function kpisFor(SalesTarget $target, ?int $teamId = null): array
    {
        $today = $this->snapshot($target, $teamId);

        $previousTarget = SalesTarget::ofWorkspace($this->workspace)
            ->where('date', $target->date->copy()->subDay()->toDateString())
            ->with('teamTargets')
            ->first();

        $previous = $previousTarget ? $this->snapshot($previousTarget, $teamId) : null;

        $sales = $today['sales'];
        $spend = $today['ad_spent'];
        $goal = $today['target_sales'];

        return [
            'date' => $target->date->toDateString(),
            'total_sales' => $sales,
            'target_sales' => $goal,
            'achievement_pct' => $today['achievement_pct'],
            'total_ad_spent' => $spend,
            'ad_budget' => $today['ad_budget'],
            'roas' => $today['roas'],
            'qualifying_roas' => $this->qualifyingRoas($target),
            'teams_total' => $today['teams_total'],
            'teams_at_target' => $today['teams_at_target'],
            'teams_at_roas' => $today['teams_at_roas'],
            'qualified_teams' => $today['qualified_teams'],
            // Signed on purpose: below target reads as a shortfall, not a zero.
            'above_target' => round($sales - $goal, 2),
            'trends' => [
                'achievement' => $this->delta($today['achievement_pct'], $previous['achievement_pct'] ?? null),
                'roas' => $this->delta($today['roas'], $previous['roas'] ?? null),
            ],
        ];
    }

    /**
     * One day's board: the target's teams with their actuals, plus the
     * deduplicated company totals.
     *
     * @return array<string, mixed>
     */
    private function snapshot(SalesTarget $target, ?int $teamId = null): array
    {
        $date = $target->date->toDateString();

        $teamTargets = $target->relationLoaded('teamTargets')
            ? $target->teamTargets
            : $target->teamTargets()->get();

        // Narrowing to one team is just a smaller board — every total below
        // then covers that team alone.
        if ($teamId !== null) {
            $teamTargets = $teamTargets->where('team_id', $teamId)->values();
        }

        $teamIds = $teamTargets->pluck('team_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $actuals = $this->actualsByTeam($date, $teamIds);
        $company = $this->companyTotals($date, $teamIds);
        $qualifyingRoas = $this->qualifyingRoas($target);

        $targetSales = 0.0;
        $adBudget = 0.0;
        $hasBudget = false;
        $teamsAtTarget = 0;
        $teamsAtRoas = 0;
        $qualified = 0;

        foreach ($teamTargets as $row) {
            $goal = (float) $row->sales_target;
            $actual = $actuals[(int) $row->team_id] ?? ['sales' => 0.0, 'ad_spent' => 0.0];

            $targetSales += $goal;

            if ($row->ad_budget !== null) {
                $adBudget += (float) $row->ad_budget;
                $hasBudget = true;
            }

            $hitTarget = $goal > 0 && $actual['sales'] >= $goal;
            $roas = $actual['ad_spent'] > 0 ? $actual['sales'] / $actual['ad_spent'] : null;
            $hitRoas = $roas !== null && round($roas, 2) >= $qualifyingRoas;

            $teamsAtTarget += $hitTarget ? 1 : 0;
            $teamsAtRoas += $hitRoas ? 1 : 0;
            $qualified += $hitTarget && $hitRoas ? 1 : 0;
        }

        $sales = $company['sales'];
        $spend = $company['ad_spent'];

        return [
            'sales' => $sales,
            'ad_spent' => $spend,
            'target_sales' => round($targetSales, 2),
            // Null, not zero, when no team was given a budget — the ads tile has
            // nothing to measure against rather than a goal of nothing.
            'ad_budget' => $hasBudget ? round($adBudget, 2) : null,
            'achievement_pct' => $targetSales > 0 ? round($sales / $targetSales * 100, 1) : null,
            'roas' => $spend > 0 ? round($sales / $spend, 2) : null,
            'teams_total' => count($teamIds),
            'teams_at_target' => $teamsAtTarget,
            'teams_at_roas' => $teamsAtRoas,
            'qualified_teams' => $qualified,
        ];
    }

    /**
     * team id => that team's sales and ad spend on the date.
     *
     * @param  array<int, int>  $teamIds
     * @return array<int, array{sales: float, ad_spent: float}>
     */
    private function actualsByTeam(string $date, array $teamIds): array
    {
        if (empty($teamIds)) {
            return [];
        }

        $query = $this->baseQuery($date);

        if ($this->source === AdvertiserPerformanceDailyRecord::SOURCE_GENCYS) {
            $query->join('gencys_interns as gi', function ($join) {
                $join->on('gi.id', '=', 'apdr.advertiser_id')
                    ->where('gi.workspace_id', '=', $this->workspace->id);
            })->join('team_user as tu', 'tu.user_id', '=', 'gi.user_id');
        } else {
            $query->join('team_user as tu', 'tu.user_id', '=', 'apdr.advertiser_id');
        }

        $rows = $query
            ->whereIn('tu.team_id', $teamIds)
            ->groupBy('tu.team_id')
            ->selectRaw('tu.team_id as team_id, SUM(COALESCE(apdr.sales, 0)) as sales, SUM(COALESCE(apdr.ad_spent, 0)) as ad_spent')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->team_id] = [
                'sales' => round((float) $row->sales, 2),
                'ad_spent' => round((float) $row->ad_spent, 2),
            ];
        }

        return $map;
    }

    /**
     * The day's totals over every advertiser on the board's teams, counted once
     * each however many of those teams they belong to.
     *
     * @param  array<int, int>  $teamIds
     * @return array{sales: float, ad_spent: float}
     */
    private function companyTotals(string $date, array $teamIds): array
    {
        $advertiserIds = $this->advertiserIds($teamIds);

        if (empty($advertiserIds)) {
            return ['sales' => 0.0, 'ad_spent' => 0.0];
        }

        $agg = $this->baseQuery($date)
            ->whereIn('apdr.advertiser_id', $advertiserIds)
            ->selectRaw('SUM(COALESCE(apdr.sales, 0)) as sales, SUM(COALESCE(apdr.ad_spent, 0)) as ad_spent')
            ->first();

        return [
            'sales' => round((float) ($agg->sales ?? 0), 2),
            'ad_spent' => round((float) ($agg->ad_spent ?? 0), 2),
        ];
    }

    /**
     * The advertiser ids behind the given teams' members, deduplicated.
     *
     * @param  array<int, int>  $teamIds
     * @return array<int, int>
     */
    private function advertiserIds(array $teamIds): array
    {
        if (empty($teamIds)) {
            return [];
        }

        $userIds = DB::table('team_user')
            ->whereIn('team_id', $teamIds)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($userIds)) {
            return [];
        }

        if ($this->source === AdvertiserPerformanceDailyRecord::SOURCE_GENCYS) {
            return DB::table('gencys_interns')
                ->where('workspace_id', $this->workspace->id)
                ->whereIn('user_id', $userIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $userIds;
    }

    /**
     * The ROAS bar for this target — its own if one was set, otherwise the
     * board's default.
     */
    private function qualifyingRoas(SalesTarget $target): float
    {
        $roas = $target->target_roas === null ? null : (float) $target->target_roas;

        return $roas !== null && $roas > 0 ? $roas : self::DEFAULT_QUALIFYING_ROAS;
    }

    private function baseQuery(string $date)
    {
        return DB::table('advertiser_performance_daily_records as apdr')
            ->where('apdr.workspace_id', $this->workspace->id)
            ->where('apdr.source', $this->source)
            // Plain comparison, not whereDate(): the column is a date, and wrapping
            // it in date() would sidestep the (workspace_id, source, date) index.
            ->where('apdr.date', $date);
    }

    /**
     * Direction + percentage change against the previous day's board. Null when
     * either side is missing — there is nothing honest to draw an arrow from.
     *
     * @return array{pct: float|null, status: 'up'|'down'|'flat'}|null
     */
    private function delta(int|float|null $current, int|float|null $previous): ?array
    {
        if ($current === null || $previous === null) {
            return null;
        }

        $cur = (float) $current;
        $prev = (float) $previous;

        return [
            'pct' => $prev != 0.0 ? round(($cur - $prev) / abs($prev) * 100, 1) : null,
            'status' => $cur > $prev ? 'up' : ($cur < $prev ? 'down' : 'flat'),
        ];
    }
}
