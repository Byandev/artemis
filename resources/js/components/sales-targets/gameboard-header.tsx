import {
    formatLongDate,
    SalesTarget,
} from '@/pages/workspaces/sales-targets/shared';
import { router } from '@inertiajs/react';
import { Maximize2, Minimize2, MonitorPlay, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface BoardTeam {
    id: number;
    name: string;
}

interface Props {
    workspaceName: string;
    /** The target the board is pointed at — today's, else the most recent one. */
    featured?: Pick<SalesTarget, 'name' | 'date'> | null;
    /** The teams on the board's day, to narrow it to one. */
    teams?: BoardTeam[];
    teamId?: number | null;
    /** True while any section is fetching, so Refresh can show it. */
    refreshing?: boolean;
    onRefresh: () => void;
    onPresent: () => void;
}

/**
 * Banner for the public sales targets board. Built to be readable across a room
 * on a wall-mounted TV, so it carries the workspace, the day it is showing and
 * the controls that matter on a screen nobody is sitting at: narrow to a team,
 * pull fresh numbers, and fill the display.
 */
export function GameboardHeader({
    workspaceName,
    featured,
    teams = [],
    teamId,
    refreshing = false,
    onRefresh,
    onPresent,
}: Props) {
    const [isFullscreen, setIsFullscreen] = useState(false);

    // Fullscreen can also be left with Esc or the browser chrome, so the label
    // follows the document rather than our own click.
    useEffect(() => {
        const sync = () => setIsFullscreen(!!document.fullscreenElement);
        document.addEventListener('fullscreenchange', sync);
        sync();

        return () => document.removeEventListener('fullscreenchange', sync);
    }, []);

    const toggleFullscreen = () => {
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
            return;
        }

        document.documentElement.requestFullscreen().catch(() => {});
    };

    // Team is the only thing in the query string — a pinned target lives in the
    // path, so reusing the current pathname keeps the board on its own day
    // without naming the target here.
    const selectTeam = (value: string) =>
        router.get(
            window.location.pathname,
            value ? { team_id: Number(value) } : {},
            { preserveScroll: true, preserveState: true },
        );

    const controlClass =
        'flex h-8 2xl:h-10 items-center gap-2 rounded-lg border border-black/8 bg-white px-3 2xl:px-4 text-[12px] 2xl:text-[14px] font-medium text-gray-700 transition-all hover:bg-stone-50 disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10';

    return (
        <header className="relative z-10 overflow-hidden border-b border-black/8 bg-white/80 backdrop-blur-md dark:border-white/8 dark:bg-zinc-950/80">
            {/* Brand wash behind the title — faint in light, a touch stronger on dark. */}
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(70%_140%_at_0%_50%,rgba(16,211,161,0.10),transparent_70%)] dark:bg-[radial-gradient(70%_140%_at_0%_50%,rgba(16,211,161,0.16),transparent_70%)]" />

            <div className="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-brand-500/60 via-brand-500/10 to-transparent dark:from-brand-400/60 dark:via-brand-400/10" />

            <div className="relative mx-auto flex w-full max-w-(--breakpoint-2xl) flex-wrap items-center justify-between gap-3 px-4 py-3 md:px-6 2xl:px-8 2xl:py-5">
                <div className="min-w-0">
                    <div className="flex items-center gap-1.5">
                        <span className="truncate font-mono text-[9px] font-medium tracking-[0.16em] text-gray-500 uppercase 2xl:text-[11px] dark:text-gray-300">
                            {workspaceName}
                        </span>
                        <span className="relative flex h-1.5 w-1.5">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-500 opacity-75" />
                            <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-brand-500" />
                        </span>
                        <span className="font-mono text-[9px] font-medium tracking-[0.16em] text-gray-400 uppercase 2xl:text-[11px]">
                            Live
                        </span>
                    </div>

                    <h1 className="my-0! text-[18px]! leading-none font-bold tracking-tight text-gray-900 uppercase sm:text-[22px]! md:text-[26px]! 2xl:text-[36px]! dark:text-white">
                        Double Digit Sales{' '}
                        <span className="text-brand-600 dark:text-brand-400">
                            Gameboard
                        </span>
                    </h1>

                    <p className="mt-1 text-[11px] text-gray-500 2xl:text-[14px] dark:text-gray-400">
                        {featured ? (
                            <>
                                {featured.name}
                                <span className="mx-1 text-gray-300 dark:text-gray-600">
                                    •
                                </span>
                                {formatLongDate(featured.date)}
                                <span className="mx-1 text-gray-300 dark:text-gray-600">
                                    —
                                </span>
                            </>
                        ) : null}
                        <em className="italic">Race to the Daily Target</em>
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    {teams.length > 0 && (
                        <select
                            aria-label="Filter by team"
                            value={teamId ?? ''}
                            onChange={(e) => selectTeam(e.target.value)}
                            className={`${controlClass} max-w-44 cursor-pointer`}
                        >
                            <option value="">All teams</option>
                            {teams.map((team) => (
                                <option key={team.id} value={team.id}>
                                    {team.name}
                                </option>
                            ))}
                        </select>
                    )}

                    <button
                        onClick={onRefresh}
                        disabled={refreshing}
                        className={controlClass}
                    >
                        Refresh
                        <RefreshCw
                            className={`h-3.5 w-3.5 2xl:h-4 2xl:w-4 ${refreshing ? 'animate-spin' : ''}`}
                        />
                    </button>

                    <button
                        onClick={onPresent}
                        className={`${controlClass} border-brand-500/50 text-brand-700 dark:border-brand-400/50 dark:text-brand-300`}
                    >
                        <MonitorPlay className="h-3.5 w-3.5 2xl:h-4 2xl:w-4" />
                        Present on TV
                    </button>

                    <button onClick={toggleFullscreen} className={controlClass}>
                        {isFullscreen ? 'Exit Full Screen' : 'Full Screen'}
                        {isFullscreen ? (
                            <Minimize2 className="h-3.5 w-3.5 2xl:h-4 2xl:w-4" />
                        ) : (
                            <Maximize2 className="h-3.5 w-3.5 2xl:h-4 2xl:w-4" />
                        )}
                    </button>
                </div>
            </div>
        </header>
    );
}
