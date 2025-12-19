import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import RTSManagementLayout from './partials/Layout';
import ParcelUpdateNotificationFilters from './partials/ParcelUpdateNotificationFilters';
import { useState, useEffect, useMemo } from 'react';
import { ParcelJourneyNotification } from '@/types/models/ParcelJourneyNotification';
import { ColumnDef } from '@tanstack/react-table';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { PaginatedData } from '@/types';
import { format } from 'date-fns';
import ComponentCard from '@/components/common/ComponentCard';
import workspaces from '@/routes/workspaces';
import { toFrontendSort } from '@/lib/sort';
import { omit } from 'lodash';

interface ParcelUpdateNotificationProps {
    workspace: Workspace;
    notifications: PaginatedData<ParcelJourneyNotification>;
    pages: string[];
    types: string[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            page_name?: string;
            type?: string;
        };
    };
}

const ParcelUpdateNotification = ({ workspace, notifications, pages, types, query }: ParcelUpdateNotificationProps) => {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [pageNameSearch, setPageNameSearch] = useState(query?.filter?.page_name ?? '');
    const [typeFilter, setTypeFilter] = useState(query?.filter?.type ?? '');

    // Sync local state with query params when they change (e.g., from navigation/pagination)
    useEffect(() => {
        setPageNameSearch(query?.filter?.page_name ?? '');
        setTypeFilter(query?.filter?.type ?? '');
    }, [query?.filter?.page_name, query?.filter?.type]);

    // Debounce page name search
    useEffect(() => {
        const currentSearchParam = query?.filter?.page_name ?? '';

        if (pageNameSearch === currentSearchParam) {
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                workspaces.rts.parcelUpdateNotification(workspace.slug),
                {
                    sort: query?.sort,
                    'filter[page_name]': pageNameSearch || undefined,
                    'filter[type]': typeFilter || undefined,
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['notifications', 'query'],
                },
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [pageNameSearch, typeFilter, query?.filter?.page_name, query?.sort, workspace.slug]);

    const handleTypeFilterChange = (value: string) => {
        setTypeFilter(value);

        router.get(
            workspaces.rts.parcelUpdateNotification(workspace.slug),
            {
                sort: query?.sort,
                'filter[page_name]': pageNameSearch || undefined,
                'filter[type]': value || undefined,
                page: 1,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['notifications', 'query'],
            },
        );
    };

    const clearFilters = () => {
        setPageNameSearch('');
        setTypeFilter('');
        router.get(
            workspaces.rts.parcelUpdateNotification(workspace.slug),
            {},
            {
                preserveState: false,
                replace: true,
                preserveScroll: true,
                only: ['notifications', 'query'],
            }
        );
    };

    const columns: ColumnDef<ParcelJourneyNotification>[] = useMemo(() => [
        {
            id: 'order.page.name',
            accessorKey: 'order.page.name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Page'} />
            ),
            cell: ({ row }) => {
                return row.original.order?.page?.name || '-';
            },
        },
        {
            id: 'order.page.product.name',
            accessorKey: 'order.page.product.name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Product'} />
            ),
            cell: ({ row }) => {
                return row.original.order?.page?.product?.name || '-';
            },
        },
        {
            id: 'order.order_number',
            accessorKey: 'order.order_number',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Order #'} />
            ),
            cell: ({ row }) => {
                return row.original.order?.order_number || '-';
            },
        },
        {
            id: 'type',
            accessorKey: 'type',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Type'} />
            ),
            cell: ({ row }) => {
                return (
                    <span className="capitalize">
                        {row.original.type}
                    </span>
                );
            },
        },
        {
            id: 'message',
            accessorKey: 'message',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Message'} />
            ),
            cell: ({ row }) => {
                return (
                    <div className="max-w-md truncate">
                        {row.original.message}
                    </div>
                );
            },
        },
        {
            id: 'created_at',
            accessorKey: 'created_at',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Sent At'} />
            ),
            cell: ({ row }) => {
                return row.original.created_at
                    ? format(new Date(row.original.created_at), 'MMM dd, yyyy hh:mm a')
                    : '-';
            },
        },
    ], []);

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Parcel Update Notification`} />
            <RTSManagementLayout workspace={workspace}>
                <ComponentCard title="List of Parcel Update Notifications">
                    <div>
                        <ParcelUpdateNotificationFilters
                            pageNameSearch={pageNameSearch}
                            typeFilter={typeFilter}
                            types={types}
                            onPageNameChange={setPageNameSearch}
                            onTypeFilterChange={handleTypeFilterChange}
                            onClearFilters={clearFilters}
                        />

                        <div className="grid grid-cols-1 gap-4">
                            <DataTable
                                columns={columns}
                                data={notifications.data || []}
                                enableInternalPagination={false}
                                initialSorting={initialSorting}
                                meta={{ ...omit(notifications, ['data']) }}
                                onFetch={(params) => {
                                    router.get(
                                        workspaces.rts.parcelUpdateNotification(workspace.slug),
                                        {
                                            sort: params?.sort,
                                            'filter[page_name]': pageNameSearch || undefined,
                                            'filter[type]': typeFilter || undefined,
                                            page: params?.page ?? 1,
                                        },
                                        {
                                            preserveState: false,
                                            replace: true,
                                            preserveScroll: true,
                                            only: ['notifications', 'query'],
                                        },
                                    );
                                }}
                            />
                        </div>
                    </div>
                </ComponentCard>
            </RTSManagementLayout>
        </AppLayout>
    );
};

export default ParcelUpdateNotification;