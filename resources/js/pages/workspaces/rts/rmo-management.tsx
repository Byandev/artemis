import PageHeader from '@/components/common/PageHeader';
import Filters, { FilterValue } from '@/components/filters/Filters';
import { createRmoColumns } from '@/components/rts/rmo-columns';
import {
    authParcelStatusConfig,
    orderStatusConfig,
} from '@/components/rts/rmo-config';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable } from '@/components/ui/data-table';
import {
    Popover,
    PopoverContent, PopoverHeader,
    PopoverTrigger,
} from '@/components/ui/popover';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import workspaces from '@/routes/workspaces';
import { PaginatedData } from '@/types';
import { OrderForDelivery } from '@/types/models/Pancake/OrderForDelivery';
import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';
import { omit } from 'lodash';
import {
    AlertTriangleIcon,
    CheckCircleIcon,
    ListFilter,
    PercentIcon,
    Search,
    TruckIcon,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

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
    total_for_delivery_today: number;
    called_rate: number;
    successful_rate: number;
    unsuccessful_rate: number;
}

const statusOptions = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'FAILED'];

function StatCard({ title, value, icon: Icon, suffix }: any) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-2 flex items-center gap-2">
                <Icon className="h-4 w-4 text-gray-400" />
                <span className="text-[11px] font-medium text-gray-400">
                    {title}
                </span>
            </div>
            <span className="font-mono text-[22px] font-semibold">
                {value.toLocaleString()}
                {suffix}
            </span>
        </div>
    );
}

function FilterPopover({ label, options, selected, onChange, icon: Icon }: any) {
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
                        <div className="flex h-8 rounded-l-md items-center justify-center border border-r bg-gray-50 px-2">
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
    total_for_delivery_today,
    called_rate,
    successful_rate,
    unsuccessful_rate,
}: Props) {
    const [isLoadingID, setIsLoadingID] = useState<number | null>(null);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [selectedStatuses, setSelectedStatuses] = useState<string[]>(() => {
        const v = query?.filter?.status;
        return v ? (Array.isArray(v) ? v : v.split(',').filter(Boolean)) : [];
    });
    const [selectedPageIds, setSelectedPageIds] = useState<string[]>(() => {
        const v = query?.filter?.page_id;
        return v ? (Array.isArray(v) ? v : v.split(',').filter(Boolean)) : [];
    });
    const [selectedParcelStatuses, setSelectedParcelStatuses] = useState<
        string[]
    >(() => {
        const v = query?.filter?.parcel_status;
        return v ? (Array.isArray(v) ? v : v.split(',').filter(Boolean)) : [];
    });
    const [selectedShopIds, setSelectedShopIds] = useState<string[]>(() => {
        const v = query?.filter?.shop_id;
        return v ? (Array.isArray(v) ? v : v.split(',').filter(Boolean)) : [];
    });

    const initialFilterValue = useMemo<FilterValue>(() => ({
        teamIds: [],
        productIds: [],
        shopIds: selectedShopIds.map(Number),
        pageIds: selectedPageIds.map(Number),
        userIds: [],
    }), []);

    const handleFilterChange = useCallback((value: FilterValue) => {
        const newPageIds = value.pageIds.map(String);
        const newShopIds = value.shopIds.map(String);
        setSelectedPageIds(newPageIds);
        setSelectedShopIds(newShopIds);
    }, []);

    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
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

    const orderStatusOptions = useMemo(
        () =>
            statusOptions.map((status) => ({
                id: status,
                name: status.replace(/_/g, ' '),
                dot: orderStatusConfig[status]?.dot || 'bg-gray-400',
                text: orderStatusConfig[status]?.text || '',
            })),
        [],
    );

    useEffect(() => {
        const timer = setTimeout(() => {
            const params: any = {
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
            router.get(workspaces.rts.rmoManagement({ workspace }), params, {
                preserveState: true,
                replace: true,
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

    const columns = useMemo(
        () =>
            createRmoColumns({
                isLoadingID,
                onChangeStatus: (status: string, orderId: number) => {
                    router.post(
                        `/workspaces/${workspace.slug}/rts/rmo-management/${orderId}`,
                        { status },
                        {
                            onStart: () => setIsLoadingID(orderId),
                            onFinish: () => setIsLoadingID(null),
                        },
                    );
                },
                parcelStatusConfig: authParcelStatusConfig,
                normalizeParcelStatus: (s: string) => s?.toLowerCase(),
            }),
        [isLoadingID],
    );

    const clearAllFilters = () => {
        setSearchValue('');
        setSelectedStatuses([]);
        setSelectedPageIds([]);
        setSelectedParcelStatuses([]);
        setSelectedShopIds([]);
    };

    const hasActiveFilters =
        searchValue ||
        selectedStatuses.length ||
        selectedPageIds.length ||
        selectedParcelStatuses.length ||
        selectedShopIds.length;

    return (
        <AppLayout>
            <div className="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="RMO Management"
                    description="Track and update delivery status for items out today"
                >
                    <Filters
                        workspace={workspace}
                        onChange={handleFilterChange}
                        initialValue={initialFilterValue}
                    />
                </PageHeader>

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
                        ) : ''}
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={orders.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(orders, ['data']) }}
                        onFetch={(params) => {
                            const fetchParams: any = {
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
                                workspaces.rts.rmoManagement({ workspace }),
                                fetchParams,
                                { preserveState: true, replace: true },
                            );
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
