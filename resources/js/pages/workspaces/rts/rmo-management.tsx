import AppLayout from '@/layouts/app-layout';
import ComponentCard from '@/components/common/ComponentCard';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Team } from '@/types/models/Team';
import { PaginatedData } from '@/types';
import {
    ORDER_STATUSES,
    OrderForDelivery,
} from '@/types/models/OrderForDelivery';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Button } from '@/components/ui/button';
import { ChevronDown, ChevronUp, RefreshCcw } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Workspace } from '@/types/models/Workspace';


interface Props {
    orders: PaginatedData<OrderForDelivery>;
    workspace: Workspace
}



export default function RmoManagement({orders, workspace} : Props){

    const [ isLoadingID, setIsLoadingID ] = useState(null);

    console.log(workspace);

    const handleChangeStatus = (status: string, orderId: number) => {
        router.post(
            `/workspaces/${workspace.slug}/rts/rmo-management/${orderId}`,
            {
                status,
            },
            {
                preserveScroll: true,
                onStart: () => setIsLoadingID(orderId),
                onFinish: () => setIsLoadingID(null),
            },
        );
    };




    const columns: ColumnDef<OrderForDelivery>[] = [
        {
            accessorKey: 'order_id',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Order Number'} />
            ),
        },
        {
            accessorKey: 'page.name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Page'} />
            ),
        },
        {
            accessorKey: 'order.tracking_code',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Tracking Number'} />
            ),
        },
        {
            accessorKey: 'order.parcel_status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'J&T Status'} />
            ),
        },
        {
            accessorKey: 'rider_name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Rider'} />
            ),
            cell: ({ row }) => {
                return (
                    <div className="flex flex-col">
                        <p> {row.original.rider_name}</p>
                        <p className="mt-1 text-xs text-gray-500">
                            {' '}
                            {row.original.rider_phone}{' '}
                        </p>
                    </div>
                );
            },
        },
        {
            accessorKey: 'order.shipping_address.full_name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Customer'} />
            ),
            cell: ({ row }) => {
                return (
                    <div className="flex flex-col">
                        <p> {row.original.order.shipping_address.full_name}</p>
                        <p className="mt-1 text-xs text-gray-500">
                            {' '}
                            {
                                row.original.order.shipping_address.phone_number
                            }{' '}
                        </p>
                    </div>
                );
            },
        },
        {
            accessorKey: 'order.shipping_address.full_address',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Address'} />
            ),
            cell: ({ row }) => {
                return (
                    <div className="flex flex-col">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <p className="w-40 truncate">
                                    {' '}
                                    {
                                        row.original.order.shipping_address
                                            .full_address
                                    }
                                </p>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p className="">
                                    {' '}
                                    {
                                        row.original.order.shipping_address
                                            .full_address
                                    }
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                );
            },
        },

        {
            accessorKey: 'order.final_amount',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'SRP'} />
            ),
        },
        {
            accessorKey: 'order.delivery_attempts',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'# of Attemptss'} />
            ),
        },
        {
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Status'} />
            ),
            cell: ({ row }) => {
                const order_id = row.original.order_id;
                return (
                    <>
                        {' '}
                        {isLoadingID === order_id ? (
                            'Updating...'
                        ) : (
                            <DropdownMenu>
                                <DropdownMenuTrigger className="flex items-center gap-1 rounded-full border px-2 py-1 text-xs focus:outline">
                                    {row.original.status}
                                </DropdownMenuTrigger>

                                <DropdownMenuContent>
                                    {ORDER_STATUSES.map((orderStatus) => (
                                        <DropdownMenuItem
                                            className="text-xs"
                                            key={orderStatus}
                                            onClick={() =>
                                                handleChangeStatus(
                                                    orderStatus,
                                                    order_id,
                                                )
                                            }
                                        >
                                            {orderStatus}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <div className="p-6">
                <div className="">
                    <div className="flex flex-col mb-6 ">
                        <h1 className="text-2xl font-bold">RMO Management</h1>

                        <p className="text-sm font-light text-gray-500">
                            Items that for delivery today.
                        </p>
                    </div>
                    <ComponentCard>
                        <DataTable columns={columns} data={orders.data} />
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
}
