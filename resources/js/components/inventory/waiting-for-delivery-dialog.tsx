import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Workspace } from '@/types/models/Workspace';
import { format, parseISO } from 'date-fns';
import { useEffect, useState } from 'react';

export interface WaitingForDeliveryTarget {
    id: number;
    sku: string;
    is_group?: boolean | number;
}

interface PendingOrder {
    id: number;
    sku: string | null;
    control_no: string | null;
    cust_po_no: string | null;
    issue_date: string | null;
    expected_delivery_date: string | null;
    status: number;
    status_label: string;
    delivery_timeliness: 'ontime' | 'delayed' | null;
    ordered_qty: number;
    delivered_qty: number;
    balance: number;
}

interface Props {
    workspace: Workspace;
    item: WaitingForDeliveryTarget | null;
    open: boolean;
    onClose: () => void;
}

const num = (v: number) => Number(v).toLocaleString('en-PH');

const shortDate = (d: string | null) => {
    if (!d) return '—';
    try {
        return format(parseISO(d), 'd MMM yyyy');
    } catch {
        return d;
    }
};

const headClass =
    'px-3 py-2 text-left font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500';
const cellClass = 'px-3 py-2 font-mono text-[12px] whitespace-nowrap';

export function WaitingForDeliveryDialog({
    workspace,
    item,
    open,
    onClose,
}: Props) {
    const [orders, setOrders] = useState<PendingOrder[]>([]);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    // Reload on open; a stale response can't clobber a newer one.
    useEffect(() => {
        if (!open || !item) return;

        let cancelled = false;
        setLoading(true);
        setFailed(false);
        fetch(
            `/workspaces/${workspace.slug}/inventory/items/${item.id}/pending-purchase-orders`,
            { headers: { Accept: 'application/json' } },
        )
            .then((r) => (r.ok ? r.json() : Promise.reject(r)))
            .then((json) => {
                if (cancelled) return;
                setOrders(json.orders ?? []);
                setTotal(json.total_balance ?? 0);
            })
            .catch(() => {
                if (cancelled) return;
                setOrders([]);
                setTotal(0);
                setFailed(true);
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [open, item?.id, workspace.slug]);

    const isGroup = !!item?.is_group;

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="w-[95vw] border-none shadow-2xl sm:max-w-[1100px] dark:bg-zinc-900">
                <DialogHeader>
                    <DialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        Waiting for Delivery
                    </DialogTitle>
                    <DialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        {item ? (
                            <>
                                Pending purchase orders for{' '}
                                <span className="font-mono text-gray-700 dark:text-gray-300">
                                    {item.sku}
                                </span>
                                {isGroup && ' and its variants'}.
                            </>
                        ) : (
                            'Pending purchase orders for this item.'
                        )}
                    </DialogDescription>
                </DialogHeader>

                {loading ? (
                    <p className="py-8 text-center font-mono text-[12px] text-gray-400 dark:text-gray-500">
                        Loading…
                    </p>
                ) : failed ? (
                    <p className="py-8 text-center font-mono text-[12px] text-red-500">
                        Could not load pending purchase orders.
                    </p>
                ) : orders.length === 0 ? (
                    <p className="py-8 text-center font-mono text-[12px] text-gray-400 dark:text-gray-500">
                        No pending purchase orders for this item.
                    </p>
                ) : (
                    <div className="max-h-[55vh] overflow-auto rounded-[10px] border border-black/8 dark:border-white/8">
                        <table className="w-full border-collapse">
                            <thead className="sticky top-0 bg-stone-50 dark:bg-zinc-800">
                                <tr>
                                    {isGroup && (
                                        <th className={headClass}>SKU</th>
                                    )}
                                    <th className={headClass}>PO</th>
                                    <th className={headClass}>Issued</th>
                                    <th className={headClass}>Expected</th>
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
                                {orders.map((order, i) => (
                                    <tr
                                        key={`${order.id}-${order.sku ?? i}`}
                                        className="border-t border-black/5 dark:border-white/5"
                                    >
                                        {isGroup && (
                                            <td
                                                className={`${cellClass} text-gray-600 dark:text-gray-300`}
                                            >
                                                {order.sku ?? '—'}
                                            </td>
                                        )}
                                        <td
                                            className={`${cellClass} font-medium text-gray-800 dark:text-gray-100`}
                                        >
                                            {order.control_no ??
                                                order.cust_po_no ??
                                                `#${order.id}`}
                                        </td>
                                        <td
                                            className={`${cellClass} text-gray-500 dark:text-gray-400`}
                                        >
                                            {shortDate(order.issue_date)}
                                        </td>
                                        <td
                                            className={`${cellClass} ${
                                                order.delivery_timeliness ===
                                                'delayed'
                                                    ? 'text-red-500 dark:text-red-400'
                                                    : 'text-gray-500 dark:text-gray-400'
                                            }`}
                                        >
                                            {shortDate(
                                                order.expected_delivery_date,
                                            )}
                                        </td>
                                        <td
                                            className={`${cellClass} text-gray-500 dark:text-gray-400`}
                                        >
                                            {order.status_label}
                                        </td>
                                        <td
                                            className={`${cellClass} text-right text-gray-600 dark:text-gray-300`}
                                        >
                                            {num(order.ordered_qty)}
                                        </td>
                                        <td
                                            className={`${cellClass} text-right text-gray-600 dark:text-gray-300`}
                                        >
                                            {num(order.delivered_qty)}
                                        </td>
                                        <td
                                            className={`${cellClass} text-right font-medium text-blue-500 dark:text-blue-400`}
                                        >
                                            {num(order.balance)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="sticky bottom-0 bg-stone-50 dark:bg-zinc-800">
                                <tr className="border-t border-black/8 dark:border-white/8">
                                    <td
                                        className={`${cellClass} text-right font-medium text-gray-500 dark:text-gray-400`}
                                        colSpan={isGroup ? 7 : 6}
                                    >
                                        Total waiting
                                    </td>
                                    <td
                                        className={`${cellClass} text-right font-medium text-blue-500 dark:text-blue-400`}
                                    >
                                        {num(total)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}

                <DialogFooter className="mt-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="h-9 rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        Close
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
