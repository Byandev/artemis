import AppLayout from '@/layouts/app-layout'
import { Workspace } from '@/types/models/Workspace'
import { Head, usePage } from '@inertiajs/react'
import RtsNavigation from './partials/RtsNavigation'
import { useState, useEffect } from 'react'
import { Order } from '@/types/models/Orders'
import { ColumnDef } from '@tanstack/react-table'
import { DataTable } from '@/components/ui/data-table'
import { router } from '@inertiajs/react'
import workspaces from '@/routes/workspaces'
import _ from 'lodash'
import ForDeliveryFilters from './partials/ForDeliveryFilters'
import Pagination from '@/components/pagination'
import { Button } from '@/components/ui/button'
import { getSortIcon } from '@/utils/getSortIcon'
import { addIf } from '@/utils/addIf'
import { PaginatedData } from '@/types'

interface Filters {
    page_name: string;
    customer: string;
    rider: string;
}

interface ForDeliveryTodayProps {
    orders: PaginatedData<Order>;
    workspace: Workspace
    customers: string[];
    riders: string[];
}

const ForDeliveryToday = ({ workspace, orders, customers, riders }: ForDeliveryTodayProps) => {
    const { url } = usePage();
    const params = new URLSearchParams(url.split('?')[1] || '');

    const [filters, setFilters] = useState<Filters>({
        page_name: params.get('page_name') || '',
        customer: params.get('customer') || '',
        rider: params.get('rider') || ''
    });
    const [pageName, setPageName] = useState<string>(filters.page_name);

    const [sortField, setSortField] = useState<string>(params.get('sort_by') || '');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc' | ''>(
        (params.get('sort_dir') as 'asc' | 'desc' | '') || ''
    );
    const [isLoading, setIsLoading] = useState<boolean>(false);

    const applyFilters = (newFilters: Filters, sortOverride?: { field?: string; dir?: string }) => {
        const params: Record<string, string> = {};

        addIf(params, 'page_name', newFilters.page_name);
        addIf(params, 'customer', newFilters.customer);
        addIf(params, 'rider', newFilters.rider);

        // include sort params (either override passed, or current state)
        const sf = sortOverride?.field ?? sortField;
        const sd = sortOverride?.dir ?? sortDirection;
        if (sf && sd) {
            params['sort_by'] = sf;
            params['sort_dir'] = sd;
        }

        setIsLoading(true);
        router.get(
            workspaces.rts.forDeliveryToday(workspace.slug),
            params,
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setIsLoading(false),
                onError: () => setIsLoading(false),
            }
        );
    }

    const handleSort = (field: string) => {
        // cycle: none -> asc -> desc -> none
        let nextField = field;
        let nextDir: 'asc' | 'desc' | '' = 'asc';

        if (sortField !== field) {
            nextDir = 'asc';
        } else if (sortDirection === 'asc') {
            nextDir = 'desc';
        } else {
            // clear sort
            nextField = '';
            nextDir = '';
        }

        setSortField(nextField);
        setSortDirection(nextDir);

        // apply current filters with the new sort
        if (nextDir === '') {
            applyFilters(filters, { field: undefined, dir: undefined });
        } else {
            applyFilters(filters, { field: nextField, dir: nextDir });
        }
    }

    // Debounce search input so typing triggers search automatically
    useEffect(() => {
        const debounced = _.debounce((value: string) => {
            setFilters(prev => {
                const newFilters = { ...prev, page_name: value };
                applyFilters(newFilters);
                return newFilters;
            });
        }, 500);

        debounced(pageName);

        return () => {
            debounced.cancel();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pageName]);

    const clearFilters = () => {
        setFilters({ page_name: '', customer: '', rider: '' });
        setPageName('');
        setIsLoading(true);
        router.get(
            workspaces.rts.forDeliveryToday(workspace.slug),
            {},
            { preserveState: true, preserveScroll: true, onFinish: () => setIsLoading(false), onError: () => setIsLoading(false) }
        );
    }

    const orderColumns: ColumnDef<Order>[] = [
        {
            accessorKey: "page.name",
            header: () => (
                <Button
                    variant="ghost"
                    onClick={() => handleSort('name')}
                    className="h-8 px-2 lg:px-3"
                >
                    Name
                    {getSortIcon('name', sortField, sortDirection)}
                </Button>
            ),
        },
        {
            accessorKey: "tracking_code",
            header: () => (
                <Button variant="ghost" onClick={() => handleSort('tracking_code')} className="h-8 px-2 lg:px-3">
                    Tracking #
                    {getSortIcon('tracking_code', sortField, sortDirection)}
                </Button>
            ),
        },
        {
            accessorKey: "shipping_address.full_name",
            header: () => (
                <Button variant="ghost" onClick={() => handleSort('shipping_address.full_name')} className="h-8 px-2 lg:px-3">
                    Customer
                    {getSortIcon('shipping_address.full_name', sortField, sortDirection)}
                </Button>
            ),
        },
        {
            accessorKey: "parcel_journey.rider_name",
            header: () => (
                <Button variant="ghost" onClick={() => handleSort('parcel_journey.rider_name')} className="h-8 px-2 lg:px-3">
                    Rider
                    {getSortIcon('parcel_journey.rider_name', sortField, sortDirection)}
                </Button>
            ),
        },
        {
            accessorKey: "status_name",
            header: () => (
                <Button variant="ghost" onClick={() => handleSort('status_name')} className="h-8 px-2 lg:px-3">
                    Status
                    {getSortIcon('status_name', sortField, sortDirection)}
                </Button>
            ),
            cell: ({ row }) => (
                <span>{_.capitalize(row.original.status_name)}</span>
            )
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

                <div className='rounded-xl border bg-card p-6 shadow-sm'>
                    <div className='flex flex-col items-start justify-between gap-4 mb-8'>
                        <div>
                            <h2 className="text-2xl font-bold tracking-tight">For Delivery Today</h2>
                            <p className="text-sm text-muted-foreground mt-1">View all orders scheduled for delivery today</p>
                        </div>

                        <ForDeliveryFilters
                            pageName={pageName}
                            setPageName={setPageName}
                            filters={filters}
                            setFilters={setFilters}
                            applyFilters={applyFilters}
                            clearFilters={clearFilters}
                            customers={customers}
                            riders={riders}
                            isLoading={isLoading}
                        />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div className='col-span-4'>
                            <DataTable columns={orderColumns} data={orders.data} />

                            <Pagination data={orders} />
                        </div>
                    </div>
                </div>
            </div>

        </AppLayout>
    )
}

export default ForDeliveryToday