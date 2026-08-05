import { Skeleton } from '@/components/ui/skeleton';
import RefreshButton from './refresh-button';
import { useInventoryStat } from './use-inventory-stat';

interface HighUnfulfilledItem {
    id: number;
    sku: string | null;
    product_name: string | null;
    /** True when the row is a parent rolling up child SKUs. */
    is_group: boolean;
    child_count: number;
    unfulfilled_count: number;
}

interface HighUnfulfilledData {
    items: HighUnfulfilledItem[];
    listed_unfulfilled: number;
    limit: number;
}

/** Locale-aware count; em dash for null/undefined. */
const num = (v: number | null | undefined) =>
    v == null ? '—' : Number(v).toLocaleString('en-PH');

const headClass =
    'px-3 py-2 text-left text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
const cellClass = 'px-3 py-2.5 align-middle';

/**
 * The items owing the most stock, worst first. Counts are per group — children
 * roll into their parent — so this reads the same way the items list does with
 * summarize on, rather than splitting a grouped SKU across several rows.
 */
export default function HighUnfulfilledTable({ slug }: { slug: string }) {
    const { data, loading, error, refetch } =
        useInventoryStat<HighUnfulfilledData>(slug, 'high-unfulfilled');

    const items = data?.items ?? [];
    const hasRows = !loading && !error && items.length > 0;

    return (
        <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <div className="flex flex-col gap-3 p-[18px] sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        High Unfulfilled Items
                    </h3>
                    <p className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                        Top {data?.limit ?? 20} by units still owed, counted per
                        group.
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-3">
                    {hasRows && (
                        <div className="text-right">
                            <div className="text-sm font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                                {num(data?.listed_unfulfilled)}
                            </div>
                            {/* "listed", not "total": this is the sum of the
                                rows shown, not the whole workspace — the KPI
                                tile above carries that figure. */}
                            <div className="text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                units listed
                            </div>
                        </div>
                    )}
                    <RefreshButton
                        onClick={refetch}
                        loading={loading}
                        error={error}
                        label="high unfulfilled items"
                    />
                </div>
            </div>

            {loading ? (
                <div className="px-[18px] pb-6">
                    <TableSkeleton />
                </div>
            ) : error ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="Couldn't load unfulfilled items." />
                </div>
            ) : items.length === 0 ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="Nothing unfulfilled — every item is covered." />
                </div>
            ) : (
                // Full-bleed to the panel edge, matching the open-PO table, and
                // capped in height so a full 20 rows scroll internally instead
                // of stretching the row this panel shares.
                <div className="max-h-[420px] overflow-auto border-t border-black/6 dark:border-white/6">
                    <table className="w-full border-collapse">
                        <thead className="sticky top-0 z-10 bg-zinc-50 dark:bg-zinc-900">
                            <tr className="border-b border-black/6 dark:border-white/6">
                                <th className={headClass}>Item</th>
                                <th className={`${headClass} text-right!`}>
                                    Unfulfilled
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr
                                    key={item.id}
                                    className="border-b border-black/5 transition-colors last:border-0 hover:bg-zinc-50 dark:border-white/5 dark:hover:bg-zinc-800/50"
                                >
                                    <td className={`${cellClass} text-xs`}>
                                        <div className="flex items-center gap-1.5">
                                            <span className="font-medium text-gray-900 dark:text-gray-100">
                                                {item.sku ?? '—'}
                                            </span>
                                            {/* Grouped rows say so, otherwise a
                                                parent's total looks inflated
                                                against the flat items list. */}
                                            {item.is_group && (
                                                <span className="rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                    {item.child_count} SKUs
                                                </span>
                                            )}
                                        </div>
                                        {item.product_name && (
                                            <div className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                                                {item.product_name}
                                            </div>
                                        )}
                                    </td>
                                    <td
                                        className={`${cellClass} text-right text-xs font-semibold whitespace-nowrap text-gray-900 tabular-nums dark:text-gray-100`}
                                    >
                                        {num(item.unfulfilled_count)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

/** Row placeholders echoing the table's two-column rhythm. */
function TableSkeleton() {
    return (
        <div className="flex flex-col gap-2">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-3">
                    <Skeleton className="h-8 flex-[4]" />
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
