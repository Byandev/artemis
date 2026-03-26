import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { cn, currencyFormatter, percentageFormatter } from '@/lib/utils';
import { PaginatedData } from '@/types';
import {
    ORDER_STATUSES,
    OrderForDelivery,
    getStatusBadgeClass,
} from '@/types/models/Pancake/OrderForDelivery';
import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    Check,
    ChevronDown,
    Loader2,
    MapPin,
    Phone,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { omit } from 'lodash';
import { toFrontendSort } from '@/lib/sort';
import workspaces from '@/routes/workspaces';


interface Props {
    orders: PaginatedData<OrderForDelivery>;
    workspace: Workspace;
    query?: {
        sort?: string | null;
        filter?: {
            search?: string;
            status?: string;
            page_id?: string;
            shop_id?: string;
        };
        page?: number;
        perPage?: number;
    };
}

const parcelStatusConfig: Record<string, { label: string; dot: string; pill: string }> = {
    delivered:        { label: 'Delivered',        dot: 'bg-emerald-500', pill: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' },
    returning:        { label: 'Returning',        dot: 'bg-orange-400',  pill: 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400' },
    returned:         { label: 'Returned',         dot: 'bg-red-500',     pill: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400' },
    undeliverable:    { label: 'Undeliverable',    dot: 'bg-rose-500',    pill: 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' },
    on_the_way:       { label: 'On the Way',       dot: 'bg-blue-500',    pill: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' },
    out_for_delivery: { label: 'Out for Delivery', dot: 'bg-violet-500',  pill: 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400' },
};

const parcelStatusOptions = Object.keys(parcelStatusConfig);

const orderStatusConfig: Record<string, { dot: string; text: string }> = {
    'PENDING':            { dot: 'bg-yellow-400',  text: 'text-yellow-700 dark:text-yellow-400' },
    'DELIVERED':          { dot: 'bg-emerald-500', text: 'text-emerald-700 dark:text-emerald-400' },
    'RIDER OTW':          { dot: 'bg-blue-500',    text: 'text-blue-700 dark:text-blue-400' },
    'RETURNING':          { dot: 'bg-orange-400',  text: 'text-orange-700 dark:text-orange-400' },
    'RESCHEDULED':        { dot: 'bg-purple-400',  text: 'text-purple-700 dark:text-purple-400' },
    'CX CBR':             { dot: 'bg-red-500',     text: 'text-red-700 dark:text-red-400' },
    'RIDER CBR':          { dot: 'bg-red-500',     text: 'text-red-700 dark:text-red-400' },
    'CANCELLED':          { dot: 'bg-gray-400',    text: 'text-gray-500 dark:text-gray-400' },
    'WRONG SEGMENT CODE': { dot: 'bg-rose-500',    text: 'text-rose-700 dark:text-rose-400' },
    'CX RINGING':         { dot: 'bg-amber-400',   text: 'text-amber-700 dark:text-amber-400' },
    'RIDER RINGING':      { dot: 'bg-amber-400',   text: 'text-amber-700 dark:text-amber-400' },
    'IN TRANSIT':         { dot: 'bg-cyan-500',    text: 'text-cyan-700 dark:text-cyan-400' },
};

export default function RmoManagement({ orders, workspace, query }: Props) {
    const [isLoadingID, setIsLoadingID] = useState<number | null>(null);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [selectedStatus, setSelectedStatus] = useState<string>(query?.filter?.status ?? '');
    const [selectedPageId, setSelectedPageId] = useState<string>(query?.filter?.page_id ?? '');
    const [selectedParcelStatus, setSelectedParcelStatus] = useState<string>('');

    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const uniquePages = useMemo(() => {
        const pagesMap = new Map();
        orders.data?.forEach(order => {
            if (order.page?.id && order.page?.name) {
                pagesMap.set(order.page.id, order.page.name);
            }
        });
        return Array.from(pagesMap.entries()).map(([id, name]) => ({ id: String(id), name }));
    }, [orders.data]);

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                workspaces.rts.rmoManagement({ workspace }),
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    'filter[status]': selectedStatus || undefined,
                    'filter[page_id]': selectedPageId || undefined,
                    'filter[parcel_status]': selectedParcelStatus || undefined,
                    'filter[shop_id]': query?.filter?.shop_id || undefined,
                    page: (searchValue || selectedStatus || selectedPageId || selectedParcelStatus) ? 1 : (query?.page ?? 1),
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['orders'],
                },
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue, selectedStatus, selectedPageId, selectedParcelStatus, query?.sort, query?.filter?.shop_id]);

    const handleChangeStatus = (status: string, orderId: number) => {
        router.post(
            `/workspaces/${workspace.slug}/rts/rmo-management/${orderId}`,
            { status },
            {
                preserveScroll: true,
                onStart: () => setIsLoadingID(orderId),
                onFinish: () => setIsLoadingID(null),
            },
        );
    };

    const hasActiveFilters = !!(selectedStatus || selectedPageId || selectedParcelStatus || searchValue);

    const clearFilters = () => {
        setSearchValue('');
        setSelectedStatus('');
        setSelectedPageId('');
        setSelectedParcelStatus('');
    };

    const columns: ColumnDef<OrderForDelivery>[] = [
        {
            accessorKey: 'order_number',
            header: ({ column }) => (
                <SortableHeader column={column} title="Order #" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                    {row.original.order.order_number}
                </span>
            ),
        },
        {
            accessorFn: (row) => (row.order.items ?? []).map((item) => item.name).join(', '),
            id: 'items',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader enabled={false} column={column} title="Items" />
            ),
            cell: ({ row }) => {
                const items = row.original.order.items ?? [];
                const pageName = row.original.page?.name;
                return (
                    <div className="space-y-0.5">
                        {items.map((item, i) => (
                            <p key={i} className="text-[12px] font-medium text-gray-800 dark:text-gray-200 leading-snug">
                                {item.name}
                            </p>
                        ))}
                        {pageName && (
                            <p className="text-[11px] text-gray-400 dark:text-gray-500 mt-1">{pageName}</p>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'order.tracking_code',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Tracking #" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                    {row.original.order.tracking_code ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'order.parcel_status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="J&T Status" />
            ),
            cell: ({ row }) => {
                const key = row.original.order.parcel_status?.toLowerCase();
                const cfg = parcelStatusConfig[key];
                return (
                    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium ${cfg?.pill ?? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'}`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${cfg?.dot ?? 'bg-gray-400'}`} />
                        {cfg?.label ?? key ?? '—'}
                    </span>
                );
            },
        },
        {
            accessorKey: 'rider_name',
            header: ({ column }) => (
                <SortableHeader column={column} title="Rider" />
            ),
            cell: ({ row }) => (
                <div className="space-y-0.5">
                    <p className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                        {row.original.rider_name || '—'}
                    </p>
                    {row.original.rider_phone && (
                        <p className="flex items-center gap-1 text-[11px] text-gray-400 dark:text-gray-500">
                            <Phone className="h-3 w-3 shrink-0" />
                            {row.original.rider_phone}
                        </p>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'order.shipping_address.full_name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Customer" />
            ),
            cell: ({ row }) => {
                const addr = row.original.order.shipping_address;
                return (
                    <div className="space-y-0.5">
                        <p className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                            {addr?.full_name || '—'}
                        </p>
                        {addr?.phone_number && (
                            <p className="flex items-center gap-1 text-[11px] text-gray-400 dark:text-gray-500">
                                <Phone className="h-3 w-3 shrink-0" />
                                {addr.phone_number}
                            </p>
                        )}
                        {addr?.full_address && (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <p className="flex max-w-[160px] items-center gap-1 text-[11px] text-gray-400 dark:text-gray-500 cursor-default">
                                        <MapPin className="h-3 w-3 shrink-0" />
                                        <span className="truncate">{addr.full_address}</span>
                                    </p>
                                </TooltipTrigger>
                                <TooltipContent side="bottom">
                                    <p className="max-w-xs text-xs">{addr.full_address}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'order.shipping_address.city_order_summary.rts_rate',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Loc. RTS" />
            ),
            cell: ({ row }) => {
                const rate = row.original.order?.shipping_address?.city_order_summary?.rts_rate ?? 0;
                const isHigh = rate >= 0.4;
                return (
                    <span className={`text-[12px] font-medium tabular-nums ${isHigh ? 'text-red-500 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'}`}>
                        {percentageFormatter(rate)}
                    </span>
                );
            },
        },
        {
            accessorKey: 'order.final_amount',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="SRP" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300 tabular-nums">
                    {currencyFormatter(row.original.order.final_amount)}
                </span>
            ),
        },
        {
            accessorKey: 'order.delivery_attempts',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Attempts" />
            ),
            cell: ({ row }) => {
                const attempts = row.original.order.delivery_attempts ?? 0;
                const isMultiple = attempts > 1;
                return (
                    <span className={`inline-flex items-center justify-center rounded-full px-2 py-0.5 text-[11px] font-medium tabular-nums ${
                        isMultiple
                            ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'
                            : 'text-gray-500 dark:text-gray-400'
                    }`}>
                        {attempts}
                    </span>
                );
            },
        },
        {
            accessorKey: 'conferrer.name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Confirmed By" />
            ),
            cell: ({ row }) => (
                <span className="text-[12px] text-gray-600 dark:text-gray-400">
                    {row.original.conferrer?.name ?? <span className="italic text-gray-300 dark:text-gray-600">—</span>}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => {
                const orderId = row.original.order_id;
                const currentStatus = row.original.status;
                const isLoading = isLoadingID === orderId;

                if (isLoading) {
                    return (
                        <div className="flex items-center gap-2 rounded-lg border border-black/6 dark:border-white/6 bg-stone-50 dark:bg-zinc-800/50 px-2.5 py-1.5 text-[12px] text-gray-400 dark:text-gray-500">
                            <Loader2 className="h-3 w-3 animate-spin" />
                            Updating…
                        </div>
                    );
                }

                const cfg = orderStatusConfig[currentStatus];

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger className="group flex items-center gap-2 rounded-lg border border-black/8 dark:border-white/8 bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-[12px] font-medium text-gray-700 dark:text-gray-200 shadow-xs outline-none transition-all hover:border-black/15 dark:hover:border-white/15 hover:shadow-sm">
                            <span className={`h-2 w-2 shrink-0 rounded-full ${cfg?.dot ?? 'bg-gray-400'}`} />
                            <span className="max-w-[110px] truncate">{currentStatus}</span>
                            <ChevronDown className="h-3 w-3 shrink-0 text-gray-400 transition-colors group-hover:text-gray-600 dark:group-hover:text-gray-300" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-52 overflow-hidden p-1">
                            <p className="px-2 pb-1.5 pt-1 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Change status
                            </p>
                            <div className="max-h-72 overflow-y-auto">
                                {ORDER_STATUSES.map((s) => {
                                    const sCfg = orderStatusConfig[s];
                                    const isActive = s === currentStatus;
                                    return (
                                        <DropdownMenuItem
                                            key={s}
                                            onClick={() => handleChangeStatus(s, orderId)}
                                            className={cn(
                                                'flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-[12px]',
                                                isActive
                                                    ? 'bg-gray-50 dark:bg-zinc-800'
                                                    : 'text-gray-600 dark:text-gray-400',
                                            )}
                                        >
                                            <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${sCfg?.dot ?? 'bg-gray-400'}`} />
                                            <span className={cn('flex-1', isActive ? `font-semibold ${sCfg?.text ?? ''}` : '')}>
                                                {s}
                                            </span>
                                            {isActive && <Check className="h-3 w-3 text-emerald-500" />}
                                        </DropdownMenuItem>
                                    );
                                })}
                            </div>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="RMO Management"
                    description="Track and update delivery status for items out today"
                />

                <div className="mb-3 flex flex-wrap items-center gap-2">
                    {/* Search */}
                    <div className="relative w-64">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            className="h-9 w-full rounded-[10px] border border-black/6 dark:border-white/6 bg-stone-100 dark:bg-zinc-800 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-600 outline-none transition-all focus:border-emerald-500 dark:focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/15"
                            placeholder="Search orders…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>

                    {/* Divider */}
                    <div className="h-5 w-px bg-black/8 dark:bg-white/8" />

                    {/* Filter group */}
                    <div className="flex items-center gap-1.5">
                        {/* Page filter */}
                        <DropdownMenu>
                            <DropdownMenuTrigger className={cn(
                                'flex h-8 items-center gap-2 rounded-lg border px-2.5 text-[12px] font-medium outline-none transition-all',
                                selectedPageId
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400'
                                    : 'border-black/6 bg-stone-100 text-gray-600 hover:border-black/12 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:border-white/12',
                            )}>
                                <span className={cn('text-[11px] font-normal', selectedPageId ? 'text-emerald-500 dark:text-emerald-500' : 'text-gray-400 dark:text-gray-500')}>
                                    Page
                                </span>
                                <span className={cn('h-3 w-px', selectedPageId ? 'bg-emerald-200 dark:bg-emerald-500/30' : 'bg-gray-200 dark:bg-gray-700')} />
                                <span className="max-w-[120px] truncate">
                                    {uniquePages.find((p) => p.id === selectedPageId)?.name || 'All'}
                                </span>
                                <ChevronDown className="h-3 w-3 opacity-40" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="max-h-72 overflow-y-auto">
                                <DropdownMenuItem onClick={() => setSelectedPageId('')}>All Pages</DropdownMenuItem>
                                {uniquePages.map((page) => (
                                    <DropdownMenuItem key={page.id} onClick={() => setSelectedPageId(page.id)}>
                                        {page.name}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>

                        {/* J&T Status filter */}
                        <DropdownMenu>
                            <DropdownMenuTrigger className={cn(
                                'flex h-8 items-center gap-2 rounded-lg border px-2.5 text-[12px] font-medium outline-none transition-all',
                                selectedParcelStatus
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400'
                                    : 'border-black/6 bg-stone-100 text-gray-600 hover:border-black/12 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:border-white/12',
                            )}>
                                <span className={cn('text-[11px] font-normal', selectedParcelStatus ? 'text-emerald-500 dark:text-emerald-500' : 'text-gray-400 dark:text-gray-500')}>
                                    J&amp;T
                                </span>
                                <span className={cn('h-3 w-px', selectedParcelStatus ? 'bg-emerald-200 dark:bg-emerald-500/30' : 'bg-gray-200 dark:bg-gray-700')} />
                                <span>{selectedParcelStatus ? parcelStatusConfig[selectedParcelStatus]?.label : 'All'}</span>
                                <ChevronDown className="h-3 w-3 opacity-40" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="max-h-72 overflow-y-auto">
                                <DropdownMenuItem onClick={() => setSelectedParcelStatus('')}>All Status</DropdownMenuItem>
                                {parcelStatusOptions.map((s) => {
                                    const cfg = parcelStatusConfig[s];
                                    return (
                                        <DropdownMenuItem key={s} onClick={() => setSelectedParcelStatus(s)}>
                                            <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium ${cfg.pill}`}>
                                                <span className={`h-1.5 w-1.5 rounded-full ${cfg.dot}`} />
                                                {cfg.label}
                                            </span>
                                        </DropdownMenuItem>
                                    );
                                })}
                            </DropdownMenuContent>
                        </DropdownMenu>

                        {/* Order status filter */}
                        <DropdownMenu>
                            <DropdownMenuTrigger className={cn(
                                'flex h-8 items-center gap-2 rounded-lg border px-2.5 text-[12px] font-medium outline-none transition-all',
                                selectedStatus
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400'
                                    : 'border-black/6 bg-stone-100 text-gray-600 hover:border-black/12 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:border-white/12',
                            )}>
                                <span className={cn('text-[11px] font-normal', selectedStatus ? 'text-emerald-500 dark:text-emerald-500' : 'text-gray-400 dark:text-gray-500')}>
                                    Status
                                </span>
                                <span className={cn('h-3 w-px', selectedStatus ? 'bg-emerald-200 dark:bg-emerald-500/30' : 'bg-gray-200 dark:bg-gray-700')} />
                                <span>{selectedStatus || 'All'}</span>
                                <ChevronDown className="h-3 w-3 opacity-40" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="max-h-72 overflow-y-auto">
                                <DropdownMenuItem onClick={() => setSelectedStatus('')}>All Status</DropdownMenuItem>
                                {ORDER_STATUSES.map((s) => (
                                    <DropdownMenuItem key={s} onClick={() => setSelectedStatus(s)}>
                                        <span className={getStatusBadgeClass(s)}>{s}</span>
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    {/* Clear filters */}
                    {hasActiveFilters && (
                        <button
                            onClick={clearFilters}
                            className="flex h-8 items-center gap-1.5 rounded-lg border border-black/6 dark:border-white/6 px-2.5 text-[12px] font-medium text-gray-400 dark:text-gray-500 transition-all hover:border-red-200 hover:bg-red-50 hover:text-red-500 dark:hover:border-red-500/20 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                        >
                            <X className="h-3.5 w-3.5" />
                            Clear
                        </button>
                    )}
                </div>

                <div className="rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={orders.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(orders, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                workspaces.rts.rmoManagement({ workspace }),
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    'filter[status]': selectedStatus || undefined,
                                    'filter[page_id]': selectedPageId || undefined,
                                    'filter[parcel_status]': selectedParcelStatus || undefined,
                                    'filter[shop_id]': query?.filter?.shop_id || undefined,
                                    page: params?.page ?? 1,
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
