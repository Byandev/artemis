import PageHeader from '@/components/common/PageHeader';
import DatePicker from '@/components/ui/date-picker';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    Coins,
    Columns3,
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
import DailySalesFilters from './daily-sales-filters';
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
    platforms: string[];
    parcelStatuses: string[];
    orderStatuses: string[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
            start_date?: string;
            end_date?: string;
            csr?: string[];
            platform?: string[];
            parcel_status?: string[];
            order_status?: string[];
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

const StatusPill = ({ value }: { value: string | null }) =>
    value ? (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-medium whitespace-nowrap',
                statusPill(value),
            )}
        >
            <span className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
            {value}
        </span>
    ) : (
        <span className="text-gray-400">—</span>
    );

interface ColumnDef {
    key: string;
    label: string;
    sortable: boolean;
    align?: 'left' | 'right';
    defaultVisible?: boolean;
    render: (o: Order) => ReactNode;
}

const baseCell =
    'px-4 py-3 align-middle font-mono text-[11px] text-gray-600 dark:text-gray-400';

const COLUMNS: ColumnDef[] = [
    {
        key: 'order_date',
        label: 'Order Date',
        sortable: true,
        render: (o) => fmtDateTime(o.order_date),
    },
    {
        key: 'csr',
        label: 'CSR',
        sortable: true,
        render: (o) => (
            <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                {o.csr || '—'}
            </span>
        ),
    },
    {
        key: 'verifier_name',
        label: 'Verifier',
        sortable: true,
        defaultVisible: false,
        render: (o) => o.verifier_name || '—',
    },
    {
        key: 'upsell_by',
        label: 'Upsell By',
        sortable: true,
        defaultVisible: false,
        render: (o) => o.upsell_by || '—',
    },
    {
        key: 'contact',
        label: 'Contact',
        sortable: true,
        render: (o) => o.contact || '—',
    },
    {
        key: 'order_details',
        label: 'Order',
        sortable: true,
        render: (o) => (
            <span className="block max-w-[260px] truncate text-[12px] text-gray-700 dark:text-gray-300">
                {o.order_details || '—'}
            </span>
        ),
    },
    {
        key: 'total_qty',
        label: 'Qty',
        sortable: true,
        align: 'right',
        render: (o) => (
            <span className="text-[12px] text-gray-700 dark:text-gray-300">
                {o.total_qty?.toLocaleString() ?? '—'}
            </span>
        ),
    },
    {
        key: 'page',
        label: 'Page',
        sortable: true,
        render: (o) => (
            <span className="block max-w-[180px] truncate">
                {o.page || '—'}
            </span>
        ),
    },
    {
        key: 'platform',
        label: 'Platform',
        sortable: true,
        render: (o) => o.platform || '—',
    },
    {
        key: 'tracking_number',
        label: 'Tracking No.',
        sortable: true,
        render: (o) => o.tracking_number || '—',
    },
    {
        key: 'parcel_status',
        label: 'Parcel Status',
        sortable: true,
        render: (o) => <StatusPill value={o.parcel_status} />,
    },
    {
        key: 'order_status',
        label: 'Order Status',
        sortable: true,
        render: (o) => <StatusPill value={o.order_status} />,
    },
    {
        key: 'encoded_date',
        label: 'Encoded',
        sortable: true,
        defaultVisible: false,
        render: (o) => fmtDateTime(o.encoded_date),
    },
    {
        key: 'parcel_updated_date',
        label: 'Parcel Updated',
        sortable: true,
        defaultVisible: false,
        render: (o) => fmtDateTime(o.parcel_updated_date),
    },
    {
        key: 'shipped_out_date',
        label: 'Shipped',
        sortable: true,
        render: (o) => fmtDate(o.shipped_out_date),
    },
    {
        key: 'date_added',
        label: 'Date Added',
        sortable: true,
        defaultVisible: false,
        render: (o) => fmtDateTime(o.date_added),
    },
    {
        key: 'price_upsell',
        label: 'Upsell ₱',
        sortable: true,
        align: 'right',
        defaultVisible: false,
        render: (o) => (o.price_upsell ? peso(o.price_upsell) : '—'),
    },
    {
        key: 'intern_brands_name',
        label: 'Intern & Brands',
        sortable: true,
        defaultVisible: false,
        render: (o) => o.intern_brands_name || '—',
    },
    {
        key: 'total_cog',
        label: 'COG',
        sortable: true,
        align: 'right',
        render: (o) => (
            <span className="text-[12px] font-semibold text-emerald-700 dark:text-emerald-400">
                {peso(o.total_cog)}
            </span>
        ),
    },
];

const STORAGE_KEY = 'gencys-dst-visible-columns';

function loadVisible(): Record<string, boolean> {
    const defaults = Object.fromEntries(
        COLUMNS.map((c) => [c.key, c.defaultVisible !== false]),
    );
    if (typeof window === 'undefined') return defaults;
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}');
        return { ...defaults, ...saved };
    } catch {
        return defaults;
    }
}

export default function DailySalesTrackerIndex({
    workspace,
    orders,
    summary,
    csrs,
    platforms,
    parcelStatuses,
    orderStatuses,
    query,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/gencys/daily-sales-tracker`;

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [dateRange, setDateRange] = useState<string[]>(() => [
        query?.filter?.start_date ?? '',
        query?.filter?.end_date ?? '',
    ]);
    const [csrFilter, setCsrFilter] = useState<string[]>(
        query?.filter?.csr ?? [],
    );
    const [platformFilter, setPlatformFilter] = useState<string[]>(
        query?.filter?.platform ?? [],
    );
    const [parcelStatusFilter, setParcelStatusFilter] = useState<string[]>(
        query?.filter?.parcel_status ?? [],
    );
    const [orderStatusFilter, setOrderStatusFilter] = useState<string[]>(
        query?.filter?.order_status ?? [],
    );
    const [visible, setVisible] =
        useState<Record<string, boolean>>(loadVisible);

    useEffect(() => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(visible));
        } catch {
            /* ignore quota / private-mode errors */
        }
    }, [visible]);

    const visibleColumns = useMemo(
        () => COLUMNS.filter((c) => visible[c.key] !== false),
        [visible],
    );

    const sort = parseSort(query?.sort);

    const arr = (v: string[]) => (v.length ? v : undefined);

    const buildFilter = () => ({
        search: searchValue || undefined,
        start_date: dateRange[0] || undefined,
        end_date: dateRange[1] || undefined,
        csr: arr(csrFilter),
        platform: arr(platformFilter),
        parcel_status: arr(parcelStatusFilter),
        order_status: arr(orderStatusFilter),
    });

    const fetchData = useCallback(
        (overrides?: { page?: number; per_page?: number; sort?: string }) => {
            router.get(
                baseUrl,
                {
                    sort: overrides?.sort ?? query?.sort,
                    filter: buildFilter(),
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
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [
            baseUrl,
            query?.sort,
            query?.perPage,
            orders.per_page,
            searchValue,
            dateRange,
            csrFilter,
            platformFilter,
            parcelStatusFilter,
            orderStatusFilter,
        ],
    );

    const debouncedFetch = useMemo(
        () => debounce(() => fetchData(), 400),
        [fetchData],
    );

    useEffect(() => {
        const f = query?.filter ?? {};
        const serverSig = JSON.stringify({
            search: f.search || undefined,
            start_date: f.start_date || undefined,
            end_date: f.end_date || undefined,
            csr: arr(f.csr ?? []),
            platform: arr(f.platform ?? []),
            parcel_status: arr(f.parcel_status ?? []),
            order_status: arr(f.order_status ?? []),
        });
        if (JSON.stringify(buildFilter()) !== serverSig) debouncedFetch();
        return () => debouncedFetch.cancel();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        searchValue,
        dateRange,
        csrFilter,
        platformFilter,
        parcelStatusFilter,
        orderStatusFilter,
    ]);

    const toggleSort = (field: string) => {
        const desc = sort.field === field ? !sort.desc : false;
        fetchData({ sort: `${desc ? '-' : ''}${field}` });
    };

    const rows = orders.data ?? [];

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

                <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <div className="relative w-full sm:w-64">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search CSR, contact, tracking…"
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

                    <div className="flex items-center gap-2 sm:ml-auto">
                        <DailySalesFilters
                            value={{
                                csr: csrFilter,
                                platform: platformFilter,
                                parcel_status: parcelStatusFilter,
                                order_status: orderStatusFilter,
                            }}
                            csrs={csrs}
                            platforms={platforms}
                            parcelStatuses={parcelStatuses}
                            orderStatuses={orderStatuses}
                            onApply={(v) => {
                                setCsrFilter(v.csr);
                                setPlatformFilter(v.platform);
                                setParcelStatusFilter(v.parcel_status);
                                setOrderStatusFilter(v.order_status);
                            }}
                        />

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="flex h-9 items-center gap-1.5 rounded-[10px] border border-black/8 bg-white px-3 font-mono text-[12px] text-gray-700 shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] transition-colors hover:border-black/14 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-200 dark:shadow-none dark:hover:border-white/14">
                                    <Columns3 className="h-3.5 w-3.5" />
                                    Columns
                                    <ChevronDown className="h-3 w-3 opacity-50" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                className="max-h-[60vh] w-52 overflow-y-auto"
                            >
                                <DropdownMenuLabel className="font-mono text-[10px] tracking-wider uppercase">
                                    Toggle columns
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                {COLUMNS.map((c) => (
                                    <DropdownMenuCheckboxItem
                                        key={c.key}
                                        checked={visible[c.key] !== false}
                                        onCheckedChange={(checked) =>
                                            setVisible((prev) => ({
                                                ...prev,
                                                [c.key]: !!checked,
                                            }))
                                        }
                                        onSelect={(e) => e.preventDefault()}
                                        className="font-mono text-[12px]"
                                    >
                                        {c.label}
                                    </DropdownMenuCheckboxItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="custom-scrollbar max-w-full overflow-x-auto">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="border-b border-black/6 dark:border-white/6">
                                    {visibleColumns.map((c) =>
                                        c.sortable ? (
                                            <SortHeader
                                                key={c.key}
                                                label={c.label}
                                                field={c.key}
                                                align={c.align}
                                                sort={sort}
                                                onSort={toggleSort}
                                            />
                                        ) : (
                                            <Th key={c.key} align={c.align}>
                                                {c.label}
                                            </Th>
                                        ),
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={visibleColumns.length || 1}
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
                                        <tr
                                            key={order.id}
                                            className="border-b border-black/6 transition-colors hover:bg-emerald-500/3 dark:border-white/6"
                                        >
                                            {visibleColumns.map((c) => (
                                                <td
                                                    key={c.key}
                                                    className={cn(
                                                        baseCell,
                                                        c.align === 'right' &&
                                                            'text-right',
                                                    )}
                                                >
                                                    {c.render(order)}
                                                </td>
                                            ))}
                                        </tr>
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

function Th({
    children,
    align,
}: {
    children: ReactNode;
    align?: 'left' | 'right';
}) {
    return (
        <th
            className={cn(
                'px-4 py-2.5 font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600',
                align === 'right' ? 'text-right' : 'text-left',
            )}
        >
            {children}
        </th>
    );
}

interface SortHeaderProps {
    label: string;
    field: string;
    align?: 'left' | 'right';
    sort: { field: string; desc: boolean };
    onSort: (field: string) => void;
}

function SortHeader({ label, field, align, sort, onSort }: SortHeaderProps) {
    const active = sort.field === field;
    return (
        <th
            className={cn(
                'px-4 py-2.5',
                align === 'right' ? 'text-right' : 'text-left',
            )}
        >
            <button
                onClick={() => onSort(field)}
                className={cn(
                    'group inline-flex items-center gap-1 font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase transition-colors hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400',
                    align === 'right' && 'flex-row-reverse',
                )}
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
