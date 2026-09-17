import { Skeleton } from '@/components/ui/skeleton';
import { BookOpen, Brain, Footprints, Sparkles } from 'lucide-react';

/** What every My ESC card says about the figures beside it. */
export interface WelleStatContext {
    /** Days Welle has a record of this month — what each figure is read over. */
    total_days: number;
    /** `YYYY-MM`. */
    month: string;
    /** "September 2026" — the month named in plain words. */
    month_label: string;
    /** Whether a Welle account is connected at all. */
    connected: boolean;
}

/** The ESC Rate endpoint's answer. See WelleStatsController::escRate. */
export interface EscRateStat extends WelleStatContext {
    /** ESC days over recorded days as a percentage; null with nothing synced. */
    value: number | null;
    /** Days where all three pillars were done. */
    esc_days: number;
}

/** The three pillars of an ESC day, as the endpoint names them. */
export type Pillar = 'movement' | 'meditation' | 'learning';

/**
 * The days-with-a-pillar endpoint's answer.
 * See WelleStatsController::daysWithPillar.
 */
export interface DaysWithPillarStat extends WelleStatContext {
    /** Days the pillar was ticked; null with nothing synced. */
    value: number | null;
    /** Those days as a share of the month's recorded days. */
    rate: number | null;
    /** Which pillar was counted. */
    pillar: Pillar;
}

/**
 * How each card is coloured — its icon and its bar, which are always the same
 * hue so the fill under a figure reads as belonging to it.
 *
 * The classes are written out rather than built from the tone name: Tailwind
 * only ships a class it can see in the source, and an interpolated one is a
 * bar that is the right width and no colour at all.
 */
export type Tone = 'brand' | 'sky' | 'violet' | 'amber';

export const TONES: Record<Tone, { icon: string; bar: string }> = {
    brand: {
        icon: 'text-brand-600 dark:text-brand-400',
        bar: 'bg-brand-500 dark:bg-brand-400',
    },
    sky: {
        icon: 'text-sky-600 dark:text-sky-400',
        bar: 'bg-sky-500 dark:bg-sky-400',
    },
    violet: {
        icon: 'text-violet-600 dark:text-violet-400',
        bar: 'bg-violet-500 dark:bg-violet-400',
    },
    amber: {
        icon: 'text-amber-600 dark:text-amber-400',
        bar: 'bg-amber-500 dark:bg-amber-400',
    },
};

/**
 * One My ESC card.
 *
 * Deliberately the same shell as the CSR and dashboard cards — border, radius,
 * the small grey label over a mono figure, a fill under it — so a card here
 * reads as the same kind of thing as a card anywhere else. It carries no
 * trend: these days are counted against the month they are in, and there is no
 * previous period to measure them against yet.
 *
 * Each card fetches its own endpoint, so each carries its own loading flag and
 * skeletons on its own rather than holding up the card beside it. The bar
 * skeletons with the figure, since a card that fills in its number and leaves
 * an empty track behind reads as a real zero for the moment before it lands.
 */
export function WelleStatCard({
    title,
    icon: Icon,
    tone,
    value,
    footnote,
    progress,
    loading,
}: {
    title: string;
    icon: React.ComponentType<{ className?: string }>;
    tone: Tone;
    value: string;
    footnote: string;
    /**
     * 0–100 fill. Null draws no bar — there is nothing recorded to measure the
     * figure against — but keeps the space, so a row of cards stays aligned
     * whether or not each of them has days behind it.
     */
    progress: number | null;
    loading: boolean;
}) {
    const t = TONES[tone];

    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-2 flex items-center justify-between gap-2">
                <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {title}
                </span>
                <Icon className={`h-4 w-4 shrink-0 ${t.icon}`} />
            </div>

            {loading ? (
                <>
                    <Skeleton className="my-[5px] block h-[22px] w-24 rounded" />
                    <Skeleton className="mt-2 block h-[13px] w-36 rounded" />
                    <Skeleton className="mt-3 block h-1.5 w-full rounded-full" />
                </>
            ) : (
                <>
                    <span className="block font-mono text-[22px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                        {value}
                    </span>
                    <span className="mt-1.5 block text-[11px] text-gray-500 dark:text-gray-400">
                        {footnote}
                    </span>
                    <div className="mt-3 h-1.5">
                        {progress !== null && (
                            <div className="h-1.5 overflow-hidden rounded-full bg-stone-200 dark:bg-zinc-800">
                                <div
                                    className={`h-full rounded-full transition-[width] duration-500 ${t.bar}`}
                                    style={{
                                        width: `${Math.min(100, Math.max(0, progress))}%`,
                                    }}
                                />
                            </div>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}

/**
 * The footnote for a card with no days behind it, or null when it has some.
 *
 * Two empty cards that look alike need different advice: a connected account
 * whose month has not been fetched yet will fill itself in, and one with no
 * Welle account behind it never will until somebody connects one.
 */
export function emptyFootnote(stat: WelleStatContext): string | null {
    if (stat.total_days > 0) return null;

    return stat.connected
        ? `No days synced yet for ${stat.month_label}`
        : 'Connect Welle in Settings → Integrations';
}

/**
 * ESC Rate — the share of this month's recorded days that were ESC days.
 *
 * Shown whole: 43.75% is "44%", because a rate counted out of sixteen days
 * cannot carry two decimals' worth of meaning, and the days it was drawn from
 * are named underneath it anyway. The bar is filled from the unrounded rate —
 * it is a length, not a number to read off.
 *
 * A dash rather than 0% while there is nothing to read — a month with no days
 * synced is not a month of missed days.
 */
export function EscRateStatCard({
    stat,
    loading,
}: {
    stat: EscRateStat | null;
    loading: boolean;
}) {
    return (
        <WelleStatCard
            title="ESC Rate"
            icon={Sparkles}
            tone="brand"
            loading={loading || stat === null}
            value={stat?.value == null ? '—' : `${Math.round(stat.value)}%`}
            progress={stat?.value ?? null}
            footnote={
                stat === null
                    ? ''
                    : (emptyFootnote(stat) ??
                      `${stat.esc_days} of ${stat.total_days} day${stat.total_days === 1 ? '' : 's'} complete`)
            }
        />
    );
}

/** How each pillar is named and drawn. Keyed by the endpoint's own names. */
export const PILLARS: Record<
    Pillar,
    {
        title: string;
        icon: React.ComponentType<{ className?: string }>;
        tone: Tone;
    }
> = {
    movement: { title: 'Days with Movement', icon: Footprints, tone: 'sky' },
    meditation: { title: 'Days with Meditation', icon: Brain, tone: 'violet' },
    learning: { title: 'Days with Learning', icon: BookOpen, tone: 'amber' },
};

/**
 * Days with one pillar — the days this month it was ticked, whether or not the
 * other two were.
 *
 * The count is the figure and the share is the footnote and the bar, the other
 * way round from ESC Rate: thirteen days is the thing worth knowing, and "81%
 * of the month so far" is what says whether thirteen is a lot.
 *
 * One card for all three pillars, as there is one endpoint behind them: they
 * differ by a name, an icon and a hue, and three copies of this would only be
 * three places to change when the footnote's wording does.
 */
export function DaysWithPillarStatCard({
    pillar,
    stat,
    loading,
}: {
    pillar: Pillar;
    stat: DaysWithPillarStat | null;
    loading: boolean;
}) {
    const { title, icon, tone } = PILLARS[pillar];

    return (
        <WelleStatCard
            title={title}
            icon={icon}
            tone={tone}
            loading={loading || stat === null}
            // A dash rather than "0 days": with nothing recorded we do not know
            // the pillar was missed, only that Welle has not said so yet.
            value={
                stat?.value == null
                    ? '—'
                    : `${stat.value} day${stat.value === 1 ? '' : 's'}`
            }
            progress={stat?.rate ?? null}
            footnote={
                stat === null
                    ? ''
                    : (emptyFootnote(stat) ??
                      `${Math.round(stat.rate ?? 0)}% of the month so far`)
            }
        />
    );
}
