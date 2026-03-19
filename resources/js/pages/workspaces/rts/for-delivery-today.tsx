import ComponentCard from '@/components/common/ComponentCard'
import { DataTable, SortableHeader } from '@/components/ui/data-table'
import AppLayout from '@/layouts/app-layout'
import { toFrontendSort } from '@/lib/sort'
import workspaces from '@/routes/workspaces'
import { PaginatedData } from '@/types'
import { Order } from '@/types/models/Orders'
import { Workspace } from '@/types/models/Workspace'
import { Head, router } from '@inertiajs/react'
import { ColumnDef } from '@tanstack/react-table'
import _, { omit } from 'lodash'
import { useEffect, useMemo, useState } from 'react'
import RTSManagementLayout from './partials/Layout'
import OrderFilters from './partials/OrderFilters'

interface ForDeliveryTodayProps {
    orders: PaginatedData<Order>;
    workspace: Workspace;
    customers: string[];
    riders: string[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            page_name?: string;
            customer?: string;
            rider?: string;
        };
    };
}

const ForDeliveryToday = ({ workspace, orders, customers, riders, query }: ForDeliveryTodayProps) => {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [pageNameSearch, setPageNameSearch] = useState(query?.filter?.page_name ?? '');
    const [customerFilter, setCustomerFilter] = useState(query?.filter?.customer ?? '');
    const [riderFilter, setRiderFilter] = useState(query?.filter?.rider ?? '');

    // Debounce page name search
    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                workspaces.rts.forDeliveryToday(workspace.slug),
                {
                    sort: query?.sort,
                    'filter[page_name]': pageNameSearch || undefined,
                    'filter[customer]': customerFilter || undefined,
                    'filter[rider]': riderFilter || undefined,
                    page: pageNameSearch ? 1 : query?.page ?? 1
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
    }, [pageNameSearch]);

    const handleFilterChange = (filterType: 'customer' | 'rider', value: string) => {
        const filters = {
            'filter[page_name]': pageNameSearch || undefined,
            'filter[customer]': filterType === 'customer' ? (value || undefined) : (customerFilter || undefined),
            'filter[rider]': filterType === 'rider' ? (value || undefined) : (riderFilter || undefined),
        };

        if (filterType === 'customer') {
            setCustomerFilter(value);
        } else {
            setRiderFilter(value);
        }

        router.get(
            workspaces.rts.forDeliveryToday(workspace.slug),
            {
                sort: query?.sort,
                ...filters,
                page: 1,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['orders'],
            },
        );
    };

    const clearFilters = () => {
        setPageNameSearch('');
        setCustomerFilter('');
        setRiderFilter('');
        router.get(
            workspaces.rts.forDeliveryToday(workspace.slug),
            {},
            {
                preserveState: false,
                replace: true,
                preserveScroll: true,
                only: ['orders'],
            }
        );
    };

    const orderColumns: ColumnDef<Order>[] = [
        {
            id: 'page.name',
            accessorKey: 'page.name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Name'} />
            ),
        },
        {
            id: 'tracking_code',
            accessorKey: 'tracking_code',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Tracking #'} />
            ),
        },
        {
            id: 'shipping_address.full_name',
            accessorKey: 'shipping_address.full_name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Customer'} />
            ),
        },
        {
            id: 'parcel_journey.rider_name',
            accessorKey: 'parcel_journey.rider_name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Rider'} />
            ),
        },
        {
            id: 'status_name',
            accessorKey: 'status_name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Status'} />
            ),
            cell: ({ row }) => (
                <span>{_.capitalize(row.original.status_name)}</span>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - For Delivery Today`} />
            <div className='p-4'>
                <ComponentCard title="List of Orders for Delivery Today" className='min-h-screen'>
                    <div>
                        <OrderFilters
                            pageNameSearch={pageNameSearch}
                            customerFilter={customerFilter}
                            riderFilter={riderFilter}
                            customers={customers}
                            riders={riders}
                            onPageNameChange={setPageNameSearch}
                            onFilterChange={handleFilterChange}
                            onClearFilters={clearFilters}
                        />

                        <div className="grid grid-cols-1 gap-4">
                            <DataTable
                                columns={orderColumns}
                                data={orders.data || []}
                                enableInternalPagination={false}
                                initialSorting={initialSorting}
                                meta={{ ...omit(orders, ['data']) }}
                                onFetch={(params) => {
                                    router.get(
                                        workspaces.rts.forDeliveryToday(workspace.slug),
                                        {
                                            sort: params?.sort,
                                            'filter[page_name]': pageNameSearch || undefined,
                                            'filter[customer]': customerFilter || undefined,
                                            'filter[rider]': riderFilter || undefined,
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
                </ComponentCard>
            </div>
        </AppLayout>
    );
};

export default ForDeliveryToday;
