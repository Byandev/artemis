import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { PURCHASED_ORDER_STATUSES } from '@/constants/purchased-order-statuses';
import axios from 'axios';
import { format, parseISO } from 'date-fns';
import { useEffect, useState } from 'react';

export interface PoDialogTarget {
    id: number;
    label: string;
    status: number;
    status_label: string;
}

type FulfillmentStatus = 'waiting' | 'partial' | 'delivered';

interface PoLine {
    id: number;
    sku: string | null;
    product_name: string | null;
    ordered_qty: number;
    delivered_qty: number;
    waiting_qty: number;
    fulfillment_status: FulfillmentStatus;
}

/** One stage transition in the ERP's audit trail for the order. */
interface PoStatusLog {
    id: number;
    /** The ERP's own label ("Approve", "To Pay", "Paid", …), not our status map. */
    status: string;
    by: string | null;
    /** "Y-m-d H:i:s", or null when the ERP logged no timestamp. */
    logged_at: string | null;
}

interface PoLinesData {
    order: {
        supplier: string | null;
        issue_date: string | null;
        paid_date: string | null;
    };
    lines: PoLine[];
    status_logs: PoStatusLog[];
    total_ordered: number;
    total_delivered: number;
    total_waiting: number;
}

/** Mirrors the badges on the purchased-orders list so the two read alike. */
const BADGE: Record<FulfillmentStatus, { label: string; color: string }> = {
    waiting: {
        label: 'Waiting',
        color: 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-400',
    },
    partial: {
        label: 'Partial',
        color: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    },
    delivered: {
        label: 'Delivered',
        color: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400',
    },
};

const num = (v: number | null | undefined) =>
    v == null ? '—' : Number(v).toLocaleString('en-PH');

/**
 * Short date. parseISO rather than `new Date` so a date-only string lands on
 * local midnight instead of being read as UTC and slipping a day.
 */
const shortDate = (iso: string | null | undefined) =>
    iso ? format(parseISO(iso), 'd MMM yyyy') : '—';

/** Trail entries carry a time of day, and the order of same-day moves matters. */
const stamp = (iso: string | null) =>
    iso ? format(parseISO(iso.replace(' ', 'T')), 'd MMM yyyy, HH:mm') : '—';

const headClass =
    'px-3 py-2 text-left text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500';
const cellClass = 'px-3 py-2 text-[12px] whitespace-nowrap';

/**
 * Drill-down for one purchase order: every line it carries, delivered and
 * outstanding alike. The dashboard table lists only what is still owed, so this
 * is where the full picture of an order lives.
 */
export default function PoLinesDialog({
    slug,
    order,
    open,
    onClose,
}: {
    slug: string;
    order: PoDialogTarget | null;
    open: boolean;
    onClose: () => void;
}) {
    const [data, setData] = useState<PoLinesData | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);

    // Reload on open; an aborted request can't clobber a newer one.
    useEffect(() => {
        if (!open || !order) return;

        const controller = new AbortController();
        setLoading(true);
        setError(false);
        setData(null);

        axios
            .get<PoLinesData>(
                `/api/workspaces/${slug}/inventory/dashboard/purchase-orders/${order.id}/lines`,
                { signal: controller.signal },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (axios.isCancel(err)) return;
                setError(true);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [open, order, slug]);

    const lines = data?.lines ?? [];

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="w-[95vw] border-none shadow-2xl sm:max-w-[900px] dark:bg-zinc-900">
                <DialogHeader>
                    <DialogTitle className="flex flex-wrap items-center gap-2 text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        {order?.label ?? 'Purchase Order'}
                        {order && (
                            <span
                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium whitespace-nowrap ${
                                    PURCHASED_ORDER_STATUSES[order.status]
                                        ?.color ??
                                    'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400'
                                }`}
                            >
                                {PURCHASED_ORDER_STATUSES[order.status]
                                    ?.label ?? order.status_label}
                            </span>
                        )}
                    </DialogTitle>
                    <DialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        Every item on this order, delivered and still waiting.
                    </DialogDescription>
                </DialogHeader>

                {/* Supplier and the two dates that bracket the order, straight
                    under the title — the questions asked before the line items. */}
                {data && (
                    <div className="flex flex-wrap gap-x-6 gap-y-2 rounded-[10px] bg-stone-50 px-3 py-2.5 dark:bg-zinc-800/60">
                        <Fact label="Supplier" value={data.order.supplier} />
                        <Fact
                            label="Issued"
                            value={shortDate(data.order.issue_date)}
                        />
                        <Fact
                            label="Paid"
                            value={shortDate(data.order.paid_date)}
                        />
                    </div>
                )}

                {loading ? (
                    <p className="py-8 text-center text-[12px] text-gray-400 dark:text-gray-500">
                        Loading…
                    </p>
                ) : error ? (
                    <p className="py-8 text-center text-[12px] text-red-500">
                        Could not load this purchase order.
                    </p>
                ) : lines.length === 0 ? (
                    <p className="py-8 text-center text-[12px] text-gray-400 dark:text-gray-500">
                        This purchase order has no items.
                    </p>
                ) : (
                    <div className="max-h-[55vh] overflow-auto rounded-[10px] border border-black/8 dark:border-white/8">
                        <table className="w-full border-collapse">
                            <thead className="sticky top-0 bg-stone-50 dark:bg-zinc-800">
                                <tr>
                                    <th className={headClass}>Item</th>
                                    <th className={headClass}>Status</th>
                                    <th className={`${headClass} text-right!`}>
                                        Ordered
                                    </th>
                                    <th className={`${headClass} text-right!`}>
                                        Delivered
                                    </th>
                                    <th className={`${headClass} text-right!`}>
                                        Waiting
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((line) => {
                                    const badge =
                                        BADGE[line.fulfillment_status];

                                    return (
                                        <tr
                                            key={line.id}
                                            className="border-t border-black/5 transition-colors hover:bg-stone-50 dark:border-white/5 dark:hover:bg-zinc-800/60"
                                        >
                                            <td className={cellClass}>
                                                <div className="font-medium text-gray-800 dark:text-gray-100">
                                                    {line.sku ?? '—'}
                                                </div>
                                                {line.product_name && (
                                                    <div className="text-[11px] text-gray-400 dark:text-gray-500">
                                                        {line.product_name}
                                                    </div>
                                                )}
                                            </td>
                                            <td className={cellClass}>
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium whitespace-nowrap ${badge.color}`}
                                                >
                                                    {badge.label}
                                                </span>
                                            </td>
                                            <td
                                                className={`${cellClass} text-right text-gray-600 tabular-nums dark:text-gray-300`}
                                            >
                                                {num(line.ordered_qty)}
                                            </td>
                                            <td
                                                className={`${cellClass} text-right text-gray-600 tabular-nums dark:text-gray-300`}
                                            >
                                                {num(line.delivered_qty)}
                                            </td>
                                            <td
                                                className={`${cellClass} text-right font-medium tabular-nums ${
                                                    line.waiting_qty > 0
                                                        ? 'text-amber-600 dark:text-amber-500'
                                                        : 'text-gray-400 dark:text-gray-600'
                                                }`}
                                            >
                                                {num(line.waiting_qty)}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                            <tfoot className="sticky bottom-0 bg-stone-50 dark:bg-zinc-800">
                                <tr className="border-t border-black/8 dark:border-white/8">
                                    <td
                                        className={`${cellClass} text-right font-medium text-gray-500 dark:text-gray-400`}
                                        colSpan={2}
                                    >
                                        Total
                                    </td>
                                    <td
                                        className={`${cellClass} text-right font-medium text-gray-600 tabular-nums dark:text-gray-300`}
                                    >
                                        {num(data?.total_ordered)}
                                    </td>
                                    <td
                                        className={`${cellClass} text-right font-medium text-gray-600 tabular-nums dark:text-gray-300`}
                                    >
                                        {num(data?.total_delivered)}
                                    </td>
                                    <td
                                        className={`${cellClass} text-right font-medium text-amber-600 tabular-nums dark:text-amber-500`}
                                    >
                                        {num(data?.total_waiting)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}

                {/* The ERP's trail, oldest first. Only rendered when the sync
                    has actually carried one — orders synced before status logs
                    existed simply have none, which is not an error. */}
                {!loading && !error && (data?.status_logs.length ?? 0) > 0 && (
                    <div>
                        <h4 className="mb-1.5 text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Status history
                        </h4>
                        <ol className="max-h-[22vh] space-y-1 overflow-auto">
                            {data?.status_logs.map((log) => (
                                <li
                                    key={log.id}
                                    className="flex flex-wrap items-baseline gap-x-2 text-[12px]"
                                >
                                    <span className="font-medium text-gray-800 dark:text-gray-200">
                                        {log.status}
                                    </span>
                                    <span className="text-gray-400 tabular-nums dark:text-gray-500">
                                        {stamp(log.logged_at)}
                                    </span>
                                    {log.by && (
                                        <span className="text-gray-400 dark:text-gray-500">
                                            · {log.by}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </div>
                )}

                <DialogFooter className="mt-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="h-9 rounded-lg border border-black/8 bg-white px-4 text-[12px] font-medium text-gray-600 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        Close
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** Label over value, for the header's supplier/date strip. */
function Fact({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <div className="text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {label}
            </div>
            <div className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                {value ?? '—'}
            </div>
        </div>
    );
}
