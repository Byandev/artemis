import PageHeader from '@/components/common/PageHeader';
import Filters, { FilterValue } from '@/components/filters/Filters';
import { createRmoColumns } from '@/components/rts/rmo-columns';
import { authParcelStatusConfig, orderStatusConfig } from '@/components/rts/rmo-config';
import { FilterOption, RmoFilterControls } from '@/components/rts/RmoFilterControls';
import { RmoStatCards } from '@/components/rts/RmoStatCards';
import { DataTable } from '@/components/ui/data-table';
import { useRmoFilterState } from '@/hooks/useRmoFilterState';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import workspaces from '@/routes/workspaces';
import { PaginatedData } from '@/types';
import { OrderForDelivery } from '@/types/models/Pancake/OrderForDelivery';
import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';
import { omit } from 'lodash';
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

    const {
        searchValue, setSearchValue,
        selectedStatuses, setSelectedStatuses,
        selectedPageIds, setSelectedPageIds,
        selectedParcelStatuses, setSelectedParcelStatuses,
        selectedShopIds, setSelectedShopIds,
        hasActiveFilters,
        clearAllFilters,
        buildParams,
    } = useRmoFilterState(query);

    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);

    const initialFilterValue = useMemo<FilterValue>(
        () => ({
            teamIds: [],
            productIds: [],
            shopIds: selectedShopIds.map(Number),
            pageIds: selectedPageIds.map(Number),
            userIds: [],
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    const handleFilterChange = useCallback((value: FilterValue) => {
        setSelectedPageIds(value.pageIds.map(String));
        setSelectedShopIds(value.shopIds.map(String));
    }, [setSelectedPageIds, setSelectedShopIds]);

    const orderStatusOptions = useMemo<FilterOption[]>(
        () =>
            Object.keys(orderStatusConfig).map((status) => ({
                id: status,
                name: status.replace(/_/g, ' '),
                dot: orderStatusConfig[status]?.dot ?? 'bg-gray-400',
                text: orderStatusConfig[status]?.text ?? '',
            })),
        [],
    );

    const parcelStatusOptions = useMemo<FilterOption[]>(
        () =>
            Object.entries(authParcelStatusConfig).map(([key, config]) => ({
                id: key,
                name: config.label,
                dot: config.dot,
            })),
        [],
    );

    const navigate = useCallback(
        (sort?: string | null, page?: number) => {
            router.get(
                workspaces.rts.rmoManagement({ workspace }),
                buildParams(sort, page),
                { preserveState: true, replace: true },
            );
        },
        [workspace, buildParams],
    );

    useEffect(() => {
        const timer = setTimeout(() => {
            navigate(
                query?.sort,
                searchValue || selectedStatuses.length || selectedPageIds.length ||
                selectedParcelStatuses.length || selectedShopIds.length
                    ? 1
                    : (query?.page ?? 1),
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [searchValue, selectedStatuses, selectedPageIds, selectedParcelStatuses, selectedShopIds, query?.sort]);

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
        [isLoadingID, workspace.slug],
    );

    return (
        <AppLayout>
            <div className="mx-auto w-(--breakpoint-2xl) p-4 md:p-6">
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

                <div className="mb-6">
                    <RmoStatCards
                        total_for_delivery_today={total_for_delivery_today}
                        called_rate={called_rate}
                        successful_rate={successful_rate}
                        unsuccessful_rate={unsuccessful_rate}
                    />
                </div>

                <div className="mb-4">
                    <RmoFilterControls
                        searchValue={searchValue}
                        onSearchChange={setSearchValue}
                        orderStatusOptions={orderStatusOptions}
                        selectedStatuses={selectedStatuses}
                        onStatusChange={setSelectedStatuses}
                        parcelStatusOptions={parcelStatusOptions}
                        selectedParcelStatuses={selectedParcelStatuses}
                        onParcelStatusChange={setSelectedParcelStatuses}
                        hasActiveFilters={hasActiveFilters}
                        onClearAll={clearAllFilters}
                    />
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={orders.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(orders, ['data']) }}
                        onFetch={(params) => {
                            navigate(params?.sort as string | undefined, Number(params?.page ?? 1));
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
