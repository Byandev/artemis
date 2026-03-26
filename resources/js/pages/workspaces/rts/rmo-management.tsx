import PageHeader from '@/components/common/PageHeader';
import { RmoFilterBar } from '@/components/rts/RmoFilterBar';
import { authParcelStatusConfig } from '@/components/rts/rmo-config';
import { createRmoColumns } from '@/components/rts/rmo-columns';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { PaginatedData } from '@/types';
import { OrderForDelivery } from '@/types/models/Pancake/OrderForDelivery';
import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';
import { omit } from 'lodash';
import { useEffect, useMemo, useState } from 'react';
import { toFrontendSort } from '@/lib/sort';
import workspaces from '@/routes/workspaces';

const parcelStatusOptions = Object.keys(authParcelStatusConfig);

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

export default function RmoManagement({ orders, workspace, query }: Props) {
    const [isLoadingID, setIsLoadingID] = useState<number | null>(null);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [selectedStatus, setSelectedStatus] = useState<string>(query?.filter?.status ?? '');
    const [selectedPageId, setSelectedPageId] = useState<string>(query?.filter?.page_id ?? '');
    const [selectedParcelStatus, setSelectedParcelStatus] = useState<string>('');

    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);

    const uniquePages = useMemo(() => {
        const map = new Map<number, string>();
        orders.data?.forEach(order => {
            if (order.page?.id && order.page?.name) map.set(order.page.id, order.page.name);
        });
        return Array.from(map.entries()).map(([id, name]) => ({ id: String(id), name }));
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
                { preserveState: true, replace: true, preserveScroll: true, only: ['orders'] },
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

    const columns = useMemo(() => createRmoColumns({
        isLoadingID,
        onChangeStatus: handleChangeStatus,
        parcelStatusConfig: authParcelStatusConfig,
        normalizeParcelStatus: (s) => s?.toLowerCase(),
    }), [isLoadingID]);

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="RMO Management"
                    description="Track and update delivery status for items out today"
                />

                <RmoFilterBar
                    searchValue={searchValue}
                    onSearchChange={setSearchValue}
                    uniquePages={uniquePages}
                    selectedPageId={selectedPageId}
                    onPageChange={setSelectedPageId}
                    parcelStatusConfig={authParcelStatusConfig}
                    parcelStatusOptions={parcelStatusOptions}
                    selectedParcelStatus={selectedParcelStatus}
                    onParcelStatusChange={setSelectedParcelStatus}
                    selectedStatus={selectedStatus}
                    onStatusChange={setSelectedStatus}
                />

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
                                { preserveState: true, replace: true, preserveScroll: true },
                            );
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
