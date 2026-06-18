import PageHeader from '@/components/common/PageHeader';
import {
    RecordDeliveryDialog,
    type DeliveryTarget,
} from '@/components/inventory/record-delivery-dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import Pagination from '@/components/ui/pagination';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { format } from 'date-fns';
import flatpickr from 'flatpickr';
import { debounce } from 'lodash';
import {
    AlertTriangle,
    CalendarClock,
    Check,
    ChevronDown,
    ChevronRight,
    Hourglass,
    Package,
    PackageCheck,
    PackageOpen,
    Pencil,
    Plus,
    Search,
    Trash2,
    Truck,
} from 'lucide-react';
import moment from 'moment';
import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { toast } from 'sonner';
import DateOption = flatpickr.Options.DateOption;

interface Delivery {
    id: number;
    delivery_date: string;
    delivery_no: string | null;
    qty: number;
}

interface PurchasedOrderRef {
    id: number;
    issue_date: string;
    cust_po_no: string | null;
    control_no: string | null;
    delivery_no: string | null;
    status: number;
}

interface MonitoringItem {
    id: number;
    count: number;
    expected_delivery_date: string | null;
    delivered_qty: number;
    balance: number;
    fulfillment_status: 'waiting' | 'partial' | 'delivered';
    delivery_timeliness: 'ontime' | 'delayed' | null;
    remarks: string | null;
    purchased_order?: PurchasedOrderRef;
    inventory_item?: { sku: string; product?: { name: string } };
    deliveries: Delivery[];
}

interface Summary {
    total: number;
    waiting: number;
    partial: number;
    delivered: number;
    delayed: number;
}

type StatusFilter = '' | 'waiting' | 'partial' | 'delivered';
type DeliveryFilter = '' | 'ontime' | 'delayed';

interface Props {
    workspace: Workspace;
    items: PaginatedData<MonitoringItem>;
    summary: Summary;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
            start_date?: string;
            end_date?: string;
            status?: StatusFilter;
            delivery_status?: DeliveryFilter;
        };
    };
}

interface PillOption {
    value: string;
    label: string;
    dot: string;
    pill: string;
}

const PO_STATUS_OPTIONS: PillOption[] = [
    {
        value: '1',
        label: 'For Approval',
        dot: 'bg-amber-400',
        pill: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
    },
    {
        value: '2',
        label: 'Approved',
        dot: 'bg-blue-500',
        pill: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
    },
    {
        value: '3',
        label: 'To Pay',
        dot: 'bg-orange-400',
        pill: 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400',
    },
    {
        value: '4',
        label: 'Paid',
        dot: 'bg-teal-500',
        pill: 'bg-teal-50 text-teal-700 dark:bg-teal-500/10 dark:text-teal-400',
    },
    {
        value: '5',
        label: 'For Purchase',
        dot: 'bg-purple-400',
        pill: 'bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400',
    },
    {
        value: '6',
        label: 'Waiting For Delivery',
        dot: 'bg-cyan-500',
        pill: 'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400',
    },
    {
        value: '7',
        label: 'Delivered',
        dot: 'bg-emerald-500',
        pill: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
    },
    {
        value: '8',
        label: 'Cancelled',
        dot: 'bg-gray-400',
        pill: 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400',
    },
];

const DELIVERY_STATUS_OPTIONS: PillOption[] = [
    {
        value: 'ontime',
        label: 'On time',
        dot: 'bg-emerald-500',
        pill: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
    },
    {
        value: 'delayed',
        label: 'Delayed',
        dot: 'bg-red-500',
        pill: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
    },
];

const fmtDate = (d: string | null) => (d ? d.slice(0, 10) : '—');

export default function PoMonitoringIndex({
    workspace,
    items,
    summary,
    query,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/inventory/po-monitoring`;

    const canManage = usePermission(PERMISSIONS.EditPurchasedOrders);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [dateRange, setDateRange] = useState<string[]>(() => [
        query?.filter?.start_date ?? '',
        query?.filter?.end_date ?? '',
    ]);
    const [statusFilter, setStatusFilter] = useState<StatusFilter>(
        query?.filter?.status ?? '',
    );
    const [deliveryFilter, setDeliveryFilter] = useState<DeliveryFilter>(
        query?.filter?.delivery_status ?? '',
    );

    const [expanded, setExpanded] = useState<Set<number>>(new Set());
    const [recordingFor, setRecordingFor] = useState<MonitoringItem | null>(
        null,
    );
    const [editingDelivery, setEditingDelivery] = useState<{
        itemId: number;
        delivery: DeliveryTarget;
    } | null>(null);
    const [deletingDelivery, setDeletingDelivery] = useState<Delivery | null>(
        null,
    );
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const buildFilter = (
        search: string,
        range: string[],
        status: StatusFilter,
        delivery: DeliveryFilter,
    ) => ({
        search: search || undefined,
        start_date: range[0] || undefined,
        end_date: range[1] || undefined,
        status: status || undefined,
        delivery_status: delivery || undefined,
    });

    const fetchData = useCallback(
        (overrides?: { page?: number; per_page?: number }) => {
            router.get(
                baseUrl,
                {
                    sort: query?.sort,
                    filter: buildFilter(
                        searchValue,
                        dateRange,
                        statusFilter,
                        deliveryFilter,
                    ),
                    page: overrides?.page ?? 1,
                    per_page:
                        overrides?.per_page ?? query?.perPage ?? items.per_page,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['items', 'summary', 'query'],
                },
            );
        },
        [
            baseUrl,
            query?.sort,
            query?.perPage,
            items.per_page,
            searchValue,
            dateRange,
            statusFilter,
            deliveryFilter,
        ],
    );

    const debouncedFetch = useMemo(
        () => debounce(() => fetchData(), 400),
        [fetchData],
    );

    useEffect(() => {
        const changed =
            searchValue !== (query?.filter?.search ?? '') ||
            (dateRange[0] || undefined) !==
                (query?.filter?.start_date ?? undefined) ||
            (dateRange[1] || undefined) !==
                (query?.filter?.end_date ?? undefined) ||
            statusFilter !== (query?.filter?.status ?? '') ||
            deliveryFilter !== (query?.filter?.delivery_status ?? '');
        if (changed) debouncedFetch();
        return () => debouncedFetch.cancel();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue, dateRange, statusFilter, deliveryFilter]);

    const toggleExpand = (id: number) =>
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });

    const setExpectedDate = (item: MonitoringItem, date: string | null) => {
        router.put(
            `${baseUrl}/items/${item.id}/expected-delivery`,
            { expected_delivery_date: date },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => toast.success('Expected delivery updated'),
                onError: () => toast.error('Could not update expected date'),
            },
        );
    };

    const setStatus = (item: MonitoringItem, status: number) => {
        if (!item.purchased_order) return;
        router.put(
            `${baseUrl}/orders/${item.purchased_order.id}/status`,
            { status },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => toast.success('PO status updated'),
                onError: () => toast.error('Could not update PO status'),
            },
        );
    };

    const setDeliveryStatus = (
        item: MonitoringItem,
        status: 'ontime' | 'delayed',
    ) => {
        router.put(
            `${baseUrl}/items/${item.id}/delivery-status`,
            { delivery_status: status },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => toast.success('Delivery status updated'),
                onError: () => toast.error('Could not update delivery status'),
            },
        );
    };

    const setRemarks = (item: MonitoringItem, remarks: string) => {
        router.put(
            `${baseUrl}/items/${item.id}/remarks`,
            { remarks },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => toast.success('Remarks updated'),
                onError: () => toast.error('Could not update remarks'),
            },
        );
    };

    const confirmDeleteDelivery = () => {
        if (!deletingDelivery) return;
        router.delete(`${baseUrl}/deliveries/${deletingDelivery.id}`, {
            preserveScroll: true,
            onStart: () => setDeleteProcessing(true),
            onSuccess: () => {
                toast.success('Delivery deleted');
                setDeletingDelivery(null);
            },
            onFinish: () => setDeleteProcessing(false),
        });
    };

    const rows = items.data ?? [];

    const summaryCards: {
        key: StatusFilter | 'delayed' | 'total';
        label: string;
        value: number;
        active: boolean;
        accent: string;
        icon: ReactNode;
    }[] = [
        {
            key: 'total',
            label: 'Total Items',
            value: summary.total,
            active: !statusFilter && !deliveryFilter,
            accent: 'text-gray-800 dark:text-gray-100',
            icon: <Package className="h-4 w-4 text-gray-400" />,
        },
        {
            key: 'waiting',
            label: 'Waiting',
            value: summary.waiting,
            active: statusFilter === 'waiting',
            accent: 'text-amber-600 dark:text-amber-400',
            icon: <Hourglass className="h-4 w-4 text-amber-500" />,
        },
        {
            key: 'partial',
            label: 'Partial',
            value: summary.partial,
            active: statusFilter === 'partial',
            accent: 'text-sky-600 dark:text-sky-400',
            icon: <PackageOpen className="h-4 w-4 text-sky-500" />,
        },
        {
            key: 'delivered',
            label: 'Delivered',
            value: summary.delivered,
            active: statusFilter === 'delivered',
            accent: 'text-green-600 dark:text-green-400',
            icon: <PackageCheck className="h-4 w-4 text-green-500" />,
        },
        {
            key: 'delayed',
            label: 'Delayed',
            value: summary.delayed,
            active: deliveryFilter === 'delayed',
            accent: 'text-red-600 dark:text-red-400',
            icon: <AlertTriangle className="h-4 w-4 text-red-500" />,
        },
    ];

    const onCardClick = (key: (typeof summaryCards)[number]['key']) => {
        if (key === 'total') {
            setStatusFilter('');
            setDeliveryFilter('');
        } else if (key === 'delayed') {
            setStatusFilter('');
            setDeliveryFilter((prev) => (prev === 'delayed' ? '' : 'delayed'));
        } else {
            setDeliveryFilter('');
            setStatusFilter((prev) => (prev === key ? '' : key));
        }
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - PO Monitoring`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="PO Monitoring"
                    description="Track deliveries, balances and timeliness for each purchase order item."
                />

                {/* Summary / quick filters */}
                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    {summaryCards.map((card) => (
                        <button
                            key={card.key}
                            onClick={() => onCardClick(card.key)}
                            aria-pressed={card.active}
                            className={`rounded-[14px] border bg-white p-4 text-left transition-all dark:bg-zinc-900 ${
                                card.active
                                    ? 'border-emerald-500/60 ring-2 ring-emerald-500/15'
                                    : 'border-black/6 hover:border-black/12 dark:border-white/6 dark:hover:border-white/12'
                            }`}
                        >
                            <div className="flex items-center gap-2 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                {card.icon}
                                <span>{card.label}</span>
                            </div>
                            <div
                                className={`mt-2 font-mono text-[20px] font-semibold ${card.accent}`}
                            >
                                {card.value.toLocaleString()}
                            </div>
                        </button>
                    ))}
                </div>

                {/* Filters */}
                <div className="mb-3 flex flex-col items-stretch gap-2 md:flex-row md:items-center">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search PO No., item, delivery no.…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            aria-label="Search purchase order items"
                        />
                    </div>
                    <DatePicker
                        id="po-monitoring-date-range"
                        mode="range"
                        placeholder="Filter by PO date"
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
                    <Select
                        value={deliveryFilter || 'all'}
                        onValueChange={(v) =>
                            setDeliveryFilter(
                                v === 'all' ? '' : (v as DeliveryFilter),
                            )
                        }
                    >
                        <SelectTrigger className="h-9 w-[160px] rounded-[10px] border border-black/6 bg-stone-100 px-3 font-mono! text-[12px]! dark:border-white/6 dark:bg-zinc-800">
                            <SelectValue placeholder="Delivery status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                value="all"
                                className="font-mono! text-[12px]!"
                            >
                                All timeliness
                            </SelectItem>
                            <SelectItem
                                value="ontime"
                                className="font-mono! text-[12px]!"
                            >
                                On time
                            </SelectItem>
                            <SelectItem
                                value="delayed"
                                className="font-mono! text-[12px]!"
                            >
                                Delayed
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="custom-scrollbar max-w-full overflow-x-auto">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="border-b border-black/6 dark:border-white/6">
                                    <th className="w-8 px-2 py-2.5" />
                                    {[
                                        'PO Date',
                                        'PO Number',
                                        'Item',
                                        'Ordered',
                                        'Delivered',
                                        'Balance',
                                        'Status',
                                        'Expected',
                                        'Delivery',
                                        'Remarks',
                                    ].map((h) => (
                                        <th
                                            key={h}
                                            className="px-4 py-2.5 text-left font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600"
                                        >
                                            {h}
                                        </th>
                                    ))}
                                    {canManage && (
                                        <th className="px-4 py-2.5 text-center font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={canManage ? 12 : 11}
                                            className="py-16 text-center"
                                        >
                                            <div className="flex flex-col items-center gap-3">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-stone-100 dark:bg-zinc-800">
                                                    <Truck className="h-5 w-5 text-gray-400 dark:text-gray-500" />
                                                </div>
                                                <p className="font-mono text-[12px] font-medium text-gray-600 dark:text-gray-400">
                                                    No purchase order items
                                                    found
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((item) => {
                                        const isOpen = expanded.has(item.id);
                                        const itemName =
                                            item.inventory_item?.product
                                                ?.name ??
                                            item.inventory_item?.sku ??
                                            '—';
                                        return (
                                            <ItemRow
                                                key={item.id}
                                                item={item}
                                                isOpen={isOpen}
                                                itemName={itemName}
                                                canManage={canManage}
                                                onToggle={() =>
                                                    toggleExpand(item.id)
                                                }
                                                onRecord={() =>
                                                    setRecordingFor(item)
                                                }
                                                onEditDelivery={(d) =>
                                                    setEditingDelivery({
                                                        itemId: item.id,
                                                        delivery: d,
                                                    })
                                                }
                                                onDeleteDelivery={(d) =>
                                                    setDeletingDelivery(d)
                                                }
                                                onSetExpected={(date) =>
                                                    setExpectedDate(item, date)
                                                }
                                                onSetStatus={(status) =>
                                                    setStatus(item, status)
                                                }
                                                onSetDeliveryStatus={(status) =>
                                                    setDeliveryStatus(
                                                        item,
                                                        status,
                                                    )
                                                }
                                                onSetRemarks={(remarks) =>
                                                    setRemarks(item, remarks)
                                                }
                                            />
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Footer */}
                    <div className="flex flex-col gap-3 border-t border-black/6 px-4 py-3 xl:flex-row xl:items-center xl:justify-between dark:border-white/6">
                        <div className="flex items-center gap-3">
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    Rows
                                </span>
                                <Select
                                    value={String(items.per_page ?? 10)}
                                    onValueChange={(v) =>
                                        fetchData({
                                            per_page: Number(v),
                                            page: 1,
                                        })
                                    }
                                >
                                    <SelectTrigger className="h-7 w-[72px] rounded-lg border border-black/6 bg-stone-50 px-2.5 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="min-w-[72px]">
                                        {[10, 25, 50, 100].map((n) => (
                                            <SelectItem
                                                key={n}
                                                value={String(n)}
                                                className="font-mono! text-[11px]!"
                                            >
                                                {n}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="h-4 w-px bg-black/6 dark:bg-white/6" />
                            <p className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                Showing {items.from ?? 0} to {items.to ?? 0} of{' '}
                                {(items.total ?? 0).toLocaleString()} entries
                            </p>
                        </div>
                        <Pagination
                            currentPage={items.current_page ?? 1}
                            totalPages={items.last_page ?? 1}
                            onPageChange={(page) => fetchData({ page })}
                        />
                    </div>
                </div>
            </div>

            {/* Record / edit delivery dialog */}
            <RecordDeliveryDialog
                workspace={workspace}
                itemId={editingDelivery?.itemId ?? recordingFor?.id ?? null}
                itemBalance={recordingFor?.balance}
                delivery={editingDelivery?.delivery ?? null}
                open={Boolean(recordingFor || editingDelivery)}
                onClose={() => {
                    setRecordingFor(null);
                    setEditingDelivery(null);
                }}
            />

            {/* Delete delivery confirm */}
            <AlertDialog
                open={Boolean(deletingDelivery)}
                onOpenChange={(o) => !o && setDeletingDelivery(null)}
            >
                <AlertDialogContent className="max-w-[400px] border-none shadow-2xl dark:bg-zinc-900">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                            Delete this delivery?
                        </AlertDialogTitle>
                        <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                            Removing this delivery of{' '}
                            <span className="font-mono text-emerald-600 dark:text-emerald-400">
                                {deletingDelivery?.qty}
                            </span>{' '}
                            unit(s) will recalculate the balance. This cannot be
                            undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter className="mt-4 gap-2">
                        <AlertDialogCancel
                            disabled={deleteProcessing}
                            className="h-9 rounded-lg border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={(e) => {
                                e.preventDefault();
                                confirmDeleteDelivery();
                            }}
                            disabled={deleteProcessing}
                            className="h-9 rounded-lg bg-red-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-red-700 disabled:opacity-50"
                        >
                            {deleteProcessing ? 'Deleting…' : 'Confirm Delete'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}

interface ItemRowProps {
    item: MonitoringItem;
    isOpen: boolean;
    itemName: string;
    canManage: boolean;
    onToggle: () => void;
    onRecord: () => void;
    onEditDelivery: (d: DeliveryTarget) => void;
    onDeleteDelivery: (d: Delivery) => void;
    onSetExpected: (date: string | null) => void;
    onSetStatus: (status: number) => void;
    onSetDeliveryStatus: (status: 'ontime' | 'delayed') => void;
    onSetRemarks: (remarks: string) => void;
}

function ItemRow({
    item,
    isOpen,
    itemName,
    canManage,
    onToggle,
    onRecord,
    onEditDelivery,
    onDeleteDelivery,
    onSetExpected,
    onSetStatus,
    onSetDeliveryStatus,
    onSetRemarks,
}: ItemRowProps) {
    const colSpan = canManage ? 12 : 11;
    const po = item.purchased_order;
    const poNumber = po?.cust_po_no || po?.control_no || po?.delivery_no || '—';

    // Running balance per delivery (deliveries arrive ordered by date from API).
    let cumulative = 0;
    const deliveriesWithBalance = item.deliveries.map((d) => {
        cumulative += d.qty;
        return { ...d, runningBalance: Math.max(0, item.count - cumulative) };
    });

    return (
        <>
            <tr className="border-b border-black/6 transition-colors hover:bg-emerald-500/3 dark:border-white/6">
                <td className="px-2 py-3 align-middle">
                    <button
                        onClick={onToggle}
                        aria-expanded={isOpen}
                        aria-label={
                            isOpen ? 'Collapse deliveries' : 'Expand deliveries'
                        }
                        className="flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-stone-100 hover:text-gray-600 dark:hover:bg-zinc-800"
                    >
                        {isOpen ? (
                            <ChevronDown className="h-3.5 w-3.5" />
                        ) : (
                            <ChevronRight className="h-3.5 w-3.5" />
                        )}
                    </button>
                </td>
                <td className="px-4 py-3 align-middle">
                    <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                        {fmtDate(po?.issue_date ?? null)}
                    </span>
                </td>
                <td className="px-4 py-3 align-middle">
                    <span className="font-mono text-[11px] text-gray-700 dark:text-gray-300">
                        {poNumber}
                    </span>
                </td>
                <td className="px-4 py-3 align-middle">
                    <div className="flex flex-col">
                        <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                            {itemName}
                        </span>
                        {item.inventory_item?.sku && (
                            <span className="font-mono text-[10px] text-gray-400 dark:text-gray-600">
                                {item.inventory_item.sku}
                            </span>
                        )}
                    </div>
                </td>
                <td className="px-4 py-3 text-right align-middle font-mono text-[12px] text-gray-700 dark:text-gray-300">
                    {item.count.toLocaleString()}
                </td>
                <td className="px-4 py-3 text-right align-middle font-mono text-[12px] text-gray-700 dark:text-gray-300">
                    {item.delivered_qty.toLocaleString()}
                </td>
                <td className="px-4 py-3 text-right align-middle">
                    <span
                        className={`font-mono text-[12px] font-semibold ${
                            item.balance === 0
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-gray-800 dark:text-gray-200'
                        }`}
                    >
                        {item.balance.toLocaleString()}
                    </span>
                </td>
                <td className="px-4 py-3 align-middle">
                    <StatusPill
                        value={po ? String(po.status) : null}
                        options={PO_STATUS_OPTIONS}
                        disabled={!canManage || !po}
                        onChange={(v) => onSetStatus(Number(v))}
                    />
                </td>
                <td className="px-4 py-3 align-middle">
                    {canManage ? (
                        <ExpectedDateCell
                            itemId={item.id}
                            value={item.expected_delivery_date}
                            onChange={onSetExpected}
                        />
                    ) : item.expected_delivery_date ? (
                        <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                            {fmtDate(item.expected_delivery_date)}
                        </span>
                    ) : (
                        <span className="font-mono text-[11px] text-amber-500 italic dark:text-amber-400/80">
                            Not set
                        </span>
                    )}
                </td>
                <td className="px-4 py-3 align-middle">
                    <StatusPill
                        value={item.delivery_timeliness}
                        options={DELIVERY_STATUS_OPTIONS}
                        placeholder="No due date"
                        disabled={!canManage}
                        onChange={(v) =>
                            onSetDeliveryStatus(v as 'ontime' | 'delayed')
                        }
                    />
                </td>
                <td className="px-4 py-3 align-middle">
                    <RemarksCell
                        value={item.remarks}
                        disabled={!canManage}
                        onSave={onSetRemarks}
                    />
                </td>
                {canManage && (
                    <td className="px-4 py-3 text-center align-middle">
                        <button
                            onClick={onRecord}
                            disabled={item.balance === 0}
                            title={
                                item.balance === 0
                                    ? 'Fully delivered'
                                    : 'Record delivery'
                            }
                            className="inline-flex h-7 items-center gap-1 rounded-lg bg-emerald-600 px-2.5 font-mono! text-[11px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <Plus className="h-3 w-3" />
                            Deliver
                        </button>
                    </td>
                )}
            </tr>

            {isOpen && (
                <tr className="bg-stone-50/60 dark:bg-zinc-950/40">
                    <td colSpan={colSpan} className="px-4 py-4">
                        <div className="rounded-[12px] border border-black/6 bg-white p-3 dark:border-white/6 dark:bg-zinc-900">
                            <p className="mb-2 flex items-center gap-1.5 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                <Truck className="h-3 w-3" />
                                Delivery attempts (
                                {deliveriesWithBalance.length})
                            </p>
                            {deliveriesWithBalance.length === 0 ? (
                                <p className="px-1 py-3 font-mono text-[11px] text-gray-400 dark:text-gray-600">
                                    No deliveries recorded yet.
                                </p>
                            ) : (
                                <table className="w-full border-collapse">
                                    <thead>
                                        <tr className="border-b border-black/6 dark:border-white/6">
                                            {[
                                                'Date',
                                                'Delivery No.',
                                                'Qty',
                                                'Balance After',
                                            ].map((h) => (
                                                <th
                                                    key={h}
                                                    className="px-3 py-2 text-left font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600"
                                                >
                                                    {h}
                                                </th>
                                            ))}
                                            {canManage && (
                                                <th className="px-3 py-2 text-right font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                                                    Actions
                                                </th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {deliveriesWithBalance.map((d) => (
                                            <tr
                                                key={d.id}
                                                className="border-b border-black/4 last:border-0 dark:border-white/4"
                                            >
                                                <td className="px-3 py-2 font-mono text-[11px] text-gray-600 dark:text-gray-400">
                                                    {fmtDate(d.delivery_date)}
                                                </td>
                                                <td className="px-3 py-2 font-mono text-[11px] text-gray-600 dark:text-gray-400">
                                                    {d.delivery_no || '—'}
                                                </td>
                                                <td className="px-3 py-2 font-mono text-[11px] text-gray-700 dark:text-gray-300">
                                                    {d.qty.toLocaleString()}
                                                </td>
                                                <td className="px-3 py-2 font-mono text-[11px] text-gray-600 dark:text-gray-400">
                                                    {d.runningBalance.toLocaleString()}
                                                </td>
                                                {canManage && (
                                                    <td className="px-3 py-2">
                                                        <div className="flex justify-end gap-1">
                                                            <button
                                                                onClick={() =>
                                                                    onEditDelivery(
                                                                        d,
                                                                    )
                                                                }
                                                                aria-label="Edit delivery"
                                                                className="flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-stone-100 hover:text-gray-600 dark:hover:bg-zinc-800"
                                                            >
                                                                <Pencil className="h-3 w-3" />
                                                            </button>
                                                            <button
                                                                onClick={() =>
                                                                    onDeleteDelivery(
                                                                        d,
                                                                    )
                                                                }
                                                                aria-label="Delete delivery"
                                                                className="flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950/40"
                                                            >
                                                                <Trash2 className="h-3 w-3" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </td>
                </tr>
            )}
        </>
    );
}

interface ExpectedDateCellProps {
    itemId: number;
    value: string | null;
    onChange: (date: string | null) => void;
}

function ExpectedDateCell({ itemId, value, onChange }: ExpectedDateCellProps) {
    const [editing, setEditing] = useState(false);

    if (!editing) {
        if (!value) {
            return (
                <button
                    onClick={() => setEditing(true)}
                    className="group inline-flex items-center gap-1.5 rounded-md border border-dashed border-amber-300 bg-amber-50 px-2 py-1 font-mono text-[11px] font-medium text-amber-600 transition-colors hover:border-amber-400 hover:bg-amber-100 dark:border-amber-500/40 dark:bg-amber-950/40 dark:text-amber-400 dark:hover:bg-amber-950/70"
                    title="No expected delivery date — click to set one"
                >
                    <CalendarClock className="h-3 w-3" />
                    Set date
                </button>
            );
        }
        return (
            <button
                onClick={() => setEditing(true)}
                className="group inline-flex items-center gap-1.5 rounded-md px-1.5 py-1 font-mono text-[11px] text-gray-600 transition-colors hover:bg-stone-100 dark:text-gray-400 dark:hover:bg-zinc-800"
                title="Edit expected delivery date"
            >
                <CalendarClock className="h-3 w-3 text-gray-400 group-hover:text-emerald-500" />
                {fmtDate(value)}
            </button>
        );
    }

    return (
        <div className="w-[150px]">
            <DatePicker
                key={`exp-${itemId}`}
                id={`expected-${itemId}`}
                mode="single"
                defaultDate={value || undefined}
                onChange={(dates) => {
                    onChange(
                        dates.length ? format(dates[0], 'yyyy-MM-dd') : null,
                    );
                    setEditing(false);
                }}
            />
        </div>
    );
}

interface StatusPillProps {
    value: string | null;
    options: PillOption[];
    placeholder?: string;
    disabled?: boolean;
    onChange: (value: string) => void;
}

function StatusPill({
    value,
    options,
    placeholder = 'Not set',
    disabled = false,
    onChange,
}: StatusPillProps) {
    const current = options.find((o) => o.value === value);
    const pill =
        current?.pill ??
        'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400';
    const dot = current?.dot ?? 'bg-gray-400';
    const label = current?.label ?? placeholder;

    if (disabled) {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium',
                    pill,
                )}
            >
                <span
                    className={`h-1.5 w-1.5 shrink-0 rounded-full ${dot}`}
                />
                <span className="max-w-[140px] truncate">{label}</span>
            </span>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    'group inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium transition-all outline-none hover:opacity-80',
                    pill,
                )}
            >
                <span
                    className={`h-1.5 w-1.5 shrink-0 rounded-full ${dot}`}
                />
                <span className="max-w-[140px] truncate">{label}</span>
                <ChevronDown className="h-3 w-3 shrink-0 opacity-60" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-52 overflow-hidden p-1">
                <p className="px-2 pt-1 pb-1.5 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    Change status
                </p>
                <div className="max-h-72 overflow-y-auto">
                    {options.map((o) => {
                        const isActive = o.value === value;
                        return (
                            <DropdownMenuItem
                                key={o.value}
                                onClick={() => {
                                    if (!isActive) onChange(o.value);
                                }}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-[12px]',
                                    isActive
                                        ? 'bg-gray-50 dark:bg-zinc-800'
                                        : 'text-gray-600 dark:text-gray-400',
                                )}
                            >
                                <span
                                    className={`h-1.5 w-1.5 shrink-0 rounded-full ${o.dot}`}
                                />
                                <span className="flex-1">{o.label}</span>
                                {isActive && (
                                    <Check className="h-3 w-3 text-emerald-500" />
                                )}
                            </DropdownMenuItem>
                        );
                    })}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

interface RemarksCellProps {
    value: string | null;
    disabled?: boolean;
    onSave: (remarks: string) => void;
}

function RemarksCell({ value, disabled = false, onSave }: RemarksCellProps) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState(value ?? '');

    useEffect(() => {
        setDraft(value ?? '');
    }, [value]);

    const save = () => {
        if ((draft ?? '').trim() !== (value ?? '')) onSave(draft.trim());
        setOpen(false);
    };

    if (disabled) {
        return (
            <span className="block max-w-[200px] truncate font-mono text-[11px] text-gray-600 dark:text-gray-400">
                {value || '—'}
            </span>
        );
    }

    return (
        <>
            <button
                onClick={() => setOpen(true)}
                title={value || 'Add remarks'}
                className={cn(
                    'block max-w-[200px] truncate rounded-md px-1.5 py-1 text-left font-mono text-[11px] transition-colors hover:bg-stone-100 dark:hover:bg-zinc-800',
                    value
                        ? 'text-gray-600 dark:text-gray-400'
                        : 'text-gray-400 italic dark:text-gray-600',
                )}
            >
                {value || 'Add remarks'}
            </button>

            <Dialog
                open={open}
                onOpenChange={(o) => {
                    if (!o) setDraft(value ?? '');
                    setOpen(o);
                }}
            >
                <DialogContent className="max-w-[440px]">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold">
                            Remarks
                        </DialogTitle>
                    </DialogHeader>
                    <Textarea
                        autoFocus
                        rows={5}
                        value={draft}
                        onChange={(e) => setDraft(e.target.value)}
                        placeholder="Add remarks…"
                        className="text-[13px]"
                    />
                    <DialogFooter className="mt-2 gap-2">
                        <Button
                            variant="outline"
                            onClick={() => {
                                setDraft(value ?? '');
                                setOpen(false);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button onClick={save}>Save</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
