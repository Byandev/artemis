import PageHeader from '@/components/common/PageHeader';
import DatePicker from '@/components/ui/date-picker';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { format, parseISO, subDays } from 'date-fns';
import { omit } from 'lodash';
import { useMemo } from 'react';

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
    };
}

const peso = (n: number) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(n) || 0);

const analyticsUrl = (workspace: Workspace) => `/workspaces/${workspace.slug}/csr/analytics`;

export default function Analytics({ workspace, records, query }: Props) {
    const today = new Date();
    const from = query?.from ? parseISO(query.from) : subDays(today, 6);
    const to = query?.to ? parseISO(query.to) : today;

    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);

    const navigate = (params: Record<string, string | number | null | undefined>) => {
        router.get(
            analyticsUrl(workspace),
            {
                sort: query?.sort,
                from: format(from, 'yyyy-MM-dd'),
                to: format(to, 'yyyy-MM-dd'),
                page: query?.page ?? 1,
                ...params,
            },
            { preserveState: false, replace: true, preserveScroll: true },
        );
    };

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
                cell: ({ row }) => peso(row.original.delivered),
            },
            {
                accessorKey: 'returning_count',
                header: ({ column }) => <SortableHeader column={column} title="Returning" />,
                cell: ({ row }) => peso(row.original.returning_count),
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
                    <DatePicker
                        id="csr-analytics-date-range"
                        mode="range"
                        defaultDate={[from, to] as never}
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

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={records.data ?? []}
                        initialSorting={initialSorting}
                        meta={omit(records, ['data'])}
                        onFetch={(params) =>
                            navigate({
                                sort: params?.sort,
                                page: params?.page ?? 1,
                            })
                        }
                    />
                </div>
            </div>
        </AppLayout>
    );
}
