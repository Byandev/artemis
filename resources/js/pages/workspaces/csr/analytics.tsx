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
import { useCallback, useEffect, useMemo, useState } from 'react';

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
}

const peso = (n: number) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(n) || 0);

function useStatCard(workspace: Workspace, endpoint: string, from: string, to: string, type: string) {
    const [value, setValue] = useState<number | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get(`/api/workspaces/${workspace.slug}/csrs/stats/${endpoint}`, {
                params: { from, to, type },
                signal: controller.signal,
            })
            .then((res) => setValue(Number(res.data?.value ?? 0)))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
    }, [workspace.slug, endpoint, from, to, type]);

    return { value, loading };
}

interface StatCardProps {
    title: string;
    value: number | null;
    loading: boolean;
    format?: (n: number) => string;
}

function StatCard({ title, value, loading, format: fmt }: StatCardProps) {
    return (
        <div className="rounded-xl border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <p className="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                {title}
            </p>
            {loading ? (
                <div className="mt-2 h-7 w-24 animate-pulse rounded bg-zinc-100 dark:bg-zinc-800" />
            ) : (
                <p className="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                    {fmt ? fmt(value ?? 0) : Number(value ?? 0).toLocaleString()}
                </p>
            )}
        </div>
    );
}

export default function Analytics({ workspace }: Props) {
    const today = new Date();
    const [range, setRange] = useState<{ from: Date; to: Date }>({
        from: subDays(today, 6),
        to: today,
    });
    const [records, setRecords] = useState<CsrRecord[]>([]);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);
    const [currentType, setCurrentType] = useState('pos');
    const perPage = 15;

    const fromStr = format(range.from, 'yyyy-MM-dd');
    const toStr = format(range.to, 'yyyy-MM-dd');

    const salesStat = useStatCard(workspace, 'total-sales', fromStr, toStr, currentType);
    const ordersStat = useStatCard(workspace, 'total-orders', fromStr, toStr, currentType);
    const deliveredStat = useStatCard(workspace, 'total-delivered', fromStr, toStr, currentType);
    const returningStat = useStatCard(workspace, 'total-returning', fromStr, toStr, currentType);
    const rtsStat = useStatCard(workspace, 'total-rts', fromStr, toStr, currentType);
    const rmoCalledStat = useStatCard(workspace, 'total-rmo-called', fromStr, toStr, currentType);

    useEffect(() => {
        setPage(1);
    }, [range?.from, range?.to, currentType]);

    useEffect(() => {
        if (!range?.from || !range?.to) return;
        const controller = new AbortController();
        setLoading(true);
        axios
            .get(`/api/workspaces/${workspace.slug}/csrs/daily-records`, {
                params: { from: fromStr, to: toStr, type: currentType },
                signal: controller.signal,
            })
            .then((res) => setRecords(res.data?.data ?? []))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
    }, [workspace.slug, fromStr, toStr, currentType]);

    const totalPages = Math.ceil(records.length / perPage);
    const pagedRecords = useMemo(
        () => records.slice((page - 1) * perPage, page * perPage),
        [records, page, perPage],
    );

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
                            const label = value === 'erp' ? 'ERP' : 'POS';
                            const isActive = currentType === value;
                            return (
                                <button
                                    key={value}
                                    onClick={() => setCurrentType(value)}
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

                <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                    <StatCard title="Total Sales" value={salesStat.value} loading={salesStat.loading} format={peso} />
                    <StatCard title="Total Orders" value={ordersStat.value} loading={ordersStat.loading} />
                    <StatCard title="Total Delivered" value={deliveredStat.value} loading={deliveredStat.loading} />
                    <StatCard title="Total Returning" value={returningStat.value} loading={returningStat.loading} />
                    <StatCard title="RTS Rate" value={rtsStat.value} loading={rtsStat.loading} format={(n) => `${n.toFixed(2)}%`} />
                    <StatCard title="RMO Called" value={rmoCalledStat.value} loading={rmoCalledStat.loading} />
                </div>

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