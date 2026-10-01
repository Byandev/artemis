import { Skeleton } from '@/components/ui/skeleton';
import { RotateCcw } from 'lucide-react';
import { formatKpi, type KpiFormat } from './kpi-card';
import StatShell from './stat-shell';
import { useSalesMarketingStat } from './use-sales-marketing-stat';

/**
 * What a leaders endpoint answers with: who led, the figure they led on, the
 * workspace total behind the share, and whatever extra sums the caption needs.
 */
export interface LeaderData {
    advertiser: { id: number; name: string | null } | null;
    value: number;
    /**
     * The workspace figure the leader's share is of. Absent when the leader is
     * a ratio — a ROAS is not a slice of anything.
     */
    total?: number;
    [key: string]:
        | number
        | { id: number; name: string | null }
        | null
        | undefined;
}

/** First letters of the first two words — "Knathan Rick Villaspina" → "KR". */
function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase() ?? '')
        .join('');
}

/**
 * Who topped a figure over the window, in the same card as the KPIs above —
 * the share, the person, the figure, and what it is made of.
 *
 * The endpoint answers with sums only; the share is worked out here, and the
 * caption works out whatever else it states, the way the KPI cards work out
 * their own trend.
 */
export default function LeaderCard({
    slug,
    label,
    endpoint,
    dateRange,
    as = 'currencyExact',
    aside,
    caption,
    emptyHint,
}: {
    slug: string;
    label: string;
    /** The leader's own endpoint under `.../dashboard/`. */
    endpoint: string;
    /** `[start, end]` as YYYY-MM-DD. */
    dateRange: string[];
    /** How the figure reads. Money by default; a ratio for ROAS. */
    as?: KpiFormat;
    /**
     * Top-right text, replacing the share. Pass one where a share makes no
     * sense — a ratio leads on efficiency, not on a slice of a total.
     */
    aside?: string;
    /** The line under the figure — what the leader's number is made of. */
    caption?: (data: LeaderData) => string | null;
    /** Why there is no leader, when the window holds nothing to lead on. */
    emptyHint?: string;
}) {
    const { data, loading, error, refetch } = useSalesMarketingStat<LeaderData>(
        slug,
        endpoint,
        { start: dateRange[0], end: dateRange[1] },
    );

    const advertiser = data?.advertiser ?? null;
    const name = advertiser?.name?.trim() || 'Unnamed advertiser';

    const share =
        data?.total && data.total > 0 ? (data.value / data.total) * 100 : null;

    return (
        <StatShell
            label={label}
            aside={
                loading ? (
                    <Skeleton className="h-4 w-20" />
                ) : (
                    advertiser &&
                    (aside || share !== null) && (
                        <span className="shrink-0 font-mono text-[11px] font-semibold text-gray-500 tabular-nums dark:text-gray-400">
                            {aside ?? `${share!.toFixed(0)}% of total`}
                        </span>
                    )
                )
            }
        >
            {loading ? (
                <>
                    <div className="flex items-center gap-2.5">
                        <Skeleton className="h-8 w-8 rounded-full" />
                        <Skeleton className="h-3.5 w-36" />
                    </div>
                    <Skeleton className="mt-3 h-[30px] w-32" />
                    <Skeleton className="mt-2.5 h-3 w-44" />
                </>
            ) : error ? (
                <>
                    <h4 className="font-mono text-[26px] font-semibold tracking-tight text-gray-300 tabular-nums dark:text-gray-600">
                        —
                    </h4>
                    <button
                        type="button"
                        onClick={refetch}
                        className="mt-1.5 flex items-center gap-1.5 text-[11px] text-red-500 hover:underline dark:text-red-400"
                    >
                        <RotateCcw className="h-3 w-3" />
                        Failed — retry
                    </button>
                </>
            ) : !advertiser ? (
                // Nothing was spent in this window, so nobody led it. Saying so
                // beats naming a leader of zero.
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
                    <div className="flex items-center gap-2.5">
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-stone-100 font-mono text-[11px] font-semibold text-gray-600 dark:bg-zinc-800 dark:text-gray-300">
                            {initials(name)}
                        </span>
                        <p
                            className="truncate text-[12px] font-medium text-gray-800 dark:text-gray-100"
                            title={name}
                        >
                            {name}
                        </p>
                    </div>

                    <h4 className="mt-3 font-mono text-[26px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                        {formatKpi(data!.value, as)}
                    </h4>

                    {caption?.(data!) && (
                        <p className="mt-1.5 text-[11px] text-gray-400 dark:text-gray-500">
                            {caption(data!)}
                        </p>
                    )}
                </>
            )}
        </StatShell>
    );
}
