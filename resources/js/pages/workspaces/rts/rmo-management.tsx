import ComponentCard from '@/components/common/ComponentCard';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { currencyFormatter } from '@/lib/utils';
import { PaginatedData } from '@/types';
import {
    ORDER_STATUSES,
    OrderForDelivery,
    getStatusBadgeClass,
    type orderStatus,
} from '@/types/models/OrderForDelivery';
import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { ChevronUp, Loader2, MapPin, Phone } from 'lucide-react';
import { useState } from 'react';

interface Props {
    orders: PaginatedData<OrderForDelivery>;
    workspace: Workspace;
}

export default function RmoManagement({ orders, workspace }: Props) {
    const [isLoadingID, setIsLoadingID] = useState<number | null>(null);

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
                        <p>{row.original.rider_name}</p>
                        <p className="mt-1 flex items-center text-xs text-gray-500">
                            <Phone className="m-0 mr-1 h-3 w-3" />
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
                    <div className="flex flex-col text-gray-500">
                        <p className="text-black">
                            {row.original.order.shipping_address.full_name}
                        </p>
                        <p className="mt-1 flex items-center text-xs text-gray-500">
                            <Phone className="m-0 mr-1 h-3 w-3" />
                            {row.original.order.shipping_address.phone_number}
                        </p>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <div className="flex w-40 items-center truncate">
                                    <MapPin className="mr-1 h-3 w-3 shrink-0" />
                                    <span className="truncate">
                                        {
                                            row.original.order.shipping_address
                                                .full_address
                                        }
                                    </span>
                                </div>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p className="max-w-xs">
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
            cell: ({ row }) => {
                const final_amount = row.original.order.final_amount;
                return <p>{currencyFormatter(final_amount)}</p>;
            },
        },
        {
            accessorKey: 'order.delivery_attempts',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'# of Attempts'} />
            ),
        },
        {
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Status'} />
            ),
            cell: ({ row }) => {
                const orderId = row.original.order_id;
                const currentStatus = row.original.status;
                const isLoading = isLoadingID === orderId;

                return (
                    <>
                        {isLoading ? (
                            <div className="flex items-center gap-2">
                                <Loader2 className="h-4 w-4 animate-spin" />
                                <span className="text-xs text-gray-500">
                                    Updating...
                                </span>
                            </div>
                        ) : (
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    className={[
                                        'flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium transition-all',
                                        'hover:opacity-80 focus:outline-none',
                                        getStatusBadgeClass(currentStatus),
                                    ].join(' ')}
                                >
                                    {currentStatus}
                                    <ChevronUp className="h-3 w-3" />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="max-h-80 overflow-y-auto"
                                >
                                    {ORDER_STATUSES.map((orderStatus) => {
                                        const statusClass = getStatusBadgeClass(
                                            orderStatus,
                                        );
                                        return (
                                            <DropdownMenuItem
                                                className="p-0 focus:bg-transparent"
                                                key={orderStatus}
                                                onClick={() =>
                                                    handleChangeStatus(
                                                        orderStatus,
                                                        orderId,
                                                    )
                                                }
                                            >
                                                <div className="w-full px-2 py-1.5 hover:bg-gray-100">
                                                    <span
                                                        className={statusClass}
                                                    >
                                                        {orderStatus}
                                                    </span>
                                                </div>
                                            </DropdownMenuItem>
                                        );
                                    })}
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
                    <div className="mb-6 flex flex-col">
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
