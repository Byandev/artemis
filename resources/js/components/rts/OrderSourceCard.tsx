import RtsBreakdownChart from '@/components/charts/RtsBreakdownChart';
import { useEffect, useState } from 'react';
import {
    buildBaseParams,
    OrderSourceRow,
    RefreshButton,
    RtsCell,
    RtsEmptyState,
    RtsQueryParams,
    ViewMode,
    ViewToggle,
} from './rts-shared';

interface Props {
    workspaceSlug: string;
    queryParams: RtsQueryParams;
}

export default function OrderSourceCard({ workspaceSlug, queryParams }: Props) {
    const [rows, setRows] = useState<OrderSourceRow[]>([]);
    const [loading, setLoading] = useState(true);
    const [view, setView] = useState<ViewMode>('chart');
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        const p = buildBaseParams(queryParams);
        fetch(
            `/workspaces/${workspaceSlug}/rts/analytics/group-by/order-source?${p}`,
            { credentials: 'same-origin' },
        )
            .then((res) => (res.ok ? res.json() : []))
            .then((data) => {
                if (!cancelled) {
                    setRows(data);
                    setLoading(false);
                }
            })
            .catch(() => {
                if (!cancelled) setLoading(false);
            });
        return () => {
            cancelled = true;
        };
    }, [workspaceSlug, JSON.stringify(queryParams), refreshKey]);

    return (
        <div className="rounded-2xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-center justify-between border-b border-black/6 px-5 py-4 dark:border-white/6">
                <div>
                    <h2 className="text-[14px] font-semibold text-gray-900 dark:text-gray-100">
                        By Order Source
                    </h2>
                    <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                        RTS rate by order source
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <RefreshButton
                        onClick={() => setRefreshKey((k) => k + 1)}
                        loading={loading}
                    />
                    <ViewToggle value={view} onChange={setView} />
                </div>
            </div>
            <div className="p-4">
                {loading ? (
                    <div className="flex h-24 items-center justify-center text-[13px] text-gray-400">
                        Loading…
                    </div>
                ) : rows.length === 0 ? (
                    <RtsEmptyState />
                ) : view === 'chart' ? (
                    <RtsBreakdownChart
                        rows={rows.map((r) => ({
                            label: r.order_source_name ?? 'Unknown',
                            rts_rate_percentage: r.rts_rate_percentage,
                        }))}
                    />
                ) : (
                    <div className="custom-scrollbar max-w-full overflow-x-auto">
                        <table className="w-full min-w-[520px] text-[12px]">
                            <thead>
                                <tr className="border-b border-black/6 dark:border-white/6">
                                    <th className="px-4 py-3 text-left font-mono text-[10px] tracking-wider whitespace-nowrap text-gray-300 uppercase dark:text-gray-600">
                                        Source
                                    </th>
                                    <th className="px-4 py-3 text-right font-mono text-[10px] tracking-wider whitespace-nowrap text-gray-300 uppercase dark:text-gray-600">
                                        Total
                                    </th>
                                    <th className="px-4 py-3 text-right font-mono text-[10px] tracking-wider whitespace-nowrap text-gray-300 uppercase dark:text-gray-600">
                                        Delivered
                                    </th>
                                    <th className="px-4 py-3 text-right font-mono text-[10px] tracking-wider whitespace-nowrap text-gray-300 uppercase dark:text-gray-600">
                                        Returned
                                    </th>
                                    <th className="px-4 py-3 text-right font-mono text-[10px] tracking-wider whitespace-nowrap text-gray-300 uppercase dark:text-gray-600">
                                        RTS Rate
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr
                                        key={row.order_source_name ?? 'unknown'}
                                        className="border-b border-black/4 last:border-0 dark:border-white/4"
                                    >
                                        <td className="px-4 py-3 font-medium whitespace-nowrap text-gray-700 dark:text-gray-300">
                                            {row.order_source_name ?? (
                                                <span className="text-gray-400">
                                                    Unknown
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap text-gray-600 dark:text-gray-400">
                                            {row.total_orders}
                                        </td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap text-green-600 dark:text-green-400">
                                            {row.delivered_count}
                                        </td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap text-red-500">
                                            {row.returned_count}
                                        </td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            <RtsCell
                                                value={row.rts_rate_percentage}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}
