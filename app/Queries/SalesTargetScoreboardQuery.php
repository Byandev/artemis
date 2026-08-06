<?php

namespace App\Queries;

use App\Models\SalesTarget;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Scores a dated sales target against what actually happened that day — the
 * numbers behind the public gameboard's KPI row.
 *
 * The target supplies the goals (per-team sales amounts, ad budget, ROAS). The
 * only actual read is sales, straight from pancake_orders: final_amount dated on
 * confirmed_at, cancelled and removed orders excluded — the same definition as
 * the workspace's Total Sales metric.
 *
 * ROAS here is sales ÷ the ad BUDGET the target set, not ÷ money actually spent.
 * It answers "what did the budget we handed out return", which is the question a
 * board about targets is asking.
 *
 * The headline totals are workspace-wide and are NOT gated on team membership —
 * "total sales" means everything the workspace confirmed that day. Only the
 * per-team figures need attribution, and they get it through the page an order
 * came in on (pages.owner_id → team_user), the same link the workspace metrics
 * use for their team filter.
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

    public function __construct(private readonly Workspace $workspace) {}

    /**
     * The headline tiles for one target, with day-over-day trends taken from the
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
        $goal = $today['target_sales'];

        return [
            'date' => $target->date->toDateString(),
            'total_sales' => $sales,
            'target_sales' => $goal,
            'achievement_pct' => $today['achievement_pct'],
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
     * Every team on the target, best first. The same ranking backs the leader,
     * so the team this puts at rank 1 is the one {@see leaderFor()} names.
     *
     * @return array<int, array<string, mixed>>
     */
    public function teamsFor(SalesTarget $target, ?int $teamId = null): array
    {
        return $this->ranked($this->snapshot($target, $teamId)['teams']);
    }

    /**
     * The team out front, with the ROAS bar it is being judged against and its
     * recent sales for the sparkline.
     *
     * @return array<string, mixed>|null
     */
    public function leaderFor(SalesTarget $target, ?int $teamId = null): ?array
    {
        $ranked = $this->teamsFor($target, $teamId);

        if (empty($ranked)) {
            return null;
        }

        $leader = $ranked[0];
        $leader['trend'] = $this->teamSalesTrend($leader['team_id'], $target->date->toDateString());
        $leader['qualifying_roas'] = $this->qualifyingRoas($target);

        return $leader;
    }

    /**
     * How the teams fall across achievement bands.
     *
     * Only teams with a sales target to measure against are placed — a team
     * carrying an ad budget alone has no achievement, so it is reported as
     * `unrated` rather than quietly counted as failing.
     *
     * @return array<string, mixed>
     */
    public function achievementDistribution(SalesTarget $target, ?int $teamId = null): array
    {
        $teams = $this->snapshot($target, $teamId)['teams'];

        $bands = [
            ['key' => 'at_target', 'label' => '100%+', 'min' => 100.0],
            ['key' => 'near', 'label' => '75% - 99%', 'min' => 75.0],
            ['key' => 'half', 'label' => '50% - 74%', 'min' => 50.0],
            ['key' => 'below', 'label' => 'Below 50%', 'min' => null],
        ];

        $rated = array_values(array_filter(
            $teams,
            fn (array $team) => $team['achievement_pct'] !== null,
        ));

        $buckets = [];

        foreach ($bands as $i => $band) {
            // Each band runs from its own floor up to the floor of the one above.
            $ceiling = $i === 0 ? null : $bands[$i - 1]['min'];
            $floor = $band['min'];

            $count = count(array_filter($rated, function (array $team) use ($floor, $ceiling) {
                $pct = (float) $team['achievement_pct'];

                return ($floor === null || $pct >= $floor)
                    && ($ceiling === null || $pct < $ceiling);
            }));

            $buckets[] = [
                'key' => $band['key'],
                'label' => $band['label'],
                'count' => $count,
                'share' => $rated === [] ? 0.0 : round($count / count($rated) * 100, 1),
            ];
        }

        return [
            'buckets' => $buckets,
            'total' => count($rated),
            'unrated' => count($teams) - count($rated),
        ];
    }

    /**
     * Each team's sales beside the target it was given — the pair of bars the
     * chart stands side by side, in the board's ranking order.
     *
     * @return array<int, array{team_id: int, name: string, sales: float, target: float}>
     */
    public function salesVsTarget(SalesTarget $target, ?int $teamId = null): array
    {
        return array_map(
            fn (array $team) => [
                'team_id' => $team['team_id'],
                'name' => $team['name'],
                'sales' => $team['sales'],
                'target' => $team['target'],
            ],
            $this->teamsFor($target, $teamId),
        );
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
        $company = $this->companySales($date, $teamId);
        $qualifyingRoas = $this->qualifyingRoas($target);

        $targetSales = 0.0;
        $adBudget = 0.0;
        $hasBudget = false;
        $teamsAtTarget = 0;
        $teamsAtRoas = 0;
        $qualified = 0;
        $teams = [];

        foreach ($teamTargets as $row) {
            $goal = (float) $row->sales_target;
            $teamSales = $actuals[(int) $row->team_id] ?? 0.0;
            $teamBudget = $row->ad_budget === null ? null : (float) $row->ad_budget;

            $targetSales += $goal;

            if ($teamBudget !== null) {
                $adBudget += $teamBudget;
                $hasBudget = true;
            }

            $hitTarget = $goal > 0 && $teamSales >= $goal;
            // ROAS on this board is the return on the budget the team was given,
            // not on what it actually spent.
            $roas = $teamBudget > 0 ? $teamSales / $teamBudget : null;
            $hitRoas = $roas !== null && round($roas, 2) >= $qualifyingRoas;

            $teamsAtTarget += $hitTarget ? 1 : 0;
            $teamsAtRoas += $hitRoas ? 1 : 0;
            $qualified += $hitTarget && $hitRoas ? 1 : 0;

            $teams[] = [
                'team_id' => (int) $row->team_id,
                'name' => $row->team?->name ?? 'Unknown team',
                'sales' => $teamSales,
                'target' => round($goal, 2),
                'ad_budget' => $teamBudget === null ? null : round($teamBudget, 2),
                'achievement_pct' => $goal > 0 ? round($teamSales / $goal * 100, 1) : null,
                'roas' => $roas === null ? null : round($roas, 2),
                'above_target' => round($teamSales - $goal, 2),
                'hit_target' => $hitTarget,
                'hit_roas' => $hitRoas,
                'qualified' => $hitTarget && $hitRoas,
            ];
        }

        $budget = $hasBudget ? round($adBudget, 2) : null;

        return [
            'sales' => $company,
            'target_sales' => round($targetSales, 2),
            // Null, not zero, when no team was given a budget — there is then no
            // budget to divide by and no ads figure to show.
            'ad_budget' => $budget,
            'achievement_pct' => $targetSales > 0 ? round($company / $targetSales * 100, 1) : null,
            'roas' => $budget > 0 ? round($company / $budget, 2) : null,
            'teams_total' => count($teamIds),
            'teams_at_target' => $teamsAtTarget,
            'teams_at_roas' => $teamsAtRoas,
            'qualified_teams' => $qualified,
            'teams' => $teams,
        ];
    }

    /**
     * Teams best-first: highest achievement against its own target, with sales
     * breaking a tie. Each row carries the rank it landed on.
     *
     * @param  array<int, array<string, mixed>>  $teams
     * @return array<int, array<string, mixed>>
     */
    private function ranked(array $teams): array
    {
        usort($teams, function (array $a, array $b) {
            // A team with no target to measure against ranks below one that has.
            $byAchievement = ($b['achievement_pct'] ?? -1) <=> ($a['achievement_pct'] ?? -1);

            return $byAchievement !== 0 ? $byAchievement : $b['sales'] <=> $a['sales'];
        });

        foreach ($teams as $i => $team) {
            $teams[$i]['rank'] = $i + 1;
        }

        return $teams;
    }

    /**
     * The team's daily sales over the window ending on the target's date, with
     * the days that have no record filled in as zero so the line has no gaps.
     *
     * @return array<int, array{date: string, sales: float}>
     */
    private function teamSalesTrend(int $teamId, string $end, int $days = 14): array
    {
        $from = Carbon::parse($end)->subDays($days - 1)->toDateString();

        $byDate = DB::table('pancake_orders')
            ->where('pancake_orders.workspace_id', $this->workspace->id)
            ->whereBetween('pancake_orders.confirmed_at', [
                $from.' 00:00:00',
                $end.' 23:59:59',
            ])
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->join('team_user as tu', 'tu.user_id', '=', 'pages.owner_id')
            ->where('tu.team_id', $teamId)
            ->groupBy('date')
            ->selectRaw('DATE(pancake_orders.confirmed_at) as date, SUM(pancake_orders.final_amount) as sales')
            ->pluck('sales', 'date');

        $trend = [];
        $cursor = Carbon::parse($from);

        for ($i = 0; $i < $days; $i++) {
            $date = $cursor->toDateString();
            $trend[] = [
                'date' => $date,
                'sales' => round((float) ($byDate[$date] ?? 0), 2),
            ];
            $cursor->addDay();
        }

        return $trend;
    }

    /**
     * team id => confirmed sales that day, straight from the orders. A team owns
     * an order through the page it came in on (pages.owner_id → team_user), the
     * same link the workspace metrics use for a team filter.
     *
     * @param  array<int, int>  $teamIds
     * @return array<int, float>
     */
    private function actualsByTeam(string $date, array $teamIds): array
    {
        if (empty($teamIds)) {
            return [];
        }

        $rows = $this->ordersQuery($date)
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->join('team_user as tu', 'tu.user_id', '=', 'pages.owner_id')
            ->whereIn('tu.team_id', $teamIds)
            ->groupBy('tu.team_id')
            ->selectRaw('tu.team_id as team_id, SUM(pancake_orders.final_amount) as sales')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->team_id] = round((float) $row->sales, 2);
        }

        return $map;
    }

    /**
     * The day's workspace-wide sales — every confirmed order, whoever owns the
     * page it came in on. Team membership deliberately doesn't gate this: the
     * headline is what the company sold, not what the linked teams sold.
     */
    private function companySales(string $date, ?int $teamId): float
    {
        $sales = $this->ordersQuery($date)
            ->when($teamId, fn ($query) => $query
                ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
                ->whereIn('pages.owner_id', fn ($sub) => $sub
                    ->from('team_user')
                    ->select('user_id')
                    ->where('team_id', $teamId)))
            ->selectRaw('COALESCE(SUM(pancake_orders.final_amount), 0) as sales')
            ->value('sales');

        return round((float) $sales, 2);
    }

    /**
     * Confirmed orders for one day. Same definition as the workspace's Total
     * Sales metric: final_amount, dated on confirmed_at, cancelled and removed
     * orders (status 6 and 7) left out.
     */
    private function ordersQuery(string $date)
    {
        return DB::table('pancake_orders')
            ->where('pancake_orders.workspace_id', $this->workspace->id)
            ->whereBetween('pancake_orders.confirmed_at', [
                $date.' 00:00:00',
                $date.' 23:59:59',
            ])
            ->whereNotIn('pancake_orders.status', [6, 7]);
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
