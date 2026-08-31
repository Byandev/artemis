import RefreshButton from '@/components/inventory/dashboard/refresh-button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import moment from 'moment';
import { useMemo, useState } from 'react';
import { formatKpi, type KpiFormat } from './kpi-card';
import {
    previousWindow,
    useSalesMarketingStat,
} from './use-sales-marketing-stat';

/** One advertiser's raw sums for a window, exactly as the endpoint answers. */
interface TeamRow {
    advertiser: { id: number; name: string | null };
    ad_spend: number;
    sales: number;
    orders: number;
    returned_amount: number;
    delivered_amount: number;
}

interface TeamComparison {
    rows: TeamRow[];
}

/**
 * The four things a team member can be compared on. `of` derives the figure
 * from the raw sums — nothing here is computed server-side, so switching metric
 * re-reads data already in hand rather than refetching.
 */
const METRICS = {
    sales: {
        label: 'Sales',
        as: 'currencyExact' as KpiFormat,
        of: (r: TeamRow) => r.sales,
    },
    ad_spend: {
        label: 'Ad spend',
        as: 'currencyExact' as KpiFormat,
        of: (r: TeamRow) => r.ad_spend,
    },
    roas: {
        label: 'ROAS',
        as: 'ratio' as KpiFormat,
        of: (r: TeamRow) => (r.ad_spend > 0 ? r.sales / r.ad_spend : 0),
    },
    rts: {
        label: 'RTS',
        as: 'percent' as KpiFormat,
        // Lower is better here, which is why `reverse` flips the delta colour.
        reverse: true,
        of: (r: TeamRow) => {
            const outcomes = r.returned_amount + r.delivered_amount;

            return outcomes > 0 ? r.returned_amount / outcomes : 0;
        },
    },
} satisfies Record<
    string,
    {
        label: string;
        as: KpiFormat;
        reverse?: boolean;
        of: (row: TeamRow) => number;
    }
>;

type MetricKey = keyof typeof METRICS;

/** How many members the chart plots before folding the rest into a note. */
const VISIBLE_ROWS = 8;

/**
 * Team member comparison: every advertiser on one scale for the selected
 * metric, against the same window last period.
 *
 * One series, so one colour — the names are already labelled down the side, and
 * a hue per member would encode nothing the bar length doesn't. The tick on each
 * bar is that member's previous-period figure, and the dashed rule is the
 * average across everyone shown.
 */
export default function TeamComparison({
    slug,
    dateRange,
}: {
    slug: string;
    /** `[start, end]` as YYYY-MM-DD. */
    dateRange: string[];
}) {
    // The chosen metric persists per workspace, so a refresh comes back to the
    // comparison you were reading rather than resetting to Sales.
    const METRIC_KEY = `sm_dashboard_team_metric_${slug}`;

    const [metric, setMetric] = useState<MetricKey>(() => {
        try {
            const saved = localStorage.getItem(METRIC_KEY);
            // Guarded: a stored value from an older build might name a metric
            // that no longer exists.
            if (saved && saved in METRICS) return saved as MetricKey;
        } catch {
            // Unreadable storage — fall through to the default.
        }
        return 'sales';
    });

    const chooseMetric = (key: MetricKey) => {
        setMetric(key);
        try {
            localStorage.setItem(METRIC_KEY, key);
        } catch {
            // Storage is best-effort; the choice still applies.
        }
    };

    const spec = METRICS[metric];

    const previous = useMemo(() => previousWindow(dateRange), [dateRange]);

    const current = useSalesMarketingStat<TeamComparison>(
        slug,
        'team-comparison',
        { start: dateRange[0], end: dateRange[1] },
    );
    const prior = useSalesMarketingStat<TeamComparison>(
        slug,
        'team-comparison',
        { start: previous[0], end: previous[1] },
    );

    const rows = useMemo(() => {
        if (!current.data) return [];

        const before = new Map(
            (prior.data?.rows ?? []).map((r) => [
                r.advertiser.id,
                (METRICS[metric].of as (row: TeamRow) => number)(r),
            ]),
        );

        return current.data.rows
            .map((row) => {
                const value = spec.of(row);
                const was = before.get(row.advertiser.id) ?? null;

                return {
                    row,
                    id: row.advertiser.id,
                    name: row.advertiser.name?.trim() || 'Unnamed advertiser',
                    value,
                    was,
                    // No percentage to report against zero.
                    change:
                        was !== null && was > 0
                            ? ((value - was) / was) * 100
                            : null,
                };
            })
            .filter((r) => r.value > 0)
            .sort((a, b) => b.value - a.value);
    }, [current.data, prior.data, metric, spec]);

    const shown = rows.slice(0, VISIBLE_ROWS);
    const max = Math.max(...shown.map((r) => Math.max(r.value, r.was ?? 0)), 0);
    const average = shown.length
        ? shown.reduce((sum, r) => sum + r.value, 0) / shown.length
        : 0;

    // Skeleton only until the first answer arrives; a later refetch holds the
    // previous render at reduced opacity rather than flashing back to bones.
    const firstLoad = (current.loading || prior.loading) && !current.data;
    const refetching = (current.loading || prior.loading) && !!current.data;

    return (
        <section className="mt-10">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-[11px] font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                    Team member comparison
                </h2>

                <div className="flex items-center gap-2">
                    {/* Controls above the chart they scope. Switching metric is
                        a client-side re-read — no refetch, no skeleton. */}
                    <div className="flex items-center gap-0.5 rounded-lg border border-black/6 bg-white p-0.5 dark:border-white/8 dark:bg-zinc-900">
                        {(Object.keys(METRICS) as MetricKey[]).map((key) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => chooseMetric(key)}
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

                    {/* Both windows are refetched together — the comparison is
                        meaningless if one half is stale. */}
                    <RefreshButton
                        onClick={() => {
                            current.refetch();
                            prior.refetch();
                        }}
                        loading={current.loading || prior.loading}
                        error={current.error || prior.error}
                        label="the team comparison"
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
                ) : current.error ? (
                    // The refresh control in the header turns red and doubles
                    // as the retry, so this only has to say what happened.
                    <p className="py-6 text-[11px] text-red-500 dark:text-red-400">
                        Failed to load — retry with the refresh button above.
                    </p>
                ) : shown.length === 0 ? (
                    <p className="py-6 text-[11px] text-gray-400 dark:text-gray-500">
                        nothing to compare in this period
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
                            {shown.map((r) => (
                                <Row
                                    key={r.id}
                                    name={r.name}
                                    value={r.value}
                                    was={r.was}
                                    change={r.change}
                                    max={max}
                                    as={spec.as}
                                    reverse={'reverse' in spec && spec.reverse}
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
                            {rows.length > VISIBLE_ROWS && (
                                <span>
                                    top {VISIBLE_ROWS} of {rows.length}
                                </span>
                            )}
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

/** One member's row: name, bar, value. */
function Row({
    name,
    value,
    was,
    change,
    max,
    as,
    reverse,
}: {
    name: string;
    value: number;
    was: number | null;
    change: number | null;
    max: number;
    as: KpiFormat;
    reverse?: boolean;
}) {
    const up = (change ?? 0) >= 0;
    // Green means "the right direction", which is down for a rate you want low.
    const good = reverse ? !up : up;

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
                {/* Square at the baseline, rounded at the data end. */}
                <div
                    className="h-full rounded-r-[4px] bg-[#059669]"
                    style={{ width: `${pct(value, max)}%` }}
                />

                {/* Where this member stood last period, on the same scale. */}
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
