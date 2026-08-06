import { formatPeso } from '@/pages/workspaces/sales-targets/shared';
import {
    BarChart3,
    LucideIcon,
    Rocket,
    Target,
    TrendingDown,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';

export interface Trend {
    pct: number | null;
    status: 'up' | 'down' | 'flat';
}

export interface GameboardKpis {
    date: string;
    total_sales: number;
    target_sales: number;
    /** Null when the day's target is zero — nothing to measure against. */
    achievement_pct: number | null;
    /** The target's summed per-team ad budgets — what the day is allowed to spend. */
    ad_budget: number | null;
    /** Sales ÷ ad budget. Null when no budget was set to divide by. */
    roas: number | null;
    qualifying_roas: number;
    teams_total: number;
    teams_at_target: number;
    teams_at_roas: number;
    qualified_teams: number;
    /** Signed: negative is a shortfall against the day's target. */
    above_target: number;
    trends: { achievement: Trend | null; roas: Trend | null };
}

type Tone = 'brand' | 'indigo';

const tones: Record<Tone, { ring: string; icon: string; bar: string }> = {
    brand: {
        ring: 'border-brand-500/40 dark:border-brand-400/40',
        icon: 'text-brand-600 dark:text-brand-400',
        bar: 'bg-brand-500 dark:bg-brand-400',
    },
    indigo: {
        ring: 'border-indigo-500/40 dark:border-indigo-400/40',
        icon: 'text-indigo-600 dark:text-indigo-400',
        bar: 'bg-indigo-500 dark:bg-indigo-400',
    },
};

interface CardProps {
    icon: LucideIcon;
    label: string;
    value: string;
    caption: string;
    tone?: Tone;
    trend?: Trend | null;
    /**
     * 0–100 fill. Null draws no bar — the tile is a figure with nothing to
     * measure it against — but keeps the space so the row stays aligned.
     */
    progress: number | null;
    /** Shown to the right of the bar when the fill alone would undersell it. */
    progressLabel?: string;
}

function KpiCard({
    icon: Icon,
    label,
    value,
    caption,
    tone = 'brand',
    trend,
    progress,
    progressLabel,
}: CardProps) {
    const t = tones[tone];
    const TrendIcon = trend?.status === 'down' ? TrendingDown : TrendingUp;

    return (
        <div className="rounded-[12px] border border-black/6 bg-white px-3 py-2.5 dark:border-white/8 dark:bg-zinc-900">
            <div className="flex items-center gap-2.5">
                <div
                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 ${t.ring} ${t.icon}`}
                >
                    <Icon className="h-4 w-4" />
                </div>

                <div className="min-w-0">
                    <p className="truncate font-mono text-[9px] font-medium tracking-[0.1em] text-gray-500 uppercase dark:text-gray-400">
                        {label}
                    </p>
                    <div className="flex items-baseline gap-1">
                        <span className="truncate text-[16px] leading-tight font-semibold text-gray-900 tabular-nums dark:text-white">
                            {value}
                        </span>
                        {trend && trend.status !== 'flat' && (
                            <TrendIcon
                                aria-label={
                                    trend.pct === null
                                        ? `Trending ${trend.status}`
                                        : `Trending ${trend.status} ${Math.abs(trend.pct)}% vs the day before`
                                }
                                className={`h-3 w-3 shrink-0 ${
                                    trend.status === 'up'
                                        ? 'text-brand-600 dark:text-brand-400'
                                        : 'text-red-500 dark:text-red-400'
                                }`}
                            />
                        )}
                    </div>
                    <p className="truncate text-[10px] text-gray-500 dark:text-gray-400">
                        {caption}
                    </p>
                </div>
            </div>

            <div className="mt-2 flex h-1 items-center gap-1.5">
                {progress !== null && (
                    <>
                        <div className="h-1 flex-1 overflow-hidden rounded-full bg-stone-200 dark:bg-zinc-800">
                            <div
                                className={`h-full rounded-full transition-[width] duration-500 ${t.bar}`}
                                style={{
                                    width: `${Math.min(100, Math.max(0, progress))}%`,
                                }}
                            />
                        </div>
                        {progressLabel && (
                            <span className="shrink-0 font-mono text-[9px] text-gray-500 tabular-nums dark:text-gray-400">
                                {progressLabel}
                            </span>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}

/**
 * The board's headline row: how the day is going against the target, and how
 * many teams are carrying it. Six tiles across on any real display — they only
 * stack on phone widths, where six abreast would be unreadable.
 */
export function GameboardKpiRow({ kpis }: { kpis: GameboardKpis }) {
    const {
        total_sales,
        target_sales,
        achievement_pct,
        ad_budget,
        roas,
        qualifying_roas,
        teams_total,
        teams_at_target,
        teams_at_roas,
        qualified_teams,
        above_target,
        trends,
    } = kpis;

    const exceeded = above_target >= 0;

    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-6">
            <KpiCard
                icon={Wallet}
                label="Total Sales"
                value={formatPeso(total_sales)}
                caption={`Target: ${formatPeso(target_sales)}`}
                progress={achievement_pct}
                progressLabel={
                    achievement_pct === null ? undefined : `${achievement_pct}%`
                }
            />

            <KpiCard
                icon={Target}
                label="Overall Achievement"
                value={achievement_pct === null ? '—' : `${achievement_pct}%`}
                caption={`${teams_at_target} of ${teams_total} ${teams_at_target === 1 ? 'team' : 'teams'} at 100%+`}
                trend={trends.achievement}
                progress={achievement_pct}
            />

            {/* The budget as set, on its own — nothing is being measured
                against it, so no spend figure and no bar. */}
            <KpiCard
                icon={Wallet}
                label="Total Ads Budget"
                value={ad_budget === null ? '—' : formatPeso(ad_budget)}
                tone="indigo"
                caption="Campaign budget"
                progress={null}
            />

            <KpiCard
                icon={BarChart3}
                label="Overall ROAS"
                value={roas === null ? '—' : roas.toFixed(2)}
                caption={`${teams_at_roas} of ${teams_total} ${teams_at_roas === 1 ? 'team' : 'teams'} at ${qualifying_roas.toFixed(2)}+`}
                trend={trends.roas}
                progress={roas === null ? null : (roas / qualifying_roas) * 100}
            />

            <KpiCard
                icon={Users}
                label="Qualified Teams"
                value={String(qualified_teams)}
                caption="Both targets met"
                progress={
                    teams_total > 0
                        ? (qualified_teams / teams_total) * 100
                        : null
                }
            />

            <KpiCard
                icon={Rocket}
                label={exceeded ? 'Above Target' : 'Below Target'}
                value={formatPeso(Math.abs(above_target))}
                caption={exceeded ? 'Company exceeded' : 'Left to cover'}
                progress={exceeded ? 100 : achievement_pct}
            />
        </div>
    );
}
