import { SortableHeader } from '@/components/ui/data-table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { currencyFormatter, percentageFormatter } from '@/lib/utils';
import { OrderForDelivery, OrderStatus } from '@/types/models/Pancake/OrderForDelivery';
import { ColumnDef } from '@tanstack/react-table';
import { Loader2, MapPin, Phone, UserPlus, X } from 'lucide-react';
import { RmoStatusPicker } from './RmoStatusPicker';
import { orderStatusConfig, ParcelStatusEntry } from './rmo-config';
import { usePage } from '@inertiajs/react';

interface CreateRmoColumnsOptions {
    onChangeStatus: (status: string, orderId: number) => void;
    /** Parcel status config — authenticated uses snake_case keys, public uses UPPERCASE. */
    parcelStatusConfig: Record<string, ParcelStatusEntry>;
    /** Transform the raw parcel_status string to the key used in parcelStatusConfig. */
    normalizeParcelStatus?: (status: string) => string;
    /** Public view only — called when "Assign to me" is clicked on an unassigned row. */
    onAssignToMe?: (orderId: number, currentStatus: string) => void;
    /** Public view only — called when the × is clicked to clear an assignee. */
    onRemoveAssignee?: (orderId: number, currentStatus: string) => void;
    /** Disable the status picker (e.g. public view with no identity set). */
    disableStatusChange?: boolean;
}

export function createRmoColumns({
    onChangeStatus,
    parcelStatusConfig,
    normalizeParcelStatus = (s) => s?.toLowerCase(),
    onAssignToMe,
    onRemoveAssignee,
    disableStatusChange = false,
}: CreateRmoColumnsOptions): ColumnDef<OrderForDelivery>[] {


    const { props } = usePage();
    const user = props.auth?.user;

    const isAuthenticated = !!user;


    return [
        {
            id: 'order_number',
            accessorKey: 'order_number',
            header: ({ column }) => <SortableHeader column={column} title="Order #" />,
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                    {row.original.order.order_number}
                </span>
            ),
        },
        {
            accessorFn: (row) => (row.order.items ?? []).map((item) => item.name).join(', '),
            id: 'items',
            enableSorting: false,
            header: ({ column }) => <SortableHeader enabled={false} column={column} title="Items" />,
            cell: ({ row }) => {
                const items = row.original.order.items ?? [];
                const pageName = row.original.page?.name;
                return (
                    <div className="space-y-0.5">
                        {items.map((item, i) => (
                            <p key={i} className="text-[12px] font-medium leading-snug text-gray-800 dark:text-gray-200">
                                {item.name}
                            </p>
                        ))}
                        {pageName && (
                            <p className="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{pageName}</p>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'order_tracking_code',
            accessorKey: 'order.tracking_code',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Tracking #" />,
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                    {row.original.order.tracking_code ?? '—'}
                </span>
            ),
        },
        {
            id: 'order_parcel_status',
            accessorKey: 'order.parcel_status',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="J&T Status" />,
            cell: ({ row }) => {
                const key = normalizeParcelStatus(row.original.order.parcel_status ?? '');
                const cfg = parcelStatusConfig[key];
                return (
                    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium ${cfg?.pill ?? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'}`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${cfg?.dot ?? 'bg-gray-400'}`} />
                        {cfg?.label ?? key ?? '—'}
                    </span>
                );
            },
        },
        {
            accessorKey: 'rider_name',
            header: ({ column }) => <SortableHeader column={column} title="Rider" />,
            cell: ({ row }) => (
                <div className="space-y-0.5">
                    <p className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                        {row.original.rider_name || '—'}
                    </p>
                    {row.original.rider_phone && (
                        <p className="flex items-center gap-1 text-[11px] text-gray-400 dark:text-gray-500">
                            <Phone className="h-3 w-3 shrink-0" />
                            {row.original.rider_phone}
                        </p>
                    )}
                </div>
            ),
        },
        {
            id: 'order_shipping_address_full_name',
            accessorKey: 'order.shipping_address.full_name',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Customer" />,
            cell: ({ row }) => {
                const addr = row.original.order.shipping_address;
                return (
                    <div className="space-y-0.5">
                        <p className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                            {addr?.full_name || '—'}
                        </p>
                        {addr?.phone_number && (
                            <p className="flex items-center gap-1 text-[11px] text-gray-400 dark:text-gray-500">
                                <Phone className="h-3 w-3 shrink-0" />
                                {addr.phone_number}
                            </p>
                        )}
                        {addr?.full_address && (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <p className="flex max-w-40 cursor-default items-center gap-1 text-[11px] text-gray-400 dark:text-gray-500">
                                        <MapPin className="h-3 w-3 shrink-0" />
                                        <span className="truncate">{addr.full_address}</span>
                                    </p>
                                </TooltipTrigger>
                                <TooltipContent side="bottom">
                                    <p className="max-w-xs text-xs">{addr.full_address}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'order_final_amount',
            accessorKey: 'order.final_amount',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="SRP" />,
            cell: ({ row }) => (
                <span className="font-mono text-[12px] tabular-nums text-gray-700 dark:text-gray-300">
                    {currencyFormatter(row.original.order.final_amount)}
                </span>
            ),
        },
        {
            id: 'order_shipping_address_city_order_summary_rts_rate',
            accessorKey: 'order.shipping_address.city_order_summary.rts_rate',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Loc. RTS" />,
            cell: ({ row }) => {
                const rate = row.original.order?.shipping_address?.city_order_summary?.rts_rate ?? 0;
                const isHigh = rate >= 0.4;
                return (
                    <span className={`text-[12px] font-medium tabular-nums ${isHigh ? 'text-red-500 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'}`}>
                        {percentageFormatter(rate)}
                    </span>
                );
            },
        },
        {
            id: 'cx_rts_rate',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Cx. RTS" />,
            cell: ({ row }) => {
                const rate = row.original.order?.cx_rts_rate ?? null;
                if (rate === null) {
                    return <span className="text-[12px] text-gray-300 dark:text-gray-600">—</span>;
                }
                const isHigh = rate >= 0.4;
                return (
                    <span className={`text-[12px] font-medium tabular-nums ${isHigh ? 'text-red-500 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'}`}>
                        {percentageFormatter(rate)}
                    </span>
                );
            },
        },
        {
            id: 'order_delivery_attempts',
            accessorKey: 'order.delivery_attempts',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Attempts" />,
            cell: ({ row }) => {
                const attempts = row.original.order.delivery_attempts ?? 0;
                const isMultiple = attempts > 1;
                return (
                    <span className={`inline-flex items-center justify-center rounded-full px-2 py-0.5 text-[11px] font-medium tabular-nums ${
                        isMultiple
                            ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'
                            : 'text-gray-500 dark:text-gray-400'
                    }`}>
                        {attempts}
                    </span>
                );
            },
        },
        {
            id: 'conferrer_name',
            accessorKey: 'conferrer.name',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Confirmed By" />,
            cell: ({ row }) => (
                <span className="text-[12px] text-gray-600 dark:text-gray-400">
                    {row.original.conferrer?.name ?? <span className="italic text-gray-300 dark:text-gray-600">—</span>}
                </span>
            ),
        },
        {
            accessorKey: 'assignee.name',
            enableSorting: false,
            header: ({ column }) => <SortableHeader enabled={false} column={column} title="Assignee" />,
            cell: ({ row }) => {
                const assignee = row.original.assignee;
                const orderId = row.original.order_id;
                const currentStatus = row.original.status;

                if (!assignee) {
                    if (onAssignToMe) {
                        return (
                            <button
                                onClick={() => onAssignToMe(orderId, currentStatus)}
                                className="flex items-center gap-1.5 rounded-lg border border-dashed border-black/10 dark:border-white/10 px-2.5 py-1 text-[11px] font-medium text-gray-400 dark:text-gray-500 transition-all hover:border-emerald-300 dark:hover:border-emerald-500/40 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 dark:hover:text-emerald-400"
                            >
                                <UserPlus className="h-3 w-3" />
                                Assign to me
                            </button>
                        );
                    }
                    return <span className="text-[12px] text-gray-300 dark:text-gray-600">—</span>;
                }

                return (
                    <div className="group flex items-center gap-1.5">
                        <span className="text-[12px] text-gray-700 dark:text-gray-300">
                            {assignee.name}
                        </span>
                        {onRemoveAssignee && (
                            <button
                                onClick={() => onRemoveAssignee(orderId, currentStatus)}
                                className="invisible flex h-4 w-4 shrink-0 items-center justify-center rounded text-gray-300 transition-colors hover:bg-red-50 hover:text-red-400 dark:text-gray-600 dark:hover:bg-red-500/10 dark:hover:text-red-400 group-hover:visible"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Status" />,
            cell: ({ row }) => {
                const orderId = row.original.order_id;
                const status = row.original.status as OrderStatus;
                const config = orderStatusConfig[status];

                if (!isAuthenticated) {
                    return (
                        <RmoStatusPicker
                            currentStatus={row.original.status as OrderStatus}
                            onChangeStatus={(status) => onChangeStatus(status, orderId)}
                            disabled={disableStatusChange}
                        />
                    );
                }

                return (
                    <div
                        className={`inline-flex w-fit items-center gap-2 rounded-full px-2 py-1 text-xs font-medium ${config?.pill ?? 'bg-gray-100 text-gray-600'}`}
                    >
                        <span
                            className={`h-2 w-2 rounded-full ${config?.dot ?? 'bg-gray-400'}`}
                        />
                        {status}
                    </div>
                );

            },
        },
    ];
}
