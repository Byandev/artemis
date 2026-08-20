import { DistributionData } from './gameboard-distribution';
import { GameboardKpis, Trend } from './gameboard-kpis';
import { LeaderTeam } from './gameboard-leader';
import { LeaderboardData } from './gameboard-leaderboard';
import { SalesVsTargetPoint } from './gameboard-sales-chart';
import { TeamPerformance } from './gameboard-teams';

/**
 * Everything the gameboard shows that is arithmetic rather than measurement.
 *
 * The server sends only what it had to read from the database: the day's sales,
 * each team's goal and budget, and the previous day's equivalents. Percentages,
 * ROAS, ranking, the achievement bands, the leaderboard slice and the day-over-day
 * arrows are all derived from those numbers here — no extra request, and one
 * definition of each rule instead of one per endpoint.
 *
 * Rounding mirrors what the API used to return so the display is unchanged:
 * money and ROAS to 2dp, percentages to 1dp.
 */

/** The measured per-team facts the server sends. */
export interface TeamFacts {
    team_id: number;
    name: string;
    sales: number;
    target: number;
    ad_budget: number | null;
}

/** One day's measured totals. */
export interface DayFacts {
    total_sales: number;
    target_sales: number;
    ad_budget: number | null;
}

/** The `kpis` endpoint's payload: this day, plus the one before it when it exists. */
export interface BoardFacts {
    date: string;
    qualifying_roas: number;
    today: DayFacts;
    previous: DayFacts | null;
}

const round = (value: number, dp: number): number => {
    const f = 10 ** dp;

    return Math.round(value * f) / f;
};

/** ROAS on this board is the return on the budget a team was given, not on spend. */
const roasOf = (sales: number, budget: number | null): number | null =>
    budget !== null && budget > 0 ? round(sales / budget, 2) : null;

/** Achievement needs a goal to measure against — a team without one is unrated. */
const achievementOf = (sales: number, target: number): number | null =>
    target > 0 ? round((sales / target) * 100, 1) : null;

/**
 * Score each team and rank them best-first: highest achievement against its own
 * target, with sales breaking a tie. A team with no target ranks below one that
 * has, since there is nothing to judge it on.
 */
export function deriveTeams(
    facts: TeamFacts[],
    qualifyingRoas: number,
): TeamPerformance[] {
    const scored = facts.map((row) => {
        const target = round(row.target, 2);
        const budget = row.ad_budget === null ? null : round(row.ad_budget, 2);
        const roas = roasOf(row.sales, budget);
        const hitTarget = target > 0 && row.sales >= target;
        const hitRoas = roas !== null && roas >= qualifyingRoas;

        return {
            team_id: row.team_id,
            name: row.name,
            rank: 0,
            sales: row.sales,
            target,
            ad_budget: budget,
            achievement_pct: achievementOf(row.sales, target),
            roas,
            above_target: round(row.sales - target, 2),
            hit_target: hitTarget,
            hit_roas: hitRoas,
            qualified: hitTarget && hitRoas,
        };
    });

    scored.sort((a, b) => {
        const byAchievement =
            (b.achievement_pct ?? -1) - (a.achievement_pct ?? -1);

        return byAchievement !== 0 ? byAchievement : b.sales - a.sales;
    });

    return scored.map((team, i) => ({ ...team, rank: i + 1 }));
}

/**
 * Direction and percentage change against the previous day. Null when either
 * side is missing — there is nothing honest to draw an arrow from.
 */
function delta(current: number | null, previous: number | null): Trend | null {
    if (current === null || previous === null) return null;

    return {
        pct:
            previous !== 0
                ? round(((current - previous) / Math.abs(previous)) * 100, 1)
                : null,
        status:
            current > previous ? 'up' : current < previous ? 'down' : 'flat',
    };
}

/** The headline tiles, scored on the day's facts and the ranked teams. */
export function deriveKpis(
    facts: BoardFacts,
    teams: TeamPerformance[],
): GameboardKpis {
    const {
        total_sales: sales,
        target_sales: goal,
        ad_budget: budget,
    } = facts.today;

    const achievement = achievementOf(sales, goal);
    const roas = roasOf(sales, budget);
    const prev = facts.previous;

    return {
        date: facts.date,
        total_sales: sales,
        target_sales: goal,
        achievement_pct: achievement,
        ad_budget: budget,
        roas,
        qualifying_roas: facts.qualifying_roas,
        teams_total: teams.length,
        teams_at_target: teams.filter((t) => t.hit_target).length,
        teams_at_roas: teams.filter((t) => t.hit_roas).length,
        qualified_teams: teams.filter((t) => t.qualified).length,
        // Signed on purpose: below target reads as a shortfall, not a zero.
        above_target: round(sales - goal, 2),
        trends: {
            achievement: delta(
                achievement,
                prev
                    ? achievementOf(prev.total_sales, prev.target_sales)
                    : null,
            ),
            roas: delta(
                roas,
                prev ? roasOf(prev.total_sales, prev.ad_budget) : null,
            ),
        },
    };
}

/** The team out front, with the sparkline the server fetched for it. */
export function deriveLeader(
    teams: TeamPerformance[],
    qualifyingRoas: number,
    trend: LeaderTeam['trend'],
): LeaderTeam | null {
    const leader = teams[0];

    return leader
        ? { ...leader, trend, qualifying_roas: qualifyingRoas }
        : null;
}

/** Each team's sales beside its target, in the board's ranking order. */
export function deriveSalesVsTarget(
    teams: TeamPerformance[],
): SalesVsTargetPoint[] {
    return teams.map(({ team_id, name, sales, target }) => ({
        team_id,
        name,
        sales,
        target,
    }));
}

/**
 * The standings, cut to the top few unless expanded. `total` is always the full
 * count so the table can say what it is holding back.
 */
export function deriveLeaderboard(
    teams: TeamPerformance[],
    limit: number,
): LeaderboardData {
    return { rows: teams.slice(0, limit), total: teams.length };
}

/**
 * How the teams fall across achievement bands.
 *
 * Only teams with a target to measure against are placed — a team carrying an ad
 * budget alone has no achievement, so it is reported as `unrated` rather than
 * quietly counted as failing.
 */
export function deriveDistribution(teams: TeamPerformance[]): DistributionData {
    const bands = [
        { key: 'at_target', label: '100%+', min: 100 },
        { key: 'near', label: '75% - 99%', min: 75 },
        { key: 'half', label: '50% - 74%', min: 50 },
        { key: 'below', label: 'Below 50%', min: null as number | null },
    ];

    const rated = teams.filter((t) => t.achievement_pct !== null);

    const buckets = bands.map((band, i) => {
        // Each band runs from its own floor up to the floor of the one above.
        const ceiling = i === 0 ? null : bands[i - 1].min;
        const floor = band.min;

        const count = rated.filter((t) => {
            const pct = t.achievement_pct as number;

            return (
                (floor === null || pct >= floor) &&
                (ceiling === null || pct < ceiling)
            );
        }).length;

        return {
            key: band.key,
            label: band.label,
            count,
            share:
                rated.length === 0 ? 0 : round((count / rated.length) * 100, 1),
        };
    });

    return {
        buckets,
        total: rated.length,
        unrated: teams.length - rated.length,
    };
}
