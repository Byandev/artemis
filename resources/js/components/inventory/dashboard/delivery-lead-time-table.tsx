import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { useState } from 'react';
import RefreshButton from './refresh-button';
import { useInventoryStat } from './use-inventory-stat';

/**
 * Fill levels, as a percentage of the ordered quantity. Must match
 * LEAD_TIME_THRESHOLDS in InventoryDashboardStatsController.
 */
const THRESHOLDS = [25, 50, 75, 100] as const;

/** Average days to each fill level, keyed by threshold. Null where unreached. */
type ByThreshold<T> = Record<string, T>;

interface LeadTimeRow {
    id: number;
    sku: string | null;
    product_name: string | null;
    /** True when the row is a parent rolling up child SKUs. */
    is_group: boolean;
    child_count: number;
    /** PO lines in the window that feed this row. */
    lines: number;
    averages: ByThreshold<number | null>;
    samples: ByThreshold<number>;
}

interface LeadTimeData {
    items: LeadTimeRow[];
    total_groups: number;
    limit: number;
    months: number;
    since: string;
    group_by_parent: boolean;
    overall: {
        lines: number;
        averages: ByThreshold<number | null>;
        samples: ByThreshold<number>;
    };
}

/** Days to one decimal; em dash when the level has not been reached. */
const days = (v: number | null | undefined) =>
    v == null ? '—' : `${Number(v).toLocaleString('en-PH')}d`;

const num = (v: number | null | undefined) =>
    v == null ? '—' : Number(v).toLocaleString('en-PH');

const headClass =
    'px-3 py-2 text-left text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
const cellClass = 'px-3 py-2.5 align-middle';

/**
 * How long purchase orders actually take to land. Each column is the average
 * days from the order's issue date to the delivery that took the line past that
 * share of the ordered quantity, so 100% is the full lead time and the earlier
 * columns show how much arrives sooner.
 *
 * Sample sizes shrink to the right — a partially delivered line counts toward
 * the levels it has crossed and not the ones it hasn't — so every cell carries
 * the count it was averaged from.
 */
export default function DeliveryLeadTimeTable({ slug }: { slug: string }) {
    const [groupByParent, setGroupByParent] = useState(true);

    const { data, loading, error, refetch } = useInventoryStat<LeadTimeData>(
        slug,
        'delivery-lead-time',
        { group_by_parent: groupByParent ? 1 : 0 },
    );

    const items = data?.items ?? [];
    const truncated = (data?.total_groups ?? 0) > items.length;

    return (
        <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <div className="flex flex-col gap-3 p-[18px] sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Delivery Lead Time
                    </h3>
                    <p className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                        Average days from PO issue date to 25 / 50 / 75 / 100%
                        of the ordered quantity delivered, for orders issued in
                        the last {data?.months ?? 6} months.
                    </p>
                </div>
                <div className="flex shrink-0 flex-wrap items-center gap-3">
                    <label className="flex h-8 cursor-pointer items-center gap-2 rounded-[10px] border border-black/6 bg-stone-100 px-3 dark:border-white/6 dark:bg-zinc-800">
                        <Switch
                            checked={groupByParent}
                            onCheckedChange={setGroupByParent}
                        />
                        <span className="text-[11px] font-medium whitespace-nowrap text-gray-600 dark:text-gray-300">
                            Group by parent
                        </span>
                    </label>
                    <RefreshButton
                        onClick={refetch}
                        loading={loading}
                        error={error}
                        label="delivery lead time"
                    />
                </div>
            </div>

            {/* Workspace-wide averages, weighted by line rather than by row, so
                a single-order item doesn't count as much as a busy one. */}
            {!loading && !error && items.length > 0 && (
                <div className="grid grid-cols-2 gap-px border-t border-black/6 bg-black/6 sm:grid-cols-4 dark:border-white/6 dark:bg-white/6">
                    {THRESHOLDS.map((threshold) => (
                        <div
                            key={threshold}
                            className="bg-zinc-50 px-[18px] py-3 dark:bg-zinc-900"
                        >
                            <div className="text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                {threshold}% delivered
                            </div>
                            <div className="mt-1 font-mono text-[18px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                                {days(data?.overall.averages[threshold])}
                            </div>
                            <div className="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500">
                                {num(data?.overall.samples[threshold])} of{' '}
                                {num(data?.overall.lines)} PO lines
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {loading ? (
                <div className="px-[18px] pb-6">
                    <TableSkeleton />
                </div>
            ) : error ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="Couldn't load delivery lead times." />
                </div>
            ) : items.length === 0 ? (
                <div className="px-[18px] pb-6">
                    <EmptyState
                        message={`No purchase orders issued in the last ${data?.months ?? 6} months.`}
                    />
                </div>
            ) : (
                <div className="max-h-[520px] overflow-auto border-t border-black/6 dark:border-white/6">
                    <table className="w-full border-collapse">
                        <thead className="sticky top-0 z-10 bg-zinc-50 dark:bg-zinc-900">
                            <tr className="border-b border-black/6 dark:border-white/6">
                                <th className={headClass}>Item</th>
                                <th className={`${headClass} text-right!`}>
                                    PO lines
                                </th>
                                {THRESHOLDS.map((threshold) => (
                                    <th
                                        key={threshold}
                                        className={`${headClass} text-right!`}
                                    >
                                        {threshold}%
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-b border-black/5 transition-colors last:border-0 hover:bg-zinc-50 dark:border-white/5 dark:hover:bg-zinc-800/50"
                                >
                                    <td className={`${cellClass} text-xs`}>
                                        <div className="flex items-center gap-1.5">
                                            <span className="font-medium text-gray-900 dark:text-gray-100">
                                                {row.sku ?? '—'}
                                            </span>
                                            {/* Grouped rows say so — the figures
                                                average several SKUs' orders. */}
                                            {row.is_group && (
                                                <span className="rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                    {row.child_count} SKUs
                                                </span>
                                            )}
                                        </div>
                                        {row.product_name && (
                                            <div className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                                                {row.product_name}
                                            </div>
                                        )}
                                    </td>
                                    <td
                                        className={`${cellClass} text-right text-xs whitespace-nowrap text-gray-500 tabular-nums dark:text-gray-400`}
                                    >
                                        {num(row.lines)}
                                    </td>
                                    {THRESHOLDS.map((threshold) => {
                                        const samples =
                                            row.samples[threshold] ?? 0;

                                        return (
                                            <td
                                                key={threshold}
                                                className={`${cellClass} text-right whitespace-nowrap`}
                                                title={`${samples} of ${row.lines} PO lines reached ${threshold}%`}
                                            >
                                                <div className="text-xs font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                                                    {days(
                                                        row.averages[threshold],
                                                    )}
                                                </div>
                                                {/* The sample behind the average
                                                    — it thins out to the right as
                                                    fewer lines have got that far. */}
                                                <div className="mt-0.5 text-[10px] text-gray-400 tabular-nums dark:text-gray-500">
                                                    {samples}/{row.lines}
                                                </div>
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Never present a capped list as the whole picture. */}
            {truncated && (
                <div className="border-t border-black/6 px-[18px] py-2.5 text-[11px] text-gray-400 dark:border-white/6 dark:text-gray-500">
                    Showing the {num(items.length)} slowest of{' '}
                    {num(data?.total_groups)}{' '}
                    {groupByParent ? 'groups' : 'items'}.
                </div>
            )}
        </div>
    );
}

/** Row placeholders echoing the table's six-column rhythm. */
function TableSkeleton() {
    return (
        <div className="flex flex-col gap-2">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-3">
                    <Skeleton className="h-8 flex-[4]" />
                    <Skeleton className="h-8 flex-1" />
                    <Skeleton className="h-8 flex-1" />
                    <Skeleton className="h-8 flex-1" />
                    <Skeleton className="h-8 flex-1" />
                    <Skeleton className="h-8 flex-1" />
                </div>
            ))}
        </div>
    );
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex h-[160px] items-center justify-center rounded-[12px] border border-dashed border-zinc-200 dark:border-zinc-800">
            <p className="text-sm text-zinc-500 dark:text-zinc-400">
                {message}
            </p>
        </div>
    );
}
