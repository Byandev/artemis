import { ArrowDown, ArrowUp } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface BreakdownRow {
    id: string | number;
    label: string;
    value: number;
}

interface Props {
    rows: BreakdownRow[];
    /** Header for the entity column — "Page", "Shop", "User". */
    entityLabel: string;
    /** Header for the value column — the active metric's name. */
    metricName: string;
    formatValue: (value: number) => string;
}

type SortKey = 'label' | 'value';
type SortDirection = 'asc' | 'desc';

const headerClass =
    'px-4 py-3 font-mono text-[10px] tracking-wider whitespace-nowrap text-gray-300 uppercase dark:text-gray-600';

/**
 * Tabular counterpart to the breakdown bar charts. Renders the exact same
 * rows/metric the chart is showing, so switching views never changes the data —
 * only how it is displayed.
 */
export default function BreakdownTable({
    rows,
    entityLabel,
    metricName,
    formatValue,
}: Props) {
    const [sortKey, setSortKey] = useState<SortKey>('value');
    const [sortDirection, setSortDirection] = useState<SortDirection>('desc');

    const sortedRows = useMemo(() => {
        return [...rows].sort((a, b) => {
            const comparison =
                sortKey === 'label'
                    ? a.label.localeCompare(b.label)
                    : a.value - b.value;

            return sortDirection === 'asc' ? comparison : -comparison;
        });
    }, [rows, sortKey, sortDirection]);

    // Bars are scaled against the largest absolute value, matching how the bar
    // chart sizes its columns.
    const maxValue = useMemo(
        () => Math.max(...rows.map((row) => Math.abs(row.value)), 0),
        [rows],
    );

    const toggleSort = (key: SortKey) => {
        if (key === sortKey) {
            setSortDirection((prev) => (prev === 'asc' ? 'desc' : 'asc'));
            return;
        }

        setSortKey(key);
        setSortDirection(key === 'label' ? 'asc' : 'desc');
    };

    const SortIcon = ({ column }: { column: SortKey }) => {
        if (column !== sortKey) return null;

        return sortDirection === 'asc' ? (
            <ArrowUp className="h-3 w-3" />
        ) : (
            <ArrowDown className="h-3 w-3" />
        );
    };

    return (
        <div className="custom-scrollbar max-h-[420px] max-w-full overflow-auto">
            <table className="w-full min-w-[420px] text-[12px]">
                <thead className="sticky top-0 z-10 bg-white dark:bg-zinc-900">
                    <tr className="border-b border-black/6 dark:border-white/6">
                        <th className={`${headerClass} w-10 text-right`}>#</th>
                        <th className={`${headerClass} text-left`}>
                            <button
                                type="button"
                                onClick={() => toggleSort('label')}
                                className="flex items-center gap-1 uppercase transition-colors hover:text-gray-500 dark:hover:text-gray-400"
                            >
                                {entityLabel}
                                <SortIcon column="label" />
                            </button>
                        </th>
                        <th className={`${headerClass} text-right`}>
                            <button
                                type="button"
                                onClick={() => toggleSort('value')}
                                className="ml-auto flex items-center gap-1 uppercase transition-colors hover:text-gray-500 dark:hover:text-gray-400"
                            >
                                {metricName}
                                <SortIcon column="value" />
                            </button>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    {sortedRows.map((row, index) => (
                        <tr
                            key={row.id}
                            className="border-b border-black/4 transition-colors last:border-0 hover:bg-stone-50 dark:border-white/4 dark:hover:bg-zinc-800/50"
                        >
                            <td className="px-4 py-3 text-right font-mono text-[11px] text-gray-300 tabular-nums dark:text-gray-600">
                                {index + 1}
                            </td>
                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                                <span className="block max-w-[280px] truncate font-medium">
                                    {row.label}
                                </span>
                                <span className="mt-1.5 block h-1 w-full max-w-[280px] overflow-hidden rounded-full bg-black/6 dark:bg-white/8">
                                    <span
                                        className="block h-full rounded-full bg-gray-300 dark:bg-gray-600"
                                        style={{
                                            width: `${maxValue > 0 ? (Math.abs(row.value) / maxValue) * 100 : 0}%`,
                                        }}
                                    />
                                </span>
                            </td>
                            <td className="px-4 py-3 text-right font-mono font-medium whitespace-nowrap text-gray-800 tabular-nums dark:text-gray-100">
                                {formatValue(row.value)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function BreakdownTableSkeleton() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 6 }).map((_, index) => (
                <div key={index} className="flex items-center gap-4 px-4">
                    <div className="h-3 w-4 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
                    <div className="h-3 flex-1 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
                    <div className="h-3 w-20 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
                </div>
            ))}
        </div>
    );
}
