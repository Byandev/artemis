import { TeamPerformance } from '@/components/sales-targets/gameboard-teams';
import { formatPeso } from '@/pages/workspaces/sales-targets/shared';
import { Award, Trophy } from 'lucide-react';

export interface LeaderboardData {
    rows: TeamPerformance[];
    /** Every team on the day, however many rows were returned. */
    total: number;
}

/** Podium ink for the rank number and its trophy. */
const podiumInk: Record<number, string> = {
    1: 'text-amber-500',
    2: 'text-slate-400',
    3: 'text-orange-400 dark:text-orange-300',
};

function Status({ rank }: { rank: number }) {
    const ink = podiumInk[rank];

    if (ink) {
        return (
            <Trophy
                className={`h-3.5 w-3.5 ${ink}`}
                aria-label={`Rank ${rank}, podium`}
            />
        );
    }

    return (
        <Award
            className="h-3.5 w-3.5 text-rose-400 dark:text-rose-300"
            aria-label={`Rank ${rank}`}
        />
    );
}

/**
 * The day's standings as a table — the same ranking as the cards, read as a
 * league. Shows the top few until asked for the rest, which refetches rather
 * than shipping every team up front.
 */
export function GameboardLeaderboard({
    data,
    expanded,
    onToggleExpanded,
}: {
    data: LeaderboardData;
    expanded: boolean;
    onToggleExpanded: () => void;
}) {
    const headCell =
        'px-2 py-1.5 font-mono text-[8px] font-medium tracking-[0.1em] text-gray-500 uppercase dark:text-gray-400';
    const cell = 'px-2 py-2 text-[12px] tabular-nums whitespace-nowrap';

    return (
        <section className="mt-4">
            <div className="overflow-hidden rounded-[12px] border border-black/6 bg-white dark:border-white/8 dark:bg-zinc-900">
                <div className="flex items-center justify-between gap-3 border-b border-black/6 px-3 py-2.5 dark:border-white/8">
                    <h2 className="my-0! font-mono text-[10px]! font-medium tracking-[0.16em] text-gray-700 uppercase dark:text-gray-200">
                        Team Leaderboard
                    </h2>
                    {data.total > data.rows.length || expanded ? (
                        <button
                            onClick={onToggleExpanded}
                            className="rounded-md border border-black/8 px-2 py-1 font-mono text-[9px] font-medium tracking-wider text-gray-600 uppercase transition-all hover:bg-stone-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/10"
                        >
                            {expanded
                                ? 'Show Less'
                                : `View All (${data.total})`}
                        </button>
                    ) : null}
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full min-w-[640px] border-collapse">
                        <thead>
                            <tr className="border-b border-black/6 dark:border-white/8">
                                <th className={`${headCell} text-left`}>
                                    Rank
                                </th>
                                <th className={`${headCell} text-left`}>
                                    Team
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Target
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Sales
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Achievement
                                </th>
                                <th className={`${headCell} text-right`}>
                                    ROAS
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Ads Budget
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Difference
                                </th>
                                <th className={`${headCell} text-center`}>
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-black/4 dark:divide-white/4">
                            {data.rows.map((team) => (
                                <tr key={team.team_id}>
                                    <td
                                        className={`${cell} font-mono font-bold ${
                                            podiumInk[team.rank] ??
                                            'text-gray-500 dark:text-gray-400'
                                        }`}
                                    >
                                        {team.rank}
                                    </td>
                                    <td
                                        className={`${cell} text-gray-800 dark:text-gray-100`}
                                    >
                                        {team.name}
                                    </td>
                                    <td
                                        className={`${cell} text-right font-mono text-gray-700 dark:text-gray-300`}
                                    >
                                        {formatPeso(team.target)}
                                    </td>
                                    <td
                                        className={`${cell} text-right font-mono font-semibold text-brand-600 dark:text-brand-400`}
                                    >
                                        {formatPeso(team.sales)}
                                    </td>
                                    <td
                                        className={`${cell} text-right font-mono text-brand-600 dark:text-brand-400`}
                                    >
                                        {team.achievement_pct === null
                                            ? '—'
                                            : `${team.achievement_pct}%`}
                                    </td>
                                    <td
                                        className={`${cell} text-right font-mono text-gray-700 dark:text-gray-300`}
                                    >
                                        {team.roas === null
                                            ? '—'
                                            : team.roas.toFixed(2)}
                                    </td>
                                    <td
                                        className={`${cell} text-right font-mono text-gray-700 dark:text-gray-300`}
                                    >
                                        {team.ad_budget === null
                                            ? '—'
                                            : formatPeso(team.ad_budget)}
                                    </td>
                                    <td
                                        className={`${cell} text-right font-mono ${
                                            team.above_target >= 0
                                                ? 'text-brand-600 dark:text-brand-400'
                                                : 'text-red-500 dark:text-red-400'
                                        }`}
                                    >
                                        {team.above_target >= 0 ? '+' : '−'}
                                        {formatPeso(
                                            Math.abs(team.above_target),
                                        )}
                                    </td>
                                    <td className={`${cell} text-center`}>
                                        <span className="inline-flex justify-center">
                                            <Status rank={team.rank} />
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    );
}
