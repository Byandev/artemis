import PageHeader from '@/components/common/PageHeader';
import DatePicker from '@/components/ui/date-picker';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import Pagination from '@/components/ui/pagination';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import { format, subDays } from 'date-fns';
import { useEffect, useMemo, useState } from 'react';

interface CsrRecord {
    csr_id: number;
    csr_name: string;
    total_orders: number;
    total_sales: number;
    delivered: number;
    returning_count: number;
    rmo_called: number;
    rts_rate: number;
}

interface Props {
    workspace: Workspace;
    records: PaginatedData<CsrRecord>;
    query?: {
        sort?: string | null;
        from?: string | null;
        to?: string | null;
        page?: number | string;
        type?: string | null;
    };
}

const peso = (n: number) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(n) || 0);

export default function Analytics({ workspace }: Props) {
    const today = new Date();
    const [range, setRange] = useState<{ from: Date; to: Date }>({
        from: subDays(today, 6),
        to: today,
    });
    const [records, setRecords] = useState<CsrRecord[]>([]);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);
    const perPage = 15;

    useEffect(() => {
        setPage(1);
    }, [range?.from, range?.to]);

    const currentType = query?.type ?? 'pos';

    const navigate = (params: Record<string, string | number | null | undefined>) => {
        router.get(
            analyticsUrl(workspace),
            {
                sort: query?.sort,
                from: format(from, 'yyyy-MM-dd'),
                to: format(to, 'yyyy-MM-dd'),
                page: query?.page ?? 1,
                type: currentType || undefined,
                ...params,
            },
            { preserveState: false, replace: true, preserveScroll: true },
        );
    };

    useEffect(() => {
        if (!range?.from || !range?.to) return;
        const controller = new AbortController();
        setLoading(true);
        axios
            .get(`/api/workspaces/${workspace.slug}/csrs/daily-records`, {
                params: {
                    from: format(range.from, 'yyyy-MM-dd'),
                    to: format(range.to, 'yyyy-MM-dd'),
                },
                signal: controller.signal,
            })
            .then((res) => setRecords(res.data?.data ?? []))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
    }, [workspace.slug, range?.from, range?.to]);

    const columns = useMemo<ColumnDef<CsrRecord>[]>(
        () => [
            {
                accessorKey: 'csr_name',
                header: ({ column }) => <SortableHeader column={column} title="CSR" />,
                cell: ({ row }) => row.original.csr_name || '-',
                size: 220,
            },
            {
                accessorKey: 'total_orders',
                header: ({ column }) => <SortableHeader column={column} title="Orders" />,
                cell: ({ row }) => Number(row.original.total_orders).toLocaleString(),
            },
            {
                accessorKey: 'total_sales',
                header: ({ column }) => <SortableHeader column={column} title="Sales" />,
                cell: ({ row }) => peso(row.original.total_sales),
            },
            {
                accessorKey: 'delivered',
                header: ({ column }) => <SortableHeader column={column} title="Delivered" />,
                cell: ({ row }) => Number(row.original.delivered).toLocaleString(),
            },
            {
                accessorKey: 'returning_count',
                header: ({ column }) => <SortableHeader column={column} title="Returning" />,
                cell: ({ row }) => Number(row.original.returning_count).toLocaleString(),
            },
            {
                accessorKey: 'rts_rate',
                header: ({ column }) => <SortableHeader column={column} title="RTS Rate" />,
                cell: ({ row }) => `${Number(row.original.rts_rate).toFixed(2)}%`,
            },
            {
                accessorKey: 'rmo_called',
                header: ({ column }) => <SortableHeader column={column} title="RMO Called" />,
                cell: ({ row }) => Number(row.original.rmo_called).toLocaleString(),
            },
        ],
        [],
    );

    return (
        <AppLayout>
            <Head title={`${workspace.name} - CSR Analytics`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="CSR Analytics"
                    description="Aggregated CSR performance from daily records"
                >
                    <div className="flex items-center p-1 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                        {['erp', 'pos'].map((value) => {
                            const label =  value === 'erp' ? 'ERP' : 'POS';
                            const isActive = currentType === value;
                            return (
                                <button
                                    key={value}
                                    onClick={() => navigate({ type: value || undefined, page: 1 })}
                                    className={`rounded-lg px-3 py-1.5 text-[12px] font-medium transition-colors ${
                                        isActive
                                            ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-600 dark:text-white'
                                            : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300'
                                    }`}
                                >
                                    {label}
                                </button>
                            );
                        })}
                    </div>
                    <DatePicker
                        id="csr-analytics-date-range"
                        mode="range"
                        defaultDate={[range.from, range.to] as never}
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setRange({
                                    from: dates[0] as Date,
                                    to: dates[1] as Date,
                                });
                            }
                        }}
                    />
                </PageHeader>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable columns={columns} data={pagedRecords} />
                    <div className="flex flex-col gap-2 border-t border-black/6 px-4 py-3 xl:flex-row xl:items-center xl:justify-between dark:border-white/6">
                        <p className="text-center font-mono text-xs font-light text-gray-400 xl:text-left">
                            Showing {(page - 1) * perPage + 1} to{' '}
                            {Math.min(page * perPage, records.length)} of{' '}
                            {records.length} entries
                        </p>
                        <Pagination
                            currentPage={page}
                            totalPages={totalPages}
                            onPageChange={setPage}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
