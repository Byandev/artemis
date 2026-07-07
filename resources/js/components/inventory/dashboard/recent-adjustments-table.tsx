import { cn } from '@/lib/utils';
import { EmptyState } from './dashboard-card';
import { type RecentAdjustment } from './types';
import { num, shortDate } from './utils';

const HEAD =
    'px-3 py-2 text-left font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
const CELL = 'px-3 py-2 font-mono text-[11px] whitespace-nowrap';

export default function RecentAdjustmentsTable({
    rows,
}: {
    rows: RecentAdjustment[];
}) {
    if (rows.length === 0) {
        return <EmptyState message="No physical counts recorded yet." />;
    }

    return (
        <div className="-m-4 custom-scrollbar overflow-x-auto">
            <table className="w-full border-collapse">
                <thead className="border-b border-black/6 dark:border-white/6">
                    <tr>
                        <th className={HEAD}>Date</th>
                        <th className={HEAD}>SKU</th>
                        <th className={cn(HEAD, 'text-right')}>Expected</th>
                        <th className={cn(HEAD, 'text-right')}>Actual</th>
                        <th className={cn(HEAD, 'text-right')}>Variance</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => {
                        const tone =
                            r.discrepancy > 0
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : r.discrepancy < 0
                                  ? 'text-red-500 dark:text-red-400'
                                  : 'text-gray-500 dark:text-gray-400';
                        return (
                            <tr
                                key={r.id}
                                className="border-b border-black/4 last:border-0 dark:border-white/4"
                            >
                                <td
                                    className={cn(
                                        CELL,
                                        'text-gray-500 dark:text-gray-400',
                                    )}
                                >
                                    {shortDate(r.date)}
                                </td>
                                <td
                                    className={cn(
                                        CELL,
                                        'font-medium text-gray-700 dark:text-gray-200',
                                    )}
                                >
                                    {r.sku}
                                </td>
                                <td
                                    className={cn(
                                        CELL,
                                        'text-right text-gray-500 dark:text-gray-400',
                                    )}
                                >
                                    {num(r.expected_qty)}
                                </td>
                                <td
                                    className={cn(
                                        CELL,
                                        'text-right text-gray-700 dark:text-gray-200',
                                    )}
                                >
                                    {num(r.counted_qty)}
                                </td>
                                <td
                                    className={cn(
                                        CELL,
                                        'text-right font-semibold',
                                        tone,
                                    )}
                                >
                                    {r.discrepancy > 0 ? '+' : ''}
                                    {num(r.discrepancy)}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
