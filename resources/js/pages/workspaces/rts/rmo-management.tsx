import PageHeader from '@/components/common/PageHeader';
import { RmoFilterBar } from '@/components/rts/RmoFilterBar';
import { createRmoColumns } from '@/components/rts/rmo-columns';
import { authParcelStatusConfig } from '@/components/rts/rmo-config';
import { DataTable } from '@/components/ui/data-table';
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
    PercentIcon,
    TruckIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const parcelStatusOptions = Object.keys(authParcelStatusConfig);

interface RmoStats {
    total_for_delivery_today: number;
    called_rate: number;
    successful_rate: number;
    unsuccessful_rate: number;
}

interface Props {
    orders: PaginatedData<OrderForDelivery>;
    workspace: Workspace;
    query?: {
        sort?: string | null;
        filter?: {
            search?: string;
            status?: string | string[];
            page_id?: string | string[];
            shop_id?: string;
            parcel_status?: string | string[];
        };
        page?: number;
        perPage?: number;
    };
    stats: RmoStats;
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

export default function RmoManagement({
    orders,
    workspace,
    query,
    stats,
}: Props) {
    const [isLoadingID, setIsLoadingID] = useState<number | null>(null);
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

    useEffect(() => {
        const timer = setTimeout(() => {
            const params: Record<string, unknown> = {
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                'filter[shop_id]': query?.filter?.shop_id || undefined,
                page:
                    searchValue ||
                    selectedStatuses.length ||
                    selectedPageIds.length ||
                    selectedParcelStatuses.length
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

            router.get(workspaces.rts.rmoManagement({ workspace }), params, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['orders'],
            });
        }, 500);
        return () => clearTimeout(timer);
    }, [
        searchValue,
        selectedStatuses,
        selectedPageIds,
        selectedParcelStatuses,
        query?.sort,
        query?.filter?.shop_id,
    ]);

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

    const columns = useMemo(
        () =>
            createRmoColumns({
                isLoadingID,
                onChangeStatus: handleChangeStatus,
                parcelStatusConfig: authParcelStatusConfig,
                normalizeParcelStatus: (s) => s?.toLowerCase(),
            }),
        [isLoadingID],
    );

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="RMO Management"
                    description="Track and update delivery status for items out today"
                />

                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <StatCard
                        title="Total For Delivery Today"
                        value={stats?.total_for_delivery_today || 0}
                        icon={TruckIcon}
                    />
                    <StatCard
                        title="Called Rate"
                        value={stats?.called_rate || 0}
                        icon={PercentIcon}
                        suffix="%"
                    />
                    <StatCard
                        title="Successful Rate"
                        value={stats?.successful_rate || 0}
                        icon={CheckCircleIcon}
                        suffix="%"
                    />
                    <StatCard
                        title="Unsuccessful Rate"
                        value={stats?.unsuccessful_rate || 0}
                        icon={AlertTriangleIcon}
                        suffix="%"
                    />
                </div>

                <RmoFilterBar
                    searchValue={searchValue}
                    onSearchChange={setSearchValue}
                    uniquePages={uniquePages}
                    selectedPageIds={selectedPageIds}
                    onPageChange={setSelectedPageIds}
                    parcelStatusConfig={authParcelStatusConfig}
                    parcelStatusOptions={parcelStatusOptions}
                    selectedParcelStatuses={selectedParcelStatuses}
                    onParcelStatusChange={setSelectedParcelStatuses}
                    selectedStatuses={selectedStatuses}
                    onStatusChange={setSelectedStatuses}
                />

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
                                'filter[shop_id]':
                                    query?.filter?.shop_id || undefined,
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

                            router.get(
                                workspaces.rts.rmoManagement({ workspace }),
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
        </AppLayout>
    );
}
