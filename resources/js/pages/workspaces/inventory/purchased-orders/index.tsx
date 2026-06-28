import PageHeader from '@/components/common/PageHeader';
import { DeleteOrderDialog } from '@/components/inventory/delete-order-dialog';
import {
    DeliveryTarget,
    RecordDeliveryDialog,
} from '@/components/inventory/record-delivery-dialog';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import flatpickr from 'flatpickr';
import { debounce, omit } from 'lodash';
import {
    Check,
    ChevronDown,
    ChevronRight,
    Download,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    Truck,
} from 'lucide-react';
import moment from 'moment';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import DateOption = flatpickr.Options.DateOption;

interface ItemDelivery {
    id: number;
    delivery_date: string;
    delivery_no: string | null;
    qty: number;
}

interface PurchasedOrderItem {
    id: number;
    count: number;
    amount: string;
    total_amount: string;
    delivered_qty: number;
    balance: number;
    fulfillment_status: 'waiting' | 'partial' | 'delivered';
    deliveries: ItemDelivery[];
    inventory_item?: {
        sku: string;
        product?: { name: string };
    };
}

const FULFILLMENT_BADGE: Record<string, { label: string; color: string }> = {
    partial: {
        label: 'Partial',
        color: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    },
    delivered: {
        label: 'Delivered',
        color: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400',
    },
};

const TIMELINESS_BADGE: Record<string, { label: string; color: string }> = {
    ontime: {
        label: 'On Time',
        color: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400',
    },
    delayed: {
        label: 'Late',
        color: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400',
    },
};

const STATUSES: Record<number, { label: string; color: string }> = {
    1: {
        label: 'For Approval',
        color: 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400',
    },
    2: {
        label: 'Approved',
        color: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400',
    },
    3: {
        label: 'To Pay',
        color: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    },
    4: {
        label: 'Paid',
        color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400',
    },
    5: {
        label: 'For Purchase',
        color: 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-400',
    },
    6: {
        label: 'Waiting For Delivery',
        color: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-400',
    },
    7: {
        label: 'Delivered',
        color: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400',
    },
    8: {
        label: 'Cancelled',
        color: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400',
    },
};

interface PurchasedOrder {
    id: number;
    issue_date: string;
    delivery_no: string | null;
    expected_delivery_date: string | null;
    cust_po_no: string | null;
    control_no: string | null;
    delivery_fee: string;
    total_amount: string;
    status: number;
    fulfillment_status: 'waiting' | 'partial' | 'delivered';
    delivery_timeliness: 'ontime' | 'delayed' | null;
    items: PurchasedOrderItem[];
}

interface Totals {
    delivery_fee: number;
    cogs: number;
    total_amount: number;
}

interface Props {
    workspace: Workspace;
    orders: PaginatedData<PurchasedOrder>;
    totals: Totals;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string; start_date?: string; end_date?: string };
    };
}

export default function PurchasedOrderIndex({
    workspace,
    orders,
    totals,
    query,
}: Props) {
    const [deletingOrder, setDeletingOrder] = useState<PurchasedOrder | null>(
        null,
    );
    const [deliveryFor, setDeliveryFor] = useState<{
        item: PurchasedOrderItem;
        delivery: DeliveryTarget | null;
    } | null>(null);
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const baseUrl = `/workspaces/${workspace.slug}/inventory/purchased-orders`;

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [dateRange, setDateRange] = useState<string[]>(() => [
        query?.filter?.start_date ?? '',
        query?.filter?.end_date ?? '',
    ]);
    const canCreatePurchasedOrders = usePermission(
        PERMISSIONS.CreatePurchasedOrders,
    );
    const canEditPurchasedOrders = usePermission(
        PERMISSIONS.EditPurchasedOrders,
    );
    const canDeletePurchasedOrders = usePermission(
        PERMISSIONS.DeletePurchasedOrders,
    );
    const canManagePurchasedOrders =
        canEditPurchasedOrders || canDeletePurchasedOrders;
    const canUsePurchasedOrderActions =
        canCreatePurchasedOrders || canManagePurchasedOrders;

    const buildFilter = (search: string, range: string[]) => ({
        search: search || undefined,
        start_date: range[0] || undefined,
        end_date: range[1] || undefined,
    });

    const performQuery = useCallback(
        debounce((search: string, range: string[]) => {
            router.get(
                baseUrl,
                {
                    sort: query?.sort,
                    filter: buildFilter(search, range),
                    page: 1,
                    per_page: query?.perPage ?? orders.per_page,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['orders', 'totals', 'query'],
                },
            );
        }, 400),
        [baseUrl, query?.sort, query?.perPage, orders.per_page],
    );

    useEffect(() => {
        const filterChanged =
            searchValue !== (query?.filter?.search ?? '') ||
            (dateRange[0] || undefined) !==
                (query?.filter?.start_date ?? undefined) ||
            (dateRange[1] || undefined) !==
                (query?.filter?.end_date ?? undefined);
        if (filterChanged) {
            performQuery(searchValue, dateRange);
        }
        return () => performQuery.cancel();
    }, [searchValue, dateRange]);

    const columns = useMemo<ColumnDef<PurchasedOrder>[]>(() => {
        const cols: ColumnDef<PurchasedOrder>[] = [
            {
                id: 'expander',
                enableSorting: false,
                meta: {
                    headerClassName: 'w-0 px-0',
                    cellClassName: 'w-0 px-0',
                },
                header: () => null,
                cell: ({ row }) => (
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            row.toggleExpanded();
                        }}
                        aria-label={
                            row.getIsExpanded()
                                ? 'Collapse order items'
                                : 'Expand order items'
                        }
                        className="flex h-6 w-5 items-center justify-center text-gray-400 transition-all hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <ChevronRight
                            className={`h-3.5 w-3.5 transition-transform ${
                                row.getIsExpanded() ? 'rotate-90' : ''
                            }`}
                        />
                    </button>
                ),
            },
            {
                accessorKey: 'issue_date',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Issue Date" />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                        {row.original.issue_date
                            ? row.original.issue_date.slice(0, 10)
                            : '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'cust_po_no',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="PO No." />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                        {row.original.cust_po_no || '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'items',
                enableSorting: false,
                header: () => (
                    <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                        Items
                    </span>
                ),
                cell: ({ row }) => (
                    <div className="flex flex-col">
                        {[...row.original.items].map((i, index) => (
                            <span
                                key={`${row.original.id}-${i.inventory_item?.sku}-${index}`}
                            >
                                {i.inventory_item?.sku}
                            </span>
                        ))}
                    </div>
                ),
            },

            {
                id: 'delivered_count',
                enableSorting: false,
                header: () => (
                    <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                        Delivered
                    </span>
                ),
                cell: ({ row }) => {
                    const deliveredQty = row.original.items.reduce(
                        (sum, i) => sum + i.delivered_qty,
                        0,
                    );

                    return (
                        <span
                            className={`font-mono text-[11px] text-gray-600 dark:text-gray-400`}
                        >
                            {deliveredQty.toLocaleString()}
                        </span>
                    );
                },
            },
            {
                id: 'total_qty',
                enableSorting: false,
                header: () => (
                    <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                        Total Count
                    </span>
                ),
                cell: ({ row }) => {
                    const totalQty = row.original.items.reduce(
                        (sum, i) => sum + i.count,
                        0,
                    );
                    return (
                        <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                            {totalQty.toLocaleString()}
                        </span>
                    );
                },
            },
            {
                id: 'subtotal',
                enableSorting: false,
                header: () => (
                    <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                        Subtotal
                    </span>
                ),
                cell: ({ row }) => {
                    const subtotal =
                        Number(row.original.total_amount) -
                        Number(row.original.delivery_fee);
                    return (
                        <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                            ₱
                            {subtotal.toLocaleString('en-PH', {
                                minimumFractionDigits: 2,
                            })}
                        </span>
                    );
                },
            },
            {
                accessorKey: 'delivery_fee',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Delivery Fee" />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                        ₱
                        {Number(row.original.delivery_fee).toLocaleString(
                            'en-PH',
                            {
                                minimumFractionDigits: 2,
                            },
                        )}
                    </span>
                ),
            },
            {
                accessorKey: 'total_amount',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Total Amount" />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[12px] font-semibold text-gray-800 dark:text-gray-200">
                        ₱
                        {Number(row.original.total_amount).toLocaleString(
                            'en-PH',
                            {
                                minimumFractionDigits: 2,
                            },
                        )}
                    </span>
                ),
            },
            {
                accessorKey: 'status',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Status" />
                ),
                cell: ({ row }) => {
                    const order = row.original;
                    const s = STATUSES[order.status] ?? STATUSES[1];
                    const badgeClass = `inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-mono text-[11px] font-medium ${s.color}`;

                    if (!canEditPurchasedOrders) {
                        return <span className={badgeClass}>{s.label}</span>;
                    }

                    return (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button
                                    type="button"
                                    className={`${badgeClass} cursor-pointer transition-opacity hover:opacity-80`}
                                >
                                    {s.label}
                                    <ChevronDown className="h-3 w-3 opacity-60" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="w-44">
                                {Object.entries(STATUSES).map(
                                    ([value, info]) => {
                                        const num = Number(value);
                                        return (
                                            <DropdownMenuItem
                                                key={value}
                                                onClick={() =>
                                                    updateOrderStatus(
                                                        order,
                                                        num,
                                                    )
                                                }
                                                className="text-[12px]"
                                            >
                                                <Check
                                                    className={`mr-2 h-3.5 w-3.5 ${
                                                        num === order.status
                                                            ? 'opacity-100'
                                                            : 'opacity-0'
                                                    }`}
                                                />
                                                {info.label}
                                            </DropdownMenuItem>
                                        );
                                    },
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    );
                },
            },
            {
                accessorKey: 'expected_delivery_date',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Expected Delivery" />
                ),
                cell: ({ row }) => {
                    const order = row.original;
                    const tBadge = order.delivery_timeliness
                        ? TIMELINESS_BADGE[order.delivery_timeliness]
                        : null;
                    return (
                        <div className="flex items-center gap-2">
                            {canEditPurchasedOrders ? (
                                <DatePicker
                                    id={`expected-${order.id}`}
                                    mode="single"
                                    compact
                                    placeholder="Set date"
                                    defaultDate={
                                        order.expected_delivery_date?.slice(
                                            0,
                                            10,
                                        ) || undefined
                                    }
                                    onChange={(_dates, dateStr) =>
                                        updateOrderExpectedDate(order, dateStr)
                                    }
                                />
                            ) : (
                                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                                    {order.expected_delivery_date?.slice(
                                        0,
                                        10,
                                    ) ?? '—'}
                                </span>
                            )}
                            {tBadge && (
                                <span
                                    className={`inline-flex w-fit items-center rounded-full px-2 py-0.5 font-mono text-[10px] font-medium ${tBadge.color}`}
                                >
                                    {tBadge.label}
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                id: 'actions',
                header: () => (
                    <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                        Actions
                    </div>
                ),
                cell: ({ row }) => {
                    const order = row.original;
                    return (
                        <div className="flex justify-center">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                        <MoreHorizontal className="h-3.5 w-3.5" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-36"
                                >
                                    {canEditPurchasedOrders && (
                                        <DropdownMenuItem
                                            onClick={() =>
                                                router.get(
                                                    `${baseUrl}/${order.id}/edit`,
                                                )
                                            }
                                        >
                                            <Pencil className="mr-2 h-3.5 w-3.5" />
                                            Edit
                                        </DropdownMenuItem>
                                    )}
                                    {canEditPurchasedOrders &&
                                        canDeletePurchasedOrders && (
                                            <DropdownMenuSeparator />
                                        )}
                                    {canDeletePurchasedOrders && (
                                        <DropdownMenuItem
                                            className="text-red-600 focus:text-red-600 dark:text-red-400"
                                            onClick={() =>
                                                setDeletingOrder(order)
                                            }
                                        >
                                            <Trash2 className="mr-2 h-3.5 w-3.5" />
                                            Delete
                                        </DropdownMenuItem>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    );
                },
            },
        ];
        return cols.filter(
            (column) => canManagePurchasedOrders || column.id !== 'actions',
        );
    }, [
        baseUrl,
        canDeletePurchasedOrders,
        canEditPurchasedOrders,
        canManagePurchasedOrders,
    ]);

    const updateOrderExpectedDate = (order: PurchasedOrder, value: string) => {
        router.put(
            `/workspaces/${workspace.slug}/inventory/po-monitoring/orders/${order.id}/expected-delivery`,
            { expected_delivery_date: value || null },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Expected delivery date updated'),
                onError: () => toast.error('Failed to update expected date'),
            },
        );
    };

    const updateOrderStatus = (order: PurchasedOrder, status: number) => {
        if (status === order.status) return;
        router.put(
            `/workspaces/${workspace.slug}/inventory/po-monitoring/orders/${order.id}/status`,
            { status },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Status updated'),
                onError: () => toast.error('Failed to update status'),
            },
        );
    };

    const deleteDelivery = (delivery: ItemDelivery) => {
        if (!window.confirm('Delete this delivery? This cannot be undone.'))
            return;
        router.delete(
            `/workspaces/${workspace.slug}/inventory/po-monitoring/deliveries/${delivery.id}`,
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Delivery deleted'),
                onError: () => toast.error('Failed to delete delivery'),
            },
        );
    };

    const renderOrderItems = (order: PurchasedOrder) => (
        <div className="space-y-2 px-4 py-3">
            {order.items.map((item) => {
                const badge =
                    item.fulfillment_status === 'waiting'
                        ? null
                        : FULFILLMENT_BADGE[item.fulfillment_status];
                return (
                    <div
                        key={item.id}
                        className="rounded-[10px] border border-black/6 bg-white p-3 dark:border-white/8 dark:bg-zinc-900"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-[12px] font-medium text-gray-800 dark:text-gray-200">
                                    {item.inventory_item?.sku ?? '—'}
                                </span>
                                {item.inventory_item?.product && (
                                    <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                        {item.inventory_item.product.name}
                                    </span>
                                )}
                                {badge && (
                                    <span
                                        className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[10px] font-medium ${badge.color}`}
                                    >
                                        {badge.label}
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                                    {item.delivered_qty} / {item.count} delivered
                                    {item.balance > 0 && (
                                        <span className="text-amber-600 dark:text-amber-400">
                                            {' '}
                                            · {item.balance} left
                                        </span>
                                    )}
                                </span>
                                {canEditPurchasedOrders && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setDeliveryFor({
                                                item,
                                                delivery: null,
                                            })
                                        }
                                        className="flex h-7 items-center gap-1.5 rounded-lg bg-emerald-600 px-3 font-mono! text-[11px]! font-medium text-white transition-all hover:bg-emerald-700"
                                    >
                                        <Plus className="h-3 w-3" />
                                        Record Delivery
                                    </button>
                                )}
                            </div>
                        </div>

                        {item.deliveries.length > 0 && (
                            <div className="mt-2 space-y-1 border-t border-black/5 pt-2 dark:border-white/5">
                                {item.deliveries.map((d) => (
                                    <div
                                        key={d.id}
                                        className="flex items-center justify-between gap-3"
                                    >
                                        <div className="flex items-center gap-3 font-mono text-[11px] text-gray-600 dark:text-gray-400">
                                            <Truck className="h-3 w-3 text-gray-400" />
                                            <span>{d.delivery_date?.slice(0, 10)}</span>
                                            <span className="text-gray-400">
                                                {d.delivery_no || 'No DR'}
                                            </span>
                                            <span>{d.qty} unit{d.qty === 1 ? '' : 's'}</span>
                                        </div>
                                        {canEditPurchasedOrders && (
                                            <div className="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setDeliveryFor({
                                                            item,
                                                            delivery: d,
                                                        })
                                                    }
                                                    className="flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition-all hover:bg-stone-100 hover:text-gray-600 dark:hover:bg-zinc-800"
                                                    aria-label="Edit delivery"
                                                >
                                                    <Pencil className="h-3 w-3" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => deleteDelivery(d)}
                                                    className="flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition-all hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950"
                                                    aria-label="Delete delivery"
                                                >
                                                    <Trash2 className="h-3 w-3" />
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Purchased Orders`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Purchased Orders"
                    description="Manage your inventory purchased orders."
                >
                    {canUsePurchasedOrderActions && (
                        <a
                            href={`${baseUrl}/export?${new URLSearchParams(
                                Object.entries({
                                    'filter[search]': searchValue || '',
                                    'filter[start_date]': dateRange[0] || '',
                                    'filter[end_date]': dateRange[1] || '',
                                    sort: query?.sort ?? '',
                                }).filter(([, v]) => v !== ''),
                            ).toString()}`}
                            className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-zinc-800"
                        >
                            <Download className="h-3.5 w-3.5" />
                            Export
                        </a>
                    )}
                    {canCreatePurchasedOrders && (
                        <button
                            onClick={() => router.get(`${baseUrl}/create`)}
                            className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            Add Order
                        </button>
                    )}
                </PageHeader>

                <div className="mb-3 flex flex-col items-stretch gap-2 md:flex-row md:items-center">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search Delivery No., Cust PO No., Control No.…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                    <DatePicker
                        id="purchased-orders-date-range"
                        mode="range"
                        placeholder="Filter by issue date"
                        defaultDate={
                            (dateRange[0] && dateRange[1]
                                ? dateRange
                                : undefined) as never as DateOption
                        }
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setDateRange([
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                ]);
                            } else if (dates.length === 0) {
                                setDateRange(['', '']);
                            }
                        }}
                    />
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={orders.data || []}
                        initialSorting={initialSorting}
                        renderSubRow={(row) => renderOrderItems(row.original)}
                        meta={{ ...omit(orders, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    filter: buildFilter(searchValue, dateRange),
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query?.perPage ??
                                        orders.per_page,
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

                <ul className="mt-3 flex flex-col items-start gap-1 rounded-[10px] border border-black/6 bg-stone-50 px-4 py-3 dark:border-white/6 dark:bg-zinc-900/60">
                    <li className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                        Total COGS : ₱
                        {Number(totals.cogs).toLocaleString('en-PH', {
                            minimumFractionDigits: 2,
                        })}
                    </li>
                    <li className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                        Total Delivery Fee : ₱
                        {Number(totals.delivery_fee).toLocaleString('en-PH', {
                            minimumFractionDigits: 2,
                        })}
                    </li>
                    <li className="font-mono text-[12px] font-semibold text-emerald-700 dark:text-emerald-400">
                        Total Amount : ₱
                        {Number(totals.total_amount).toLocaleString('en-PH', {
                            minimumFractionDigits: 2,
                        })}
                    </li>
                </ul>

                <DeleteOrderDialog
                    order={deletingOrder}
                    workspace={workspace}
                    onClose={() => setDeletingOrder(null)}
                />

                <RecordDeliveryDialog
                    workspace={workspace}
                    itemId={deliveryFor?.item.id ?? null}
                    itemBalance={deliveryFor?.item.balance}
                    delivery={deliveryFor?.delivery}
                    open={deliveryFor !== null}
                    onClose={() => setDeliveryFor(null)}
                />
            </div>
        </AppLayout>
    );
}
