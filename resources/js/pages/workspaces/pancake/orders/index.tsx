import PageHeader from '@/components/common/PageHeader';
import {
    ColumnsDropdown,
    useColumnVisibility,
    type ColumnOption,
} from '@/components/ui/columns-dropdown';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import flatpickr from 'flatpickr';
import { debounce, omit } from 'lodash';
import { Search, X } from 'lucide-react';
import moment from 'moment';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

interface OrderItem {
    id: number;
    name: string | null;
    quantity: number | null;
}

interface OrderTag {
    id: number;
    name: string | null;
}

interface ShippingAddress {
    id: number;
    full_name: string | null;
    phone_number: string | null;
    full_address: string | null;
}

interface Order {
    id: number;
    order_number: number | string | null;
    status_name: string | null;
    tracking_code: string | null;
    total_amount: string | number | null;
    inserted_at: string | null;
    updated_at: string | null;
    shipping_address: ShippingAddress | null;
    items: OrderItem[];
    tags: OrderTag[];
}

interface Props {
    workspace: Workspace;
    orders: PaginatedData<Order>;
    statusCounts: Record<string, number>;
    totalCount: number;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
            status?: string;
            date_from?: string;
            date_to?: string;
            rider?: string;
        };
    };
}

const peso = (v: number | string | null | undefined) =>
    v == null
        ? '₱0'
        : `₱${Number(v).toLocaleString('en-PH', {
              minimumFractionDigits: 0,
              maximumFractionDigits: 2,
          })}`;

// Status badge palette. Falls back to neutral for anything unmapped.
const STATUS_STYLES: Record<string, string> = {
    new: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
    confirmed:
        'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400',
    submitted:
        'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400',
    shipped:
        'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-400',
    delivered:
        'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400',
    returning:
        'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
    returned:
        'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-400',
    canceled: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400',
};

const statusStyle = (status: string | null) =>
    STATUS_STYLES[(status ?? '').toLowerCase()] ??
    'bg-stone-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400';

const prettyDate = (iso: string | null) => {
    if (!iso) return '—';
    const m = moment(iso);
    return m.isValid() ? m.format('DD MMM, h:mm a') : '—';
};

// Column ids double as the sort keys sent to the backend, so they must match the
// accessorKeys the server sorts on. `required` columns can't be hidden.
const COLUMN_OPTIONS: ColumnOption[] = [
    { id: 'order_number', label: 'ID', required: true },
    { id: 'customer', label: 'Customer' },
    { id: 'phone_number', label: 'Phone number' },
    { id: 'delivery', label: 'Delivery' },
    { id: 'tracking_code', label: 'Tracking code' },
    { id: 'total_amount', label: 'Total amount' },
    { id: 'products', label: 'Products' },
    { id: 'inserted_at', label: 'Created at' },
    { id: 'updated_at', label: 'Status updated at' },
    { id: 'status_name', label: 'Status' },
];

const COLUMNS_STORAGE_KEY = 'pancake-orders-cols';

// Tab order mirrors the Pancake POS lifecycle; only statuses that exist show up.
const TAB_ORDER = [
    'new',
    'confirmed',
    'submitted',
    'shipped',
    'delivered',
    'returning',
    'returned',
    'canceled',
];

export default function PancakeOrdersIndex({
    workspace,
    orders,
    statusCounts,
    totalCount,
    query,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/pancake/orders`;
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? '-inserted_at'),
        [query?.sort],
    );

    const [search, setSearch] = useState(query?.filter?.search ?? '');
    const [status, setStatus] = useState<string>(query?.filter?.status ?? '');
    const [rider, setRider] = useState<string>(query?.filter?.rider ?? '');
    const [dateFrom, setDateFrom] = useState<string>(
        query?.filter?.date_from ?? '',
    );
    const [dateTo, setDateTo] = useState<string>(query?.filter?.date_to ?? '');
    const defaultDate = useMemo(
        () =>
            dateFrom && dateTo
                ? ([dateFrom, dateTo] as never as DateOption)
                : undefined,
        [],
    );

    const buildFilter = (
        s: string,
        st: string,
        df: string,
        dt: string,
        r: string,
    ) => ({
        search: s || undefined,
        status: st || undefined,
        date_from: df || undefined,
        date_to: dt || undefined,
        rider: r || undefined,
    });

    const reload = useCallback(
        debounce((s: string, st: string, df: string, dt: string, r: string) => {
            router.get(
                baseUrl,
                {
                    sort: query?.sort,
                    filter: buildFilter(s, st, df, dt, r),
                    page: 1,
                    per_page: query?.perPage ?? orders.per_page,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['orders', 'statusCounts', 'totalCount', 'query'],
                },
            );
        }, 400),
        [baseUrl, query?.sort, query?.perPage, orders.per_page],
    );

    const initialMount = useRef(true);
    useEffect(() => {
        if (initialMount.current) {
            initialMount.current = false;
            return;
        }
        reload(search, status, dateFrom, dateTo, rider);
        return () => reload.cancel();
    }, [search, status, dateFrom, dateTo, rider]);

    const { visibility: columnVisibility, setVisibility: setColumnVisibility } =
        useColumnVisibility(COLUMN_OPTIONS, COLUMNS_STORAGE_KEY);

    const tabs = useMemo(() => {
        const known = TAB_ORDER.filter((s) => s in statusCounts);
        const extras = Object.keys(statusCounts).filter(
            (s) => !TAB_ORDER.includes(s),
        );
        return [...known, ...extras];
    }, [statusCounts]);

    const columns: ColumnDef<Order>[] = [
        {
            accessorKey: 'order_number',
            id: 'order_number',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="ID" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                    #{row.original.order_number ?? row.original.id}
                </span>
            ),
        },
        {
            accessorKey: 'shipping_address.full_name',
            id: 'customer',
            enableSorting: false,
            header: () => (
                <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                    Customer
                </span>
            ),
            cell: ({ row }) => (
                <span className="text-[12px] text-gray-800 dark:text-gray-200">
                    {row.original.shipping_address?.full_name ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'phone_number',
            id: 'phone_number',
            enableSorting: false,
            header: () => (
                <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                    Phone number
                </span>
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.shipping_address?.phone_number ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'delivery',
            id: 'delivery',
            enableSorting: false,
            header: () => (
                <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                    Delivery
                </span>
            ),
            cell: ({ row }) => (
                <span
                    className="block max-w-[220px] truncate text-[12px] text-gray-600 dark:text-gray-400"
                    title={row.original.shipping_address?.full_address ?? ''}
                >
                    {row.original.shipping_address?.full_address ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'tracking_code',
            id: 'tracking_code',
            enableSorting: false,
            header: () => (
                <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                    Tracking code
                </span>
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.tracking_code || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'total_amount',
            id: 'total_amount',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Total amount" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] font-semibold text-gray-800 dark:text-gray-200">
                    {peso(row.original.total_amount)}
                </span>
            ),
        },
        {
            accessorKey: 'products',
            id: 'products',
            enableSorting: false,
            header: () => (
                <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                    Products
                </span>
            ),
            cell: ({ row }) => {
                const items = row.original.items ?? [];
                if (items.length === 0) {
                    return (
                        <span className="text-[11px] text-red-500 dark:text-red-400">
                            No product
                        </span>
                    );
                }
                const label = items
                    .map((it) => `${it.name ?? 'Item'} x ${it.quantity ?? 1}`)
                    .join(', ');
                return (
                    <span
                        className="block max-w-[220px] truncate text-[12px] text-gray-700 dark:text-gray-300"
                        title={label}
                    >
                        {label}
                    </span>
                );
            },
        },
        {
            accessorKey: 'inserted_at',
            id: 'inserted_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Created at" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {prettyDate(row.original.inserted_at)}
                </span>
            ),
        },
        {
            accessorKey: 'updated_at',
            id: 'updated_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Status updated at" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {prettyDate(row.original.updated_at)}
                </span>
            ),
        },
        {
            accessorKey: 'status_name',
            id: 'status_name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <span
                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium capitalize ${statusStyle(row.original.status_name)}`}
                >
                    {row.original.status_name ?? 'unknown'}
                </span>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Orders`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Orders"
                    description="All Pancake orders synced for this workspace."
                />

                {/* Status tabs */}
                <div className="mb-3 flex flex-wrap items-center gap-1.5">
                    <StatusTab
                        label="All"
                        count={totalCount}
                        active={status === ''}
                        onClick={() => setStatus('')}
                    />
                    {tabs.map((s) => (
                        <StatusTab
                            key={s}
                            label={s}
                            count={statusCounts[s] ?? 0}
                            active={status === s}
                            onClick={() => setStatus(s)}
                        />
                    ))}
                </div>

                {/* Filters */}
                <div className="mb-3 flex flex-col items-stretch gap-2 md:flex-row md:items-center">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600"
                            placeholder="Order #, tracking, name, phone, address…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <DatePicker
                        id="pancake-orders-date-range"
                        mode="range"
                        placeholder="Filter by created date"
                        defaultDate={defaultDate}
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setDateFrom(
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                );
                                setDateTo(
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                );
                            } else if (dates.length === 0) {
                                setDateFrom('');
                                setDateTo('');
                            }
                        }}
                    />
                    {rider && (
                        <span className="inline-flex h-9 items-center gap-1.5 rounded-[10px] border border-emerald-300 bg-emerald-50 px-3 font-mono! text-[12px]! text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-400">
                            Rider: {rider}
                            <button
                                onClick={() => setRider('')}
                                className="rounded-full p-0.5 hover:bg-emerald-100 dark:hover:bg-emerald-500/20"
                                title="Remove rider filter"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </span>
                    )}
                    {(search || status || dateFrom || dateTo || rider) && (
                        <button
                            onClick={() => {
                                setSearch('');
                                setStatus('');
                                setRider('');
                                setDateFrom('');
                                setDateTo('');
                            }}
                            className="flex h-9 items-center gap-1 rounded-[10px] border border-black/10 bg-white px-3 font-mono! text-[12px]! text-gray-700 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300"
                        >
                            <X className="h-3.5 w-3.5" />
                            Clear
                        </button>
                    )}

                    <div className="md:flex-1" />

                    <ColumnsDropdown
                        options={COLUMN_OPTIONS}
                        visibility={columnVisibility}
                        onChange={setColumnVisibility}
                    />
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={orders.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(orders, ['data']) }}
                        columnVisibility={columnVisibility}
                        onColumnVisibilityChange={setColumnVisibility}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    filter: buildFilter(
                                        search,
                                        status,
                                        dateFrom,
                                        dateTo,
                                        rider,
                                    ),
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query?.perPage ??
                                        orders.per_page,
                                },
                                {
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                },
                            );
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}

function StatusTab({
    label,
    count,
    active,
    onClick,
}: {
    label: string;
    count: number;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            onClick={onClick}
            className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 font-mono text-[11px] capitalize transition-colors ${
                active
                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-400'
                    : 'border-black/6 bg-white text-gray-600 hover:bg-stone-50 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-400 dark:hover:bg-zinc-800'
            }`}
        >
            {label}
            <span
                className={`rounded-full px-1.5 py-0.5 text-[10px] ${
                    active
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                        : 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-500'
                }`}
            >
                {count.toLocaleString('en-PH')}
            </span>
        </button>
    );
}
