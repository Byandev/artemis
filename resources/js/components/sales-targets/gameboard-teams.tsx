import { formatPeso } from '@/pages/workspaces/sales-targets/shared';
import { ArrowUp, Flame } from 'lucide-react';

export interface TeamPerformance {
    team_id: number;
    name: string;
    rank: number;
    sales: number;
    target: number;
    ad_budget: number | null;
    achievement_pct: number | null;
    /** Sales ÷ the team's ad budget. */
    roas: number | null;
    /** Signed: negative is a shortfall against the team's target. */
    above_target: number;
    hit_target: boolean;
    hit_roas: boolean;
    qualified: boolean;
}

/** The bar runs to 125%, so beating the target still has visible room above it. */
const SCALE_MAX = 125;
const TICKS = [0, 25, 50, 75, 100, 125];

/** Podium colours for the top three; everyone else gets the neutral treatment. */
const podium: Record<number, { card: string; badge: string }> = {
    1: {
        card: 'border-amber-400/60 dark:border-amber-400/50',
        badge: 'bg-amber-400 text-amber-950',
    },
    2: {
        card: 'border-slate-300 dark:border-slate-400/50',
        badge: 'bg-slate-300 text-slate-800',
    },
    3: {
        card: 'border-orange-400/60 dark:border-orange-400/50',
        badge: 'bg-orange-400 text-orange-950',
    },
};

function Stat({
    label,
    value,
    tone = 'ink',
    trailing,
}: {
    label: string;
    value: string;
    tone?: 'ink' | 'brand' | 'red';
    trailing?: React.ReactNode;
}) {
    const toneClass = {
        ink: 'text-gray-800 dark:text-gray-100',
        brand: 'text-brand-600 dark:text-brand-400',
        red: 'text-red-500 dark:text-red-400',
    }[tone];

    return (
        <div className="min-w-0">
            <p className="truncate font-mono text-[8px] font-medium tracking-[0.1em] text-gray-500 uppercase dark:text-gray-400">
                {label}
            </p>
            <p
                className={`flex items-center gap-1 truncate font-mono text-[13px] font-semibold tabular-nums ${toneClass}`}
            >
                {value}
                {trailing}
            </p>
        </div>
    );
}

function TeamCard({ team }: { team: TeamPerformance }) {
    const style = podium[team.rank];
    const achievement = team.achievement_pct ?? 0;
    const exceeded = team.above_target >= 0;

    return (
        <div
            className={`rounded-[12px] border bg-white p-3 dark:bg-zinc-900 ${
                style?.card ?? 'border-black/6 dark:border-white/8'
            }`}
        >
            <div className="flex items-center gap-2">
                <span
                    className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full font-mono text-[10px] font-bold ${
                        style?.badge ??
                        'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400'
                    }`}
                >
                    {team.rank}
                </span>
                <h3 className="my-0! truncate text-[14px]! font-bold tracking-tight text-gray-900 uppercase dark:text-white">
                    {team.name}
                </h3>
            </div>

            <div className="mt-2.5 grid grid-cols-2 gap-x-3 gap-y-2">
                <Stat label="Sales Target" value={formatPeso(team.target)} />
                <Stat
                    label="Current Sales"
                    value={formatPeso(team.sales)}
                    tone="brand"
                />
                <Stat
                    label="Ads Budget"
                    value={
                        team.ad_budget === null
                            ? '—'
                            : formatPeso(team.ad_budget)
                    }
                />
                <Stat
                    label="Target Achievement"
                    value={
                        team.achievement_pct === null
                            ? '—'
                            : `${team.achievement_pct}%`
                    }
                    tone="brand"
                    trailing={
                        team.hit_target ? (
                            <ArrowUp className="h-3 w-3 shrink-0" />
                        ) : null
                    }
                />
                <Stat
                    label="ROAS"
                    value={team.roas === null ? '—' : team.roas.toFixed(2)}
                    tone="brand"
                />
                <Stat
                    label={exceeded ? 'Above Target' : 'Below Target'}
                    value={formatPeso(Math.abs(team.above_target))}
                    tone={exceeded ? 'brand' : 'red'}
                />
            </div>

            <div className="mt-3">
                <div className="h-1.5 overflow-hidden rounded-full bg-stone-200 dark:bg-zinc-800">
                    <div
                        className="h-full rounded-full bg-brand-500 transition-[width] duration-500 dark:bg-brand-400"
                        style={{
                            width: `${Math.min(100, Math.max(0, (achievement / SCALE_MAX) * 100))}%`,
                        }}
                    />
                </div>
                <div className="mt-1 flex justify-between font-mono text-[8px] text-gray-400 tabular-nums dark:text-gray-500">
                    {TICKS.map((tick) => (
                        <span key={tick}>{tick}%</span>
                    ))}
                </div>
                <p className="text-right font-mono text-[11px] font-semibold text-brand-600 tabular-nums dark:text-brand-400">
                    {team.achievement_pct === null
                        ? '—'
                        : `${team.achievement_pct}%`}
                </p>
            </div>

            <div className="mt-2 flex flex-wrap items-center gap-2">
                {team.qualified && (
                    <span className="rounded-md border border-brand-500/40 px-2 py-0.5 font-mono text-[9px] font-medium tracking-wider text-brand-600 uppercase dark:border-brand-400/40 dark:text-brand-400">
                        Qualified
                    </span>
                )}
                {team.hit_target && (
                    <span className="flex items-center gap-1 font-mono text-[9px] font-medium tracking-wider text-amber-600 uppercase dark:text-amber-500">
                        <Flame className="h-3 w-3" />
                        Target Breaker
                    </span>
                )}
            </div>
        </div>
    );
}

/**
 * Every team on the day, best first. The cards repeat the tiles' measures one
 * team at a time — the row above says how the company is doing, this says who
 * is doing it.
 */
export function GameboardTeams({ teams }: { teams: TeamPerformance[] }) {
    if (teams.length === 0) {
        return null;
    }

    return (
        <section className="mt-4">
            <div className="flex items-center gap-3">
                <h2 className="my-0! shrink-0 font-mono text-[9px]! font-medium tracking-[0.16em] text-gray-500 uppercase dark:text-gray-400">
                    Team Performance
                </h2>
                <div className="h-px flex-1 bg-black/8 dark:bg-white/8" />
            </div>

            <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                {teams.map((team) => (
                    <TeamCard key={team.team_id} team={team} />
                ))}
            </div>
        </section>
    );
}
