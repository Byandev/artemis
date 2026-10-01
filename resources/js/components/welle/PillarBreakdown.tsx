import { Skeleton } from '@/components/ui/skeleton';
import {
    PILLARS,
    TONES,
    emptyFootnote,
    type Pillar,
    type WelleStatContext,
} from '@/components/welle/WelleStatCards';

/** One bar of the breakdown. */
interface PillarRow {
    pillar: Pillar;
    /** Days this pillar was ticked. */
    days: number;
    /** Those days as a share of the month's recorded days; null with none. */
    rate: number | null;
}

/**
 * The Pillar Breakdown endpoint's answer.
 * See WelleStatsController::pillarBreakdown.
 */
export interface PillarBreakdownStat extends WelleStatContext {
    /** In the order the bars are drawn. */
    pillars: PillarRow[];
}

/** The bars a skeleton draws, before the endpoint has said how many there are. */
const PILLAR_ORDER: Pillar[] = ['movement', 'meditation', 'learning'];

/**
 * Pillar Breakdown — the three pillars against each other, each bar the same
 * month of recorded days.
 *
 * Every bar is the whole month and the fill is the days that pillar was
 * ticked, so the bars are directly comparable: a short bar is a pillar that
 * was missed, not a pillar measured over fewer days. Each one names the days
 * it was drawn from and the days it went untouched, the same claim the cards
 * above make.
 *
 * The figures are the pillar cards' own, read in one request rather than three
 * so no two bars can be drawn from different months.
 */
export function PillarBreakdown({
    stat,
    loading,
}: {
    stat: PillarBreakdownStat | null;
    loading: boolean;
}) {
    const empty = stat ? emptyFootnote(stat) : null;

    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-4 flex items-center justify-between gap-2">
                <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    Pillar Breakdown
                </span>
                {loading || !stat ? (
                    <Skeleton className="block h-[13px] w-24 rounded" />
                ) : (
                    <span className="text-[11px] text-gray-400 dark:text-gray-500">
                        {stat.month_label}
                    </span>
                )}
            </div>

            {loading || !stat ? (
                <div className="space-y-4">
                    {PILLAR_ORDER.map((pillar) => (
                        <div key={pillar}>
                            <div className="mb-1.5 flex items-center justify-between gap-2">
                                <span className="text-[11px] font-medium text-gray-500 capitalize dark:text-gray-400">
                                    {pillar}
                                </span>
                                <Skeleton className="block h-[13px] w-12 rounded" />
                            </div>
                            <Skeleton className="block h-2 w-full rounded-full" />
                            <Skeleton className="mt-1.5 block h-[13px] w-40 rounded" />
                        </div>
                    ))}
                </div>
            ) : empty ? (
                // No days behind the bars: the same advice the cards give,
                // rather than three empty tracks reading as three missed
                // pillars.
                <p className="py-6 text-center text-[12px] text-gray-400 dark:text-gray-500">
                    {empty}
                </p>
            ) : (
                <div className="space-y-4">
                    {stat.pillars.map(({ pillar, days, rate }) => (
                        <div key={pillar}>
                            <div className="mb-1.5 flex items-center justify-between gap-2">
                                <span className="text-[11px] font-medium text-gray-500 capitalize dark:text-gray-400">
                                    {pillar}
                                </span>
                                <span className="font-mono text-[11px] text-gray-500 tabular-nums dark:text-gray-400">
                                    {days} / {stat.total_days}
                                </span>
                            </div>

                            <div className="h-2 overflow-hidden rounded-full bg-stone-200 dark:bg-zinc-800">
                                <div
                                    className={`h-full rounded-full transition-[width] duration-500 ${TONES[PILLARS[pillar].tone].bar}`}
                                    style={{
                                        width: `${Math.min(100, Math.max(0, rate ?? 0))}%`,
                                    }}
                                />
                            </div>

                            {/* The bar in words: what share of the month it
                                covers, and how many days it did not. */}
                            <span className="mt-1.5 block text-[11px] text-gray-400 dark:text-gray-500">
                                {Math.round(rate ?? 0)}% of days ·{' '}
                                {stat.total_days - days === 0
                                    ? 'none missed'
                                    : `missed ${stat.total_days - days}`}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
