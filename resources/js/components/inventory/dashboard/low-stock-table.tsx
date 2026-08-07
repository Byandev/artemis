import { Skeleton } from '@/components/ui/skeleton';
import { differenceInCalendarDays, format, parseISO } from 'date-fns';
import RefreshButton from './refresh-button';
import { useInventoryStat } from './use-inventory-stat';

interface LowStockItem {
    id: number;
    sku: string | null;
    product_name: string | null;
    /** True when the row is a parent rolling up child SKUs. */
    is_group: boolean;
    child_count: number;
    po_needed: number;
    current_stocks: number | null;
    /**
     * Date-only (Y-m-d) of the most recent purchase order raised for anything
     * in this group, cancelled orders excluded. Null when the group has never
     * been ordered.
     */
    last_issued_at: string | null;
}

interface LowStockData {
    items: LowStockItem[];
    listed_po_needed: number;
    limit: number;
}

/** Locale-aware count; em dash for null/undefined. */
const num = (v: number | null | undefined) =>
    v == null ? '—' : Number(v).toLocaleString('en-PH');

const headClass =
    'px-3 py-2 text-left text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
const cellClass = 'px-3 py-2.5 align-middle';

/**
 * Sits beside the high-unfulfilled table: the items most in need of a purchase
 * order. PO Needed is the group's figure, recomputed from its parts rather than
 * summed, so it matches the items list's column of the same name.
 */
export default function LowStockTable({ slug }: { slug: string }) {
    const { data, loading, error, refetch } = useInventoryStat<LowStockData>(
        slug,
        'low-stock',
    );

    const items = data?.items ?? [];
    const hasRows = !loading && !error && items.length > 0;

    return (
        <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <div className="flex flex-col gap-3 p-[18px] sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Low Stock Items
                    </h3>
                    <p className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                        Top {data?.limit ?? 20} by units to reorder, counted per
                        group.
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-3">
                    {hasRows && (
                        <div className="text-right">
                            <div className="text-sm font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                                {num(data?.listed_po_needed)}
                            </div>
                            <div className="text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                units to order
                            </div>
                        </div>
                    )}
                    <RefreshButton
                        onClick={refetch}
                        loading={loading}
                        error={error}
                        label="low stock items"
                    />
                </div>
            </div>

            {loading ? (
                <div className="px-[18px] pb-6">
                    <TableSkeleton />
                </div>
            ) : error ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="Couldn't load low stock items." />
                </div>
            ) : items.length === 0 ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="Nothing to reorder — every item is covered." />
                </div>
            ) : (
                <div className="max-h-[420px] overflow-auto border-t border-black/6 dark:border-white/6">
                    <table className="w-full border-collapse">
                        <thead className="sticky top-0 z-10 bg-zinc-50 dark:bg-zinc-900">
                            <tr className="border-b border-black/6 dark:border-white/6">
                                <th className={headClass}>Item</th>
                                <th className={`${headClass} text-right!`}>
                                    Last PO Issued
                                </th>
                                <th className={`${headClass} text-right!`}>
                                    PO Needed
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
                                                parent's figure looks inflated
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
                                    {/* An item that needs stock and was last
                                        ordered months ago is a different problem
                                        from one ordered yesterday — the reorder
                                        figure alone cannot tell them apart. */}
                                    <td
                                        className={`${cellClass} text-right text-xs whitespace-nowrap tabular-nums`}
                                    >
                                        <LastOrdered
                                            iso={item.last_issued_at}
                                        />
                                    </td>
                                    <td
                                        className={`${cellClass} text-right text-xs font-semibold whitespace-nowrap text-gray-900 tabular-nums dark:text-gray-100`}
                                    >
                                        {num(item.po_needed)}
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

/**
 * When this group was last ordered, with how long ago underneath.
 *
 * Never-ordered is called out in red: on a row that already says stock is
 * needed, "no purchase order has ever been raised" is the loudest fact on the
 * line. Anything past a fortnight is amber — long enough that the reorder is
 * unlikely to be already in flight.
 */
function LastOrdered({ iso }: { iso: string | null }) {
    if (!iso) {
        return (
            <span className="font-medium text-red-600 dark:text-red-400">
                Never
            </span>
        );
    }

    // parseISO rather than `new Date` so a date-only string lands on local
    // midnight instead of being read as UTC and slipping a day.
    const when = parseISO(iso);
    const ago = differenceInCalendarDays(new Date(), when);

    return (
        <>
            <span
                className={
                    ago > 14
                        ? 'text-amber-600 dark:text-amber-500'
                        : 'text-gray-500 dark:text-gray-400'
                }
            >
                {format(when, 'd MMM yyyy')}
            </span>
            <span className="mt-0.5 block text-[10px] text-gray-400 dark:text-gray-500">
                {ago === 0 ? 'today' : `${ago}d ago`}
            </span>
        </>
    );
}

/** Row placeholders echoing the table's three-column rhythm. */
function TableSkeleton() {
    return (
        <div className="flex flex-col gap-2">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-3">
                    <Skeleton className="h-8 flex-[4]" />
                    <Skeleton className="h-8 flex-[2]" />
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
