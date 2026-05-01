import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import DatePicker from '@/components/ui/date-picker';
import { toFrontendSort } from '@/lib/sort';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import flatpickr from 'flatpickr';
import DateOption = flatpickr.Options.DateOption;
import moment from 'moment';
import { debounce, omit } from 'lodash';
import { Download, MoreHorizontal, Pencil, Search, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Workspace } from '@/types/models/Workspace';
import { DeleteOrderDialog } from '@/components/inventory/delete-order-dialog';
import { PaginatedData } from '@/types';

interface PurchasedOrderItem {
    id: number;
    count: number;
    amount: string;
    total_amount: string;
    inventory_item?: {
        sku: string;
        product?: { name: string };
    };
}

const STATUSES: Record<number, { label: string; color: string }> = {
    1: { label: 'For Approval',        color: 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400' },
    2: { label: 'Approved',            color: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400' },
    3: { label: 'To Pay',              color: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400' },
    4: { label: 'Paid',                color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400' },
    5: { label: 'For Purchase',        color: 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-400' },
    6: { label: 'Waiting For Delivery',color: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-400' },
    7: { label: 'Delivered',           color: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400' },
    8: { label: 'Cancelled',           color: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400' },
};

interface PurchasedOrder {
    id: number;
    issue_date: string;
    delivery_no: string | null;
    cust_po_no: string | null;
    control_no: string | null;
    delivery_fee: string;
    total_amount: string;
    status: number;
    items: PurchasedOrderItem[];
}

interface Props {
    workspace: Workspace;
    orders: PaginatedData<PurchasedOrder>;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string; start_date?: string; end_date?: string };
    };
}

export default function PurchasedOrderIndex({ workspace, orders, query }: Props) {
    const [deletingOrder, setDeletingOrder] = useState<PurchasedOrder | null>(null);
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);

    const baseUrl = `/workspaces/${workspace.slug}/inventory/purchased-orders`;

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [dateRange, setDateRange] = useState<string[]>(() => [
        query?.filter?.start_date ?? '',
        query?.filter?.end_date ?? '',
    ]);

    const buildFilter = (search: string, range: string[]) => ({
        search: search || undefined,
        start_date: range[0] || undefined,
        end_date: range[1] || undefined,
    });

    const performQuery = useCallback(
        debounce((search: string, range: string[]) => {
            router.get(
                baseUrl,
                {
                    sort: query?.sort,
                    filter: buildFilter(search, range),
                    page: 1,
                    per_page: query?.perPage ?? orders.per_page,
                },
                { preserveState: true, replace: true, preserveScroll: true, only: ['orders', 'query'] }
            );
        }, 400),
        [baseUrl, query?.sort, query?.perPage, orders.per_page]
    );

    useEffect(() => {
        const filterChanged =
            searchValue !== (query?.filter?.search ?? '') ||
            (dateRange[0] || undefined) !== (query?.filter?.start_date ?? undefined) ||
            (dateRange[1] || undefined) !== (query?.filter?.end_date ?? undefined);
        if (filterChanged) {
            performQuery(searchValue, dateRange);
        }
        return () => performQuery.cancel();
    }, [searchValue, dateRange]);

    const columns: ColumnDef<PurchasedOrder>[] = [
        {
            accessorKey: 'issue_date',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Issue Date" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.issue_date ? row.original.issue_date.slice(0, 10) : '—'}
                </span>
            ),
        },
        {
            accessorKey: 'delivery_no',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Delivery No." />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.delivery_no || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'cust_po_no',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Cust PO No." />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.cust_po_no || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'control_no',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Control No." />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.control_no || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'delivery_fee',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Delivery Fee" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    ₱{Number(row.original.delivery_fee).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                </span>
            ),
        },
        {
            accessorKey: 'total_amount',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Total Amount" />,
            cell: ({ row }) => (
                <span className="font-mono text-[12px] font-semibold text-gray-800 dark:text-gray-200">
                    ₱{Number(row.original.total_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Status" />,
            cell: ({ row }) => {
                const s = STATUSES[row.original.status] ?? STATUSES[1];
                return (
                    <span className={`inline-flex items-center rounded-full px-2.5 py-1 font-mono text-[11px] font-medium ${s.color}`}>
                        {s.label}
                    </span>
                );
            },
        },
        {
            accessorKey: 'items',
            enableSorting: false,
            header: () => <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400">Items</span>,
            cell: ({ row }) => (
                <span className="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                    {row.original.items.length}
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Actions</div>,
            cell: ({ row }) => {
                const order = row.original;
                return (
                    <div className="flex justify-center">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                    <MoreHorizontal className="h-3.5 w-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-36">
                                <DropdownMenuItem onClick={() => router.get(`${baseUrl}/${order.id}/edit`)}>
                                    <Pencil className="mr-2 h-3.5 w-3.5" />
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    className="text-red-600 focus:text-red-600 dark:text-red-400"
                                   onClick={() => setDeletingOrder(order)}
                                >
                                    <Trash2 className="mr-2 h-3.5 w-3.5" />
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Purchased Orders`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Purchased Orders"
                    description="Manage your inventory purchased orders."
                >
                    <a
                        href={`${baseUrl}/export?${new URLSearchParams(
                            Object.entries({
                                'filter[search]': searchValue || '',
                                'filter[start_date]': dateRange[0] || '',
                                'filter[end_date]': dateRange[1] || '',
                                sort: query?.sort ?? '',
                            }).filter(([, v]) => v !== '')
                        ).toString()}`}
                        className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-zinc-800"
                    >
                        <Download className="h-3.5 w-3.5" />
                        Export
                    </a>
                    <button
                        onClick={() => router.get(`${baseUrl}/create`)}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        Add Order
                    </button>
                </PageHeader>

                <div className="mb-3 flex flex-col items-stretch gap-2 md:flex-row md:items-center">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 outline-none transition-all placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search Delivery No., Cust PO No., Control No.…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                    <DatePicker
                        id="purchased-orders-date-range"
                        mode="range"
                        placeholder="Filter by issue date"
                        defaultDate={(dateRange[0] && dateRange[1] ? dateRange : undefined) as never as DateOption}
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setDateRange([
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                ]);
                            } else if (dates.length === 0) {
                                setDateRange(['', '']);
                            }
                        }}
                    />
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={orders.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(orders, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    filter: buildFilter(searchValue, dateRange),
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page ?? query?.perPage ?? orders.per_page,
                                },
                                { preserveState: true, replace: true, preserveScroll: true }
                            );
                        }}
                    />
                </div>
                <DeleteOrderDialog
                    order={deletingOrder}
                    workspace={workspace}
                    onClose={() => setDeletingOrder(null)}
                />
            </div>
        </AppLayout>
    );
}
