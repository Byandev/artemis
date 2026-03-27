import { useEffect, useState } from 'react';
import { PaginatedData } from '@/types';
import { buildBaseParams, OrderItemRow, RtsCell, RtsQueryParams } from './rts-shared';

const SORT_LABELS: Record<string, string> = {
    item_name: 'Item Name',
    total_orders: 'Total Orders',
    delivered_count: 'Delivered',
    returned_count: 'Returned',
    rts_rate_percentage: 'RTS Rate',
};

const SORT_COLUMNS = ['item_name', 'total_orders', 'delivered_count', 'returned_count', 'rts_rate_percentage'] as const;

interface Props {
    workspaceSlug: string;
    queryParams: RtsQueryParams;
}

export default function ProductCard({ workspaceSlug, queryParams }: Props) {
    const [data, setData] = useState<PaginatedData<OrderItemRow> | null>(null);
    const [loading, setLoading] = useState(true);
    const [sort, setSort] = useState('-total_orders');

    const fetchPage = (page: number, currentSort: string) => {
        setLoading(true);
        const p = buildBaseParams(queryParams);
        p.append('page', String(page));
        p.append('per_page', '15');
        p.append('sort', currentSort);
        fetch(`/workspaces/${workspaceSlug}/rts/analytics/group-by/order-item?${p}`, { credentials: 'same-origin' })
            .then((res) => (res.ok ? res.json() : null))
            .then((json) => { setData(json); setLoading(false); })
            .catch(() => setLoading(false));
    };

    // eslint-disable-next-line react-hooks/exhaustive-deps
    useEffect(() => { fetchPage(1, sort); }, [workspaceSlug, JSON.stringify(queryParams)]);

    const handleSort = (col: string) => {
        const active = sort.replace('-', '') === col;
        const next = active && sort.startsWith('-') ? col : `-${col}`;
        setSort(next);
        fetchPage(1, next);
    };

    return (
        <div className="rounded-2xl border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
            <div className="flex items-center justify-between border-b border-black/6 dark:border-white/6 px-5 py-4">
                <div>
                    <h2 className="text-[14px] font-semibold text-gray-900 dark:text-gray-100">By Product</h2>
                    <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">RTS rate broken down by product/item name</p>
                </div>
            </div>
            <div className="p-4">
                {loading ? (
                    <div className="flex h-24 items-center justify-center text-[13px] text-gray-400">Loading…</div>
                ) : (
                    <>
                        <table className="w-full text-[12px]">
                            <thead>
                                <tr className="border-b border-black/6 dark:border-white/6">
                                    {SORT_COLUMNS.map((col) => {
                                        const isActive = sort.replace('-', '') === col;
                                        const isDesc = isActive && sort.startsWith('-');
                                        return (
                                            <th
                                                key={col}
                                                onClick={() => handleSort(col)}
                                                className={`px-3 py-2 font-mono text-[10px] uppercase tracking-wider cursor-pointer select-none transition-colors hover:text-gray-500 dark:hover:text-gray-400 ${col === 'item_name' ? 'text-left' : 'text-right'} ${isActive ? 'text-gray-500 dark:text-gray-400' : 'text-gray-300 dark:text-gray-600'}`}
                                            >
                                                {SORT_LABELS[col]}{isActive ? (isDesc ? ' ↓' : ' ↑') : ''}
                                            </th>
                                        );
                                    })}
                                </tr>
                            </thead>
                            <tbody>
                                {(data?.data ?? []).map((row, i) => (
                                    <tr key={`${row.item_name}-${i}`} className="border-b border-black/4 dark:border-white/4 last:border-0">
                                        <td className="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">
                                            {row.item_name ?? <span className="text-gray-400">Unknown</span>}
                                        </td>
                                        <td className="px-3 py-2.5 text-right text-gray-600 dark:text-gray-400">{row.total_orders}</td>
                                        <td className="px-3 py-2.5 text-right text-green-600 dark:text-green-400">{row.delivered_count}</td>
                                        <td className="px-3 py-2.5 text-right text-red-500">{row.returned_count}</td>
                                        <td className="px-3 py-2.5 text-right"><RtsCell value={row.rts_rate_percentage} /></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {data && data.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between border-t border-black/4 pt-4 dark:border-white/4">
                                <p className="font-mono text-[11px] text-gray-400">
                                    Showing {data.from}–{data.to} of {data.total}
                                </p>
                                <div className="flex gap-1">
                                    <button
                                        disabled={data.current_page === 1}
                                        onClick={() => fetchPage(data.current_page - 1, sort)}
                                        className="rounded px-2.5 py-1 font-mono text-[11px] text-gray-500 transition-colors hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-white/6"
                                    >
                                        Previous
                                    </button>
                                    <button
                                        disabled={data.current_page === data.last_page}
                                        onClick={() => fetchPage(data.current_page + 1, sort)}
                                        className="rounded px-2.5 py-1 font-mono text-[11px] text-gray-500 transition-colors hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-white/6"
                                    >
                                        Next
                                    </button>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
