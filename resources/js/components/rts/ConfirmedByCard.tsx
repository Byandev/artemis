import { useEffect, useMemo, useState } from 'react';
import { omit } from 'lodash';
import { ColumnDef } from '@tanstack/react-table';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import RtsBreakdownChart from '@/components/charts/RtsBreakdownChart';
import { PaginatedData } from '@/types';
import { toFrontendSort } from '@/lib/sort';
import { buildBaseParams, ConfirmedByRow, RtsCell, RtsQueryParams, ViewMode, ViewToggle } from './rts-shared';

interface Props {
    workspaceSlug: string;
    queryParams: RtsQueryParams;
}

export default function ConfirmedByCard({ workspaceSlug, queryParams }: Props) {
    const [data, setData] = useState<PaginatedData<ConfirmedByRow> | null>(null);
    const [loading, setLoading] = useState(true);
    const [sort, setSort] = useState('-total_orders');
    const [view, setView] = useState<ViewMode>('table');

    const fetchPage = (page: number, currentSort: string, perPage = 15) => {
        setLoading(true);
        const p = buildBaseParams(queryParams);
        p.append('page', String(page));
        p.append('per_page', String(perPage));
        p.append('sort', currentSort);
        fetch(`/workspaces/${workspaceSlug}/rts/analytics/group-by/confirmed-by?${p}`, { credentials: 'same-origin' })
            .then((res) => (res.ok ? res.json() : null))
            .then((json) => { setData(json); setLoading(false); })
            .catch(() => setLoading(false));
    };

    // eslint-disable-next-line react-hooks/exhaustive-deps
    useEffect(() => { fetchPage(1, sort); }, [workspaceSlug, JSON.stringify(queryParams)]);

    const columns: ColumnDef<ConfirmedByRow>[] = useMemo(() => [
        {
            accessorKey: 'confirmed_by_name',
            header: ({ column }) => <SortableHeader column={column} title="Confirmed By" />,
            cell: ({ row }) => row.original.confirmed_by_name ?? <span className="text-gray-400">Unknown</span>,
        },
        {
            accessorKey: 'total_orders',
            header: ({ column }) => <SortableHeader column={column} title="Total Orders" />,
        },
        {
            accessorKey: 'delivered_count',
            header: ({ column }) => <SortableHeader column={column} title="Delivered" />,
            cell: ({ row }) => <span className="text-green-600 dark:text-green-400">{row.original.delivered_count}</span>,
        },
        {
            accessorKey: 'returned_count',
            header: ({ column }) => <SortableHeader column={column} title="Returned" />,
            cell: ({ row }) => <span className="text-red-500">{row.original.returned_count}</span>,
        },
        {
            accessorKey: 'rts_rate_percentage',
            header: ({ column }) => <SortableHeader column={column} title="RTS Rate" />,
            cell: ({ row }) => <RtsCell value={row.original.rts_rate_percentage} />,
        },
    ], []);

    return (
        <div className="rounded-2xl border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
            <div className="flex items-center justify-between border-b border-black/6 dark:border-white/6 px-5 py-4">
                <div>
                    <h2 className="text-[14px] font-semibold text-gray-900 dark:text-gray-100">By Confirmed By</h2>
                    <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">RTS rate broken down by confirming agent</p>
                </div>
                <ViewToggle value={view} onChange={setView} />
            </div>
            <div className="p-4">
                {loading ? (
                    <div className="flex h-32 items-center justify-center text-[13px] text-gray-400">Loading…</div>
                ) : view === 'chart' ? (
                    <RtsBreakdownChart
                        rows={(data?.data ?? []).map((r) => ({
                            label: r.confirmed_by_name ?? 'Unknown',
                            rts_rate_percentage: r.rts_rate_percentage,
                        }))}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={data?.data ?? []}
                        enableInternalPagination={false}
                        meta={data ? { ...omit(data, ['data']) } : undefined}
                        initialSorting={toFrontendSort(sort)}
                        onFetch={(params) => {
                            const s = params?.sort as string ?? '-total_orders';
                            setSort(s);
                            fetchPage(Number(params?.page ?? 1), s, Number(params?.per_page ?? data?.per_page ?? 15));
                        }}
                    />
                )}
            </div>
        </div>
    );
}
