import { peso } from './formatters';
import type { PendingOrder } from './types';

interface Props {
    orders: PendingOrder[];
}

export default function PendingOrdersTable({ orders }: Props) {
    if (orders.length === 0) {
        return (
            <div className="flex h-32 items-center justify-center text-sm text-gray-400 dark:text-gray-500">
                No pending orders — great work!
            </div>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full">
                <thead>
                    <tr className="text-left text-[10px] font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        <th className="px-5 py-3">Order #</th>
                        <th className="px-5 py-3">Customer</th>
                        <th className="px-5 py-3">Rider</th>
                        <th className="px-5 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    {orders.map((o) => (
                        <tr key={o.id} className="border-t border-black/4 hover:bg-gray-50 dark:border-white/4 dark:hover:bg-zinc-800/40">
                            <td className="px-5 py-3">
                                <p className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                                    {o.order.order_number}
                                </p>
                                {o.order.tracking_code && (
                                    <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                        {o.order.tracking_code}
                                    </p>
                                )}
                            </td>
                            <td className="px-5 py-3 text-[12px] text-gray-600 dark:text-gray-400">
                                {o.order.shipping_address?.full_name ?? '—'}
                            </td>
                            <td className="px-5 py-3 text-[12px] text-gray-600 dark:text-gray-400">
                                {o.rider_name ?? (
                                    <span className="italic text-gray-400 dark:text-gray-500">Unassigned</span>
                                )}
                            </td>
                            <td className="px-5 py-3 text-right font-mono text-[12px] font-semibold text-gray-800 tabular-nums dark:text-gray-200">
                                {peso(o.order.final_amount)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
