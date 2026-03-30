import Filters, { FilterValue } from '@/components/filters/Filters';
import { createRmoColumns } from '@/components/rts/rmo-columns';
import {
    authParcelStatusConfig,
    orderStatusConfig,
    publicParcelStatusConfig,
} from '@/components/rts/rmo-config';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable } from '@/components/ui/data-table';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { toFrontendSort } from '@/lib/sort';
import publicPage from '@/routes/public-page';
import { PaginatedData } from '@/types';
import { OrderForDelivery } from '@/types/models/Pancake/OrderForDelivery';
import { User } from '@/types/models/Pancake/User';
import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';
import { omit } from 'lodash';
import {
    AlertTriangleIcon,
    BarChart3,
    CheckCircleIcon,
    ChevronDown,
    ChevronUp,
    ListFilter,
    PercentIcon,
    Search,
    TruckIcon,
    User as UserIcon,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import FormModal from './formModal';

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

function StatCard({
                      title,
                      value,
                      icon: Icon,
                      suffix,
                  }: {
    title: string;
    value: number;
    icon: React.ComponentType<{ className?: string }>;
    suffix?: string;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-2 flex items-center gap-2">
                <Icon className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {title}
                </span>
            </div>
            <span className="font-mono text-[22px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                {value.toLocaleString()}
                {suffix}
            </span>
        </div>
    );
}

function FilterPopover({
                           label,
                           options,
                           selected,
                           onChange,
                           icon: Icon,
                       }: any) {
    const [open, setOpen] = useState(false);

    const toggleOption = (id: string) => {
        if (selected.includes(id)) {
            onChange(selected.filter((s: string) => s !== id));
        } else {
            onChange([...selected, id]);
        }
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className="flex h-8 items-center overflow-hidden px-0 text-[12px] font-medium"
                >
                    {/* Icon segment */}
                    {Icon && (
                        <div className="flex h-8 items-center justify-center rounded-l-md border border-r bg-gray-50 px-2">
                            <Icon className="h-3.5 w-3.5 text-gray-500" />
                        </div>
                    )}

                    {/* Label + badge */}
                    <div className="flex items-center gap-1.5 px-3">
                        {label}

                        {selected.length > 0 && (
                            <span className="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-500/10 px-1 text-[10px] font-semibold text-emerald-600">
                                {selected.length}
                            </span>
                        )}
                    </div>
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[calc(100vw-2rem)] overflow-hidden rounded-[14px] border border-black/6 bg-white p-0 shadow-[0_8px_30px_rgba(0,0,0,0.08)] sm:w-72 dark:border-white/6 dark:bg-zinc-900 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
                align="start"
            >
                <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <span className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                        {label}
                    </span>
                </div>
                <div className="max-h-64 overflow-y-auto">
                    {options.map((option: any) => (
                        <label
                            key={option.id}
                            className="flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 hover:bg-gray-50 dark:hover:bg-zinc-800"
                        >
                            <Checkbox
                                checked={selected.includes(option.id)}
                                onCheckedChange={() => toggleOption(option.id)}
                                className="h-3.5 w-3.5 rounded-sm border border-gray-200"
                            />
                            <div className="flex items-center gap-2">
                                {option.dot && (
                                    <div
                                        className={`h-2 w-2 rounded-full ${option.dot}`}
                                    />
                                )}
                                <span
                                    className={`text-[13px] ${option.text || ''}`}
                                >
                                    {option.name}
                                </span>
                            </div>
                        </label>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
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
    const [isLoadingID, setIsLoadingID] = useState<number | null>(null);
    const [assigningOrderId, setAssigningOrderId] = useState<number | null>(
        null,
    );
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [selectedStatuses, setSelectedStatuses] = useState<string[]>(() => {
        const v = query?.filter?.status;
        if (!v) return [];
        return Array.isArray(v) ? v : v.split(',').filter(Boolean);
    });
    const [selectedPageIds, setSelectedPageIds] = useState<string[]>(() => {
        const v = query?.filter?.page_id;
        if (!v) return [];
        return Array.isArray(v) ? v : v.split(',').filter(Boolean);
    });
    const [selectedParcelStatuses, setSelectedParcelStatuses] = useState<
        string[]
    >(() => {
        const v = query?.filter?.parcel_status;
        if (!v) return [];
        return Array.isArray(v) ? v : v.split(',').filter(Boolean);
    });
    const [selectedShopIds, setSelectedShopIds] = useState<string[]>(() => {
        const v = query?.filter?.shop_id;
        if (!v) return [];
        return Array.isArray(v) ? v : v.split(',').filter(Boolean);
    });
    const [userName, setUserName] = useState<string | false>(false);
    const [isOpen, setIsOpen] = useState(false);
    const [showStats, setShowStats] = useState(
        () => localStorage.getItem('rmo_show_stats') === 'true',
    );
    const [pendingAssign, setPendingAssign] = useState<{
        orderId: number;
        currentStatus: string;
    } | null>(null);

    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const uniquePages = useMemo(() => {
        const map = new Map<number, string>();
        orders.data?.forEach((order) => {
            if (order.page?.id && order.page?.name)
                map.set(order.page.id, order.page.name);
        });
        return Array.from(map.entries()).map(([id, name]) => ({
            id: String(id),
            name,
        }));
    }, [orders.data]);

    const initialFilterValue = useMemo<FilterValue>(
        () => ({
            teamIds: [],
            productIds: [],
            shopIds: selectedShopIds.map(Number),
            pageIds: selectedPageIds.map(Number),
            userIds: [],
        }),
        [],
    );

    const handleFilterChange = useCallback((value: FilterValue) => {
        const newPageIds = value.pageIds.map(String);
        const newShopIds = value.shopIds.map(String);
        setSelectedPageIds(newPageIds);
        setSelectedShopIds(newShopIds);
    }, []);

    useEffect(() => {
        const name = localStorage.getItem('user_name');
        if (name) setUserName(name);
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => {
            const params: Record<string, unknown> = {
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                page:
                    searchValue ||
                    selectedStatuses.length ||
                    selectedPageIds.length ||
                    selectedParcelStatuses.length ||
                    selectedShopIds.length
                        ? 1
                        : (query?.page ?? 1),
            };
            if (selectedStatuses.length)
                params['filter[status]'] = selectedStatuses.join(',');
            if (selectedPageIds.length)
                params['filter[page_id]'] = selectedPageIds.join(',');
            if (selectedParcelStatuses.length)
                params['filter[parcel_status]'] =
                    selectedParcelStatuses.join(',');
            if (selectedShopIds.length)
                params['filter[shop_id]'] = selectedShopIds.join(',');

            router.get(publicPage.rmoManagement({ workspace }), params, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            });
        }, 500);
        return () => clearTimeout(timer);
    }, [
        searchValue,
        selectedStatuses,
        selectedPageIds,
        selectedParcelStatuses,
        selectedShopIds,
        query?.sort,
    ]);

    const doAssign = (
        orderId: number,
        currentStatus: string,
        userId: string | null,
    ) => {
        const payload =
            userId === null
                ? { status: currentStatus, removeAssignee: true }
                : { status: currentStatus, userId };
        router.post(
            `/public/workspaces/${workspace.slug}/rts/rmo-management/${orderId}`,
            payload,
            {
                preserveScroll: true,
                onStart: () => setAssigningOrderId(orderId),
                onFinish: () => setAssigningOrderId(null),
            },
        );
    };

    const handleChangeStatus = (status: string, orderId: number) => {
        const userId = localStorage.getItem('user_id');
        router.post(
            `/public/workspaces/${workspace.slug}/rts/rmo-management/${orderId}`,
            { status, userId },
            {
                preserveScroll: true,
                onStart: () => setIsLoadingID(orderId),
                onFinish: () => setIsLoadingID(null),
            },
        );
    };

    const handleAssignToMe = (orderId: number, currentStatus: string) => {
        const userId = localStorage.getItem('user_id');
        if (userId) {
            doAssign(orderId, currentStatus, userId);
        } else {
            setPendingAssign({ orderId, currentStatus });
            setIsOpen(true);
        }
    };

    const handleUserSelected = (userId: string) => {
        const name = localStorage.getItem('user_name') ?? '';
        setUserName(name);
        if (pendingAssign) {
            doAssign(
                pendingAssign.orderId,
                pendingAssign.currentStatus,
                userId,
            );
            setPendingAssign(null);
        }
    };

    const handleRemoveAssignee = (orderId: number, currentStatus: string) => {
        doAssign(orderId, currentStatus, null);
    };

    const columns = useMemo(
        () =>
            createRmoColumns({
                isLoadingID,
                onChangeStatus: handleChangeStatus,
                parcelStatusConfig: publicParcelStatusConfig,
                normalizeParcelStatus: (s) => s?.toLowerCase(),
                onAssignToMe: handleAssignToMe,
                onRemoveAssignee: handleRemoveAssignee,
                assigningOrderId,
            }),
        [isLoadingID, assigningOrderId],
    );

    const orderStatusOptions = useMemo(
        () =>
            Object.keys(orderStatusConfig).map((status) => ({
                id: status,
                name: status.replace(/_/g, ' '),
                dot: orderStatusConfig[status]?.dot || 'bg-gray-400',
                text: orderStatusConfig[status]?.text || '',
            })),
        [],
    );

    const parcelStatusOptions = useMemo(
        () =>
            Object.entries(authParcelStatusConfig).map(([key, config]) => ({
                id: key,
                name: config.label,
                dot: config.dot,
            })),
        [],
    );

    const hasActiveFilters =
        searchValue ||
        selectedStatuses.length ||
        selectedPageIds.length ||
        selectedParcelStatuses.length ||
        selectedShopIds.length;

    const clearAllFilters = () => {
        setSearchValue('');
        setSelectedStatuses([]);
        setSelectedPageIds([]);
        setSelectedParcelStatuses([]);
        setSelectedShopIds([]);
    };

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
                    {/* Brand + date */}
                    <div className="flex items-center gap-3">
                        <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-600">
                            <span className="text-[11px] font-bold text-white">
                                R
                            </span>
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

                    {/* Identity button */}
                    {userName ? (
                        <button
                            onClick={() => setIsOpen(true)}
                            className="group flex items-center gap-2.5 rounded-xl border border-black/6 bg-stone-50 px-3 py-1.5 transition-all hover:border-black/12 hover:bg-white dark:border-white/6 dark:bg-zinc-800 dark:hover:border-white/12 dark:hover:bg-zinc-700"
                        >
                            <span className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-[10px] font-bold text-white">
                                {userName
                                    .split(' ')
                                    .slice(0, 2)
                                    .map((w) => w[0])
                                    .join('')
                                    .toUpperCase()}
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
                {/* Page title + filters + stats toggle */}
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
                                    localStorage.setItem(
                                        'rmo_show_stats',
                                        String(next),
                                    );
                                    return next;
                                })
                            }
                            className="flex items-center gap-1.5 rounded-lg text-[12px]"
                        >
                            <BarChart3 className="h-3.5 w-3.5" />
                            {showStats ? 'Hide' : 'Show'} Statistics
                            {showStats ? (
                                <ChevronUp className="h-3.5 w-3.5" />
                            ) : (
                                <ChevronDown className="h-3.5 w-3.5" />
                            )}
                        </Button>
                    </div>
                </div>

                {showStats && (
                    <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <StatCard
                            title="Total For Delivery Today"
                            value={total_for_delivery_today || 0}
                            icon={TruckIcon}
                        />
                        <StatCard
                            title="Called Rate"
                            value={called_rate || 0}
                            icon={PercentIcon}
                            suffix="%"
                        />
                        <StatCard
                            title="Successful Rate"
                            value={successful_rate || 0}
                            icon={CheckCircleIcon}
                            suffix="%"
                        />
                        <StatCard
                            title="Unsuccessful Rate"
                            value={unsuccessful_rate || 0}
                            icon={AlertTriangleIcon}
                            suffix="%"
                        />
                    </div>
                )}

                <div className="mb-4 space-y-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative w-60">
                            <Search className="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                className="h-8 w-full rounded-lg border border-black/6 bg-stone-100 pr-3 pl-8 text-[12px] outline-none focus:border-emerald-500 dark:bg-zinc-800 dark:text-gray-300"
                                placeholder="Search orders…"
                                value={searchValue}
                                onChange={(e) => setSearchValue(e.target.value)}
                            />
                        </div>

                        <FilterPopover
                            label="Order Status"
                            options={orderStatusOptions}
                            selected={selectedStatuses}
                            onChange={setSelectedStatuses}
                            icon={ListFilter}
                        />

                        <FilterPopover
                            label="Parcel Status"
                            options={parcelStatusOptions}
                            selected={selectedParcelStatuses}
                            onChange={setSelectedParcelStatuses}
                            icon={ListFilter}
                        />

                        {hasActiveFilters ? (
                            <button
                                onClick={clearAllFilters}
                                className="inline-flex h-8 items-center gap-1 rounded-lg border border-red-200 bg-white px-3 text-[12px] text-red-600 hover:bg-red-50 dark:border-red-800 dark:bg-zinc-900 dark:text-red-400 dark:hover:bg-red-950/30"
                            >
                                <X className="h-3.5 w-3.5" />
                                Clear all
                            </button>
                        ) : (
                            ''
                        )}
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
                            const fetchParams: Record<string, unknown> = {
                                sort: params?.sort,
                                'filter[search]': searchValue || undefined,
                                page: params?.page ?? 1,
                            };
                            if (selectedStatuses.length)
                                fetchParams['filter[status]'] =
                                    selectedStatuses.join(',');
                            if (selectedPageIds.length)
                                fetchParams['filter[page_id]'] =
                                    selectedPageIds.join(',');
                            if (selectedParcelStatuses.length)
                                fetchParams['filter[parcel_status]'] =
                                    selectedParcelStatuses.join(',');
                            if (selectedShopIds.length)
                                fetchParams['filter[shop_id]'] =
                                    selectedShopIds.join(',');

                            router.get(
                                publicPage.rmoManagement({ workspace }),
                                fetchParams,
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
        </div>
    );
}
