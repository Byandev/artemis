import { useEffect, useState } from 'react';
import RtsBreakdownChart from '@/components/charts/RtsBreakdownChart';
import { buildBaseParams, PRICE_LABELS, PriceRow, RtsCell, RtsQueryParams, ViewMode, ViewToggle } from './rts-shared';

interface Props {
    workspaceSlug: string;
    queryParams: RtsQueryParams;
}

export default function PriceCard({ workspaceSlug, queryParams }: Props) {
    const [price, setPrice] = useState<PriceRow[]>([]);
    const [loading, setLoading] = useState(true);
    const [view, setView] = useState<ViewMode>('chart');

    // eslint-disable-next-line react-hooks/exhaustive-deps
    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        const p = buildBaseParams(queryParams);
        fetch(`/workspaces/${workspaceSlug}/rts/analytics/group-by/price?${p}`, { credentials: 'same-origin' })
            .then((res) => (res.ok ? res.json() : []))
            .then((data) => { if (!cancelled) { setPrice(data); setLoading(false); } })
            .catch(() => { if (!cancelled) setLoading(false); });
        return () => { cancelled = true; };
    }, [workspaceSlug, JSON.stringify(queryParams)]);

    return (
        <div className="rounded-2xl border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
            <div className="flex items-center justify-between border-b border-black/6 dark:border-white/6 px-5 py-4">
                <div>
                    <h2 className="text-[14px] font-semibold text-gray-900 dark:text-gray-100">By Price (Final Amount)</h2>
                    <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">RTS rate by order price range</p>
                </div>
                <ViewToggle value={view} onChange={setView} />
            </div>
            <div className="p-4">
                {loading ? (
                    <div className="flex h-24 items-center justify-center text-[13px] text-gray-400">Loading…</div>
                ) : view === 'chart' ? (
                    <RtsBreakdownChart
                        rows={price.map((r) => ({
                            label: r.price_bucket ? (PRICE_LABELS[r.price_bucket] ?? r.price_bucket) : 'Unknown',
                            rts_rate_percentage: r.rts_rate_percentage,
                        }))}
                    />
                ) : (
                    <table className="w-full text-[12px]">
                        <thead>
                            <tr className="border-b border-black/6 dark:border-white/6">
                                <th className="px-3 py-2 text-left font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Price Range</th>
                                <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Total</th>
                                <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Delivered</th>
                                <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Returned</th>
                                <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">RTS Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            {price.map((row) => (
                                <tr key={row.price_bucket ?? 'unknown'} className="border-b border-black/4 dark:border-white/4 last:border-0">
                                    <td className="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">
                                        {row.price_bucket ? (PRICE_LABELS[row.price_bucket] ?? row.price_bucket) : <span className="text-gray-400">Unknown</span>}
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
