import { Skeleton } from '@/components/ui/skeleton';
import { PURCHASED_ORDER_STATUSES } from '@/constants/purchased-order-statuses';
import { format, parseISO } from 'date-fns';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import DeliveryProgressRing from './delivery-progress-ring';
import PoLinesDialog, { type PoDialogTarget } from './po-lines-dialog';
import RefreshButton from './refresh-button';
import { useInventoryStat } from './use-inventory-stat';

interface OpenPoLine {
    id: number;
    purchased_order_id: number;
    control_no: string | null;
    cust_po_no: string | null;
    /** Date-only string (Y-m-d), or null on an order with no issue date. */
    issue_date: string | null;
    /** Date-only string (Y-m-d), or null when no delivery date was promised. */
    expected_delivery_date: string | null;
    status: number;
    status_label: string;
    sku: string | null;
    product_name: string | null;
    ordered_qty: number;
    delivered_qty: number;
    waiting_qty: number;
}

interface OpenPoData {
    lines: OpenPoLine[];
    total_waiting: number;
}

/** Locale-aware count; em dash for null/undefined. */
const num = (v: number | null | undefined) =>
    v == null ? '—' : Number(v).toLocaleString('en-PH');

/** Control no, then the customer's PO no, then the bare id as a last resort. */
const poLabel = (line: OpenPoLine) =>
    line.control_no ?? line.cust_po_no ?? `#${line.purchased_order_id}`;

/**
 * Short, unambiguous date. parseISO rather than `new Date` so a date-only
 * string lands on local midnight instead of being read as UTC and slipping a
 * day either side of the meridian.
 */
const issuedOn = (iso: string | null) =>
    iso ? format(parseISO(iso), 'd MMM yyyy') : '—';

const headClass =
    'px-3 py-2 text-left text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
const cellClass = 'px-3 py-2.5 align-middle';
const numClass = `${cellClass} text-right text-xs tabular-nums whitespace-nowrap`;

/**
 * Sits under the movement chart: the open purchase-order lines the chart's
 * "in" arm is still waiting on, so a flat week of arrivals can be read against
 * what is actually outstanding.
 */
export default function OpenPosTable({ slug }: { slug: string }) {
    const { data, loading, error, refetch } = useInventoryStat<OpenPoData>(
        slug,
        'open-purchase-orders',
    );
    // The drill-down target doubles as the dialog's open flag.
    const [target, setTarget] = useState<PoDialogTarget | null>(null);

    const lines = data?.lines ?? [];
    const hasRows = !loading && !error && lines.length > 0;

    return (
        <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <div className="flex flex-col gap-3 p-[18px] sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Open Purchase Orders
                    </h3>
                    <p className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                        Lines still owing stock — ordered, delivered so far, and
                        what is still waiting.
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-3">
                    {hasRows && (
                        <div className="text-right">
                            <div className="text-sm font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                                {num(data?.total_waiting)}
                            </div>
                            <div className="text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                units waiting
                            </div>
                        </div>
                    )}
                    <RefreshButton
                        onClick={refetch}
                        loading={loading}
                        error={error}
                        label="open purchase orders"
                    />
                </div>
            </div>

            {loading ? (
                <div className="px-[18px] pb-6">
                    <TableSkeleton />
                </div>
            ) : error ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="Couldn't load open purchase orders." />
                </div>
            ) : lines.length === 0 ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="No open purchase orders — everything ordered has landed." />
                </div>
            ) : (
                // Full-bleed to the panel edge: a bordered table inside a
                // bordered card reads as two nested boxes. Every open line is
                // listed rather than a top-N, so this scrolls internally
                // instead of pushing the page down.
                <div className="max-h-[420px] overflow-auto border-t border-black/6 dark:border-white/6">
                    <table className="w-full border-collapse">
                        <thead className="sticky top-0 z-10 bg-zinc-50 dark:bg-zinc-900">
                            <tr className="border-b border-black/6 dark:border-white/6">
                                <th className={headClass}>PO</th>
                                <th className={headClass}>Issued</th>
                                <th className={headClass}>Expected</th>
                                <th className={headClass}>Status</th>
                                <th className={headClass}>Item</th>
                                <th className={`${headClass} text-right!`}>
                                    Total PO
                                </th>
                                <th className={`${headClass} text-right!`}>
                                    Delivered
                                </th>
                                <th className={`${headClass} text-right!`}>
                                    Waiting
                                </th>
                                <th className={headClass}>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            {lines.map((line) => (
                                <tr
                                    key={line.id}
                                    className="border-b border-black/5 transition-colors last:border-0 hover:bg-zinc-50 dark:border-white/5 dark:hover:bg-zinc-800/50"
                                >
                                    <td className={`${cellClass} text-xs`}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setTarget({
                                                    id: line.purchased_order_id,
                                                    label: poLabel(line),
                                                    status: line.status,
                                                    status_label:
                                                        line.status_label,
                                                })
                                            }
                                            className="group inline-flex items-center gap-0.5 font-medium whitespace-nowrap text-blue-600 underline-offset-2 hover:underline dark:text-blue-400"
                                        >
                                            {poLabel(line)}
                                            <ChevronRight className="h-3 w-3 opacity-0 transition-opacity group-hover:opacity-100" />
                                        </button>
                                    </td>
                                    <td
                                        className={`${cellClass} text-xs whitespace-nowrap text-gray-500 tabular-nums dark:text-gray-400`}
                                    >
                                        {issuedOn(line.issue_date)}
                                    </td>
                                    <td
                                        className={`${cellClass} text-xs whitespace-nowrap text-gray-500 tabular-nums dark:text-gray-400`}
                                    >
                                        {issuedOn(line.expected_delivery_date)}
                                    </td>
                                    <td className={cellClass}>
                                        <StatusBadge
                                            status={line.status}
                                            label={line.status_label}
                                        />
                                    </td>
                                    <td className={`${cellClass} text-xs`}>
                                        <div className="font-medium text-gray-900 dark:text-gray-100">
                                            {line.sku ?? '—'}
                                        </div>
                                        {line.product_name && (
                                            <div className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                                                {line.product_name}
                                            </div>
                                        )}
                                    </td>
                                    <td
                                        className={`${numClass} text-gray-500 dark:text-gray-400`}
                                    >
                                        {num(line.ordered_qty)}
                                    </td>
                                    <td
                                        className={`${numClass} text-gray-500 dark:text-gray-400`}
                                    >
                                        {num(line.delivered_qty)}
                                    </td>
                                    {/* The row's headline figure, so it carries
                                        the weight. Deliberately not a colour —
                                        every row here is waiting, so tinting
                                        them all would signal nothing. */}
                                    <td
                                        className={`${numClass} font-semibold text-gray-900 dark:text-gray-100`}
                                    >
                                        {num(line.waiting_qty)}
                                    </td>
                                    {/* Per line, not per order: the same order can
                                        carry several lines at different stages, and
                                        one ring across them would hide that. */}
                                    <td className={cellClass}>
                                        <DeliveryProgressRing
                                            ordered={line.ordered_qty}
                                            delivered={line.delivered_qty}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <PoLinesDialog
                slug={slug}
                order={target}
                open={target !== null}
                onClose={() => setTarget(null)}
            />
        </div>
    );
}

/** Workflow stage, styled from the shared status map the PO pages use. */
function StatusBadge({ status, label }: { status: number; label: string }) {
    const badge = PURCHASED_ORDER_STATUSES[status];

    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium whitespace-nowrap ${
                badge?.color ??
                'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400'
            }`}
        >
            {badge?.label ?? label}
        </span>
    );
}

/** Row placeholders echoing the table's column rhythm. */
function TableSkeleton() {
    return (
        <div className="flex flex-col gap-2">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-3">
                    <Skeleton className="h-8 flex-[2]" />
                    <Skeleton className="h-8 flex-[2]" />
                    <Skeleton className="h-8 flex-[2]" />
                    <Skeleton className="h-8 flex-[2]" />
                    <Skeleton className="h-8 flex-[3]" />
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
