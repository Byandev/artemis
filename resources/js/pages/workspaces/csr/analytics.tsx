import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { format, parseISO, subDays } from 'date-fns';
import { omit } from 'lodash';
import { useEffect, useMemo, useRef, useState } from 'react';

interface CsrRecord {
    id: number;
    name: string;
    pancake_user_id: string;
    total_orders: number;
    total_sales: number;
    delivered: number;
    returning_count: number;
    rts_rate: number;
    total_called: number;
    total_call_time: number;
    total_rmo_call_attempts: number;
    total_confirmed: number;
    rmo_percentage: number;
    total_delivered: number;
    total_returning: number;
}

interface Props {
    workspace: Workspace;
    records: PaginatedData<CsrRecord>;
    query?: {
        sort?: string | null;
        from?: string | null;
        to?: string | null;
        page?: number | string;
        per_page?: number | string;
        type?: 'erp' | 'pos' | null;
        search?: string | null;
    };
}

const peso = (n: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(Number(n) || 0);

const formatCallTime = (seconds: number) => {
    const s = Math.max(0, Math.floor(Number(seconds) || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = (n: number) => n.toString().padStart(2, '0');
    return h > 0 ? `${h}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
};
export default function Analytics({ workspace, records, query }: Props) {
    const today = new Date();
    const currentType = query?.type === 'erp' ? 'erp' : 'pos';
    const currentSort = query?.sort ?? '-total_sales';
    const range = {
        from: query?.from ? parseISO(query.from) : subDays(today, 6),
        to: query?.to ? parseISO(query.to) : today,
    };
    const [searchInput, setSearchInput] = useState(query?.search ?? '');

    const fromStr = format(range.from, 'yyyy-MM-dd');
    const toStr = format(range.to, 'yyyy-MM-dd');

    // Single entry point for every filter/sort/page change: re-request the
    // Inertia page (controller already returns `records`) with the merged
    // params and only swap the data props. No API access.
    const navigate = (overrides: Record<string, string | number | undefined>) =>
        router.get(
            `/workspaces/${workspace.slug}/csr/analytics`,
            {
                type: currentType,
                from: fromStr,
                to: toStr,
                sort: currentSort,
                'filter[search]': searchInput || undefined,
                page: query?.page ?? 1,
                per_page: query?.per_page ?? records.per_page,
                ...overrides,
            },
            {
                only: ['records', 'query'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );

    // Debounced search — skip the initial mount so we don't refetch on load.
    const isFirstRender = useRef(true);
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timer = setTimeout(() => {
            navigate({
                'filter[search]': searchInput || undefined,
                page: 1,
            });
        }, 400);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchInput]);

    const initialSorting = useMemo(
        () => toFrontendSort(currentSort),
        [currentSort],
    );

    const columns = useMemo<ColumnDef<CsrRecord>[]>(
        () => [
            {
                accessorKey: 'name',
                header: ({ column }) => (
                    <SortableHeader column={column} title="CSR" />
                ),
                // Fall back to the pancake_user_id when the user has no synced
                // name (e.g. assignees not yet pulled into pancake_users).
                cell: ({ row }) =>
                    row.original.name,
                size: 220,
            },
            {
                accessorKey: 'total_orders',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Orders" />
                ),
                cell: ({ row }) =>
                    Number(row.original.total_orders).toLocaleString(),
            },
            {
                accessorKey: 'total_sales',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Sales" />
                ),
                cell: ({ row }) => peso(row.original.total_sales),
            },
            {
                accessorKey: 'total_delivered',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Delivered" />
                ),
                cell: ({ row }) => peso(row.original.total_delivered),
            },
            {
                accessorKey: 'total_returning',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Returning" />
                ),
                cell: ({ row }) => peso(row.original.total_returning),
            },
            {
                accessorKey: 'rts_rate',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RTS Rate" />
                ),
                cell: ({ row }) =>
                    `${Number(row.original.rts_rate).toFixed(2)}%`,
            },
            {
                accessorKey: 'total_confirmed',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RMO Confirmed" />
                ),
                cell: ({ row }) =>
                    Number(row.original.total_confirmed).toLocaleString(),
            },
            {
                accessorKey: 'total_called',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RMO Assigned" />
                ),
                cell: ({ row }) =>
                    Number(row.original.total_called).toLocaleString(),
            },
            {
                accessorKey: 'total_rmo_call_attempts',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RMO Called" />
                ),
                cell: ({ row }) =>
                    Number(
                        row.original.total_rmo_call_attempts,
                    ).toLocaleString(),
            },
            {
                accessorKey: 'rmo_percentage',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RMO %" />
                ),
                cell: ({ row }) => {
                    // RMO % = RMO assigned / RMO confirmed (computed on the backend).
                    const confirmed = Number(row.original.total_confirmed) || 0;
                    if (confirmed === 0) return '—';
                    return `${Number(row.original.rmo_percentage).toFixed(2)}%`;
                },
            },
            {
                accessorKey: 'total_call_time',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RMO Call Time" />
                ),
                cell: ({ row }) => formatCallTime(row.original.total_call_time),
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
                    stackActionsOnMobile
                >
                    <div className="flex items-center rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800">
                        {['erp', 'pos'].map((value) => {
                            const label = value === 'erp' ? 'ERP' : 'POS';
                            const isActive = currentType === value;
                            const isDisabled = value === 'erp';
                            return (
                                <button
                                    key={value}
                                    disabled={isDisabled}
                                    onClick={() =>
                                        navigate({ type: value, page: 1 })
                                    }
                                    className={`rounded-lg px-3 py-1.5 text-[12px]! font-medium transition-colors ${
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
                                navigate({
                                    from: format(dates[0] as Date, 'yyyy-MM-dd'),
                                    to: format(dates[1] as Date, 'yyyy-MM-dd'),
                                    page: 1,
                                });
                            }
                        }}
                    />
                </PageHeader>

                {/*<div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">*/}
                {/*    <StatCard*/}
                {/*        title="Total Sales"*/}
                {/*        value={salesStat.value}*/}
                {/*        loading={salesStat.loading}*/}
                {/*        format={peso}*/}
                {/*    />*/}
                {/*    <StatCard*/}
                {/*        title="Total Orders"*/}
                {/*        value={ordersStat.value}*/}
                {/*        loading={ordersStat.loading}*/}
                {/*    />*/}
                {/*    <StatCard*/}
                {/*        title="Total Delivered"*/}
                {/*        value={deliveredStat.value}*/}
                {/*        loading={deliveredStat.loading}*/}
                {/*    />*/}
                {/*    <StatCard*/}
                {/*        title="Total Returning"*/}
                {/*        value={returningStat.value}*/}
                {/*        loading={returningStat.loading}*/}
                {/*    />*/}
                {/*    <StatCard*/}
                {/*        title="RTS Rate"*/}
                {/*        value={rtsStat.value}*/}
                {/*        loading={rtsStat.loading}*/}
                {/*        format={(n) => `${n.toFixed(2)}%`}*/}
                {/*    />*/}
                {/*    <StatCard*/}
                {/*        title="RMO Called"*/}
                {/*        value={rmoCalledStat.value}*/}
                {/*        loading={rmoCalledStat.loading}*/}
                {/*    />*/}
                {/*</div>*/}

                <input
                    type="text"
                    placeholder="Search CSR..."
                    value={searchInput}
                    onChange={(e) => setSearchInput(e.target.value)}
                    className="h-9 rounded-lg border border-zinc-200 bg-white px-3 text-sm! text-zinc-900 placeholder-zinc-400 focus:border-zinc-400 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 dark:focus:border-zinc-500"
                />

                <div className="mt-2 rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        key={currentSort}
                        columns={columns}
                        data={records.data ?? []}
                        initialSorting={initialSorting}
                        meta={omit(records, ['data'])}
                        onFetch={(params) => {
                            const overrides: Record<
                                string,
                                string | number | undefined
                            > = {};
                            if (params?.sort !== undefined) {
                                overrides.sort = params.sort as string;
                                overrides.page = 1;
                            }
                            if (params?.per_page !== undefined) {
                                overrides.per_page = params.per_page as number;
                                overrides.page = 1;
                            }
                            if (params?.page !== undefined) {
                                overrides.page = params.page as number;
                            }
                            navigate(overrides);
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
