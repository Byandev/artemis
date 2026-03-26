import PageHeader from '@/components/common/PageHeader';
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
import { currencyFormatter, percentageFormatter } from '@/lib/utils';
import { PaginatedData } from '@/types';
import {
    ORDER_STATUSES,
    OrderForDelivery,
    getStatusBadgeClass,
} from '@/types/models/Pancake/OrderForDelivery';
import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    CheckCircleIcon,
    ChevronUp,
    ClipboardListIcon,
    ClockIcon,
    Loader2,
    MapPin,
    Phone,
    PhoneIcon,
    RotateCcw,
    Search,
    TruckIcon,
    XCircleIcon,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { omit } from 'lodash';
import { toFrontendSort } from '@/lib/sort';


interface RmoStats {
    assigned_orders: number;
    total_called: number;
    total_pending: number;
    total_delivered: number;
    total_returning: number;
    total_undeliverable: number;
    total_out_for_delivery: number;
}
interface Props {
    orders: PaginatedData<OrderForDelivery>;
    workspace: Workspace;
    stats: RmoStats;
}

interface StatCardProps {
    title: string;
    value: number;
    icon: React.ComponentType<{ className?: string }>;
}

function StatCard({ title, value }: StatCardProps) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-2 flex items-center justify-between">
                <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {title}
                </span>
            </div>
            <span className="font-mono text-[22px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                {value.toLocaleString()}
            </span>
        </div>
    );
}


export default function RmoManagement({ orders, workspace, stats }: Props) {
    const [isLoadingID, setIsLoadingID] = useState<number | null>(null);
    const currentStats = Array.isArray(stats) ? stats[0] : stats;

    const statCards = [
        {
            title: 'Assigned Orders',
            value: currentStats?.assigned_orders || 0,
            icon: ClipboardListIcon,
        },
        {
            title: 'Total Called',
            value: currentStats?.total_called || 0,
            icon: PhoneIcon,
        },
        {
            title: 'Total Pending',
            value: currentStats?.total_pending || 0,
            icon: ClockIcon,
        },
        {
            title: 'Total Delivered',
            value: currentStats?.total_delivered || 0,
            icon: CheckCircleIcon,
        },
        {
            title: 'Total Returning',
            value: currentStats?.total_returning || 0,
            icon: RotateCcw,
        },
        {
            title: 'Total Undeliverable',
            value: currentStats?.total_undeliverable || 0,
            icon: XCircleIcon,
        },
        {
            title: 'Out for Delivery',
            value: currentStats?.total_out_for_delivery || 0,
            icon: TruckIcon,
        },
    ];

    // const initialSorting = useMemo(() => {
    //     return toFrontendSort(query?.sort ?? null);
    // }, [query?.sort]);

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
            accessorKey: 'order_number',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Order Number'} />
            ),
            cell: ({ row }) => row.original.order.order_number
        },
        {
            accessorFn: (row) =>
                (row.order.items ?? []).map((item) => item.name).join(', '),
            id: 'items',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader enabled={false} column={column} title={'Orders'} />
            ),
            cell: ({ row }) => {
                const items = row.original.order.items ?? [];
                const page = row.original.page.name;

                return (
                    <div>
                        <ul>
                            {items.map((item, index) => (
                                <li key={index}>{item.name}</li>
                            ))}
                        </ul>
                        <p className="text-gray-500">{page}</p>
                    </div>
                );
            },
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
                            {row.original.order.shipping_address?.full_name}
                        </p>
                        <p className="mt-1 flex items-center text-xs text-gray-500">
                            <Phone className="m-0 mr-1 h-3 w-3" />
                            {row.original.order.shipping_address?.phone_number}
                        </p>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <div className="flex w-40 items-center truncate">
                                    <MapPin className="mr-1 h-3 w-3 shrink-0" />
                                    <span className="truncate">
                                        {
                                            row.original.order.shipping_address
                                                ?.full_address
                                        }
                                    </span>
                                </div>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p className="max-w-xs">
                                    {
                                        row.original.order.shipping_address?.full_address
                                    }
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                );
            },
        },
        {
            accessorKey: 'order.shipping_address.city_order_summary.rts_rate',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Location RTS'} />
            ),
            cell: ({ row }) => percentageFormatter(row.original.order?.shipping_address?.city_order_summary?.rts_rate ?? 0)
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
            accessorKey: 'conferrer.name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Confirmed By'} />
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
                                        const statusClass =
                                            getStatusBadgeClass(orderStatus);
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
        // {
        //     accessorKey: 'created_at',
        //     enableSorting: true,
        //     header: ({ column }) => (
        //         <SortableHeader
        //             column={column}
        //             title={'Actions'}
        //             enabled={false}
        //         />
        //     ),
        //     cell: ({ row }) => {
        //         return (
        //             <div>
        //                 <Tooltip>
        //                     <TooltipTrigger asChild>
        //                         <UserRoundPlus className="h-4 w-4" />
        //                     </TooltipTrigger>
        //                     <TooltipContent>Assign to me</TooltipContent>
        //                 </Tooltip>
        //             </div>
        //         );
        //     },
        // },
    ];

    return (
        <AppLayout>
            <div className="p-4 md:p-6">
                <div className="">
                    <PageHeader
                        title="RMO Management"
                        description="Track and update delivery status for items out today"
                    />
                    <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {statCards.map((card, index) => (
                            <StatCard key={index} {...card} />
                        ))}
                    </div>
                    <div className="mb-3 flex items-center gap-2">
                        <div className="relative w-full max-w-xs">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                            <input
                                type="text"
                                className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-[family-name:--font-dm-mono] text-[12px] text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                placeholder="Search orders..."
                            />
                        </div>
                    </div>

                    <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={columns}
                            enableInternalPagination={false}
                            data={orders.data || []}
                            meta={{ ...omit(orders, ['data']) }}
                            onFetch={(params) => {
                                router.get(
                                    `/workspaces/${workspace.slug}/rts/rmo-management`,
                                    {
                                        sort: params?.sort,
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
            </div>
        </AppLayout>
    );
}
