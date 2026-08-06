import { PURCHASED_ORDER_STATUSES } from '@/constants/purchased-order-statuses';
import RefreshButton from '../refresh-button';
import { useInventoryStat } from '../use-inventory-stat';
import {
    cellClass,
    days,
    EmptyState,
    headClass,
    num,
    numCellClass,
    panelClass,
    PanelHead,
    TableSkeleton,
} from './shared';
import type { WorklistData } from './types';

/**
 * Purchase orders held inside the business, longest wait first.
 *
 * Ranked by time waiting rather than size: a 13,000-unit order raised
 * yesterday is not a problem, and a 400-unit one raised in May is. "Covers"
 * carries the impact instead — how many days of demand the order is holding up.
 */
export default function WorklistPanel({ slug }: { slug: string }) {
    const { data, loading, error, refetch } = useInventoryStat<WorklistData>(
        slug,
        'po-flow/worklist',
    );

    const lines = data?.lines ?? [];

    return (
        <section className={panelClass}>
            <PanelHead
                title="Clear these first"
                action={
                    <div className="flex items-center gap-3">
                        {!loading &&
                            !error &&
                            data &&
                            data.overdue_units > 0 && (
                                <div className="text-right">
                                    <div className="font-mono text-sm font-semibold text-red-600 tabular-nums dark:text-red-400">
                                        {num(data.overdue_units)}
                                    </div>
                                    <div className="text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                        units past target
                                    </div>
                                </div>
                            )}
                        <RefreshButton
                            onClick={refetch}
                            loading={loading}
                            error={error}
                            label="the worklist"
                        />
                    </div>
                }
            >
                Orders held inside the company, longest wait first. “Covers” is
                how many days of demand each one is holding up. Anything past{' '}
                {data?.sla_days ?? 7} days is flagged.
            </PanelHead>

            {loading ? (
                <div className="px-[18px] pb-6">
                    <TableSkeleton rows={5} cols={6} />
                </div>
            ) : error ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="Couldn't load the worklist." />
                </div>
            ) : lines.length === 0 ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="Nothing waiting inside the company — every open order is with a supplier." />
                </div>
            ) : (
                <div className="max-h-[420px] overflow-auto border-t border-black/6 dark:border-white/6">
                    <table className="w-full border-collapse">
                        <thead className="sticky top-0 z-10 bg-zinc-50 dark:bg-zinc-900">
                            <tr className="border-b border-black/6 dark:border-white/6">
                                <th className={headClass}>Stage</th>
                                <th className={headClass}>Waiting</th>
                                <th className={headClass}>PO</th>
                                <th className={headClass}>Item</th>
                                <th className={`${headClass} text-right!`}>
                                    Units
                                </th>
                                <th className={`${headClass} text-right!`}>
                                    Covers
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {lines.map((line) => {
                                const badge =
                                    PURCHASED_ORDER_STATUSES[line.status];

                                return (
                                    <tr
                                        key={line.id}
                                        className="border-b border-black/5 transition-colors last:border-0 hover:bg-zinc-50 dark:border-white/5 dark:hover:bg-zinc-800/50"
                                    >
                                        <td className={cellClass}>
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium whitespace-nowrap ${
                                                    badge?.color ??
                                                    'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400'
                                                }`}
                                            >
                                                {badge?.label ?? line.stage}
                                            </span>
                                        </td>
                                        <td
                                            className={`${cellClass} font-mono text-xs whitespace-nowrap tabular-nums ${
                                                line.overdue
                                                    ? 'font-semibold text-red-600 dark:text-red-400'
                                                    : 'text-gray-400 dark:text-gray-500'
                                            }`}
                                        >
                                            {days(line.age)}
                                        </td>
                                        <td
                                            className={`${cellClass} font-mono text-xs whitespace-nowrap text-gray-400 dark:text-gray-500`}
                                        >
                                            {line.po}
                                        </td>
                                        <td className={`${cellClass} text-xs`}>
                                            <div className="font-medium text-gray-900 dark:text-gray-100">
                                                {line.item}
                                            </div>
                                            {line.product_name && (
                                                <div className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                                                    {line.product_name}
                                                </div>
                                            )}
                                        </td>
                                        <td
                                            className={`${numCellClass} font-semibold text-gray-900 dark:text-gray-100`}
                                        >
                                            {num(line.units)}
                                        </td>
                                        <td
                                            className={`${numCellClass} text-gray-500 dark:text-gray-400`}
                                        >
                                            {days(line.covers_days)}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}
