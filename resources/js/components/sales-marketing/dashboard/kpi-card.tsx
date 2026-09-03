import { Skeleton } from '@/components/ui/skeleton';
import { cn, currencyFormatter } from '@/lib/utils';
import { ArrowDownRight, ArrowUpRight, RotateCcw } from 'lucide-react';
import moment from 'moment';
import { useMemo } from 'react';
import StatShell from './stat-shell';
import { useSalesMarketingStat } from './use-sales-marketing-stat';

/**
 * What an endpoint answers with. Every KPI returns `value`; some carry extra
 * figures their caption states (blended ROAS's `actual`, RTS rate's
 * `returning_amount`), which is why the rest is left open.
 */
export interface StatData {
    /** Null when the figure does not apply — a ratio with nothing to divide by. */
    value: number | null;
    [key: string]: number | null | undefined;
}

/**
 * How a KPI's figures read. `currency` abbreviates (₱31.94M) for headline
 * amounts; `currencyExact` spells them out, for a caption where the exact
 * figure is the point.
 */
export type KpiFormat =
    | 'currency'
    | 'currencyExact'
    | 'ratio'
    | 'percent'
    | 'number';

const pesos = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0,
});

const counts = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 });

export const formatKpi = (value: number, as: KpiFormat): string => {
    switch (as) {
        case 'currency':
            return currencyFormatter(value);
        case 'currencyExact':
            return pesos.format(value);
        case 'number':
            return counts.format(value);
        case 'percent':
            // Rates arrive as a 0–1 ratio, the way the metrics report them.
            return `${(value * 100).toFixed(2)}%`;
        default:
            return value.toFixed(2);
    }
};

/**
 * The window immediately before the selected one, of the same length, so
 * "previous period" always compares like with like. Worked out here rather than
 * server-side: the page owns the date range, and each endpoint stays a plain
 * "this figure for this window" lookup.
 */
function previousWindow(dateRange: string[]): string[] {
    const start = moment(dateRange[0]);
    const days = moment(dateRange[1]).diff(start, 'days') + 1;

    return [
        start.clone().subtract(days, 'days').format('YYYY-MM-DD'),
        start.clone().subtract(1, 'days').format('YYYY-MM-DD'),
    ];
}

/**
 * One KPI: a figure for the selected window, against the window before it. Each
 * card owns its endpoint and loads both windows on their own requests, so the
 * page paints before any number does and a slow KPI never holds up the others.
 */
export default function KpiCard({
    slug,
    label,
    endpoint,
    dateRange,
    as = 'currency',
    reverse = false,
    emptyHint,
    caption,
}: {
    slug: string;
    label: string;
    /** The KPI's own endpoint under `.../dashboard/`. */
    endpoint: string;
    /** `[start, end]` as YYYY-MM-DD. */
    dateRange: string[];
    as?: KpiFormat;
    /**
     * For KPIs where a rise is bad news (RTS rate), so the trend colours the
     * way the number actually reads rather than by sign alone.
     */
    reverse?: boolean;
    /** Why the figure is absent, for KPIs that can legitimately have none. */
    emptyHint?: string;
    /**
     * The line under the figure. Defaults to the previous period's value; pass
     * one when the KPI has something more useful to say there.
     */
    caption?: (data: StatData, prior: StatData | null) => string | null;
}) {
    const previous = useMemo(() => previousWindow(dateRange), [dateRange]);

    // Team narrowing comes from the workspace-wide "viewing as team" switcher,
    // which the server resolves per request — nothing to send from here.
    const current = useSalesMarketingStat<StatData>(slug, endpoint, {
        start: dateRange[0],
        end: dateRange[1],
    });

    const prior = useSalesMarketingStat<StatData>(slug, endpoint, {
        start: previous[0],
        end: previous[1],
    });

    const loading = current.loading || prior.loading;
    const value = current.data?.value ?? null;
    const priorValue = prior.data?.value ?? null;

    // No percentage to report against zero, so the card just states the figure.
    const change =
        priorValue !== null && priorValue > 0 && value !== null
            ? ((value - priorValue) / priorValue) * 100
            : null;
    const up = (change ?? 0) >= 0;
    const Arrow = up ? ArrowUpRight : ArrowDownRight;
    // Green means "the right direction", which is down for a rate you want low.
    const good = reverse ? !up : up;

    const line = current.data
        ? caption
            ? caption(current.data, prior.data)
            : priorValue !== null
              ? `vs ${formatKpi(priorValue, as)} previous period`
              : null
        : null;

    return (
        // The trend sits in the shell's top-right slot — where the icon used to
        // be, so the direction reads at a glance down a row of cards.
        <StatShell
            label={label}
            aside={
                loading ? (
                    <Skeleton className="h-4 w-12" />
                ) : (
                    change !== null && (
                        <span
                            className={cn(
                                'flex shrink-0 items-center gap-0.5 font-mono text-[11px] font-semibold tabular-nums',
                                good
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-red-600 dark:text-red-400',
                            )}
                        >
                            <Arrow className="h-3.5 w-3.5" />
                            {Math.abs(change).toFixed(1)}%
                        </span>
                    )
                )
            }
        >
            <>
                {loading ? (
                    <>
                        <Skeleton className="h-[30px] w-32" />
                        <Skeleton className="mt-2.5 h-3 w-44" />
                    </>
                ) : current.error ? (
                    <>
                        <h4 className="font-mono text-[26px] font-semibold tracking-tight text-gray-300 tabular-nums dark:text-gray-600">
                            —
                        </h4>
                        <button
                            type="button"
                            onClick={() => {
                                current.refetch();
                                prior.refetch();
                            }}
                            className="mt-1.5 flex items-center gap-1.5 text-[11px] text-red-500 hover:underline dark:text-red-400"
                        >
                            <RotateCcw className="h-3 w-3" />
                            Failed — retry
                        </button>
                    </>
                ) : value === null ? (
                    // The request succeeded, the figure just does not apply —
                    // a ratio with nothing to divide by. Say so rather than
                    // offering a retry that would return the same nothing.
                    <>
                        <h4 className="font-mono text-[26px] font-semibold tracking-tight text-gray-300 tabular-nums dark:text-gray-600">
                            —
                        </h4>
                        {emptyHint && (
                            <p className="mt-1.5 text-[11px] text-gray-400 dark:text-gray-500">
                                {emptyHint}
                            </p>
                        )}
                    </>
                ) : (
                    <>
                        <h4 className="font-mono text-[26px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                            {formatKpi(value, as)}
                        </h4>
                        {line && (
                            <p className="mt-1.5 text-[11px] text-gray-400 dark:text-gray-500">
                                {line}
                            </p>
                        )}
                    </>
                )}
            </>
        </StatShell>
    );
}
