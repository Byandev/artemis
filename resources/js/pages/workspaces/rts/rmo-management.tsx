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
import { currencyFormatter, percentageFormatter } from '@/lib/utils';
import { PaginatedData } from '@/types';
import {
    ORDER_STATUSES,
    OrderForDelivery,
    getStatusBadgeClass,
} from '@/types/models/Pancake/OrderForDelivery';
import { Workspace } from '@/types/models/Workspace';
import { router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    ChevronUp,
    Loader2,
    MapPin,
    Phone,
    Search,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import { omit } from 'lodash';
import { toFrontendSort } from '@/lib/sort';
import workspaces from '@/routes/workspaces';


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

    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    // Static J&T Status Options
    const parcelStatusOptions = [
        'DELIVERED',
        'PENDING',
        'RETURNED',
        'UNDELIVERABLE',
        'CANCELLED',
        'SHIPPED',
        'OUT FOR DELIVERY'
    ];

    // Get unique pages with their IDs and names
    const uniquePages = useMemo(() => {
        const pagesMap = new Map();
        orders.data?.forEach(order => {
            if (order.page?.id && order.page?.name) {
                pagesMap.set(order.page.id, order.page.name);
            }
        });
        return Array.from(pagesMap.entries()).map(([id, name]) => ({ id: String(id), name }));
    }, [orders.data]);

    // Function to get badge class for parcel status
    const getParcelStatusBadgeClass = (status: string) => {
        const statusUpper = status?.toUpperCase() || '';
        if (statusUpper === 'DELIVERED') return 'bg-green-100 text-green-800';
        if (statusUpper === 'RETURNED') return 'bg-red-100 text-red-800';
        if (statusUpper === 'PENDING') return 'bg-yellow-100 text-yellow-800';
        if (statusUpper === 'UNDELIVERABLE') return 'bg-orange-100 text-orange-800';
        if (statusUpper === 'CANCELLED') return 'bg-gray-100 text-gray-800';
        if (statusUpper === 'SHIPPED') return 'bg-blue-100 text-blue-800';
        if (statusUpper === 'OUT FOR DELIVERY') return 'bg-purple-100 text-purple-800';
        return 'bg-gray-100 text-gray-800';
    };

    // Update the useEffect to properly handle all filters
    useEffect(() => {
        const timer = setTimeout(() => {
            const currentSort = query?.sort;

            router.get(
                workspaces.rts.rmoManagement({ workspace }),
                {
                    sort: currentSort,
                    'filter[search]': searchValue || undefined,
                    'filter[status]': selectedStatus || undefined,
                    'filter[page_id]': selectedPageId || undefined,
                    'filter[parcel_status]': selectedParcelStatus || undefined,
                    'filter[shop_id]': query?.filter?.shop_id || undefined,
                    page: (searchValue || selectedStatus || selectedPageId || selectedParcelStatus) ? 1 : (query?.page ?? 1),
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
    }, [
        searchValue,
        selectedStatus,
        selectedPageId,
        selectedParcelStatus,
        query?.sort,
        query?.filter?.shop_id,
    ]);

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

    // Handle status filter change
    const handleStatusFilterChange = (status: string) => {
        setSelectedStatus(status === 'ALL' ? '' : status);
    };

    // Handle page filter change - now using page ID instead of name
    const handlePageFilterChange = (pageId: string) => {
        setSelectedPageId(pageId === 'ALL' ? '' : pageId);
    };

    // Handle parcel status filter change
    const handleParcelStatusFilterChange = (parcelStatus: string) => {
        setSelectedParcelStatus(parcelStatus === 'ALL' ? '' : parcelStatus);
    };

    // Clear all filters
    const clearFilters = () => {
        setSearchValue('');
        setSelectedStatus('');
        setSelectedPageId('');
        setSelectedParcelStatus('');
    };

    const columns: ColumnDef<OrderForDelivery>[] = [
        {
            accessorKey: 'order_number',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Order Number'} />
            ),
            cell: ({ row }) => row.original.order.order_number,
        },
        {
            accessorFn: (row) =>
                (row.order.items ?? []).map((item) => item.name).join(', '),
            id: 'items',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader
                    enabled={false}
                    column={column}
                    title={'Orders'}
                />
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
            cell: ({ row }) => {
                const parcelStatus = row.original.order.parcel_status;
                return (
                    <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${getParcelStatusBadgeClass(parcelStatus)}`}>
                        {parcelStatus || 'N/A'}
                    </span>
                );
            },
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
                                        row.original.order.shipping_address
                                            ?.full_address
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
            cell: ({ row }) =>
                percentageFormatter(
                    row.original.order?.shipping_address?.city_order_summary
                        ?.rts_rate ?? 0,
                ),
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
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                            <div className="relative w-80 sm:w-64">
                                <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <Search className="z-10 h-4 w-4 text-gray-400" />
                                </div>

                                <Input
                                    type="text"
                                    placeholder="Search by order #, tracking #, rider, or conferrer."
                                    className="h-9 w-80 pl-8 text-sm"
                                    value={searchValue}
                                    onChange={(e) =>
                                        setSearchValue(e.target.value)
                                    }
                                />
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                {/* Page Filter Dropdown */}
                                <DropdownMenu>
                                    <DropdownMenuTrigger className="flex items-center gap-1 rounded-lg border px-2.5 py-2 text-xs font-medium transition-all hover:opacity-80">
                                        {uniquePages.find(
                                            (p) => p.id === selectedPageId,
                                        )?.name || 'All Pages'}
                                        <ChevronUp className="h-3 w-3" />
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="max-h-80 overflow-y-auto"
                                    >
                                        <DropdownMenuItem
                                            className="p-0 focus:bg-transparent"
                                            onClick={() =>
                                                handlePageFilterChange('ALL')
                                            }
                                        >
                                            <div className="w-full px-2 py-1.5 text-xs hover:bg-gray-100">
                                                <span>All Pages</span>
                                            </div>
                                        </DropdownMenuItem>
                                        {uniquePages.map((page) => {
                                            return (
                                                <DropdownMenuItem
                                                    className="p-0 focus:bg-transparent"
                                                    key={page.id}
                                                    onClick={() =>
                                                        handlePageFilterChange(
                                                            page.id,
                                                        )
                                                    }
                                                >
                                                    <div className="w-full px-2 py-1.5 text-xs hover:bg-gray-100">
                                                        <span>{page.name}</span>
                                                    </div>
                                                </DropdownMenuItem>
                                            );
                                        })}
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                {/* J&T Status Filter Dropdown */}
                                <DropdownMenu>
                                    <DropdownMenuTrigger className="flex items-center gap-1 rounded-lg border px-2.5 py-2 text-xs font-medium transition-all hover:opacity-80">
                                        {selectedParcelStatus || 'J&T Status'}
                                        <ChevronUp className="h-3 w-3" />
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="max-h-80 overflow-y-auto"
                                    >
                                        <DropdownMenuItem
                                            className="p-0 focus:bg-transparent"
                                            onClick={() =>
                                                handleParcelStatusFilterChange(
                                                    'ALL',
                                                )
                                            }
                                        >
                                            <div className="w-full px-2 py-1.5 text-xs hover:bg-gray-100">
                                                <span>All Status</span>
                                            </div>
                                        </DropdownMenuItem>
                                        {parcelStatusOptions.map(
                                            (parcelStatus) => {
                                                return (
                                                    <DropdownMenuItem
                                                        className="p-0 focus:bg-transparent"
                                                        key={parcelStatus}
                                                        onClick={() =>
                                                            handleParcelStatusFilterChange(
                                                                parcelStatus,
                                                            )
                                                        }
                                                    >
                                                        <div className="w-full px-2 py-1.5 text-xs hover:bg-gray-100">
                                                            <span
                                                                className={`rounded-lg px-2 py-0.5 text-xs ${getParcelStatusBadgeClass(parcelStatus)}`}
                                                            >
                                                                {parcelStatus}
                                                            </span>
                                                        </div>
                                                    </DropdownMenuItem>
                                                );
                                            },
                                        )}
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                {/* Status Filter Dropdown */}
                                <DropdownMenu>
                                    <DropdownMenuTrigger className="flex items-center gap-1 rounded-lg border px-2.5 py-2 text-xs font-medium transition-all hover:opacity-80">
                                        {selectedStatus || 'Order Status'}
                                        <ChevronUp className="h-3 w-3" />
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="max-h-80 overflow-y-auto"
                                    >
                                        <DropdownMenuItem
                                            className="p-0 focus:bg-transparent"
                                            onClick={() =>
                                                handleStatusFilterChange('ALL')
                                            }
                                        >
                                            <div className="w-full px-2 py-1.5 text-xs hover:bg-gray-100">
                                                <span>All Status</span>
                                            </div>
                                        </DropdownMenuItem>
                                        {ORDER_STATUSES.map((orderStatus) => {
                                            return (
                                                <DropdownMenuItem
                                                    className="p-0 focus:bg-transparent"
                                                    key={orderStatus}
                                                    onClick={() =>
                                                        handleStatusFilterChange(
                                                            orderStatus,
                                                        )
                                                    }
                                                >
                                                    <div className="w-full px-2 py-1.5 text-xs hover:bg-gray-100">
                                                        <span
                                                            className={getStatusBadgeClass(
                                                                orderStatus,
                                                            )}
                                                        >
                                                            {orderStatus}
                                                        </span>
                                                    </div>
                                                </DropdownMenuItem>
                                            );
                                        })}
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                {/* Clear Filters Button */}
                                {(selectedStatus ||
                                    selectedPageId ||
                                    selectedParcelStatus ||
                                    searchValue) && (
                                    <button
                                        onClick={clearFilters}
                                        className="rounded-lg border px-2.5 py-2 text-xs font-medium transition-all hover:bg-gray-50"
                                    >
                                        Clear Filters
                                    </button>
                                )}
                            </div>
                        </div>
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
                                        'filter[search]':
                                            searchValue || undefined,
                                        'filter[status]':
                                            selectedStatus || undefined,
                                        'filter[page_id]':
                                            selectedPageId || undefined,
                                        'filter[parcel_status]':
                                            selectedParcelStatus || undefined,
                                        'filter[shop_id]':
                                            query?.filter?.shop_id || undefined,
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
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
}
