import Filters, { FilterValue } from '@/components/filters/Filters';
import {
    authParcelStatusConfig,
    orderStatusConfig,
    ParcelStatusEntry,
} from '@/components/rts/rmo-config';
import { RmoStatCards } from '@/components/rts/RmoStatCards';
import { RmoStatusPicker } from '@/components/rts/RmoStatusPicker';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import CsrAwareLayout from '@/layouts/csr-aware-layout';
import { toFrontendSort } from '@/lib/sort';
import { currencyFormatter, percentageFormatter } from '@/lib/utils';
import { PaginatedData, SharedData } from '@/types';
import { CallLog } from '@/types/models/CallLog';
import {
    OrderForDelivery,
    OrderStatus,
} from '@/types/models/Pancake/OrderForDelivery';
import { User } from '@/types/models/Pancake/User';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import {
    BarChart3,
    ChevronDown,
    ChevronUp,
    ClipboardCopy,
    Download,
    MapPin,
    Pencil,
    Phone,
    PhoneCall,
    Search,
    User as UserIcon,
    UserPlus,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const EXPORT_COLUMNS = [
    { key: 'order_id', label: 'Order ID' },
    { key: 'tracking_number', label: 'Tracking Number' },
    { key: 'jnt_status', label: 'J&T Status' },
    { key: 'rider_name', label: "Rider's Name" },
    { key: 'rider_number', label: "Rider's Number" },
    { key: 'cx_name', label: 'CX Name' },
    { key: 'cx_number', label: 'CX Number' },
    { key: 'address', label: 'Address' },
    { key: 'srp', label: 'SRP' },
    { key: 'attempts', label: '# of Attempts' },
    { key: 'confirmed_by', label: 'Confirmed By' },
    { key: 'cx_rts', label: 'CX RTS' },
    { key: 'location_rts', label: 'Location RTS' },
    { key: 'updated_status', label: 'Updated Status' },
    { key: 'csr', label: 'CSR' },
] as const;

const ALL_COLUMN_KEYS = EXPORT_COLUMNS.map((c) => c.key);

interface Props {
    orders: PaginatedData<OrderForDelivery>;
    workspace: Workspace;
    query?: {
        sort?: string | null;
        filter?: {
            search?: string;
            status?: string | string[];
            page_id?: string | string[];
            shop_id?: string | string[];
            user_id?: string | string[];
            parcel_status?: string | string[];
        };
        page?: number;
        perPage?: number;
        delivery_date?: string;
    };
    users: User[];
    total_for_delivery_today: number;
    called_count: number;
    delivered_count: number;
    returning_count: number;
    problematic_count: number;
    pancakeAccounts: { id: string; name: string }[];
}

function formatDuration(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return m > 0 ? `${m}m ${s}s` : `${s}s`;
}

function EditablePhone({
    value,
    onSave,
    disabled = false,
}: {
    value: string;
    onSave: (v: string) => void;
    disabled?: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(value);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (editing) inputRef.current?.focus();
    }, [editing]);

    useEffect(() => {
        setDraft(value);
    }, [value]);

    const save = () => {
        setEditing(false);
        if (draft.trim() !== value) {
            onSave(draft.trim());
        }
    };

    if (editing && !disabled) {
        return (
            <input
                ref={inputRef}
                type="text"
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                onBlur={save}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') save();
                    if (e.key === 'Escape') {
                        setDraft(value);
                        setEditing(false);
                    }
                }}
                className="h-6 w-28 rounded border border-emerald-300 px-1.5 text-[11px] outline-none focus:ring-1 focus:ring-emerald-400 dark:border-emerald-600 dark:bg-zinc-800 dark:text-gray-300"
            />
        );
    }

    if (disabled) {
        return (
            <span className="flex items-center gap-1 text-[11px] text-gray-400 dark:text-gray-500">
                <Phone className="h-3 w-3 shrink-0" />
                {value || '—'}
            </span>
        );
    }

    return (
        <button
            onClick={() => setEditing(true)}
            className="group/phone flex items-center gap-1 text-[11px] text-gray-400 hover:text-emerald-600 dark:text-gray-500 dark:hover:text-emerald-400"
        >
            <Phone className="h-3 w-3 shrink-0" />
            {value || '—'}
            <Pencil className="h-2.5 w-2.5 shrink-0 opacity-0 group-hover/phone:opacity-100" />
        </button>
    );
}

function CallLogModal({
    open,
    onOpenChange,
    phoneNumber,
    label,
    workspaceSlug,
    date,
    csrName,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    phoneNumber: string;
    label: string;
    workspaceSlug: string;
    date: string;
    csrName: string;
}) {
    const [logs, setLogs] = useState<CallLog[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open || !phoneNumber) return;
        setLoading(true);
        fetch(
            `/workspaces/${workspaceSlug}/csr/rmo-management/call-logs?phone_number=${encodeURIComponent(phoneNumber)}&date=${date}`,
        )
            .then((r) => r.json())
            .then((data) => setLogs(data))
            .finally(() => setLoading(false));
    }, [open, phoneNumber, workspaceSlug, date]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="text-sm font-semibold">
                        Call Logs — {label}
                    </DialogTitle>
                    <p className="text-[11px] text-gray-400 dark:text-gray-500">
                        {phoneNumber} · {date}
                    </p>
                </DialogHeader>
                {loading ? (
                    <p className="py-6 text-center text-[12px] text-gray-400">
                        Loading...
                    </p>
                ) : logs.length === 0 ? (
                    <p className="py-6 text-center text-[12px] text-gray-400">
                        No call logs found
                    </p>
                ) : (
                    <div className="max-h-72 overflow-y-auto">
                        <table className="w-full text-[12px]">
                            <thead>
                                <tr className="border-b text-left text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    <th className="pr-3 pb-2">Time</th>
                                    <th className="pr-3 pb-2">Type</th>
                                    <th className="pr-3 pb-2">By</th>
                                    <th className="pr-3 pb-2 text-right">
                                        Duration
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.map((log) => (
                                    <tr
                                        key={log.id}
                                        className="border-b border-black/5 dark:border-white/5"
                                    >
                                        <td className="py-2 pr-3 font-mono text-gray-600 dark:text-gray-300">
                                            {log.call_time}
                                        </td>
                                        <td className="py-2 pr-3">
                                            <span
                                                className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium ${
                                                    log.type === 'outgoing'
                                                        ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                                                        : log.type ===
                                                            'incoming'
                                                          ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400'
                                                          : 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400'
                                                }`}
                                            >
                                                {log.type}
                                            </span>
                                        </td>
                                        <td className="py-2 pr-3 text-[11px] text-gray-600 dark:text-gray-300">
                                            {csrName}
                                        </td>
                                        <td className="py-2 pr-3 text-right font-mono text-gray-600 dark:text-gray-300">
                                            {formatDuration(log.duration)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="mt-3 flex justify-between border-t pt-2 text-[11px] text-gray-500 dark:text-gray-400">
                            <span>
                                {logs.length} call{logs.length !== 1 ? 's' : ''}
                            </span>
                            <span>
                                Total:{' '}
                                {formatDuration(
                                    logs.reduce(
                                        (sum, l) => sum + l.duration,
                                        0,
                                    ),
                                )}
                            </span>
                        </div>
                    </div>
                )}
                <div className="flex justify-end">
                    <button
                        onClick={() => onOpenChange(false)}
                        className="rounded-lg border border-black/10 px-4 py-1.5 text-[12px] font-medium text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:text-gray-400 dark:hover:bg-zinc-800"
                    >
                        Close
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function CallLogBadge({
    attempts,
    duration,
    onClick,
}: {
    attempts: number;
    duration: number;
    onClick: () => void;
}) {
    return (
        <button
            onClick={onClick}
            className="flex items-center gap-1 text-[10px] text-gray-400 hover:text-emerald-600 hover:underline dark:text-gray-500 dark:hover:text-emerald-400"
        >
            <PhoneCall className="h-2.5 w-2.5 shrink-0" />
            {attempts} call{attempts !== 1 ? 's' : ''} ·{' '}
            {formatDuration(duration)}
        </button>
    );
}

export default function CsrRmoManagement({
    orders,
    workspace,
    query,
    users,
    total_for_delivery_today,
    called_count,
    delivered_count,
    returning_count,
    problematic_count,
    pancakeAccounts,
}: Props) {
    const { appEnv } = usePage<SharedData>().props;
    const canEditPhone = appEnv !== 'production';

    const [activePancakeAccount, setActivePancakeAccount] = useState(
        () => pancakeAccounts[0] ?? null,
    );
    const userId = activePancakeAccount?.id ?? '';
    const userName = activePancakeAccount?.name ?? '';

    const rmoUrl = `/workspaces/${workspace.slug}/csr/rmo-management`;

    const [showStats, setShowStats] = useState(false);
    const [showMyAssigneeOnly, setShowMyAssigneeOnly] = useState(false);
    const [showMyConfirmeeOnly, setShowMyConfirmeeOnly] = useState(false);
    const [callLogModal, setCallLogModal] = useState<{
        phone: string;
        label: string;
    } | null>(null);
    const [exportModalOpen, setExportModalOpen] = useState(false);
    const [exportColumns, setExportColumns] = useState<string[]>([
        ...ALL_COLUMN_KEYS,
    ]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    const [selectedPageIds, setSelectedPageIds] = useState<string[]>(() =>
        query?.filter?.page_id
            ? Array.isArray(query.filter.page_id)
                ? query.filter.page_id
                : query.filter.page_id.split(',').filter(Boolean)
            : [],
    );

    const [selectedShopIds, setSelectedShopIds] = useState<string[]>(() =>
        query?.filter?.shop_id
            ? Array.isArray(query.filter.shop_id)
                ? query.filter.shop_id
                : query.filter.shop_id.split(',').filter(Boolean)
            : [],
    );

    const [selectedUserIds, setSelectedUserIds] = useState<string[]>(() =>
        query?.filter?.user_id
            ? Array.isArray(query.filter.user_id)
                ? query.filter.user_id
                : query.filter.user_id.split(',').filter(Boolean)
            : [],
    );

    const currentStatus = useMemo(
        () =>
            Array.isArray(query?.filter?.status)
                ? (query.filter.status[0] ?? '')
                : (query?.filter?.status ?? ''),
        [query?.filter?.status],
    );

    const currentParcelStatus = useMemo(
        () =>
            Array.isArray(query?.filter?.parcel_status)
                ? (query.filter.parcel_status[0] ?? '')
                : (query?.filter?.parcel_status ?? ''),
        [query?.filter?.parcel_status],
    );

    const todayLocal = (() => {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    })();
    const deliveryDate = query?.delivery_date ?? todayLocal;
    const isToday = deliveryDate === todayLocal;

    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const initialFilterValue = useMemo<FilterValue>(
        () => ({
            teamIds: [],
            productIds: [],
            shopIds: [...selectedShopIds],
            pageIds: [...selectedPageIds],
            userIds: [...selectedUserIds],
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    const handleFilterChange = useCallback(
        (value: FilterValue) => {
            const newPageIds = value.pageIds.map(String);
            const newShopIds = value.shopIds.map(String);
            const newUserIds = value.userIds.map(String);

            setSelectedPageIds(newPageIds);
            setSelectedShopIds(newShopIds);
            setSelectedUserIds(newUserIds);

            router.get(
                rmoUrl,
                {
                    sort: query?.sort || undefined,
                    'filter[search]': searchValue || undefined,
                    ...(currentStatus
                        ? { 'filter[status]': currentStatus }
                        : {}),
                    ...(currentParcelStatus
                        ? { 'filter[parcel_status]': currentParcelStatus }
                        : {}),
                    ...(newPageIds.length
                        ? { 'filter[page_id]': newPageIds.join(',') }
                        : {}),
                    ...(newShopIds.length
                        ? { 'filter[shop_id]': newShopIds.join(',') }
                        : {}),
                    ...(newUserIds.length
                        ? { 'filter[user_id]': newUserIds.join(',') }
                        : {}),
                    page: 1,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [
            rmoUrl,
            query?.sort,
            searchValue,
            currentStatus,
            currentParcelStatus,
        ],
    );

    const buildAllParams = useCallback(
        (
            sort?: string | null,
            page?: number,
            status?: string,
            parcelStatus?: string,
            perPage?: number,
        ) => ({
            sort: sort ?? undefined,
            'filter[search]': searchValue || undefined,
            ...(status !== undefined
                ? status
                    ? { 'filter[status]': status }
                    : {}
                : currentStatus
                  ? { 'filter[status]': currentStatus }
                  : {}),
            ...(parcelStatus !== undefined
                ? parcelStatus
                    ? { 'filter[parcel_status]': parcelStatus }
                    : {}
                : currentParcelStatus
                  ? { 'filter[parcel_status]': currentParcelStatus }
                  : {}),
            ...(selectedPageIds.length
                ? { 'filter[page_id]': selectedPageIds.join(',') }
                : {}),
            ...(selectedShopIds.length
                ? { 'filter[shop_id]': selectedShopIds.join(',') }
                : {}),
            ...(selectedUserIds.length
                ? { 'filter[user_id]': selectedUserIds.join(',') }
                : {}),
            page: page ?? 1,
            per_page: perPage ?? orders.per_page,
            delivery_date: deliveryDate,
            ...(showMyAssigneeOnly ? { assignee_id: userId } : {}),
            ...(showMyConfirmeeOnly ? { confirmee_id: userId } : {}),
        }),
        [
            searchValue,
            currentStatus,
            currentParcelStatus,
            selectedPageIds,
            selectedShopIds,
            selectedUserIds,
            showMyAssigneeOnly,
            showMyConfirmeeOnly,
            orders.per_page,
            deliveryDate,
            userId,
        ],
    );

    const handleStatusChange = useCallback(
        (status: string) => {
            router.get(rmoUrl, buildAllParams(query?.sort, 1, status), {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            });
        },
        [rmoUrl, buildAllParams, query?.sort],
    );

    const handleParcelStatusChange = useCallback(
        (parcelStatus: string) => {
            router.get(
                rmoUrl,
                buildAllParams(query?.sort, 1, undefined, parcelStatus),
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [rmoUrl, buildAllParams, query?.sort],
    );

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(rmoUrl, buildAllParams(query?.sort, 1), {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            });
        }, 400);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue]);

    useEffect(() => {
        router.get(rmoUrl, buildAllParams(query?.sort, 1), {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [showMyAssigneeOnly, showMyConfirmeeOnly]);

    const doExport = useCallback(
        (columns: string[]) => {
            const params = new URLSearchParams();
            if (searchValue) params.set('filter[search]', searchValue);
            if (currentStatus) params.set('filter[status]', currentStatus);
            if (currentParcelStatus)
                params.set('filter[parcel_status]', currentParcelStatus);
            if (selectedPageIds.length)
                params.set('filter[page_id]', selectedPageIds.join(','));
            if (selectedShopIds.length)
                params.set('filter[shop_id]', selectedShopIds.join(','));
            if (selectedUserIds.length)
                params.set('filter[user_id]', selectedUserIds.join(','));
            params.set('delivery_date', deliveryDate);
            if (showMyAssigneeOnly) {
                params.set('assignee_id', userId);
            }
            if (showMyConfirmeeOnly) {
                params.set('confirmee_id', userId);
            }
            if (columns.length > 0 && columns.length < ALL_COLUMN_KEYS.length) {
                params.set('columns', columns.join(','));
            }

            const qs = params.toString();
            window.location.href = `/workspaces/${workspace.slug}/csr/rmo-management/export${qs ? `?${qs}` : ''}`;
        },
        [
            workspace.slug,
            searchValue,
            currentStatus,
            currentParcelStatus,
            selectedPageIds,
            selectedShopIds,
            selectedUserIds,
            showMyAssigneeOnly,
            showMyConfirmeeOnly,
            deliveryDate,
            userId,
        ],
    );

    const handleDateChange = useCallback(
        (date: string) => {
            router.get(
                rmoUrl,
                {
                    sort: query?.sort || undefined,
                    'filter[search]': searchValue || undefined,
                    ...(currentStatus
                        ? { 'filter[status]': currentStatus }
                        : {}),
                    ...(currentParcelStatus
                        ? { 'filter[parcel_status]': currentParcelStatus }
                        : {}),
                    ...(selectedPageIds.length
                        ? { 'filter[page_id]': selectedPageIds.join(',') }
                        : {}),
                    ...(selectedShopIds.length
                        ? { 'filter[shop_id]': selectedShopIds.join(',') }
                        : {}),
                    ...(selectedUserIds.length
                        ? { 'filter[user_id]': selectedUserIds.join(',') }
                        : {}),
                    ...(showMyAssigneeOnly ? { assignee_id: userId } : {}),
                    ...(showMyConfirmeeOnly ? { confirmee_id: userId } : {}),
                    delivery_date: date,
                    page: 1,
                    per_page: orders.per_page,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [
            rmoUrl,
            query?.sort,
            searchValue,
            currentStatus,
            currentParcelStatus,
            selectedPageIds,
            selectedShopIds,
            selectedUserIds,
            showMyAssigneeOnly,
            showMyConfirmeeOnly,
            orders.per_page,
            userId,
        ],
    );

    const handleAssignUser = useCallback(
        (id: number, assigneeId: string) => {
            router.post(
                `/workspaces/${workspace.slug}/csr/rmo-management/${id}/assign`,
                { userId: assigneeId },
                { preserveScroll: true },
            );
        },
        [workspace.slug],
    );

    const handleRemoveAssignee = useCallback(
        (id: number) => {
            router.post(
                `/workspaces/${workspace.slug}/csr/rmo-management/${id}/remove-assignee`,
                {},
                { preserveScroll: true },
            );
        },
        [workspace.slug],
    );

    const handleChangeStatus = useCallback(
        (status: string, id: number) => {
            router.post(
                `/workspaces/${workspace.slug}/csr/rmo-management/${id}`,
                { status },
                { preserveScroll: true, preserveState: false },
            );
        },
        [workspace.slug],
    );

    const handleAssignToMe = useCallback(
        (id: number) => {
            if (!userId) return;
            handleAssignUser(id, userId);
        },
        [handleAssignUser, userId],
    );

    const handleUpdatePhone = useCallback(
        (
            id: number,
            field: 'customer_phone' | 'rider_phone',
            value: string,
        ) => {
            router.post(
                `/workspaces/${workspace.slug}/csr/rmo-management/${id}/update-phones`,
                { [field]: value },
                { preserveScroll: true },
            );
        },
        [workspace.slug],
    );

    const [copiedRider, setCopiedRider] = useState(false);
    const [copiedCustomer, setCopiedCustomer] = useState(false);

    const pendingOrders = useMemo(
        () =>
            (orders.data ?? [])
                .filter((o) => o.status === 'PENDING')
                .slice(0, 10),
        [orders.data],
    );

    const copyPendingPhones = useCallback(
        (type: 'rider' | 'customer') => {
            const phones = pendingOrders
                .map((o) =>
                    type === 'rider'
                        ? o.rider_phone
                        : (o.customer_phone ??
                          o.order.shipping_address?.phone_number ??
                          ''),
                )
                .filter(Boolean);
            if (phones.length === 0) return;
            navigator.clipboard.writeText(phones.join('\n')).then(() => {
                if (type === 'rider') {
                    setCopiedRider(true);
                    setTimeout(() => setCopiedRider(false), 2000);
                } else {
                    setCopiedCustomer(true);
                    setTimeout(() => setCopiedCustomer(false), 2000);
                }
            });
        },
        [pendingOrders],
    );

    const columns = useMemo<ColumnDef<OrderForDelivery>[]>(
        () => [
            {
                accessorFn: (row) =>
                    (row.order.items ?? []).map((item) => item.name).join(', '),
                id: 'items',
                enableSorting: false,
                header: ({ column }) => (
                    <SortableHeader
                        enabled={false}
                        column={column}
                        title="Items"
                    />
                ),
                cell: ({ row }) => {
                    const items = row.original.order.items ?? [];
                    const trackingCode = row.original.order.tracking_code;
                    const key = (
                        row.original.parcel_status ??
                        row.original.order.parcel_status ??
                        ''
                    ).toLowerCase();
                    const cfg = authParcelStatusConfig[key] as
                        | ParcelStatusEntry
                        | undefined;
                    return (
                        <div className="space-y-1.5">
                            <div className="space-y-0.5">
                                {items.map((item, i) => (
                                    <p
                                        key={i}
                                        className="text-[12px] leading-snug font-medium text-gray-800 dark:text-gray-200"
                                    >
                                        {item.name}
                                    </p>
                                ))}
                            </div>
                            <div className="flex items-center gap-2">
                                {trackingCode && (
                                    <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                        {trackingCode}
                                    </span>
                                )}
                            </div>
                            <div>
                                {cfg ? (
                                    <span
                                        className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium ${cfg.pill}`}
                                    >
                                        <span
                                            className={`h-1.5 w-1.5 rounded-full ${cfg.dot}`}
                                        />
                                        {cfg.label}
                                    </span>
                                ) : key ? (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        {key}
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'rider_name',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Rider" />
                ),
                cell: ({ row }) => {
                    const attempts = row.original.rider_call_logs_count ?? 0;
                    const duration = row.original.rider_call_duration ?? 0;
                    const phone = row.original.rider_phone ?? '';
                    return (
                        <div className="space-y-1.5">
                            <p className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                                {row.original.rider_name || '—'}
                            </p>
                            <EditablePhone
                                value={phone}
                                onSave={(v) =>
                                    handleUpdatePhone(
                                        row.original.id,
                                        'rider_phone',
                                        v,
                                    )
                                }
                                disabled={!canEditPhone}
                            />
                            <CallLogBadge
                                attempts={attempts}
                                duration={duration}
                                onClick={() =>
                                    phone &&
                                    setCallLogModal({
                                        phone,
                                        label:
                                            row.original.rider_name || 'Rider',
                                    })
                                }
                            />
                        </div>
                    );
                },
            },
            {
                id: 'order_shipping_address_full_name',
                accessorKey: 'order.shipping_address.full_name',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Customer" />
                ),
                cell: ({ row }) => {
                    const addr = row.original.order.shipping_address;
                    const attempts = row.original.customer_call_logs_count ?? 0;
                    const duration = row.original.customer_call_duration ?? 0;
                    const phone =
                        row.original.customer_phone ?? addr?.phone_number ?? '';
                    return (
                        <div className="space-y-1.5">
                            <p className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                                {addr?.full_name || '—'}
                            </p>
                            <EditablePhone
                                value={phone}
                                onSave={(v) =>
                                    handleUpdatePhone(
                                        row.original.id,
                                        'customer_phone',
                                        v,
                                    )
                                }
                                disabled={!canEditPhone}
                            />
                            <CallLogBadge
                                attempts={attempts}
                                duration={duration}
                                onClick={() =>
                                    phone &&
                                    setCallLogModal({
                                        phone,
                                        label: addr?.full_name || 'Customer',
                                    })
                                }
                            />
                            {addr?.full_address && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <p className="flex max-w-40 cursor-default items-center gap-1 text-[11px] text-gray-400 dark:text-gray-500">
                                            <MapPin className="h-3 w-3 shrink-0" />
                                            <span className="truncate">
                                                {addr.full_address}
                                            </span>
                                        </p>
                                    </TooltipTrigger>
                                    <TooltipContent side="bottom">
                                        <p className="max-w-xs text-xs">
                                            {addr.full_address}
                                        </p>
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
                header: ({ column }) => (
                    <SortableHeader column={column} title="SRP" />
                ),
                cell: ({ row }) => (
                    <span className="text-center font-mono text-[12px] text-gray-700 tabular-nums dark:text-gray-300">
                        {currencyFormatter(row.original.order.final_amount)}
                    </span>
                ),
            },
            {
                id: 'order_delivery_attempts',
                accessorKey: 'order.delivery_attempts',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Attempts" />
                ),
                cell: ({ row }) => {
                    const attempts = row.original.order.delivery_attempts ?? 0;
                    const isMultiple = attempts > 1;
                    return (
                        <span
                            className={`inline-flex items-center justify-center rounded-full px-2 py-0.5 text-center text-[11px] font-medium tabular-nums ${
                                isMultiple
                                    ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'
                                    : 'text-gray-500 dark:text-gray-400'
                            }`}
                        >
                            {attempts}
                        </span>
                    );
                },
            },
            {
                id: 'cx_rts_rate',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Cx. RTS" />
                ),
                cell: ({ row }) => {
                    const rate = row.original.order?.cx_rts_rate ?? null;
                    if (rate === null) {
                        return (
                            <span className="text-center text-[12px] text-gray-300 dark:text-gray-600">
                                —
                            </span>
                        );
                    }
                    const isHigh = rate >= 0.4;
                    return (
                        <span
                            className={`text-center text-[12px] font-medium tabular-nums ${isHigh ? 'text-red-500 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'}`}
                        >
                            {percentageFormatter(rate)}
                        </span>
                    );
                },
            },
            {
                id: 'order_shipping_address_city_order_summary_rts_rate',
                accessorKey:
                    'order.shipping_address.city_order_summary.rts_rate',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Loc. RTS" />
                ),
                cell: ({ row }) => {
                    const rate =
                        row.original.order?.shipping_address?.city_order_summary
                            ?.rts_rate ?? 0;
                    const isHigh = rate >= 0.4;
                    return (
                        <span
                            className={`text-center text-[12px] font-medium tabular-nums ${isHigh ? 'text-red-500 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'}`}
                        >
                            {percentageFormatter(rate)}
                        </span>
                    );
                },
            },
            {
                id: 'rider_rts_rate',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Rider RTS" />
                ),
                cell: ({ row }) => {
                    const rate = row.original.rider_rts_rate ?? null;
                    if (rate === null) {
                        return (
                            <span className="text-center text-[12px] text-gray-300 dark:text-gray-600">
                                —
                            </span>
                        );
                    }
                    const isHigh = rate >= 0.4;
                    return (
                        <span
                            className={`text-center text-[12px] font-medium tabular-nums ${isHigh ? 'text-red-500 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'}`}
                        >
                            {percentageFormatter(rate)}
                        </span>
                    );
                },
            },
            {
                id: 'conferrer_name',
                accessorKey: 'conferrer.name',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Confirmed By" />
                ),
                cell: ({ row }) => (
                    <span className="text-[12px] text-gray-600 dark:text-gray-400">
                        {row.original.conferrer?.name ?? (
                            <span className="text-gray-300 italic dark:text-gray-600">
                                —
                            </span>
                        )}
                    </span>
                ),
            },
            {
                accessorKey: 'assignee.name',
                enableSorting: false,
                header: ({ column }) => (
                    <SortableHeader
                        enabled={false}
                        column={column}
                        title="Assignee"
                    />
                ),
                cell: ({ row }) => {
                    const assignee = row.original.assignee;
                    const id = row.original.id;

                    if (!assignee) {
                        return (
                            <button
                                onClick={() => handleAssignToMe(id)}
                                disabled={!isToday}
                                className="flex items-center gap-1.5 rounded-lg border border-dashed border-black/10 px-2.5 py-1 text-[11px] font-medium text-gray-400 transition-all hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-600 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-black/10 disabled:hover:bg-transparent disabled:hover:text-gray-400 dark:border-white/10 dark:text-gray-500 dark:hover:border-emerald-500/40 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-400 dark:disabled:hover:border-white/10 dark:disabled:hover:bg-transparent dark:disabled:hover:text-gray-500"
                            >
                                <UserPlus className="h-3 w-3" />
                                Assign to me
                            </button>
                        );
                    }

                    return (
                        <div className="group flex items-center gap-1.5">
                            <span className="text-[12px] text-gray-700 dark:text-gray-300">
                                {assignee.name}
                            </span>
                            {isToday && (
                                <button
                                    onClick={() => handleRemoveAssignee(id)}
                                    className="invisible flex h-4 w-4 shrink-0 items-center justify-center rounded text-gray-300 transition-colors group-hover:visible hover:bg-red-50 hover:text-red-400 dark:text-gray-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
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
                header: ({ column }) => (
                    <SortableHeader column={column} title="Status" />
                ),
                cell: ({ row }) => (
                    <RmoStatusPicker
                        currentStatus={row.original.status as OrderStatus}
                        onChangeStatus={(status) =>
                            handleChangeStatus(status, row.original.id)
                        }
                        disabled={!isToday}
                    />
                ),
            },
        ],
        [
            handleAssignToMe,
            handleRemoveAssignee,
            handleChangeStatus,
            handleUpdatePhone,
            isToday,
            canEditPhone,
        ],
    );

    return (
        <CsrAwareLayout>
            <Head title={`${workspace.name} - RMO Management`} />

            <CallLogModal
                open={!!callLogModal}
                onOpenChange={(open) => {
                    if (!open) setCallLogModal(null);
                }}
                phoneNumber={callLogModal?.phone ?? ''}
                label={callLogModal?.label ?? ''}
                workspaceSlug={workspace.slug}
                date={deliveryDate}
                csrName={userName}
            />

            {/* Export column picker modal */}
            <Dialog open={exportModalOpen} onOpenChange={setExportModalOpen}>
                <DialogContent className="max-w-sm">
                    <DialogHeader>
                        <DialogTitle className="text-sm font-semibold">
                            Export Columns
                        </DialogTitle>
                        <p className="text-[11px] text-gray-400 dark:text-gray-500">
                            Select which columns to include in the export.
                        </p>
                    </DialogHeader>
                    <div className="max-h-72 space-y-1.5 overflow-y-auto py-2">
                        {EXPORT_COLUMNS.map((col) => (
                            <label
                                key={col.key}
                                className="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-stone-50 dark:hover:bg-zinc-800"
                            >
                                <Checkbox
                                    checked={exportColumns.includes(col.key)}
                                    onCheckedChange={(checked) => {
                                        setExportColumns((prev) =>
                                            checked
                                                ? [...prev, col.key]
                                                : prev.filter(
                                                      (k) => k !== col.key,
                                                  ),
                                        );
                                    }}
                                />
                                <span className="text-[12px] text-gray-700 dark:text-gray-300">
                                    {col.label}
                                </span>
                            </label>
                        ))}
                    </div>
                    <div className="flex items-center justify-between border-t pt-3 dark:border-white/6">
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() =>
                                    setExportColumns([...ALL_COLUMN_KEYS])
                                }
                                className="text-[11px] font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300"
                            >
                                Select all
                            </button>
                            <span className="text-gray-300 dark:text-gray-600">
                                |
                            </span>
                            <button
                                onClick={() => setExportColumns([])}
                                className="text-[11px] font-medium text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                            >
                                Clear
                            </button>
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => setExportModalOpen(false)}
                                className="rounded-lg border border-black/10 px-3 py-1.5 text-[12px] font-medium text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:text-gray-400 dark:hover:bg-zinc-800"
                            >
                                Cancel
                            </button>
                            <Button
                                size="sm"
                                disabled={exportColumns.length === 0}
                                onClick={() => {
                                    doExport(exportColumns);
                                    setExportModalOpen(false);
                                }}
                                className="rounded-lg bg-emerald-600 px-4 text-[12px] font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                            >
                                <Download className="mr-1.5 h-3.5 w-3.5" />
                                Download
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <div className="mx-auto w-full p-4 md:p-6">
                <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="min-w-0">
                        <h1 className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                            RMO Management
                        </h1>
                        <p className="mt-0.5 text-[13px] text-gray-400 dark:text-gray-500">
                            Delivery tracking for assigned orders on{' '}
                            {new Date(deliveryDate).toLocaleDateString(
                                'en-US',
                                {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric',
                                },
                            )}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Filters
                            workspace={workspace}
                            onChange={handleFilterChange}
                            initialValue={initialFilterValue}
                        />

                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setExportModalOpen(true)}
                            className="flex items-center gap-1.5 rounded-lg text-[12px]"
                        >
                            <Download className="h-3.5 w-3.5" />
                            Export
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setShowStats((prev) => !prev)}
                            className="flex items-center gap-1.5 rounded-lg text-[12px]"
                        >
                            <BarChart3 className="h-3.5 w-3.5" />
                            {showStats ? 'Hide' : 'Show'} Statistics
                            {showStats ? (
                                <ChevronUp className="h-3.5 w-3.5" />
                            ) : (
                                <ChevronDown className="h-3.5 w-3.5" />
                            )}
                        </Button>

                        <DatePicker
                            id="delivery-date"
                            mode="single"
                            defaultDate={deliveryDate}
                            placeholder="Select date"
                            onChange={(_, dateStr) => {
                                if (dateStr && dateStr !== deliveryDate)
                                    handleDateChange(dateStr);
                            }}
                        />

                        {pancakeAccounts.length > 1 ? (
                            <div className="relative flex items-center">
                                <UserIcon className="pointer-events-none absolute left-2.5 h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
                                <select
                                    value={activePancakeAccount?.id ?? ''}
                                    onChange={(e) => {
                                        const account = pancakeAccounts.find(
                                            (a) => a.id === e.target.value,
                                        );
                                        if (account)
                                            setActivePancakeAccount(account);
                                    }}
                                    className="h-8 appearance-none rounded-lg border border-black/6 bg-white py-0 pr-7 pl-8 text-[12px] font-medium text-gray-700 outline-none transition-colors hover:border-black/12 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:border-white/12 dark:focus:border-emerald-400"
                                >
                                    {pancakeAccounts.map((account) => (
                                        <option
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.name}
                                        </option>
                                    ))}
                                </select>
                                <ChevronDown className="pointer-events-none absolute right-2 h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
                            </div>
                        ) : activePancakeAccount ? (
                            <div className="flex h-8 items-center gap-2 rounded-lg border border-black/6 bg-white px-3 dark:border-white/6 dark:bg-zinc-800">
                                <UserIcon className="h-3.5 w-3.5 text-emerald-500" />
                                <span className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                                    {activePancakeAccount.name}
                                </span>
                            </div>
                        ) : null}
                    </div>
                </div>

                {showStats && (
                    <div className="mb-6">
                        <RmoStatCards
                            total_for_delivery_today={total_for_delivery_today}
                            called_count={called_count}
                            delivered_count={delivered_count}
                            returning_count={returning_count}
                            problematic_count={problematic_count}
                        />
                    </div>
                )}

                <div className="mb-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative w-full sm:max-w-sm md:max-w-md">
                            <Search className="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                className="h-8 w-full rounded-lg border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! outline-none focus:border-emerald-500 dark:bg-zinc-800 dark:text-gray-300"
                                placeholder="Search by order #, tracking code, rider, customer, or confirmed by..."
                                value={searchValue}
                                onChange={(e) => setSearchValue(e.target.value)}
                            />
                        </div>

                        <select
                            value={currentStatus}
                            onChange={(e) => handleStatusChange(e.target.value)}
                            className="h-8 rounded-lg border border-black/6 bg-stone-100 px-2 text-[12px]! text-gray-700 outline-none focus:border-emerald-500 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            <option value="">All Statuses</option>
                            {Object.keys(orderStatusConfig).map((status) => (
                                <option key={status} value={status}>
                                    {status}
                                </option>
                            ))}
                        </select>

                        <select
                            value={currentParcelStatus}
                            onChange={(e) =>
                                handleParcelStatusChange(e.target.value)
                            }
                            className="h-8 rounded-lg border border-black/6 bg-stone-100 px-2 text-[12px]! text-gray-700 outline-none focus:border-emerald-500 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            <option value="">All Parcel Statuses</option>
                            {Object.entries(authParcelStatusConfig).map(
                                ([key, config]) => (
                                    <option key={key} value={key}>
                                        {config.label}
                                    </option>
                                ),
                            )}
                        </select>

                        <button
                            type="button"
                            onClick={() =>
                                setShowMyAssigneeOnly((prev) => !prev)
                            }
                            className={`inline-flex h-8 items-center gap-2 rounded-lg border px-3 text-[12px]! font-medium transition-all ${
                                showMyAssigneeOnly
                                    ? 'border-emerald-500/40 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-400'
                                    : 'border-black/6 bg-stone-100 text-gray-500 hover:border-black/12 hover:text-gray-700 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-gray-200'
                            }`}
                        >
                            <span
                                className={`inline-flex h-3.5 w-3.5 items-center justify-center rounded border ${
                                    showMyAssigneeOnly
                                        ? 'border-emerald-500 bg-emerald-500 dark:border-emerald-400 dark:bg-emerald-400'
                                        : 'border-gray-300 dark:border-gray-600'
                                }`}
                            >
                                {showMyAssigneeOnly && (
                                    <svg
                                        className="h-2.5 w-2.5 text-white"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        strokeWidth={3}
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                )}
                            </span>
                            My Assignee Only
                        </button>

                        <button
                            type="button"
                            onClick={() =>
                                setShowMyConfirmeeOnly((prev) => !prev)
                            }
                            className={`inline-flex h-8 items-center gap-2 rounded-lg border px-3 text-[12px]! font-medium transition-all ${
                                showMyConfirmeeOnly
                                    ? 'border-emerald-500/40 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-400'
                                    : 'border-black/6 bg-stone-100 text-gray-500 hover:border-black/12 hover:text-gray-700 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-gray-200'
                            }`}
                        >
                            <span
                                className={`inline-flex h-3.5 w-3.5 items-center justify-center rounded border ${
                                    showMyConfirmeeOnly
                                        ? 'border-emerald-500 bg-emerald-500 dark:border-emerald-400 dark:bg-emerald-400'
                                        : 'border-gray-300 dark:border-gray-600'
                                }`}
                            >
                                {showMyConfirmeeOnly && (
                                    <svg
                                        className="h-2.5 w-2.5 text-white"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        strokeWidth={3}
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                )}
                            </span>
                            My Confirmee Only
                        </button>

                        {window.location.hostname === 'efb.on-forge.com' && (
                            <div className="ml-auto flex items-center gap-2">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={
                                                pendingOrders.length === 0
                                            }
                                            onClick={() =>
                                                copyPendingPhones('rider')
                                            }
                                            className="flex items-center gap-1.5 rounded-lg text-[12px]"
                                        >
                                            <ClipboardCopy className="h-3.5 w-3.5" />
                                            {copiedRider
                                                ? 'Copied!'
                                                : 'Copy Rider Phones'}
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent side="bottom">
                                        <p className="text-xs">
                                            Copy rider phone numbers from top 10
                                            pending orders
                                        </p>
                                    </TooltipContent>
                                </Tooltip>

                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={
                                                pendingOrders.length === 0
                                            }
                                            onClick={() =>
                                                copyPendingPhones('customer')
                                            }
                                            className="flex items-center gap-1.5 rounded-lg text-[12px]"
                                        >
                                            <ClipboardCopy className="h-3.5 w-3.5" />
                                            {copiedCustomer
                                                ? 'Copied!'
                                                : 'Copy CX Phones'}
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent side="bottom">
                                        <p className="text-xs">
                                            Copy customer phone numbers from top
                                            10 pending orders
                                        </p>
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                        )}
                    </div>
                </div>

                <div className="max-w-full overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={orders.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(orders, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                rmoUrl,
                                buildAllParams(
                                    params?.sort as string | null,
                                    params?.page as number | undefined,
                                    undefined,
                                    undefined,
                                    params?.per_page as number | undefined,
                                ),
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
        </CsrAwareLayout>
    );
}
