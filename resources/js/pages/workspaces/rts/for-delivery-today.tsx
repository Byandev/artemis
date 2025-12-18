import AppLayout from '@/layouts/app-layout'
import { Workspace } from '@/types/models/Workspace'
import { Head } from '@inertiajs/react'
import RtsNavigation from './partials/RtsNavigation'
import { useState, useEffect, useMemo } from 'react'
import { Order } from '@/types/models/Orders'
import { ColumnDef } from '@tanstack/react-table'
import { DataTable, SortableHeader } from '@/components/ui/data-table'
import { router } from '@inertiajs/react'
import workspaces from '@/routes/workspaces'
import _ from 'lodash'
import { PaginatedData } from '@/types'
import { toFrontendSort } from '@/lib/sort'
import { omit } from 'lodash'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Button } from '@/components/ui/button'
import ComponentCard from '@/components/common/ComponentCard'

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
                    page: 1,
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
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">RTS Management</h1>
                        <p className="text-muted-foreground mt-1">Manage RTS analytics and reports</p>
                    </div>
                </div>
                <RtsNavigation workspace={workspace} />

                <ComponentCard title="Orders For Delivery Today" >
                    <div>
                        <div className='flex flex-col items-start justify-between gap-4 mb-8'>
                            <div className="flex flex-col gap-4 w-full sm:flex-row sm:items-end">
                                <div className="flex-1 space-y-2">
                                    <label className="text-sm font-medium">Search Page Name</label>
                                    <Input
                                        placeholder="Search by page name..."
                                        value={pageNameSearch}
                                        onChange={(e) => setPageNameSearch(e.target.value)}
                                    />
                                </div>
                                <div className="flex-1 space-y-2">
                                    <label className="text-sm font-medium">Customer</label>
                                    <Select
                                        value={customerFilter || undefined}
                                        onValueChange={(value) => handleFilterChange('customer', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All customers" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {customers.map((customer) => (
                                                <SelectItem key={customer} value={customer}>
                                                    {customer}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex-1 space-y-2">
                                    <label className="text-sm font-medium">Rider</label>
                                    <Select
                                        value={riderFilter || undefined}
                                        onValueChange={(value) => handleFilterChange('rider', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All riders" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {riders.map((rider) => (
                                                <SelectItem key={rider} value={rider}>
                                                    {rider}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                {(pageNameSearch || customerFilter || riderFilter) && (
                                    <Button onClick={clearFilters} variant="outline">
                                        Clear Filters
                                    </Button>
                                )}
                            </div>
                        </div>

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
                                            preserveState: false,
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