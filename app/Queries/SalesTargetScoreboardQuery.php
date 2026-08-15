<?php

namespace App\Queries;

use App\Models\SalesTarget;
use App\Models\SalesTargetTeam;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads what actually happened on the day a sales target covers — the measured
 * numbers behind the public gameboard.
 *
 * This class deliberately stops at measurement. It returns the day's sales, each
 * included team's goal, budget and actual, and the previous day's equivalents;
 * the board derives percentages, ROAS, ranking, achievement bands and the
 * day-over-day arrows from those in the browser. Keeping the arithmetic on one
 * side means one definition of each rule rather than one per endpoint, and the
 * board no longer re-runs this query once per panel.
 *
 * Sales come straight from pancake_orders: final_amount dated on confirmed_at,
 * cancelled and removed orders (status 6 and 7) excluded — the same definition as
 * the workspace's Total Sales metric.
 *
 * The totals cover exactly the teams the target included — the ones ticked when
 * it was created — and nothing else. A team left out of the target has no goal on
 * this board, so counting its sales would score the workspace against a target
 * that never asked for it.
 *
 * A team owns an order through the shop the order's page belongs to
 * (pages.shop_id → team_shop), the same link team-level visibility uses — see
 * App\Models\Concerns\ScopesToVisibleTeams and Order::visibilityTeamRelation(),
 * which reaches teams via `page.shop.teams`. Attribution has to match visibility:
 * a scoped user who may only see their team's shops would otherwise read a team
 * total built from orders they cannot list.
 *
 * This deliberately does *not* attribute through the page's owner
 * (pages.owner_id → team_user). team_user is many-to-many by design — a user can
 * sit on several teams — so grouping over it credits one order to every team its
 * owner belongs to. team_shop is keyed on (team_id, shop_id) and assigned
 * deliberately by an admin, so one shop lands on one team unless someone chooses
 * otherwise.
 *
 * The day total is still not the sum of the per-team rows: it filters orders
 * rather than joining through the pivot, so a shop deliberately shared between
 * two included teams still counts its orders once toward the day.
 *
 * No team *visibility* scoping is applied — every included team's number is
 * shown, which is why this only backs the password-gated public page and not a
 * per-user view.
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
     * The day's measured totals, plus the previous day's for the trend arrows.
     *
     * Pass $teamId to narrow every total to a single team.
     *
     * @return array<string, mixed>
     */
    public function factsFor(SalesTarget $target, ?int $teamId = null): array
    {
        $previous = SalesTarget::ofWorkspace($this->workspace)
            ->where('date', $target->date->copy()->subDay()->toDateString())
            ->with('teamTargets')
            ->first();

        return [
            // The cast keeps this a Carbon instance; send the bare date so the
            // frontend parses it as a local day.
            'date' => $target->date->toDateString(),
            'qualifying_roas' => $this->qualifyingRoas($target),
            'today' => $this->dayTotals($target, $teamId),
            'previous' => $previous ? $this->dayTotals($previous, $teamId) : null,
        ];
    }

    /**
     * Each included team's goal, budget and what it actually sold — unscored, in
     * no particular order. The board ranks and rates them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function teamRows(SalesTarget $target, ?int $teamId = null): array
    {
        $teamTargets = $this->teamTargets($target, $teamId);
        $actuals = $this->actualsByTeam(
            $target->date->toDateString(),
            $this->teamIds($teamTargets),
        );

        return $teamTargets
            ->map(fn ($row) => [
                'team_id' => (int) $row->team_id,
                'name' => $row->team?->name ?? 'Unknown team',
                'sales' => $actuals[(int) $row->team_id] ?? 0.0,
                'target' => round((float) $row->sales_target, 2),
                'ad_budget' => $row->ad_budget === null ? null : round((float) $row->ad_budget, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * One team's daily sales over the window ending on the target's date, with
     * days that have no orders filled in as zero so the line has no gaps.
     *
     * @return array<int, array{date: string, sales: float}>
     */
    public function teamSalesTrend(int $teamId, string $end, int $days = 14): array
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
            // Filtered, not joined: the sum is grouped by date, so a second
            // team_shop row for this shop would double every day on the line.
            ->whereIn('pages.shop_id', fn ($sub) => $sub
                ->from('team_shop')
                ->select('shop_id')
                ->where('team_id', $teamId))
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
     * One day's measured totals: what the included teams sold, what they were
     * asked for, and the budget they were given.
     *
     * @return array<string, float|null>
     */
    private function dayTotals(SalesTarget $target, ?int $teamId): array
    {
        $teamTargets = $this->teamTargets($target, $teamId);

        $budgets = $teamTargets->whereNotNull('ad_budget');

        return [
            'total_sales' => $this->includedSales(
                $target->date->toDateString(),
                $this->teamIds($teamTargets),
            ),
            'target_sales' => round((float) $teamTargets->sum('sales_target'), 2),
            // Null, not zero, when no team was given a budget — there is then no
            // budget to divide by and no ads figure to show.
            'ad_budget' => $budgets->isEmpty() ? null : round((float) $budgets->sum('ad_budget'), 2),
        ];
    }

    /**
     * The target's team rows, narrowed to one team when asked. A team filter that
     * isn't on the target yields nothing, which is the honest answer.
     *
     * @return Collection<int, SalesTargetTeam>
     */
    private function teamTargets(SalesTarget $target, ?int $teamId)
    {
        $rows = $target->relationLoaded('teamTargets')
            ? $target->teamTargets
            : $target->teamTargets()->with('team:id,name')->get();

        return $teamId === null ? $rows : $rows->where('team_id', $teamId)->values();
    }

    /**
     * @param  Collection<int, SalesTargetTeam>  $teamTargets
     * @return array<int, int>
     */
    private function teamIds($teamTargets): array
    {
        return $teamTargets->pluck('team_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * team id => confirmed sales that day. A team owns an order through the shop
     * behind the page it came in on (pages.shop_id → team_shop), the same link
     * team-level visibility uses.
     *
     * team_shop is keyed on (team_id, shop_id), so the join adds at most one row
     * per order per team and a team's own figure can never count an order twice.
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
            ->join('team_shop as ts', 'ts.shop_id', '=', 'pages.shop_id')
            ->whereIn('ts.team_id', $teamIds)
            ->groupBy('ts.team_id')
            ->selectRaw('ts.team_id as team_id, SUM(pancake_orders.final_amount) as sales')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->team_id] = round((float) $row->sales, 2);
        }

        return $map;
    }

    /**
     * The day's sales for the teams the target included, counted once each.
     *
     * Filtering the orders by shop (rather than joining through team_shop and
     * summing) is what keeps it once: a shop shared between two included teams
     * would otherwise have its orders double-counted in the headline.
     *
     * A target with no teams has nothing to compare, so it totals zero rather
     * than falling back to the whole workspace.
     *
     * @param  array<int, int>  $teamIds
     */
    private function includedSales(string $date, array $teamIds): float
    {
        if (empty($teamIds)) {
            return 0.0;
        }

        $sales = $this->ordersQuery($date)
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->whereIn('pages.shop_id', fn ($sub) => $sub
                ->from('team_shop')
                ->select('shop_id')
                ->whereIn('team_id', $teamIds))
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
}
