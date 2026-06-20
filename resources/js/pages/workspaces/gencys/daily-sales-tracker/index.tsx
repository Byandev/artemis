import PageHeader from '@/components/common/PageHeader';
import DatePicker from '@/components/ui/date-picker';
import Pagination from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import flatpickr from 'flatpickr';
import { debounce } from 'lodash';
import {
    ChevronDown,
    ChevronRight,
    Coins,
    Package,
    PackageCheck,
    Search,
    TrendingUp,
    Truck,
} from 'lucide-react';
import moment from 'moment';
import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import DateOption = flatpickr.Options.DateOption;

interface Order {
    id: number;
    order_no: string | null;
    order_date: string | null;
    csr: string | null;
    verifier_name: string | null;
    upsell_by: string | null;
    contact: string | null;
    order_details: string | null;
    total_qty: number | null;
    page: string | null;
    platform: string | null;
    tracking_number: string | null;
    parcel_status: string | null;
    order_status: string | null;
    encoded_date: string | null;
    parcel_updated_date: string | null;
    shipped_out_date: string | null;
    date_added: string | null;
    price_upsell: string | null;
    intern_brands_name: string | null;
    total_cog: string | null;
}

interface Summary {
    total_orders: number;
    total_qty: number;
    total_cog: number;
    total_upsell: number;
}

interface Props {
    workspace: Workspace;
    orders: PaginatedData<Order>;
    summary: Summary;
    csrs: string[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
            start_date?: string;
            end_date?: string;
            csr?: string;
        };
    };
}

const fmtDate = (d: string | null) => (d ? d.slice(0, 10) : '—');
const fmtDateTime = (d: string | null) =>
    d ? d.slice(0, 16).replace('T', ' ') : '—';
const peso = (v: number | string | null) =>
    `₱${Number(v ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;

function parseSort(sort?: string | null): { field: string; desc: boolean } {
    if (!sort) return { field: 'order_date', desc: true };
    const desc = sort.startsWith('-');
    return { field: desc ? sort.slice(1) : sort, desc };
}

function statusPill(status: string | null): string {
    const s = (status ?? '').toUpperCase();
    if (s.includes('DELIVERED'))
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400';
    if (s.includes('SHIPPED'))
        return 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400';
    if (s.includes('RETURN') || s.includes('CANCEL'))
        return 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400';
    if (s.includes('CONFIRMED'))
        return 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400';
    return 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400';
}

export default function DailySalesTrackerIndex({
    workspace,
    orders,
    summary,
    csrs,
    query,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/gencys/daily-sales-tracker`;

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [dateRange, setDateRange] = useState<string[]>(() => [
        query?.filter?.start_date ?? '',
        query?.filter?.end_date ?? '',
    ]);
    const [csrFilter, setCsrFilter] = useState(query?.filter?.csr ?? '');
    const [expanded, setExpanded] = useState<Set<number>>(new Set());

    const sort = parseSort(query?.sort);

    const buildFilter = (search: string, range: string[], csr: string) => ({
        search: search || undefined,
        start_date: range[0] || undefined,
        end_date: range[1] || undefined,
        csr: csr || undefined,
    });

    const fetchData = useCallback(
        (overrides?: { page?: number; per_page?: number; sort?: string }) => {
            router.get(
                baseUrl,
                {
                    sort: overrides?.sort ?? query?.sort,
                    filter: buildFilter(searchValue, dateRange, csrFilter),
                    page: overrides?.page ?? 1,
                    per_page:
                        overrides?.per_page ??
                        query?.perPage ??
                        orders.per_page,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['orders', 'summary', 'query'],
                },
            );
        },
        [
            baseUrl,
            query?.sort,
            query?.perPage,
            orders.per_page,
            searchValue,
            dateRange,
            csrFilter,
        ],
    );

    const debouncedFetch = useMemo(
        () => debounce(() => fetchData(), 400),
        [fetchData],
    );

    useEffect(() => {
        const changed =
            searchValue !== (query?.filter?.search ?? '') ||
            (dateRange[0] || undefined) !==
                (query?.filter?.start_date ?? undefined) ||
            (dateRange[1] || undefined) !==
                (query?.filter?.end_date ?? undefined) ||
            csrFilter !== (query?.filter?.csr ?? '');
        if (changed) debouncedFetch();
        return () => debouncedFetch.cancel();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue, dateRange, csrFilter]);

    const toggleSort = (field: string) => {
        const desc = sort.field === field ? !sort.desc : false;
        fetchData({ sort: `${desc ? '-' : ''}${field}` });
    };

    const toggleExpand = (id: number) =>
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });

    const rows = orders.data ?? [];
    const colSpan = 12;

    const summaryCards: {
        label: string;
        value: string;
        accent: string;
        icon: ReactNode;
    }[] = [
        {
            label: 'Total Orders',
            value: summary.total_orders.toLocaleString(),
            accent: 'text-gray-800 dark:text-gray-100',
            icon: <Package className="h-4 w-4 text-gray-400" />,
        },
        {
            label: 'Total Qty',
            value: summary.total_qty.toLocaleString(),
            accent: 'text-sky-600 dark:text-sky-400',
            icon: <PackageCheck className="h-4 w-4 text-sky-500" />,
        },
        {
            label: 'Total COG',
            value: peso(summary.total_cog),
            accent: 'text-emerald-600 dark:text-emerald-400',
            icon: <Coins className="h-4 w-4 text-emerald-500" />,
        },
        {
            label: 'Total Upsell',
            value: peso(summary.total_upsell),
            accent: 'text-purple-600 dark:text-purple-400',
            icon: <TrendingUp className="h-4 w-4 text-purple-500" />,
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Daily Sales Tracker`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Daily Sales Tracker"
                    description="Daily sales orders synced from Gencys ERP."
                />

                <div className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {summaryCards.map((card) => (
                        <div
                            key={card.label}
                            className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900"
                        >
                            <div className="flex items-center gap-2 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                {card.icon}
                                <span>{card.label}</span>
                            </div>
                            <div
                                className={`mt-2 font-mono text-[20px] font-semibold ${card.accent}`}
                            >
                                {card.value}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="mb-3 flex flex-col items-stretch gap-2 md:flex-row md:items-center">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search CSR, contact, tracking, page…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            aria-label="Search daily sales orders"
                        />
                    </div>
                    <DatePicker
                        id="gencys-sales-date-range"
                        mode="range"
                        placeholder="Filter by order date"
                        defaultDate={
                            (dateRange[0] && dateRange[1]
                                ? dateRange
                                : undefined) as never as DateOption
                        }
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
                    <Select
                        value={csrFilter || 'all'}
                        onValueChange={(v) =>
                            setCsrFilter(v === 'all' ? '' : v)
                        }
                    >
                        <SelectTrigger className="h-9 w-[180px] rounded-[10px] border border-black/6 bg-stone-100 px-3 font-mono! text-[12px]! dark:border-white/6 dark:bg-zinc-800">
                            <SelectValue placeholder="All CSRs" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                value="all"
                                className="font-mono! text-[12px]!"
                            >
                                All CSRs
                            </SelectItem>
                            {csrs.map((c) => (
                                <SelectItem
                                    key={c}
                                    value={c}
                                    className="font-mono! text-[12px]!"
                                >
                                    {c}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="custom-scrollbar max-w-full overflow-x-auto">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="border-b border-black/6 dark:border-white/6">
                                    <th className="w-8 px-2 py-2.5" />
                                    <SortHeader
                                        label="Order Date"
                                        field="order_date"
                                        sort={sort}
                                        onSort={toggleSort}
                                    />
                                    <SortHeader
                                        label="CSR"
                                        field="csr"
                                        sort={sort}
                                        onSort={toggleSort}
                                    />
                                    <Th>Contact</Th>
                                    <Th>Order</Th>
                                    <SortHeader
                                        label="Qty"
                                        field="total_qty"
                                        sort={sort}
                                        onSort={toggleSort}
                                    />
                                    <SortHeader
                                        label="Page"
                                        field="page"
                                        sort={sort}
                                        onSort={toggleSort}
                                    />
                                    <Th>Tracking No.</Th>
                                    <Th>Parcel Status</Th>
                                    <Th>Order Status</Th>
                                    <SortHeader
                                        label="Shipped"
                                        field="shipped_out_date"
                                        sort={sort}
                                        onSort={toggleSort}
                                    />
                                    <SortHeader
                                        label="COG"
                                        field="total_cog"
                                        sort={sort}
                                        onSort={toggleSort}
                                    />
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={colSpan}
                                            className="py-16 text-center"
                                        >
                                            <div className="flex flex-col items-center gap-3">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-stone-100 dark:bg-zinc-800">
                                                    <Truck className="h-5 w-5 text-gray-400 dark:text-gray-500" />
                                                </div>
                                                <p className="font-mono text-[12px] font-medium text-gray-600 dark:text-gray-400">
                                                    No sales orders found
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((order) => (
                                        <OrderRow
                                            key={order.id}
                                            order={order}
                                            isOpen={expanded.has(order.id)}
                                            colSpan={colSpan}
                                            onToggle={() =>
                                                toggleExpand(order.id)
                                            }
                                        />
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex flex-col gap-3 border-t border-black/6 px-4 py-3 xl:flex-row xl:items-center xl:justify-between dark:border-white/6">
                        <div className="flex items-center gap-3">
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    Rows
                                </span>
                                <Select
                                    value={String(orders.per_page ?? 25)}
                                    onValueChange={(v) =>
                                        fetchData({
                                            per_page: Number(v),
                                            page: 1,
                                        })
                                    }
                                >
                                    <SelectTrigger className="h-7 w-[72px] rounded-lg border border-black/6 bg-stone-50 px-2.5 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="min-w-[72px]">
                                        {[25, 50, 100, 200].map((n) => (
                                            <SelectItem
                                                key={n}
                                                value={String(n)}
                                                className="font-mono! text-[11px]!"
                                            >
                                                {n}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="h-4 w-px bg-black/6 dark:bg-white/6" />
                            <p className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                Showing {orders.from ?? 0} to {orders.to ?? 0}{' '}
                                of {(orders.total ?? 0).toLocaleString()}{' '}
                                entries
                            </p>
                        </div>
                        <Pagination
                            currentPage={orders.current_page ?? 1}
                            totalPages={orders.last_page ?? 1}
                            onPageChange={(page) => fetchData({ page })}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function Th({ children }: { children: ReactNode }) {
    return (
        <th className="px-4 py-2.5 text-left font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
            {children}
        </th>
    );
}

interface SortHeaderProps {
    label: string;
    field: string;
    sort: { field: string; desc: boolean };
    onSort: (field: string) => void;
}

function SortHeader({ label, field, sort, onSort }: SortHeaderProps) {
    const active = sort.field === field;
    return (
        <th className="px-4 py-2.5 text-left">
            <button
                onClick={() => onSort(field)}
                className="group inline-flex items-center gap-1 font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase transition-colors hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400"
            >
                {label}
                <ChevronDown
                    className={cn(
                        'h-3 w-3 transition-transform',
                        active ? 'text-emerald-500' : 'opacity-30',
                        active && !sort.desc && 'rotate-180',
                    )}
                />
            </button>
        </th>
    );
}

interface OrderRowProps {
    order: Order;
    isOpen: boolean;
    colSpan: number;
    onToggle: () => void;
}

function OrderRow({ order, isOpen, colSpan, onToggle }: OrderRowProps) {
    const detail: { label: string; value: ReactNode }[] = [
        { label: 'Platform', value: order.platform || '—' },
        { label: 'Verifier', value: order.verifier_name || '—' },
        { label: 'Upsell By', value: order.upsell_by || '—' },
        {
            label: 'Upsell Price',
            value: order.price_upsell ? peso(order.price_upsell) : '—',
        },
        { label: 'Encoded Date', value: fmtDateTime(order.encoded_date) },
        {
            label: 'Parcel Updated',
            value: fmtDateTime(order.parcel_updated_date),
        },
        { label: 'Date Added', value: fmtDateTime(order.date_added) },
        { label: 'Intern & Brands', value: order.intern_brands_name || '—' },
    ];

    return (
        <>
            <tr className="border-b border-black/6 transition-colors hover:bg-emerald-500/3 dark:border-white/6">
                <td className="px-2 py-3 align-middle">
                    <button
                        onClick={onToggle}
                        aria-expanded={isOpen}
                        aria-label={
                            isOpen ? 'Collapse detail' : 'Expand detail'
                        }
                        className="flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-stone-100 hover:text-gray-600 dark:hover:bg-zinc-800"
                    >
                        {isOpen ? (
                            <ChevronDown className="h-3.5 w-3.5" />
                        ) : (
                            <ChevronRight className="h-3.5 w-3.5" />
                        )}
                    </button>
                </td>
                <td className="px-4 py-3 align-middle font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {fmtDateTime(order.order_date)}
                </td>
                <td className="px-4 py-3 align-middle text-[12px] font-medium text-gray-800 dark:text-gray-200">
                    {order.csr || '—'}
                </td>
                <td className="px-4 py-3 align-middle font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {order.contact || '—'}
                </td>
                <td className="max-w-[260px] px-4 py-3 align-middle">
                    <span className="block truncate text-[12px] text-gray-700 dark:text-gray-300">
                        {order.order_details || '—'}
                    </span>
                </td>
                <td className="px-4 py-3 text-right align-middle font-mono text-[12px] text-gray-700 dark:text-gray-300">
                    {order.total_qty?.toLocaleString() ?? '—'}
                </td>
                <td className="max-w-[180px] px-4 py-3 align-middle">
                    <span className="block truncate font-mono text-[11px] text-gray-600 dark:text-gray-400">
                        {order.page || '—'}
                    </span>
                </td>
                <td className="px-4 py-3 align-middle font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {order.tracking_number || '—'}
                </td>
                <td className="px-4 py-3 align-middle">
                    {order.parcel_status ? (
                        <span
                            className={cn(
                                'inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-medium',
                                statusPill(order.parcel_status),
                            )}
                        >
                            {order.parcel_status}
                        </span>
                    ) : (
                        <span className="text-gray-400">—</span>
                    )}
                </td>
                <td className="px-4 py-3 align-middle">
                    {order.order_status ? (
                        <span
                            className={cn(
                                'inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-medium',
                                statusPill(order.order_status),
                            )}
                        >
                            {order.order_status}
                        </span>
                    ) : (
                        <span className="text-gray-400">—</span>
                    )}
                </td>
                <td className="px-4 py-3 align-middle font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {fmtDate(order.shipped_out_date)}
                </td>
                <td className="px-4 py-3 text-right align-middle font-mono text-[12px] font-semibold text-emerald-700 dark:text-emerald-400">
                    {peso(order.total_cog)}
                </td>
            </tr>

            {isOpen && (
                <tr className="bg-stone-50/60 dark:bg-zinc-950/40">
                    <td colSpan={colSpan} className="px-4 py-4">
                        <div className="grid grid-cols-2 gap-x-6 gap-y-2 rounded-[12px] border border-black/6 bg-white p-4 sm:grid-cols-4 dark:border-white/6 dark:bg-zinc-900">
                            {detail.map((d) => (
                                <div key={d.label}>
                                    <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                        {d.label}
                                    </p>
                                    <p className="mt-0.5 font-mono text-[12px] text-gray-700 dark:text-gray-300">
                                        {d.value}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </td>
                </tr>
            )}
        </>
    );
}
