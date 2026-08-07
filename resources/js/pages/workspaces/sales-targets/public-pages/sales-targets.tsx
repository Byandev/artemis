import { BoardSectionState } from '@/components/sales-targets/board-section';
import {
    BoardFacts,
    TeamFacts,
    deriveDistribution,
    deriveKpis,
    deriveLeader,
    deriveLeaderboard,
    deriveSalesVsTarget,
    deriveTeams,
} from '@/components/sales-targets/derive';
import { GameboardBackdrop } from '@/components/sales-targets/gameboard-backdrop';
import { GameboardDistribution } from '@/components/sales-targets/gameboard-distribution';
import {
    BoardTeam,
    GameboardHeader,
} from '@/components/sales-targets/gameboard-header';
import { GameboardKpiRow } from '@/components/sales-targets/gameboard-kpis';
import { GameboardLeader } from '@/components/sales-targets/gameboard-leader';
import { GameboardLeaderboard } from '@/components/sales-targets/gameboard-leaderboard';
import { GameboardSalesChart } from '@/components/sales-targets/gameboard-sales-chart';
import { GameboardSlideshow } from '@/components/sales-targets/gameboard-slideshow';
import { GameboardTeams } from '@/components/sales-targets/gameboard-teams';
import { useBoardSection } from '@/components/sales-targets/use-board-section';
import { Head, useForm } from '@inertiajs/react';
import { Lock, Target } from 'lucide-react';
import { useMemo, useState } from 'react';

interface PublicWorkspace {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    workspace: PublicWorkspace;
    /** When true the password gate is shown and no data is sent. */
    locked?: boolean;
    /** The day the board is scored on — today's target, else the most recent. */
    featured?: { id: number; name: string; date: string } | null;
    /** The teams on the featured day, and the one the board is narrowed to. */
    teams?: BoardTeam[];
    teamId?: number | null;
    /**
     * Set when the board was opened from a target's detail page — it stays on
     * that target instead of rolling to today's.
     */
    targetId?: number | null;
}

/** Password gate shown before the board when the workspace requires it. */
function SalesTargetsLock({ workspace }: { workspace: PublicWorkspace }) {
    const form = useForm({ password: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(
            `/public/workspaces/${workspace.slug}/sales-targets/verify-password`,
            {
                preserveScroll: true,
                onError: () => form.reset('password'),
            },
        );
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-stone-50 p-4 dark:bg-zinc-950">
            <div className="w-full max-w-sm rounded-2xl border border-black/8 bg-white p-6 dark:border-white/8 dark:bg-zinc-900">
                <div className="mb-4 flex flex-col items-center text-center">
                    <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                        <Lock className="h-5 w-5" />
                    </div>
                    <h1 className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        Protected page
                    </h1>
                    <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">
                        Enter the password to view {workspace.name}&apos;s sales
                        targets.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-3">
                    <input
                        type="password"
                        autoFocus
                        autoComplete="current-password"
                        value={form.data.password}
                        onChange={(e) =>
                            form.setData('password', e.target.value)
                        }
                        placeholder="Password"
                        className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600"
                    />
                    {form.errors.password && (
                        <p className="text-center font-mono text-[11px] text-red-500">
                            {form.errors.password}
                        </p>
                    )}
                    <button
                        type="submit"
                        disabled={form.processing || !form.data.password}
                        className="h-10 w-full rounded-[10px] bg-brand-600 font-mono! text-[12px]! font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
                    >
                        {form.processing ? 'Unlocking…' : 'Unlock'}
                    </button>
                </form>
            </div>
        </div>
    );
}

export default function PublicSalesTargets({
    workspace,
    locked,
    featured,
    teams,
    teamId,
    targetId,
}: Props) {
    // Refresh doesn't reload the page — it re-runs every section's own request.
    const [refreshKey, setRefreshKey] = useState(0);

    const shared = {
        workspaceSlug: workspace.slug,
        teamId,
        targetId,
        refreshKey,
    };

    // Two requests carry the whole board: the day's measured totals, and each
    // team's goal against what it sold. Everything below is arithmetic on those.
    const facts = useBoardSection<BoardFacts>({ ...shared, section: 'kpis' });
    const teamFacts = useBoardSection<TeamFacts[]>({
        ...shared,
        section: 'teams',
    });

    const qualifyingRoas = facts.data?.qualifying_roas ?? 0;

    const ranked = useMemo(
        () =>
            teamFacts.data ? deriveTeams(teamFacts.data, qualifyingRoas) : null,
        [teamFacts.data, qualifyingRoas],
    );

    const kpis = useMemo(
        () => (facts.data && ranked ? deriveKpis(facts.data, ranked) : null),
        [facts.data, ranked],
    );

    // Only the leader's sparkline still needs the server, so it is fetched for
    // whichever team the ranking above puts first.
    const leaderId = ranked?.[0]?.team_id ?? null;
    const trend = useBoardSection<{ date: string; sales: number }[]>({
        ...shared,
        section: 'team-trend',
        params: leaderId ? { team_id: leaderId } : undefined,
        enabled: leaderId !== null,
    });

    const leader = useMemo(
        () =>
            ranked
                ? deriveLeader(ranked, qualifyingRoas, trend.data ?? [])
                : null,
        [ranked, qualifyingRoas, trend.data],
    );

    // "View all" is just a bigger slice — no refetch, the rows are already here.
    const [showAllRanks, setShowAllRanks] = useState(false);
    const leaderboard = useMemo(
        () =>
            ranked ? deriveLeaderboard(ranked, showAllRanks ? 100 : 5) : null,
        [ranked, showAllRanks],
    );

    const salesChart = useMemo(
        () => (ranked ? deriveSalesVsTarget(ranked) : null),
        [ranked],
    );

    const distribution = useMemo(
        () => (ranked ? deriveDistribution(ranked) : null),
        [ranked],
    );

    const [presenting, setPresenting] = useState(false);

    // "Present on TV" is a presentation, so it takes the whole screen; leaving
    // it hands the screen back.
    const startPresenting = () => {
        setPresenting(true);
        document.documentElement.requestFullscreen?.().catch(() => {});
    };

    const stopPresenting = () => {
        setPresenting(false);
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        }
    };

    if (locked) {
        return <SalesTargetsLock workspace={workspace} />;
    }

    const busy = [facts, teamFacts, trend].some((section) => section.loading);

    // Every panel is derived from the same two requests, so they share one
    // loading/failed state and one retry.
    const panel = {
        loading: facts.loading || teamFacts.loading,
        failed: facts.failed || teamFacts.failed,
        onRetry: () => {
            facts.reload();
            teamFacts.reload();
        },
    };

    return (
        <div className="relative min-h-screen bg-stone-50 dark:bg-zinc-950">
            <Head title={`${workspace.name} - Sales Targets`} />

            <GameboardBackdrop />

            <GameboardHeader
                workspaceName={workspace.name}
                featured={featured}
                teams={teams}
                teamId={teamId}
                refreshing={busy}
                onRefresh={() => setRefreshKey((key) => key + 1)}
                onPresent={startPresenting}
            />

            {presenting && (
                <GameboardSlideshow
                    data={{
                        workspaceName: workspace.name,
                        featured,
                        kpis,
                        leader,
                        teams: ranked,
                    }}
                    onClose={stopPresenting}
                />
            )}

            <div className="relative mx-auto w-full max-w-(--breakpoint-2xl) px-4 py-3 md:px-6 2xl:px-8 2xl:py-5">
                {featured ? (
                    <>
                        {kpis ? (
                            <GameboardKpiRow kpis={kpis} />
                        ) : (
                            <BoardSectionState
                                {...panel}
                                label="the totals"
                                height="h-24"
                            />
                        )}

                        {leader ? (
                            <GameboardLeader
                                leader={leader}
                                qualifyingRoas={leader.qualifying_roas}
                            />
                        ) : (
                            <div className="mt-2">
                                <BoardSectionState
                                    {...panel}
                                    label="the leader"
                                    height="h-20"
                                />
                            </div>
                        )}

                        {ranked ? (
                            <GameboardTeams teams={ranked} />
                        ) : (
                            <div className="mt-4">
                                <BoardSectionState
                                    {...panel}
                                    label="team performance"
                                    height="h-40"
                                />
                            </div>
                        )}

                        {/* Standings, trend and spread sit side by side once
                            there is width for all three. */}
                        <div className="mt-4 grid grid-cols-1 gap-2 xl:grid-cols-12 2xl:mt-5 2xl:gap-3">
                            <div className="xl:col-span-5">
                                {leaderboard ? (
                                    <GameboardLeaderboard
                                        data={leaderboard}
                                        expanded={showAllRanks}
                                        onToggleExpanded={() =>
                                            setShowAllRanks((shown) => !shown)
                                        }
                                    />
                                ) : (
                                    <BoardSectionState
                                        {...panel}
                                        label="the leaderboard"
                                        height="h-56"
                                    />
                                )}
                            </div>

                            <div className="xl:col-span-4">
                                {salesChart ? (
                                    <GameboardSalesChart points={salesChart} />
                                ) : (
                                    <BoardSectionState
                                        {...panel}
                                        label="sales vs target"
                                        height="h-56"
                                    />
                                )}
                            </div>

                            <div className="xl:col-span-3">
                                {distribution ? (
                                    <GameboardDistribution
                                        data={distribution}
                                    />
                                ) : (
                                    <BoardSectionState
                                        {...panel}
                                        label="the distribution"
                                        height="h-56"
                                    />
                                )}
                            </div>
                        </div>
                    </>
                ) : (
                    <div className="flex flex-col items-center justify-center rounded-[16px] border border-dashed border-black/8 bg-white py-20 dark:border-white/8 dark:bg-zinc-900">
                        <div className="rounded-2xl bg-stone-100 p-3.5 dark:bg-zinc-800">
                            <Target className="h-7 w-7 text-gray-400 dark:text-gray-500" />
                        </div>
                        <p className="mt-4 text-[14px] font-semibold text-gray-700 dark:text-gray-200">
                            No sales targets yet
                        </p>
                        <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">
                            The board lights up once a target is set for the
                            day.
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}
