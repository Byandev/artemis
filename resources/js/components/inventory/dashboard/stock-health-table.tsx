import { cn } from '@/lib/utils';
import { EmptyState } from './dashboard-card';
import { type StockHealthRow } from './types';
import { num } from './utils';

const HEAD =
    'px-3 py-2 text-left font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
const CELL = 'px-3 py-2 font-mono text-[11px] whitespace-nowrap';

export default function StockHealthTable({ rows }: { rows: StockHealthRow[] }) {
    if (rows.length === 0) {
        return <EmptyState message="No active items to reorder." />;
    }

    return (
        <div className="-m-4 custom-scrollbar overflow-x-auto">
            <table className="w-full border-collapse">
                <thead className="border-b border-black/6 dark:border-white/6">
                    <tr>
                        <th className={HEAD}>SKU / Product</th>
                        <th className={cn(HEAD, 'text-right')}>On Hand</th>
                        <th className={cn(HEAD, 'text-right')}>3-Day Avg</th>
                        <th className={cn(HEAD, 'text-right')}>Days Left</th>
                        <th className={cn(HEAD, 'text-right')}>Lead</th>
                        <th className={cn(HEAD, 'text-right')}>Incoming</th>
                        <th className={cn(HEAD, 'text-right')}>PO Needed</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr
                            key={r.id}
                            className={cn(
                                'border-b border-black/4 last:border-0 dark:border-white/4',
                                r.at_risk && 'bg-red-50/50 dark:bg-red-500/5',
                            )}
                        >
                            <td className={CELL}>
                                <div className="flex flex-col gap-0.5">
                                    <span className="font-medium text-gray-700 dark:text-gray-200">
                                        {r.sku}
                                    </span>
                                    {r.product_name && (
                                        <span className="text-[10px] text-gray-400 dark:text-gray-500">
                                            {r.product_name}
                                        </span>
                                    )}
                                </div>
                            </td>
                            <td
                                className={cn(
                                    CELL,
                                    'text-right text-violet-600 dark:text-violet-400',
                                )}
                            >
                                {num(r.current_stocks)}
                            </td>
                            <td
                                className={cn(
                                    CELL,
                                    'text-right text-gray-500 dark:text-gray-400',
                                )}
                            >
                                {num(r.three_days_average, 1)}
                            </td>
                            <td
                                className={cn(
                                    CELL,
                                    'text-right font-medium',
                                    r.at_risk
                                        ? 'text-red-500 dark:text-red-400'
                                        : 'text-emerald-600 dark:text-emerald-400',
                                )}
                            >
                                {r.three_days_average > 0
                                    ? num(r.days_it_can_last, 1)
                                    : '∞'}
                            </td>
                            <td
                                className={cn(
                                    CELL,
                                    'text-right text-gray-500 dark:text-gray-400',
                                )}
                            >
                                {r.lead_time}d
                            </td>
                            <td
                                className={cn(
                                    CELL,
                                    'text-right text-blue-500 dark:text-blue-400',
                                )}
                            >
                                {num(r.waiting_for_delivery_stocks)}
                            </td>
                            <td
                                className={cn(
                                    CELL,
                                    'text-right font-semibold',
                                    r.po_needed > 0
                                        ? 'text-amber-600 dark:text-amber-400'
                                        : 'text-gray-400 dark:text-gray-600',
                                )}
                            >
                                {num(r.po_needed)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
