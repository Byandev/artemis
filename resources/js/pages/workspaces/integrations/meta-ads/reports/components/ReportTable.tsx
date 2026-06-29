import { ArrowDown, ArrowUp, ImageOff } from 'lucide-react';
import { formatMetricValue, metricLabel } from '../../_shared';
import { type ReportRow } from '../types';

/**
 * The breakdown table shown beneath the chart (and gallery), like the SuperAds
 * detail page: one row per breakdown item, a column per selected metric, with
 * sortable headers wired to the report's server-side `sort`.
 */
export function ReportTable({
    rows,
    metrics,
    sort,
    onSort,
    showThumbnail,
    label = 'Name',
    onRowClick,
}: {
    rows: ReportRow[];
    metrics: string[];
    sort: string;
    onSort: (sort: string) => void;
    showThumbnail: boolean;
    label?: string;
    onRowClick?: (row: ReportRow) => void;
}) {
    const desc = sort.startsWith('-');
    const sortField = desc ? sort.slice(1) : sort;
    const columns = metrics.length > 0 ? metrics : ['spend'];

    const toggleSort = (field: string) =>
        onSort(`${sortField === field && !desc ? '-' : ''}${field}`);

    return (
        <div className="mt-3 overflow-hidden rounded-2xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <div className="custom-scrollbar max-w-full overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b border-black/6 dark:border-white/6">
                            <th className="sticky left-0 bg-white px-4 py-2.5 text-left text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:bg-zinc-900 dark:text-gray-500">
                                {label}
                            </th>
                            {columns.map((m) => {
                                const active = sortField === m;
                                return (
                                    <th
                                        key={m}
                                        className="px-4 py-2.5 text-right"
                                    >
                                        <button
                                            type="button"
                                            onClick={() => toggleSort(m)}
                                            className="group ml-auto inline-flex items-center gap-1 text-[10px] font-medium tracking-wider text-gray-400 uppercase transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                        >
                                            {metricLabel(m)}
                                            {active ? (
                                                desc ? (
                                                    <ArrowDown className="h-3 w-3 text-emerald-500" />
                                                ) : (
                                                    <ArrowUp className="h-3 w-3 text-emerald-500" />
                                                )
                                            ) : (
                                                <ArrowDown className="h-3 w-3 opacity-0 group-hover:opacity-40" />
                                            )}
                                        </button>
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => {
                            const thumb =
                                row.thumbnail_url || row.image_url || null;
                            return (
                                <tr
                                    key={row.id}
                                    onClick={() => onRowClick?.(row)}
                                    className={`border-b border-black/6 transition-colors last:border-0 hover:bg-emerald-500/3 dark:border-white/6 ${
                                        onRowClick ? 'cursor-pointer' : ''
                                    }`}
                                >
                                    <td className="sticky left-0 bg-white px-4 py-2.5 dark:bg-zinc-900">
                                        <div className="flex items-center gap-2">
                                            {showThumbnail &&
                                                (thumb ? (
                                                    <img
                                                        src={thumb}
                                                        alt=""
                                                        loading="lazy"
                                                        className="h-7 w-7 shrink-0 rounded object-cover"
                                                    />
                                                ) : (
                                                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded bg-gray-100 text-gray-300 dark:bg-zinc-800 dark:text-gray-600">
                                                        <ImageOff className="h-3.5 w-3.5" />
                                                    </span>
                                                ))}
                                            <div className="min-w-0">
                                                <p
                                                    className="truncate text-[12px] font-medium text-gray-800 dark:text-gray-100"
                                                    title={row.name ?? ''}
                                                >
                                                    {row.name || '—'}
                                                </p>
                                                {row.ads_count != null && (
                                                    <p className="text-[10px] text-gray-400 dark:text-gray-500">
                                                        {row.ads_count.toLocaleString()}{' '}
                                                        ads
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </td>
                                    {columns.map((m) => (
                                        <td
                                            key={m}
                                            className={`px-4 py-2.5 text-right font-mono text-[12px] tabular-nums ${
                                                sortField === m
                                                    ? 'font-semibold text-gray-900 dark:text-gray-100'
                                                    : 'text-gray-600 dark:text-gray-300'
                                            }`}
                                        >
                                            {formatMetricValue(row, m)}
                                        </td>
                                    ))}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
