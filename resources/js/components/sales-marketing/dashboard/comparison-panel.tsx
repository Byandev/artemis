import RefreshButton from '@/components/inventory/dashboard/refresh-button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { Workspace } from '@/types/models/Workspace';
import { usePage } from '@inertiajs/react';
import moment from 'moment';
import { useMemo, useState } from 'react';
import { formatKpi, type KpiFormat } from './kpi-card';

/**
 * The raw sums a comparison endpoint answers with, one row per thing compared —
 * per advertiser for the team panel, per product for the product one. Both
 * endpoints deliberately return sums and nothing else, so every metric below is
 * derived here and switching between them re-reads data already in hand.
 */
export interface ComparisonSums {
    ad_spend: number;
    sales: number;
    orders: number;
    returned_amount: number;
    delivered_amount: number;
}

export type MetricKey = 'sales' | 'ad_spend' | 'roas' | 'rts';

export interface MetricSpec {
    label: string;
    as: KpiFormat;
    /** For metrics where a rise is bad news, so the delta colours by meaning. */
    reverse?: boolean;
    of: (row: ComparisonSums) => number;
}

/** The four things anything on this dashboard can be compared on. */
export const METRICS: Record<MetricKey, MetricSpec> = {
    sales: {
        label: 'Sales',
        as: 'currencyExact',
        of: (r) => r.sales,
    },
    ad_spend: {
        label: 'Ad spend',
        as: 'currencyExact',
        of: (r) => r.ad_spend,
    },
    roas: {
        label: 'ROAS',
        as: 'ratio',
        of: (r) => (r.ad_spend > 0 ? r.sales / r.ad_spend : 0),
    },
    rts: {
        label: 'RTS',
        as: 'percent',
        // Lower is better here, which is why `reverse` flips the delta colour.
        reverse: true,
        of: (r) => {
            const outcomes = r.returned_amount + r.delivered_amount;

            return outcomes > 0 ? r.returned_amount / outcomes : 0;
        },
    },
};

/** Every metric, in the order the switcher lays them out. */
const ALL_METRICS = Object.keys(METRICS) as MetricKey[];

/** The metrics derived from ad spend, hidden together or not at all. */
const SPEND_METRICS: MetricKey[] = ['ad_spend', 'roas'];

/**
 * Whether the per-product panels state ad spend. A Gencys partner's spend is not
 * attributed per product on our side, so those panels drop the spend column and
 * the two metrics derived from it rather than stating sums they only half know.
 * The team panels keep all four — spend there is per advertiser, which we do
 * have.
 *
 * Read from the shared workspace rather than passed down, so the chart and the
 * table under it can't disagree about what this workspace shows.
 */
export function useProductSpendShown(): boolean {
    const { currentWorkspace } = usePage().props as unknown as {
        currentWorkspace?: Workspace;
    };

    return !currentWorkspace?.is_gencys_partner;
}

/**
 * Which metrics the product comparison may offer here — the switcher and the
 * remembered selection both read it, so neither can name a metric the other
 * doesn't have.
 */
export function useProductComparisonMetrics(): MetricKey[] {
    const spendShown = useProductSpendShown();

    return useMemo(
        () =>
            spendShown
                ? ALL_METRICS
                : ALL_METRICS.filter((key) => !SPEND_METRICS.includes(key)),
        [spendShown],
    );
}

/**
 * Raw sums added together — how a set of rows folded into one bar ("Others")
 * gets its figures. Summing first and deriving after is the only order that
 * works: a bucket's ROAS is its total sales over its total spend, never the
 * mean of the ratios inside it.
 */
export const sumComparisonRows = (rows: ComparisonSums[]): ComparisonSums =>
    rows.reduce(
        (total, row) => ({
            ad_spend: total.ad_spend + row.ad_spend,
            sales: total.sales + row.sales,
            orders: total.orders + row.orders,
            returned_amount: total.returned_amount + row.returned_amount,
            delivered_amount: total.delivered_amount + row.delivered_amount,
        }),
        {
            ad_spend: 0,
            sales: 0,
            orders: 0,
            returned_amount: 0,
            delivered_amount: 0,
        },
    );

/** A percentage change, or null when there is no base to state one against. */
export const changeFrom = (value: number, was: number | null): number | null =>
    was !== null && was > 0 ? ((value - was) / was) * 100 : null;

/**
 * The selected metric, remembered per panel so a refresh comes back to the
 * comparison you were reading rather than resetting to Sales.
 */
export function useComparisonMetric(
    storageKey: string,
    available: MetricKey[] = ALL_METRICS,
): [MetricKey, (key: MetricKey) => void] {
    const [metric, setMetric] = useState<MetricKey>(() => {
        try {
            const saved = localStorage.getItem(storageKey);
            // Guarded: a stored value can name a metric that no longer exists,
            // or one this workspace is no longer offered.
            if (saved && available.includes(saved as MetricKey)) {
                return saved as MetricKey;
            }
        } catch {
            // Unreadable storage — fall through to the default.
        }
        return available[0] ?? 'sales';
    });

    const choose = (key: MetricKey) => {
        setMetric(key);
        try {
            localStorage.setItem(storageKey, key);
        } catch {
            // Storage is best-effort; the choice still applies.
        }
    };

    return [metric, choose];
}

/** One plotted bar, after the caller has picked a metric and ranked its rows. */
export interface ComparisonBar {
    key: string | number;
    name: string;
    value: number;
    /** The same figure last period, or null when there was nothing to compare. */
    was: number | null;
    change: number | null;
    /**
     * The bar's fill in each theme. Left off for a single-series comparison,
     * where one colour is the honest answer — the names are already labelled
     * down the side, so a hue per row would encode nothing the length doesn't.
     */
    fill?: { light: string; dark: string };
}

/** The single-series fill, for panels that compare one kind of thing. */
const DEFAULT_FILL = { light: '#059669', dark: '#059669' };

/**
 * A ranked comparison: every row on one scale for the selected metric, against
 * the same window last period.
 *
 * The tick on each bar is that row's previous-period figure, and the dashed rule
 * is the average across everything shown. The caller owns the fetching, the
 * ranking and any folding into an "Others" bar; this owns how the result reads.
 */
export default function ComparisonPanel({
    title,
    metric,
    metrics = ALL_METRICS,
    onMetric,
    bars,
    previous,
    loading,
    error,
    firstLoad,
    onRefresh,
    refreshLabel,
    emptyHint,
    note,
}: {
    title: string;
    metric: MetricKey;
    /** The metrics the switcher offers — see `useComparisonMetrics`. */
    metrics?: MetricKey[];
    onMetric: (key: MetricKey) => void;
    bars: ComparisonBar[];
    /** `[start, end]` of the window the ticks compare against. */
    previous: string[];
    loading: boolean;
    error: boolean;
    /** True until the first answer lands, so a refetch dims rather than flashes. */
    firstLoad: boolean;
    onRefresh: () => void;
    /** Names what is being refreshed, for the control's label. */
    refreshLabel: string;
    /** Why there is nothing to plot, in the panel's own terms. */
    emptyHint: string;
    /** An extra line in the footer, where the panel has one to add. */
    note?: string;
}) {
    const spec = METRICS[metric];

    const max = Math.max(...bars.map((b) => Math.max(b.value, b.was ?? 0)), 0);
    const average = bars.length
        ? bars.reduce((sum, b) => sum + b.value, 0) / bars.length
        : 0;

    // A refetch holds the previous render at reduced opacity rather than
    // flashing back to bones.
    const refetching = loading && !firstLoad;

    return (
        <section className="mt-10">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-[11px] font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                    {title}
                </h2>

                <div className="flex items-center gap-2">
                    {/* Controls above the chart they scope. Switching metric is
                        a client-side re-read — no refetch, no skeleton. */}
                    <div className="flex items-center gap-0.5 rounded-lg border border-black/6 bg-white p-0.5 dark:border-white/8 dark:bg-zinc-900">
                        {metrics.map((key) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => onMetric(key)}
                                aria-pressed={metric === key}
                                className={cn(
                                    'rounded-md px-3 py-1 text-[11px] font-medium transition-colors',
                                    metric === key
                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                        : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-100',
                                )}
                            >
                                {METRICS[key].label}
                            </button>
                        ))}
                    </div>

                    <RefreshButton
                        onClick={onRefresh}
                        loading={loading}
                        error={error}
                        label={refreshLabel}
                    />
                </div>
            </div>

            <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
                {firstLoad ? (
                    <div className="space-y-3">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <div key={i} className="flex items-center gap-4">
                                <Skeleton className="h-3 w-44 shrink-0" />
                                <Skeleton className="h-6 flex-1" />
                                <Skeleton className="h-3 w-24 shrink-0" />
                            </div>
                        ))}
                    </div>
                ) : error ? (
                    // The refresh control in the header turns red and doubles
                    // as the retry, so this only has to say what happened.
                    <p className="py-6 text-[11px] text-red-500 dark:text-red-400">
                        Failed to load — retry with the refresh button above.
                    </p>
                ) : bars.length === 0 ? (
                    <p className="py-6 text-[11px] text-gray-400 dark:text-gray-500">
                        {emptyHint}
                    </p>
                ) : (
                    <div
                        className={cn(
                            'transition-opacity',
                            refetching && 'opacity-50',
                        )}
                    >
                        {/* The rows, with the average rule laid over them as a
                            single mark. The overlay is a sibling row using the
                            same column widths, so it tracks the bars without
                            competing with them for space. */}
                        <div className="relative space-y-2.5">
                            {bars.map((bar) => (
                                <Row
                                    key={bar.key}
                                    bar={bar}
                                    max={max}
                                    as={spec.as}
                                    reverse={spec.reverse}
                                />
                            ))}

                            {average > 0 && (
                                <div
                                    className={cn(
                                        'pointer-events-none absolute inset-0 flex items-stretch',
                                        ROW_GAP,
                                    )}
                                    aria-hidden
                                >
                                    <div className={NAME_COL} />
                                    <div className="relative flex-1">
                                        <span
                                            className="absolute -top-1 -bottom-1 border-l-2 border-dashed border-gray-500 dark:border-gray-300"
                                            style={{
                                                left: `${pct(average, max)}%`,
                                            }}
                                        />
                                    </div>
                                    <div className={VALUE_COL} />
                                </div>
                            )}
                        </div>

                        {/* The rule's label, on the same column geometry so it
                            sits directly under the line it names. */}
                        {average > 0 && (
                            <div
                                className={cn('mt-2 flex items-start', ROW_GAP)}
                            >
                                <div className={NAME_COL} />
                                <div className="relative h-4 flex-1">
                                    <span
                                        className="absolute -translate-x-1/2 font-mono text-[11px] whitespace-nowrap text-gray-500 tabular-nums dark:text-gray-400"
                                        style={{
                                            left: `${pct(average, max)}%`,
                                        }}
                                    >
                                        Average {formatKpi(average, spec.as)}
                                    </span>
                                </div>
                                <div className={VALUE_COL} />
                            </div>
                        )}

                        <div className="mt-3 flex flex-wrap items-center justify-end gap-3 border-t border-black/6 pt-3 text-[11px] text-gray-400 dark:border-white/6 dark:text-gray-500">
                            {note && <span>{note}</span>}
                            {/* The tick's legend — identity is never left to
                                the mark alone. */}
                            <span className="flex items-center gap-1.5">
                                <span className="inline-block h-3 w-[2px] bg-gray-500 dark:bg-gray-300" />
                                vs {moment(previous[0]).format('MMM D')} –{' '}
                                {moment(previous[1]).format('MMM D')}
                            </span>
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}

/** A value's position on the shared scale, as a percentage of the largest. */
const pct = (value: number, max: number) =>
    max > 0 ? Math.min(100, (value / max) * 100) : 0;

/**
 * The row's column geometry, in one place. The average rule and its label are
 * separate rows laid over the same widths, so they only line up with the bars
 * while all three agree — hence the shared constants rather than three copies.
 */
const NAME_COL = 'w-[5.5rem] shrink-0 sm:w-52';
const VALUE_COL = 'w-32 shrink-0 sm:w-44';
const ROW_GAP = 'gap-3 sm:gap-4';

/** One row: name, bar, value. */
function Row({
    bar,
    max,
    as,
    reverse,
}: {
    bar: ComparisonBar;
    max: number;
    as: KpiFormat;
    reverse?: boolean;
}) {
    const { name, value, was, change } = bar;
    const up = (change ?? 0) >= 0;
    // Green means "the right direction", which is down for a rate you want low.
    const good = reverse ? !up : up;
    const fill = bar.fill ?? DEFAULT_FILL;

    return (
        <div className={cn('flex items-center', ROW_GAP)}>
            <p
                className={cn(
                    'truncate font-mono text-[11px] text-gray-600 uppercase dark:text-gray-300',
                    NAME_COL,
                )}
                title={name}
            >
                {name}
            </p>

            <div
                className="relative h-6 flex-1 overflow-hidden rounded-md bg-stone-100 dark:bg-zinc-800"
                title={`${name}\n${formatKpi(value, as)}${was !== null ? ` (was ${formatKpi(was, as)})` : ''}`}
            >
                {/* Square at the baseline, rounded at the data end. The fill is
                    carried as a variable per theme so one element serves both. */}
                <div
                    className="h-full rounded-r-[4px] bg-[var(--bar-fill)] dark:bg-[var(--bar-fill-dark)]"
                    style={
                        {
                            width: `${pct(value, max)}%`,
                            '--bar-fill': fill.light,
                            '--bar-fill-dark': fill.dark,
                        } as React.CSSProperties
                    }
                />

                {/* Where this row stood last period, on the same scale. */}
                {was !== null && was > 0 && (
                    <span
                        className="absolute top-0.5 bottom-0.5 w-[2px] bg-gray-500 dark:bg-gray-300"
                        style={{ left: `calc(${pct(was, max)}% - 1px)` }}
                        aria-hidden
                    />
                )}
            </div>

            <div
                className={cn('flex items-center justify-end gap-2', VALUE_COL)}
            >
                <span className="font-mono text-[11px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                    {formatKpi(value, as)}
                </span>
                {change !== null && (
                    <span
                        className={cn(
                            'rounded px-1 py-0.5 font-mono text-[10px] font-semibold tabular-nums',
                            good
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                : 'bg-red-500/10 text-red-600 dark:text-red-400',
                        )}
                    >
                        {up ? '+' : '−'}
                        {Math.abs(change).toFixed(1)}%
                    </span>
                )}
            </div>
        </div>
    );
}
