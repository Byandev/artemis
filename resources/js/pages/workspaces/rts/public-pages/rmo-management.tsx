import Filters, { FilterValue } from '@/components/filters/Filters';
import { authParcelStatusConfig, orderStatusConfig, ParcelStatusEntry } from '@/components/rts/rmo-config';
import { RmoStatCards } from '@/components/rts/RmoStatCards';
import { RmoStatusPicker } from '@/components/rts/RmoStatusPicker';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { currencyFormatter, percentageFormatter } from '@/lib/utils';
import { toFrontendSort } from '@/lib/sort';
import publicPage from '@/routes/public-page';
import { PaginatedData } from '@/types';
import { OrderForDelivery, OrderStatus } from '@/types/models/Pancake/OrderForDelivery';
import { User } from '@/types/models/Pancake/User';
import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import {
    BarChart3,
    ChevronDown,
    ChevronUp,
    MapPin,
    Phone,
    Search,
    User as UserIcon,
    UserPlus,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import FormModal from './formModal';
import workspaces from '@/routes/workspaces';

interface Props {
    orders: PaginatedData<OrderForDelivery>;
    workspace: Workspace;
    query?: {
        sort?: string | null;
        filter?: {
            search?: string;
            status?: string | string[];
            page_id?: string | string[];
            shop_id?: string | string[];
            user_id?: string | string[];
            parcel_status?: string | string[];
        };
        page?: number;
        perPage?: number;
    };
    users: User[];
    total_for_delivery_today: number;
    called_rate: number;
    successful_rate: number;
    unsuccessful_rate: number;
}

export default function RmoManagement({
    orders,
    workspace,
    query,
    users,
    total_for_delivery_today,
    called_rate,
    successful_rate,
    unsuccessful_rate,
}: Props) {
    const [userName, setUserName] = useState<string | false>(false);
    const [isOpen, setIsOpen] = useState(false);
    const [showStats, setShowStats] = useState(() => localStorage.getItem('rmo_show_stats') === 'true');
    const [pendingAssign, setPendingAssign] = useState<{ orderId: number; currentStatus: string } | null>(null);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    const [selectedPageIds, setSelectedPageIds] = useState<string[]>(() =>
        query?.filter?.page_id
            ? (Array.isArray(query.filter.page_id) ? query.filter.page_id : query.filter.page_id.split(',').filter(Boolean))
            : []
    );

    const [selectedShopIds, setSelectedShopIds] = useState<string[]>(() =>
        query?.filter?.shop_id
            ? (Array.isArray(query.filter.shop_id) ? query.filter.shop_id : query.filter.shop_id.split(',').filter(Boolean))
            : []
    );

    const [selectedUserIds, setSelectedUserIds] = useState<string[]>(() =>
        query?.filter?.user_id
            ? (Array.isArray(query.filter.user_id) ? query.filter.user_id : query.filter.user_id.split(',').filter(Boolean))
            : []
    );

    // Use URL as source of truth for status (avoids stale closure issues with select)
    const currentStatus = useMemo(() => Array.isArray(query?.filter?.status)
        ? (query.filter.status[0] ?? '')
        : (query?.filter?.status ?? ''), [query?.filter?.status]);

    const currentParcelStatus = useMemo(() =>
        Array.isArray(query?.filter?.parcel_status)
            ? (query.filter.parcel_status[0] ?? '')
            : (query?.filter?.parcel_status ?? ''), [query?.filter?.parcel_status]
    );

    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);

    const initialFilterValue = useMemo<FilterValue>(
        () => ({
            teamIds: [],
            productIds: [],
            shopIds: selectedShopIds.map(Number),
            pageIds: selectedPageIds.map(Number),
            userIds: selectedUserIds.map(Number),
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    const handleFilterChange = useCallback((value: FilterValue) => {
        const newPageIds = value.pageIds.map(String);
        const newShopIds = value.shopIds.map(String);
        const newUserIds = value.userIds.map(String);

        setSelectedPageIds(newPageIds);
        setSelectedShopIds(newShopIds);
        setSelectedUserIds(newUserIds);

        router.get(
            publicPage.rmoManagement({ workspace }),
            {
                sort: query?.sort || undefined,
                'filter[search]': searchValue || undefined,
                ...(currentStatus ? { 'filter[status]': currentStatus } : {}),
                ...(currentParcelStatus ? { 'filter[parcel_status]': currentParcelStatus } : {}),
                ...(newPageIds.length ? { 'filter[page_id]': newPageIds.join(',') } : {}),
                ...(newShopIds.length ? { 'filter[shop_id]': newShopIds.join(',') } : {}),
                ...(newUserIds.length ? { 'filter[user_id]': newUserIds.join(',') } : {}),
                page: 1,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }, [workspace, query?.sort, searchValue, currentStatus, currentParcelStatus]);

    const buildAllParams = useCallback(
        (sort?: string | null, page?: number, status?: string, parcelStatus?: string) => ({
            sort: sort ?? undefined,
            'filter[search]': searchValue || undefined,
            ...(status !== undefined
                ? (status ? { 'filter[status]': status } : {})
                : (currentStatus ? { 'filter[status]': currentStatus } : {})),
            ...(parcelStatus !== undefined
                ? (parcelStatus ? { 'filter[parcel_status]': parcelStatus } : {})
                : (currentParcelStatus ? { 'filter[parcel_status]': currentParcelStatus } : {})),
            ...(selectedPageIds.length ? { 'filter[page_id]': selectedPageIds.join(',') } : {}),
            ...(selectedShopIds.length ? { 'filter[shop_id]': selectedShopIds.join(',') } : {}),
            ...(selectedUserIds.length ? { 'filter[user_id]': selectedUserIds.join(',') } : {}),
            page: page ?? 1,
        }),
        [searchValue, currentStatus, currentParcelStatus, selectedPageIds, selectedShopIds, selectedUserIds],
    );

    const handleStatusChange = useCallback(
        (status: string) => {
            router.get(
                publicPage.rmoManagement({ workspace }),
                buildAllParams(query?.sort, 1, status),
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [workspace, buildAllParams, query?.sort],
    );

    const handleParcelStatusChange = useCallback(
        (parcelStatus: string) => {
            router.get(
                publicPage.rmoManagement({ workspace }),
                buildAllParams(query?.sort, 1, undefined, parcelStatus),
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [workspace, buildAllParams, query?.sort],
    );

    useEffect(() => {
        const name = localStorage.getItem('user_name');
        if (name) setUserName(name);
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                publicPage.rmoManagement({ workspace }),
                buildAllParams(query?.sort, 1),
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 400);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue]);


    const doAssign = useCallback(
        (orderId: number, currentStatus: string, userId: string | null) => {
            const payload =
                userId === null
                    ? { status: currentStatus, removeAssignee: true }
                    : { status: currentStatus, userId };
            router.post(
                `/public/workspaces/${workspace.slug}/rts/rmo-management/${orderId}`,
                payload,
                { preserveScroll: true },
            );
        },
        [workspace.slug],
    );

    const handleChangeStatus = useCallback(
        (status: string, orderId: number) => {
            router.post(
                `/public/workspaces/${workspace.slug}/rts/rmo-management/${orderId}`,
                { status },
                { preserveScroll: true },
            );
        },
        [workspace.slug],
    );

    const handleAssignToMe = useCallback(
        (orderId: number, currentStatus: string) => {
            const userId = localStorage.getItem('user_id');
            if (userId) {
                doAssign(orderId, currentStatus, userId);
            } else {
                setPendingAssign({ orderId, currentStatus });
                setIsOpen(true);
            }
        },
        [doAssign],
    );

    const handleUserSelected = useCallback(
        (userId: string) => {
            setUserName(localStorage.getItem('user_name') ?? '');
            if (pendingAssign) {
                doAssign(pendingAssign.orderId, pendingAssign.currentStatus, userId);
                setPendingAssign(null);
            }
        },
        [pendingAssign, doAssign],
    );

    const handleRemoveAssignee = useCallback(
        (orderId: number, currentStatus: string) => doAssign(orderId, currentStatus, null),
        [doAssign],
    );

    const columns = useMemo<ColumnDef<OrderForDelivery>[]>(
        () => [
            {
                accessorFn: (row) => (row.order.items ?? []).map((item) => item.name).join(', '),
                id: 'items',
                enableSorting: false,
                header: ({ column }) => <SortableHeader enabled={false} column={column} title="Items" />,
                cell: ({ row }) => {
                    const items = row.original.order.items ?? [];
                    const trackingCode = row.original.order.tracking_code;
                    const key = (row.original.order.parcel_status ?? '').toLowerCase();
                    const cfg = authParcelStatusConfig[key] as ParcelStatusEntry | undefined;
                    return (
                        <div className="space-y-1.5">
                            <div className="space-y-0.5">
                                {items.map((item, i) => (
                                    <p key={i} className="text-[12px] font-medium leading-snug text-gray-800 dark:text-gray-200">
                                        {item.name}
                                    </p>
                                ))}
                            </div>
                            <div className="flex items-center gap-2">
                                {trackingCode && (
                                    <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                        {trackingCode}
                                    </span>
                                )}
                            </div>
                            <div>
                                {cfg ? (
                                    <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium ${cfg.pill}`}>
                                        <span className={`h-1.5 w-1.5 rounded-full ${cfg.dot}`} />
                                        {cfg.label}
                                    </span>
                                ) : key ? (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        {key}
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'rider_name',
                header: ({ column }) => <SortableHeader column={column} title="Rider" />,
                cell: ({ row }) => (
                    <div className="space-y-1.5">
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
                id: 'order_shipping_address_full_name',
                accessorKey: 'order.shipping_address.full_name',
                enableSorting: true,
                header: ({ column }) => <SortableHeader column={column} title="Customer" />,
                cell: ({ row }) => {
                    const addr = row.original.order.shipping_address;
                    return (
                        <div className="space-y-1.5">
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
                                        <p className="flex max-w-40 cursor-default items-center gap-1 text-[11px] text-gray-400 dark:text-gray-500">
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
                id: 'order_final_amount',
                accessorKey: 'order.final_amount',
                enableSorting: true,
                header: ({ column }) => <SortableHeader column={column} title="SRP" />,
                cell: ({ row }) => (
                    <span className="font-mono text-[12px] text-center tabular-nums text-gray-700 dark:text-gray-300">
                        {currencyFormatter(row.original.order.final_amount)}
                    </span>
                ),
            },
            {
                id: 'order_delivery_attempts',
                accessorKey: 'order.delivery_attempts',
                enableSorting: true,
                header: ({ column }) => <SortableHeader column={column} title="Attempts" />,
                cell: ({ row }) => {
                    const attempts = row.original.order.delivery_attempts ?? 0;
                    const isMultiple = attempts > 1;
                    return (
                        <span
                            className={`inline-flex items-center justify-center rounded-full px-2 py-0.5 text-center text-[11px] font-medium tabular-nums ${
                                isMultiple
                                    ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'
                                    : 'text-gray-500 dark:text-gray-400'
                            }`}
                        >
                            {attempts}
                        </span>
                    );
                },
            },
            {
                id: 'cx_rts_rate',
                enableSorting: true,
                header: ({ column }) => <SortableHeader column={column} title="Cx. RTS" />,
                cell: ({ row }) => {
                    const rate = row.original.order?.cx_rts_rate ?? null;
                    if (rate === null) {
                        return (
                            <span className="text-center text-[12px] text-gray-300 dark:text-gray-600">
                                —
                            </span>
                        );
                    }
                    const isHigh = rate >= 0.4;
                    return (
                        <span
                            className={`text-center text-[12px] font-medium tabular-nums ${isHigh ? 'text-red-500 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'}`}
                        >
                            {percentageFormatter(rate)}
                        </span>
                    );
                },
            },
            {
                id: 'order_shipping_address_city_order_summary_rts_rate',
                accessorKey: 'order.shipping_address.city_order_summary.rts_rate',
                enableSorting: true,
                header: ({ column }) => <SortableHeader column={column} title="Loc. RTS" />,
                cell: ({ row }) => {
                    const rate = row.original.order?.shipping_address?.city_order_summary?.rts_rate ?? 0;
                    const isHigh = rate >= 0.4;
                    return (
                        <span
                            className={`text-center text-[12px] font-medium tabular-nums ${isHigh ? 'text-red-500 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'}`}
                        >
                            {percentageFormatter(rate)}
                        </span>
                    );
                },
            },
            {
                id: 'rider_rts_rate',
                enableSorting: true,
                header: ({ column }) => <SortableHeader column={column} title="Rider RTS" />,
                cell: ({ row }) => {
                    const rate = row.original.rider_rts_rate ?? null;
                    if (rate === null) {
                        return (
                            <span className="text-center text-[12px] text-gray-300 dark:text-gray-600">
                                —
                            </span>
                        );
                    }
                    const isHigh = rate >= 0.4;
                    return (
                        <span
                            className={`text-center  text-[12px] font-medium tabular-nums ${isHigh ? 'text-red-500 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'}`}
                        >
                            {percentageFormatter(rate)}
                        </span>
                    );
                },
            },
            {
                id: 'conferrer_name',
                accessorKey: 'conferrer.name',
                enableSorting: true,
                header: ({ column }) => <SortableHeader column={column} title="Confirmed By" />,
                cell: ({ row }) => (
                    <span className="text-[12px] text-gray-600 dark:text-gray-400">
                        {row.original.conferrer?.name ?? <span className="italic text-gray-300 dark:text-gray-600">—</span>}
                    </span>
                ),
            },
            {
                accessorKey: 'assignee.name',
                enableSorting: false,
                header: ({ column }) => <SortableHeader enabled={false} column={column} title="Assignee" />,
                cell: ({ row }) => {
                    const assignee = row.original.assignee;
                    const orderId = row.original.order_id;
                    const currentStatus = row.original.status;

                    if (!assignee) {
                        return (
                            <button
                                onClick={() => handleAssignToMe(orderId, currentStatus)}
                                className="flex items-center gap-1.5 rounded-lg border border-dashed border-black/10 dark:border-white/10 px-2.5 py-1 text-[11px] font-medium text-gray-400 dark:text-gray-500 transition-all hover:border-emerald-300 dark:hover:border-emerald-500/40 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 dark:hover:text-emerald-400"
                            >
                                <UserPlus className="h-3 w-3" />
                                Assign to me
                            </button>
                        );
                    }

                    return (
                        <div className="group flex items-center gap-1.5">
                            <span className="text-[12px] text-gray-700 dark:text-gray-300">
                                {assignee.name}
                            </span>
                            <button
                                onClick={() => handleRemoveAssignee(orderId, currentStatus)}
                                className="invisible flex h-4 w-4 shrink-0 items-center justify-center rounded text-gray-300 transition-colors hover:bg-red-50 hover:text-red-400 dark:text-gray-600 dark:hover:bg-red-500/10 dark:hover:text-red-400 group-hover:visible"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'status',
                enableSorting: true,
                header: ({ column }) => <SortableHeader column={column} title="Status" />,
                cell: ({ row }) => (
                    <RmoStatusPicker
                        currentStatus={row.original.status as OrderStatus}
                        onChangeStatus={(status) => handleChangeStatus(status, row.original.order_id)}
                    />
                ),
            },
        ],
        [handleAssignToMe, handleRemoveAssignee, handleChangeStatus],
    );

    return (
        <div className="min-h-screen bg-stone-50 dark:bg-zinc-950">
            <FormModal
                open={isOpen}
                onOpenChange={(open) => {
                    setIsOpen(open);
                    if (!open) setPendingAssign(null);
                }}
                users={users}
                onSubmit={handleUserSelected}
            />

            {/* Top bar */}
            <div className="border-b border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                <div className="mx-auto flex w-full items-center justify-between px-4 py-3 md:px-6">
                    <div className="flex items-center gap-3">
                        <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-600">
                            <span className="text-[11px] font-bold text-white">R</span>
                        </div>
                        <div className="h-4 w-px bg-black/8 dark:bg-white/8" />
                        <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                            {new Date().toLocaleDateString('en-US', {
                                weekday: 'short',
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric',
                            })}
                        </span>
                    </div>

                    {userName ? (
                        <button
                            onClick={() => setIsOpen(true)}
                            className="group flex items-center gap-2.5 rounded-xl border border-black/6 bg-stone-50 px-3 py-1.5 transition-all hover:border-black/12 hover:bg-white dark:border-white/6 dark:bg-zinc-800 dark:hover:border-white/12 dark:hover:bg-zinc-700"
                        >
                            <span className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-[10px] font-bold text-white">
                                {userName.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()}
                            </span>
                            <div className="flex flex-col items-start">
                                <span className="font-mono text-[9px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    Logged in as
                                </span>
                                <span className="text-[12px] leading-tight font-semibold text-gray-800 dark:text-gray-100">
                                    {userName}
                                </span>
                            </div>
                            <span className="ml-1 text-[10px] font-medium text-gray-400 opacity-0 transition-opacity group-hover:opacity-100 dark:text-gray-500">
                                Change
                            </span>
                        </button>
                    ) : (
                        <Button
                            size="sm"
                            onClick={() => setIsOpen(true)}
                            className="rounded-lg bg-emerald-600 px-4 text-[12px] font-medium text-white hover:bg-emerald-700"
                        >
                            <UserIcon className="mr-1.5 h-3.5 w-3.5" />
                            Set Identity
                        </Button>
                    )}
                </div>
            </div>

            <div className="mx-auto w-full p-4 md:p-6">
                <div className="mb-6 flex items-start justify-between">
                    <div>
                        <h1 className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                            RMO Management
                        </h1>
                        <p className="mt-0.5 text-[13px] text-gray-400 dark:text-gray-500">
                            Delivery tracking for today's assigned orders
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Filters
                            workspace={workspace}
                            onChange={handleFilterChange}
                            initialValue={initialFilterValue}
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setShowStats((prev) => {
                                    const next = !prev;
                                    localStorage.setItem('rmo_show_stats', String(next));
                                    return next;
                                })
                            }
                            className="flex items-center gap-1.5 rounded-lg text-[12px]"
                        >
                            <BarChart3 className="h-3.5 w-3.5" />
                            {showStats ? 'Hide' : 'Show'} Statistics
                            {showStats ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
                        </Button>
                    </div>
                </div>

                {showStats && (
                    <div className="mb-6">
                        <RmoStatCards
                            total_for_delivery_today={total_for_delivery_today}
                            called_rate={called_rate}
                            successful_rate={successful_rate}
                            unsuccessful_rate={unsuccessful_rate}
                        />
                    </div>
                )}

                <div className="mb-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative w-60">
                            <Search className="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                className="h-8 w-full rounded-lg border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! outline-none focus:border-emerald-500 dark:bg-zinc-800 dark:text-gray-300"
                                placeholder="Search orders…"
                                value={searchValue}
                                onChange={(e) => setSearchValue(e.target.value)}
                            />
                        </div>

                        <select
                            value={currentStatus}
                            onChange={(e) => handleStatusChange(e.target.value)}
                            className="h-8 rounded-lg border border-black/6 bg-stone-100 px-2 text-[12px]! text-gray-700 outline-none focus:border-emerald-500 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            <option value="">All Statuses</option>
                            {Object.keys(orderStatusConfig).map((status) => (
                                <option key={status} value={status}>
                                    {status}
                                </option>
                            ))}
                        </select>

                        <select
                            value={currentParcelStatus}
                            onChange={(e) => handleParcelStatusChange(e.target.value)}
                            className="h-8 rounded-lg border border-black/6 bg-stone-100 px-2 text-[12px]! text-gray-700 outline-none focus:border-emerald-500 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            <option value="">All J&amp;T Statuses</option>
                            {Object.entries(authParcelStatusConfig).map(([key, config]) => (
                                <option key={key} value={key}>
                                    {config.label}
                                </option>
                            ))}
                        </select>
                    </div>
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
                                publicPage.rmoManagement({ workspace }),
                                {
                                    sort: params?.sort || undefined,
                                    'filter[search]': searchValue || undefined,
                                    ...(currentStatus ? { 'filter[status]': currentStatus } : {}),
                                    ...(currentParcelStatus ? { 'filter[parcel_status]': currentParcelStatus } : {}),
                                    ...(selectedPageIds.length ? { 'filter[page_id]': selectedPageIds.join(',') } : {}),
                                    ...(selectedShopIds.length ? { 'filter[shop_id]': selectedShopIds.join(',') } : {}),
                                    ...(selectedUserIds.length ? { 'filter[user_id]': selectedUserIds.join(',') } : {}),
                                    page: params?.page ?? 1,
                                },
                                { preserveState: true, replace: true, preserveScroll: true },
                            );
                        }}
                    />
                </div>
            </div>
        </div>
    );
}
