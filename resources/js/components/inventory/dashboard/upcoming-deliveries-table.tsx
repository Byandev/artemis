import { cn } from '@/lib/utils';
import { EmptyState } from './dashboard-card';
import { type UpcomingDelivery } from './types';
import { shortDate } from './utils';

const HEAD =
    'px-3 py-2 text-left font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
const CELL = 'px-3 py-2 font-mono text-[11px] whitespace-nowrap';

export default function UpcomingDeliveriesTable({
    rows,
}: {
    rows: UpcomingDelivery[];
}) {
    if (rows.length === 0) {
        return <EmptyState message="No scheduled deliveries." />;
    }

    return (
        <div className="-m-4 custom-scrollbar overflow-x-auto">
            <table className="w-full border-collapse">
                <thead className="border-b border-black/6 dark:border-white/6">
                    <tr>
                        <th className={HEAD}>Reference</th>
                        <th className={HEAD}>Expected</th>
                        <th className={HEAD}>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr
                            key={r.id}
                            className="border-b border-black/4 last:border-0 dark:border-white/4"
                        >
                            <td
                                className={cn(
                                    CELL,
                                    'font-medium text-gray-700 dark:text-gray-200',
                                )}
                            >
                                {r.reference}
                            </td>
                            <td className={CELL}>
                                <span
                                    className={cn(
                                        'font-medium',
                                        r.delayed
                                            ? 'text-red-500 dark:text-red-400'
                                            : 'text-gray-600 dark:text-gray-300',
                                    )}
                                >
                                    {shortDate(r.expected_delivery_date)}
                                    {r.delayed && (
                                        <span className="ml-1.5 rounded-full bg-red-50 px-1.5 py-0.5 text-[9px] tracking-wide text-red-500 uppercase dark:bg-red-500/10 dark:text-red-400">
                                            Delayed
                                        </span>
                                    )}
                                </span>
                            </td>
                            <td
                                className={cn(
                                    CELL,
                                    'text-gray-500 dark:text-gray-400',
                                )}
                            >
                                {r.status_label}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
