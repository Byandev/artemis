import { useEffect, useState } from 'react';
import RtsBreakdownChart from '@/components/charts/RtsBreakdownChart';
import { buildBaseParams, CX_RTS_LABELS, CxRtsRow, RtsCell, RtsQueryParams, ViewMode, ViewToggle } from './rts-shared';

interface Props {
    workspaceSlug: string;
    queryParams: RtsQueryParams;
}

export default function CxRtsCard({ workspaceSlug, queryParams }: Props) {
    const [rows, setRows] = useState<CxRtsRow[]>([]);
    const [loading, setLoading] = useState(true);
    const [view, setView] = useState<ViewMode>('chart');
    const [type, setType] = useState<'latest' | 'initial'>('latest');

    // eslint-disable-next-line react-hooks/exhaustive-deps
    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        const p = buildBaseParams(queryParams);
        p.append('type', type);
        fetch(`/workspaces/${workspaceSlug}/rts/analytics/group-by/cx-rts?${p}`, { credentials: 'same-origin' })
            .then((res) => (res.ok ? res.json() : []))
            .then((data) => { if (!cancelled) { setRows(data); setLoading(false); } })
            .catch(() => { if (!cancelled) setLoading(false); });
        return () => { cancelled = true; };
    }, [workspaceSlug, type, JSON.stringify(queryParams)]);

    return (
        <div className="rounded-2xl border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
            <div className="flex items-center justify-between border-b border-black/6 dark:border-white/6 px-5 py-4">
                <div>
                    <h2 className="text-[14px] font-semibold text-gray-900 dark:text-gray-100">By Customer RTS (Phone Number Report)</h2>
                    <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                        {type === 'latest'
                            ? 'Latest report — current cumulative RTS rate per phone number'
                            : 'Initial report — RTS rate at the time the order was placed'}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <div className="flex overflow-hidden rounded-lg border border-black/8 text-[12px] font-medium dark:border-white/8">
                        <button
                            onClick={() => setType('latest')}
                            className={`px-3 py-1.5 transition-colors ${type === 'latest' ? 'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-gray-100' : 'text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-300'}`}
                        >
                            Latest
                        </button>
                        <button
                            onClick={() => setType('initial')}
                            className={`border-l border-black/8 px-3 py-1.5 transition-colors dark:border-white/8 ${type === 'initial' ? 'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-gray-100' : 'text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-300'}`}
                        >
                            Initial
                        </button>
                    </div>
                    <ViewToggle value={view} onChange={setView} />
                </div>
            </div>
            <div className="p-4">
                {loading ? (
                    <div className="flex h-24 items-center justify-center text-[13px] text-gray-400">Loading…</div>
                ) : view === 'chart' ? (
                    <RtsBreakdownChart
                        rows={rows.map((r) => ({
                            label: CX_RTS_LABELS[r.cx_rts_bucket] ?? r.cx_rts_bucket,
                            rts_rate_percentage: r.rts_rate_percentage,
                        }))}
                    />
                ) : (
                    <table className="w-full text-[12px]">
                        <thead>
                            <tr className="border-b border-black/6 dark:border-white/6">
                                <th className="px-3 py-2 text-left font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Cx. RTS Bucket</th>
                                <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Total Orders</th>
                                <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Delivered</th>
                                <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Returned</th>
                                <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">RTS Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.cx_rts_bucket} className="border-b border-black/4 dark:border-white/4 last:border-0">
                                    <td className="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">
                                        {CX_RTS_LABELS[row.cx_rts_bucket] ?? row.cx_rts_bucket}
                                    </td>
                                    <td className="px-3 py-2.5 text-right text-gray-600 dark:text-gray-400">{row.total_orders}</td>
                                    <td className="px-3 py-2.5 text-right text-green-600 dark:text-green-400">{row.delivered_count}</td>
                                    <td className="px-3 py-2.5 text-right text-red-500">{row.returned_count}</td>
                                    <td className="px-3 py-2.5 text-right"><RtsCell value={row.rts_rate_percentage} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}
