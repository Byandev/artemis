import Filters, { FilterValue } from '@/components/filters/Filters';
import InputError from '@/components/input-error';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { toFrontendSort } from '@/lib/sort';
import { currencyFormatter, percentageFormatter } from '@/lib/utils';
import publicPage from '@/routes/public-page';
import { PaginatedData, SharedData } from '@/types';
import { CallLog } from '@/types/models/CallLog';
import {
    ORDER_STATUSES,
    OrderForDelivery,
    OrderStatus,
} from '@/types/models/Pancake/OrderForDelivery';
import { User } from '@/types/models/Pancake/User';
import { Workspace } from '@/types/models/Workspace';
import { router, useForm, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import {
    BarChart3,
    ChevronDown,
    ChevronUp,
    ClipboardCopy,
    Download,
    ListChecks,
    Lock,
    MapPin,
    Pencil,
    Phone,
    PhoneCall,
    Search,
    Tags,
    User as UserIcon,
    UserPlus,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast, Toaster } from 'sonner';
import FormModal from './formModal';

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
    /** When true, the page is password-gated and data props are omitted. */
    locked?: boolean;
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
        /** Assignee / confirmee narrowing — top-level, not part of filter[]. */
        assignee_id?: string | null;
        confirmee_id?: string | null;
        /** Whose calls the call-log cards report. */
        caller_id?: string | null;
    };
    users: User[];
    /**
     * Workspace-wide switch stored on rmo_settings. When on, every past
     * delivery date is assignable / re-statusable.
     */
    enable_edit_previous_day?: boolean;
    /**
     * Workspace-wide switch stored on rmo_settings. When on, the selection bar
     * offers a "Set status" action that re-statuses every selected order.
     */
    enable_bulk_status_update?: boolean;
    /**
     * Workspace-wide switch stored on rmo_settings. When on, delivered and
     * returning parcels re-tag their RMO status nightly — which also means they
     * overwrite manual edits, so the page says so out loud.
     */
    enable_auto_tag_status?: boolean;
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

/** The three things a CSR rings, in the order the modal lists them. */
const BREAKDOWN_GROUPS = [
    {
        key: 'rmo_customer' as const,
        label: 'RMO Customer',
        hint: 'Calls to the customer on a delivery loaded for this day.',
    },
    {
        key: 'rmo_rider' as const,
        label: 'RMO Rider',
        hint: "Calls to the rider carrying one of this day's deliveries.",
    },
    {
        key: 'order_verification' as const,
        label: 'Order Verification',
        hint: 'Not tracked yet — verification calls are not marked as such at the source, and a call that matched no delivery is just as likely a wrong number or a late-syncing order.',
    },
];

interface BreakdownLog {
    id: number;
    phone_number: string;
    type: string;
    duration: number;
    call_time: string;
    persona: string | null;
    called_by: string | null;
    order_number: string | null;
}

interface BreakdownGroup {
    calls: number;
    duration: number;
    connected: number;
    logs: BreakdownLog[];
}

type BreakdownResponse = {
    date: string;
    groups: Record<
        'rmo_customer' | 'rmo_rider' | 'order_verification',
        BreakdownGroup
    >;
};

/**
 * The day's calls split by who was on the other end.
 *
 * The stat cards say how many calls were made; this says what they were for,
 * which is the question that actually follows. Each tab carries its own totals
 * so the split is readable before opening any of them.
 */
function CallLogBreakdownModal({
    open,
    onOpenChange,
    workspaceSlug,
    date,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspaceSlug: string;
    date: string;
}) {
    const [data, setData] = useState<BreakdownResponse | null>(null);
    const [loading, setLoading] = useState(false);
    const [tab, setTab] = useState<string>(BREAKDOWN_GROUPS[0].key);

    useEffect(() => {
        if (!open) return;
        setLoading(true);
        fetch(
            `/public/workspaces/${workspaceSlug}/rts/rmo-management/call-logs/breakdown?date=${date}`,
        )
            .then((r) => r.json())
            .then(setData)
            .finally(() => setLoading(false));
    }, [open, workspaceSlug, date]);

    const totalCalls = data
        ? BREAKDOWN_GROUPS.reduce(
              (sum, g) => sum + (data.groups[g.key]?.calls ?? 0),
              0,
          )
        : 0;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="text-sm font-semibold">
                        Call Logs Breakdown
                    </DialogTitle>
                    <p className="text-[11px] text-gray-400 dark:text-gray-500">
                        {date} · {totalCalls} call{totalCalls !== 1 ? 's' : ''}{' '}
                        across all users
                    </p>
                </DialogHeader>

                {loading ? (
                    <p className="py-10 text-center text-[12px] text-gray-400">
                        Loading…
                    </p>
                ) : !data ? (
                    <p className="py-10 text-center text-[12px] text-gray-400">
                        Could not load the breakdown
                    </p>
                ) : (
                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList className="grid w-full grid-cols-3">
                            {BREAKDOWN_GROUPS.map((g) => (
                                <TabsTrigger
                                    key={g.key}
                                    value={g.key}
                                    className="text-[11px]"
                                >
                                    {g.label}
                                    <span className="ml-1.5 rounded-full bg-black/5 px-1.5 py-0.5 font-mono text-[10px] dark:bg-white/10">
                                        {data.groups[g.key]?.calls ?? '—'}
                                    </span>
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        {BREAKDOWN_GROUPS.map((g) => {
                            const group = data.groups[g.key];

                            // Null is "we don't measure this yet" — a zeroed-out
                            // panel would read as a quiet day instead.
                            if (!group) {
                                return (
                                    <TabsContent key={g.key} value={g.key}>
                                        <div className="py-10 text-center">
                                            <p className="text-[12px] font-medium text-gray-500 dark:text-gray-400">
                                                Not tracked yet
                                            </p>
                                            <p className="mx-auto mt-1 max-w-sm text-[11px] text-gray-400 dark:text-gray-500">
                                                {g.hint}
                                            </p>
                                        </div>
                                    </TabsContent>
                                );
                            }

                            return (
                                <TabsContent key={g.key} value={g.key}>
                                    <p className="mb-3 text-[11px] text-gray-400 dark:text-gray-500">
                                        {g.hint}
                                    </p>

                                    <div className="mb-3 grid grid-cols-3 gap-2">
                                        {[
                                            {
                                                label: 'Calls',
                                                value: group.calls.toLocaleString(),
                                            },
                                            {
                                                label: 'Duration',
                                                value: formatDuration(
                                                    group.duration,
                                                ),
                                            },
                                            {
                                                label: 'Connected (5s+)',
                                                value: group.connected.toLocaleString(),
                                            },
                                        ].map((stat) => (
                                            <div
                                                key={stat.label}
                                                className="rounded-[10px] border border-black/6 px-3 py-2 dark:border-white/6"
                                            >
                                                <p className="text-[10px] text-gray-400 dark:text-gray-500">
                                                    {stat.label}
                                                </p>
                                                <p className="font-mono text-[14px] font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                                                    {stat.value}
                                                </p>
                                            </div>
                                        ))}
                                    </div>

                                    {group.logs.length === 0 ? (
                                        <p className="py-8 text-center text-[12px] text-gray-400">
                                            No calls in this group
                                        </p>
                                    ) : (
                                        <>
                                            <div className="max-h-64 overflow-y-auto">
                                                <table className="w-full text-[12px]">
                                                    <thead>
                                                        <tr className="border-b text-left text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                                            <th className="pr-3 pb-2">
                                                                Time
                                                            </th>
                                                            <th className="pr-3 pb-2">
                                                                Number
                                                            </th>
                                                            <th className="pr-3 pb-2">
                                                                Order
                                                            </th>
                                                            <th className="pr-3 pb-2">
                                                                By
                                                            </th>
                                                            <th className="pr-3 pb-2 text-right">
                                                                Duration
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {group.logs.map(
                                                            (log) => (
                                                                <tr
                                                                    key={log.id}
                                                                    className="border-b border-black/5 dark:border-white/5"
                                                                >
                                                                    <td className="py-2 pr-3 font-mono text-gray-600 dark:text-gray-300">
                                                                        {
                                                                            log.call_time
                                                                        }
                                                                    </td>
                                                                    <td className="py-2 pr-3 font-mono text-[11px] text-gray-600 dark:text-gray-300">
                                                                        {
                                                                            log.phone_number
                                                                        }
                                                                    </td>
                                                                    <td className="py-2 pr-3 font-mono text-[11px] text-gray-500 dark:text-gray-400">
                                                                        {log.order_number ??
                                                                            '—'}
                                                                    </td>
                                                                    <td className="py-2 pr-3 text-[11px] text-gray-600 dark:text-gray-300">
                                                                        {log.called_by ??
                                                                            '—'}
                                                                    </td>
                                                                    <td className="py-2 pr-3 text-right font-mono text-gray-600 dark:text-gray-300">
                                                                        {formatDuration(
                                                                            log.duration,
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>

                                            {group.logs.length <
                                                group.calls && (
                                                <p className="mt-2 text-[11px] text-gray-400 dark:text-gray-500">
                                                    Showing the{' '}
                                                    {group.logs.length} most
                                                    recent of {group.calls}{' '}
                                                    calls. The totals above
                                                    cover all of them.
                                                </p>
                                            )}
                                        </>
                                    )}
                                </TabsContent>
                            );
                        })}
                    </Tabs>
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

function CallLogModal({
    open,
    onOpenChange,
    phoneNumber,
    label,
    workspaceSlug,
    date,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    phoneNumber: string;
    label: string;
    workspaceSlug: string;
    date: string;
}) {
    const [logs, setLogs] = useState<CallLog[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open || !phoneNumber) return;
        setLoading(true);
        fetch(
            `/public/workspaces/${workspaceSlug}/rts/rmo-management/call-logs?phone_number=${encodeURIComponent(phoneNumber)}&date=${date}`,
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
                                            {log.called_by ?? '—'}
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

/** The ten stat-card figures, as the `stats` endpoint returns them. */
interface RmoStats {
    total_for_delivery_today: number;
    called_count: number;
    delivered_count: number;
    returning_count: number;
    problematic_count: number;
    /** Calls placed by the caller the page asked about. */
    total_call_logs_count: number;
    /** Combined talk time of those calls, in seconds. */
    total_call_duration: number;
    /** How many of those calls lasted at least 5 seconds. */
    connected_call_logs_count: number;
    /**
     * Derived server-side rather than here, so these and the daily Discord
     * report quote the same arithmetic. Null, not zero, when nobody has called.
     */
    avg_call_duration: number | null;
    hit_rate: number | null;
}

function RmoManagement({
    orders,
    workspace,
    query,
    users,
    enable_edit_previous_day = false,
    enable_bulk_status_update = false,
    enable_auto_tag_status = false,
}: Props) {
    const { appEnv, flash } = usePage<SharedData>().props;
    const canEditPhone = appEnv !== 'production';

    // This page renders outside the app/CSR layouts, so it has no Toaster of
    // its own. Without this every controller rejection — "status can only be
    // updated for…", "please select a user" — redirects back silently and a
    // click looks like it did nothing.
    const flashMessages = flash as
        | { success?: string | null; error?: string | null }
        | undefined;

    useEffect(() => {
        if (flashMessages?.success) {
            toast.success(flashMessages.success);
        }
        if (flashMessages?.error) {
            toast.error(flashMessages.error);
        }
    }, [flashMessages]);

    // Who the page is acting as — picked in the "Logged in as" button up top and
    // remembered in localStorage. Held in state rather than read out of
    // localStorage at each call site: the "mine only" toggles, the exports and
    // the stat cards all resolve against it, and changing it has to re-run every
    // one of them. Read during render, like the toggles below, so the very first
    // visit already carries the right id.
    const [currentUserId, setCurrentUserId] = useState<string>(
        () => localStorage.getItem('user_id') ?? '',
    );
    const [userName, setUserName] = useState<string | false>(false);
    const [isOpen, setIsOpen] = useState(false);
    const [showStats, setShowStats] = useState(
        () => localStorage.getItem('rmo_show_stats') === 'true',
    );
    const [showMyAssigneeOnly, setShowMyAssigneeOnly] = useState(
        () => localStorage.getItem('rmo_show_my_assignee_only') === 'true',
    );
    const [showMyConfirmeeOnly, setShowMyConfirmeeOnly] = useState(
        () => localStorage.getItem('rmo_show_my_confirmee_only') === 'true',
    );
    const [pendingAssign, setPendingAssign] = useState<{
        id: number;
        currentStatus: string;
    } | null>(null);
    const [callLogModal, setCallLogModal] = useState<{
        phone: string;
        label: string;
    } | null>(null);
    const [stats, setStats] = useState<RmoStats | null>(null);
    const [statsLoading, setStatsLoading] = useState(true);
    const [exportModalOpen, setExportModalOpen] = useState(false);
    const [breakdownOpen, setBreakdownOpen] = useState(false);
    const [exportColumns, setExportColumns] = useState<string[]>(() => {
        const saved = localStorage.getItem('rmo_export_columns');
        return saved ? JSON.parse(saved) : [...ALL_COLUMN_KEYS];
    });

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

    // Whose orders to show, picked by name. "My Assignee Only" is the same
    // narrowing pinned to the logged-in CSR, so the toggle wins while it's on
    // and the picker is left disabled rather than silently ignored.
    const [selectedAssigneeId, setSelectedAssigneeId] = useState<string>(() =>
        showMyAssigneeOnly ? '' : (query?.assignee_id ?? ''),
    );

    // Use URL as source of truth for status (avoids stale closure issues with select)
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

    // CSRs by name, for the assignee picker — the list comes off the shops in
    // this workspace, in whatever order the query returned it.
    const assigneeOptions = useMemo(
        () =>
            [...users].sort((a, b) =>
                (a.name ?? '').localeCompare(b.name ?? ''),
            ),
        [users],
    );

    // The assignee actually sent to the server: the toggle pins it to the
    // logged-in CSR, otherwise it's whoever the picker has selected.
    const activeAssigneeId = useMemo(
        () => (showMyAssigneeOnly ? currentUserId : selectedAssigneeId),
        [showMyAssigneeOnly, currentUserId, selectedAssigneeId],
    );

    // Whose calls the five call-log cards count. Those cards are about who was
    // on the phone, so they follow the person, not the order set.
    //
    // Only an explicit narrowing cuts them: either "mine only" toggle, or a name
    // picked in the assignee filter. Having identified yourself in the picker up
    // top does not — that is who you are, not a filter, and the page opens on
    // the whole workspace's day until you ask for less.
    const callerId =
        activeAssigneeId || (showMyConfirmeeOnly ? currentUserId : '');

    const todayLocal = (() => {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    })();
    const yesterdayLocal = (() => {
        const d = new Date();
        d.setDate(d.getDate() - 1);
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    })();
    const deliveryDate = query?.delivery_date ?? todayLocal;
    const isToday = deliveryDate === todayLocal;
    const isYesterday = deliveryDate === yesterdayLocal;
    // Any past delivery date — not just yesterday — is editable when the
    // workspace enabled the switch.
    const canEditPastDay =
        enable_edit_previous_day && deliveryDate < todayLocal;

    // Exactly the inputs the stats endpoint reads — deliberately not the search
    // box or the status selects, which don't move the figures. Keeping it narrow
    // is what stops every keystroke in search from firing a stats request.
    const statsQuery = useMemo(() => {
        const params = new URLSearchParams();
        params.set('delivery_date', deliveryDate);
        if (selectedPageIds.length)
            params.set('filter[page_id]', selectedPageIds.join(','));
        if (selectedShopIds.length)
            params.set('filter[shop_id]', selectedShopIds.join(','));
        if (selectedUserIds.length)
            params.set('filter[user_id]', selectedUserIds.join(','));
        if (activeAssigneeId) params.set('assignee_id', activeAssigneeId);
        if (showMyConfirmeeOnly && currentUserId)
            params.set('confirmee_id', currentUserId);
        if (callerId) params.set('caller_id', callerId);
        return params.toString();
    }, [
        deliveryDate,
        selectedPageIds,
        selectedShopIds,
        selectedUserIds,
        activeAssigneeId,
        showMyConfirmeeOnly,
        currentUserId,
        callerId,
    ]);

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

    const buildAllParams = useCallback(
        (
            sort?: string | null,
            page?: number,
            status?: string,
            parcelStatus?: string,
            perPage?: number,
            /**
             * Freshly picked filter ids, for callers that change them in the
             * same tick and so can't read them back off state yet.
             */
            ids?: {
                pageIds?: string[];
                shopIds?: string[];
                userIds?: string[];
                assigneeId?: string;
            },
        ) => {
            const pageIds = ids?.pageIds ?? selectedPageIds;
            const shopIds = ids?.shopIds ?? selectedShopIds;
            const userIds = ids?.userIds ?? selectedUserIds;
            const assigneeId = showMyAssigneeOnly
                ? currentUserId
                : (ids?.assigneeId ?? selectedAssigneeId);

            return {
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
                ...(pageIds.length
                    ? { 'filter[page_id]': pageIds.join(',') }
                    : {}),
                ...(shopIds.length
                    ? { 'filter[shop_id]': shopIds.join(',') }
                    : {}),
                ...(userIds.length
                    ? { 'filter[user_id]': userIds.join(',') }
                    : {}),
                page: page ?? 1,
                per_page: perPage ?? orders.per_page,
                delivery_date: deliveryDate,
                ...(assigneeId ? { assignee_id: assigneeId } : {}),
                ...(assigneeId || currentUserId
                    ? { caller_id: assigneeId || currentUserId }
                    : {}),
                ...(showMyConfirmeeOnly && currentUserId
                    ? { confirmee_id: currentUserId }
                    : {}),
            };
        },
        [
            searchValue,
            currentStatus,
            currentParcelStatus,
            selectedPageIds,
            selectedShopIds,
            selectedUserIds,
            selectedAssigneeId,
            showMyAssigneeOnly,
            showMyConfirmeeOnly,
            currentUserId,
            orders.per_page,
            deliveryDate,
        ],
    );

    const handleAssigneeChange = useCallback(
        (assigneeId: string) => {
            setSelectedAssigneeId(assigneeId);
            router.get(
                publicPage.rmoManagement({ workspace }),
                buildAllParams(
                    query?.sort,
                    1,
                    undefined,
                    undefined,
                    undefined,
                    { assigneeId },
                ),
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [workspace, buildAllParams, query?.sort],
    );

    const handleFilterChange = useCallback(
        (value: FilterValue) => {
            const newPageIds = value.pageIds.map(String);
            const newShopIds = value.shopIds.map(String);
            const newUserIds = value.userIds.map(String);

            setSelectedPageIds(newPageIds);
            setSelectedShopIds(newShopIds);
            setSelectedUserIds(newUserIds);

            // Goes through buildAllParams so the delivery date, page size and
            // "mine only" toggles survive a filter change. Hand-rolling the
            // params here used to drop delivery_date, which sent the server
            // back to today and blanked the table on any other date.
            router.get(
                publicPage.rmoManagement({ workspace }),
                buildAllParams(
                    query?.sort,
                    1,
                    undefined,
                    undefined,
                    undefined,
                    {
                        pageIds: newPageIds,
                        shopIds: newShopIds,
                        userIds: newUserIds,
                    },
                ),
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [workspace, buildAllParams, query?.sort],
    );

    const handleStatusChange = useCallback(
        (status: string) => {
            router.get(
                publicPage.rmoManagement({ workspace }),
                buildAllParams(query?.sort, 1, status),
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [workspace, buildAllParams, query?.sort],
    );

    const handleParcelStatusChange = useCallback(
        (parcelStatus: string) => {
            router.get(
                publicPage.rmoManagement({ workspace }),
                buildAllParams(query?.sort, 1, undefined, parcelStatus),
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [workspace, buildAllParams, query?.sort],
    );

    useEffect(() => {
        const name = localStorage.getItem('user_name');
        if (name) setUserName(name);
    }, []);

    // The cards come from their own endpoint, so they reload on their own —
    // independently of the table, and only when something they actually depend
    // on changes. Sorting, paging and searching leave them alone.
    //
    // Nothing is fetched while the cards are collapsed, which is the default.
    useEffect(() => {
        if (!showStats) return;

        const controller = new AbortController();
        setStatsLoading(true);

        fetch(
            `/public/workspaces/${workspace.slug}/rts/rmo-management/stats?${statsQuery}`,
            {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            },
        )
            .then((response) => {
                if (!response.ok) throw new Error(String(response.status));
                return response.json();
            })
            .then((data: RmoStats) => {
                setStats(data);
                setStatsLoading(false);
            })
            .catch((error: Error) => {
                // An abort is this effect superseding itself — the newer request
                // owns the loading flag now, so leave it be.
                if (error.name === 'AbortError') return;
                setStatsLoading(false);
                toast.error('Could not load the stat cards.');
            });

        return () => controller.abort();
    }, [workspace.slug, statsQuery, showStats]);

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                publicPage.rmoManagement({ workspace }),
                buildAllParams(query?.sort, 1),
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 400);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue]);

    // The "mine only" toggles resolve against whoever the identity picker says
    // you are, so switching identity has to re-ask the server — otherwise the
    // table and the stat cards sit on the previous person's orders.
    useEffect(() => {
        router.get(
            publicPage.rmoManagement({ workspace }),
            buildAllParams(query?.sort, 1),
            { preserveState: true, replace: true, preserveScroll: true },
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [showMyAssigneeOnly, showMyConfirmeeOnly, currentUserId]);

    // The filters currently applied to the table, as query params — so every
    // export downloads exactly the rows on screen.
    const exportParams = useCallback(() => {
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
        if (activeAssigneeId) {
            params.set('assignee_id', activeAssigneeId);
        }
        if (callerId) {
            params.set('caller_id', callerId);
        }
        if (showMyConfirmeeOnly && currentUserId) {
            params.set('confirmee_id', currentUserId);
        }
        return params;
    }, [
        searchValue,
        currentStatus,
        currentParcelStatus,
        selectedPageIds,
        selectedShopIds,
        selectedUserIds,
        activeAssigneeId,
        callerId,
        showMyConfirmeeOnly,
        currentUserId,
        deliveryDate,
    ]);

    const doExport = useCallback(
        (columns: string[]) => {
            localStorage.setItem('rmo_export_columns', JSON.stringify(columns));
            const params = exportParams();
            if (columns.length > 0 && columns.length < ALL_COLUMN_KEYS.length) {
                params.set('columns', columns.join(','));
            }

            const qs = params.toString();
            window.location.href = `/public/workspaces/${workspace.slug}/rts/rmo-management/export${qs ? `?${qs}` : ''}`;
        },
        [workspace.slug, exportParams],
    );

    // Every call placed against the filtered orders on this delivery date — the
    // bulk version of the per-order call-log modal.
    const doExportCallLogs = useCallback(() => {
        const qs = exportParams().toString();
        window.location.href = `/public/workspaces/${workspace.slug}/rts/rmo-management/call-logs/export${qs ? `?${qs}` : ''}`;
    }, [workspace.slug, exportParams]);

    const handleDateChange = useCallback(
        (date: string) => {
            router.get(
                publicPage.rmoManagement({ workspace }),
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
                    ...(activeAssigneeId
                        ? { assignee_id: activeAssigneeId }
                        : {}),
                    ...(callerId ? { caller_id: callerId } : {}),
                    ...(showMyConfirmeeOnly && currentUserId
                        ? { confirmee_id: currentUserId }
                        : {}),
                    delivery_date: date,
                    page: 1,
                    per_page: orders.per_page,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [
            workspace,
            query?.sort,
            searchValue,
            currentStatus,
            currentParcelStatus,
            selectedPageIds,
            selectedShopIds,
            selectedUserIds,
            activeAssigneeId,
            callerId,
            showMyConfirmeeOnly,
            currentUserId,
            orders.per_page,
        ],
    );

    const handleAssignUser = useCallback(
        (id: number, userId: string) => {
            router.post(
                `/public/workspaces/${workspace.slug}/rts/rmo-management/${id}/assign`,
                { userId },
                { preserveScroll: true },
            );
        },
        [workspace.slug],
    );

    const handleRemoveAssignee = useCallback(
        (id: number) => {
            router.post(
                `/public/workspaces/${workspace.slug}/rts/rmo-management/${id}/remove-assignee`,
                {},
                { preserveScroll: true },
            );
        },
        [workspace.slug],
    );

    const handleChangeStatus = useCallback(
        (status: string, id: number) => {
            router.post(
                `/public/workspaces/${workspace.slug}/rts/rmo-management/${id}`,
                { status },
                { preserveScroll: true, preserveState: false },
            );
        },
        [workspace.slug],
    );

    const handleAssignToMe = useCallback(
        (id: number) => {
            if (currentUserId) {
                handleAssignUser(id, currentUserId);
            } else {
                setPendingAssign({ id, currentStatus: '' });
                setIsOpen(true);
            }
        },
        [handleAssignUser, currentUserId],
    );

    const handleUpdatePhone = useCallback(
        (
            id: number,
            field: 'customer_phone' | 'rider_phone',
            value: string,
        ) => {
            router.post(
                `/public/workspaces/${workspace.slug}/rts/rmo-management/${id}/update-phones`,
                { [field]: value },
                { preserveScroll: true },
            );
        },
        [workspace.slug],
    );

    const handleUserSelected = useCallback(
        (userId: string) => {
            setCurrentUserId(userId);
            setUserName(localStorage.getItem('user_name') ?? '');
            if (pendingAssign) {
                handleAssignUser(pendingAssign.id, userId);
                setPendingAssign(null);
            }
        },
        [pendingAssign, handleAssignUser],
    );

    const [copiedRider, setCopiedRider] = useState(false);
    const [copiedCustomer, setCopiedCustomer] = useState(false);

    // Selected orders, keyed by id, and holding the row itself rather than just
    // the id: the selection outlives paging, so by the time "Assign to me" runs
    // most of what it counts is no longer in `orders.data`.
    const [selectedOrders, setSelectedOrders] = useState<
        Map<number, OrderForDelivery>
    >(new Map());
    const selectedCount = selectedOrders.size;

    const allPageIds = useMemo(
        () => (orders.data ?? []).map((o) => o.id),
        [orders.data],
    );
    const allSelected =
        allPageIds.length > 0 &&
        allPageIds.every((id) => selectedOrders.has(id));
    const someSelected = allPageIds.some((id) => selectedOrders.has(id));
    // Selected orders the table isn't showing — another page of the same list,
    // or rows a search has narrowed out of sight. Worth saying out loud: the
    // bulk actions still reach every one of them.
    const selectedOffPageCount = useMemo(() => {
        const onPage = new Set(allPageIds);
        let count = 0;
        selectedOrders.forEach((_, id) => {
            if (!onPage.has(id)) count += 1;
        });
        return count;
    }, [selectedOrders, allPageIds]);

    const toggleRow = useCallback((order: OrderForDelivery) => {
        setSelectedOrders((prev) => {
            const next = new Map(prev);
            if (next.has(order.id)) {
                next.delete(order.id);
            } else {
                next.set(order.id, order);
            }
            return next;
        });
    }, []);

    const toggleAll = useCallback(() => {
        const pageOrders = orders.data ?? [];
        setSelectedOrders((prev) => {
            const next = new Map(prev);
            if (pageOrders.every((o) => prev.has(o.id))) {
                pageOrders.forEach((o) => next.delete(o.id));
                return next;
            }
            pageOrders.forEach((o) => next.set(o.id, o));
            return next;
        });
    }, [orders.data]);

    const clearSelection = useCallback(() => {
        setSelectedOrders(new Map());
        setBulkConflict(null);
    }, []);

    // Rows the selection is holding go stale as the table reloads — an order
    // assigned from its own row, say, comes back with an assignee. Refresh the
    // snapshots from whichever selected rows the current page carries.
    useEffect(() => {
        setSelectedOrders((prev) => {
            if (prev.size === 0) return prev;
            const next = new Map(prev);
            let changed = false;
            (orders.data ?? []).forEach((o) => {
                if (next.has(o.id)) {
                    next.set(o.id, o);
                    changed = true;
                }
            });
            return changed ? next : prev;
        });
    }, [orders.data]);

    // What the selection belongs to. Paging, sorting, searching and the
    // assignee / confirmee narrowing only change which of a day's orders you
    // happen to be looking at, so a selection rides through all of them — the
    // bulk actions post ids, so an order stays selected while it scrolls out of
    // view. That's what makes them useful together: search a rider, tick two,
    // flip to "My Assignee Only", tick three more, assign all five. The
    // assignee picker goes with its toggle, being the same narrowing aimed at
    // someone else, and `callerId` follows both.
    //
    // What's left resets the selection: the status, page, shop and user filters
    // pick a different set of orders to work through rather than another view of
    // the same one, and a new delivery date is a different day's work — one the
    // server's editable-date window would mostly refuse anyway.
    //
    // Spelled out rather than borrowed from `exportParams`, close as the two
    // lists are: an export that one day carries the search term or the sort
    // order along must not quietly start clearing the selection.
    const selectionScope = useMemo(
        () =>
            JSON.stringify([
                currentStatus,
                currentParcelStatus,
                selectedPageIds,
                selectedShopIds,
                selectedUserIds,
                deliveryDate,
            ]),
        [
            currentStatus,
            currentParcelStatus,
            selectedPageIds,
            selectedShopIds,
            selectedUserIds,
            deliveryDate,
        ],
    );

    useEffect(() => {
        setSelectedOrders(new Map());
        setBulkConflict(null);
    }, [selectionScope]);

    const [bulkConflict, setBulkConflict] = useState<{
        already: number;
        toAssign: number;
    } | null>(null);

    const doBulkAssign = useCallback(
        (userId: string) => {
            router.post(
                `/public/workspaces/${workspace.slug}/rts/rmo-management/bulk-assign`,
                { ids: Array.from(selectedOrders.keys()), userId },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setSelectedOrders(new Map());
                        setBulkConflict(null);
                    },
                },
            );
        },
        [selectedOrders, workspace.slug],
    );

    const handleBulkAssignToMe = useCallback(() => {
        const userId = currentUserId;
        if (!userId) {
            setIsOpen(true);
            return;
        }
        const alreadyAssigned = Array.from(selectedOrders.values()).filter(
            (o) => o.assignee != null,
        ).length;
        if (alreadyAssigned > 0) {
            setBulkConflict({
                already: alreadyAssigned,
                toAssign: selectedOrders.size - alreadyAssigned,
            });
            return;
        }
        doBulkAssign(userId);
    }, [selectedOrders, doBulkAssign, currentUserId]);

    const handleBulkUpdateStatus = useCallback(
        (status: OrderStatus) => {
            router.post(
                `/public/workspaces/${workspace.slug}/rts/rmo-management/bulk-status`,
                { ids: Array.from(selectedOrders.keys()), status },
                {
                    preserveScroll: true,
                    preserveState: false,
                    onSuccess: () => {
                        setSelectedOrders(new Map());
                        setBulkConflict(null);
                    },
                },
            );
        },
        [selectedOrders, workspace.slug],
    );

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
                id: 'select',
                enableSorting: false,
                header: () => (
                    <Checkbox
                        checked={
                            allSelected
                                ? true
                                : someSelected
                                  ? 'indeterminate'
                                  : false
                        }
                        onCheckedChange={toggleAll}
                        aria-label="Select all"
                        className="translate-y-px"
                    />
                ),
                cell: ({ row }) => (
                    <Checkbox
                        checked={selectedOrders.has(row.original.id)}
                        onCheckedChange={() => toggleRow(row.original)}
                        aria-label="Select row"
                        className="translate-y-px"
                    />
                ),
                size: 40,
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
                        title="Items"
                    />
                ),
                cell: ({ row }) => {
                    const items = row.original.order.items ?? [];
                    const trackingCode = row.original.order.tracking_code;
                    // `upsell_price` is a decimal column, so it arrives as a
                    // string over the wire. Gencys sends "0.00" for an order
                    // that was never upsold, so compare on the number.
                    const upsellPrice = Number(row.original.upsell_price ?? 0);
                    const hasUpsell = upsellPrice > 0;
                    // Once an order has been upsold, the Pancake items are the
                    // line-up as originally confirmed. Gencys' order details are
                    // what actually went out, as a comma-separated list.
                    const upsoldItems =
                        hasUpsell && row.original.order_details
                            ? row.original.order_details
                                  .split(',')
                                  .map((item) => item.trim())
                                  .filter(Boolean)
                            : null;
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
                                {(upsoldItems ?? items.map((i) => i.name)).map(
                                    (name, i) => (
                                        <p
                                            key={i}
                                            className="text-[12px] leading-snug font-medium text-gray-800 dark:text-gray-200"
                                        >
                                            {name}
                                        </p>
                                    ),
                                )}
                            </div>
                            <div className="space-y-0.5">
                                {trackingCode && (
                                    <span className="block font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                        {trackingCode}
                                    </span>
                                )}
                                {hasUpsell && (
                                    <span className="block font-mono text-[11px] font-medium text-emerald-600 tabular-nums dark:text-emerald-400">
                                        Upsell {currencyFormatter(upsellPrice)}
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
                id: 'shop_rts_rate',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Shop RTS" />
                ),
                cell: ({ row }) => {
                    const rate = row.original.shop_rts_rate ?? null;
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

                    // Assignee is editable for today's orders, and for
                    // yesterday's orders only when the parcel was delivered or
                    // returned.
                    const parcelStatus =
                        row.original.parcel_status?.toLowerCase();
                    const canEditAssignee =
                        isToday ||
                        canEditPastDay ||
                        (isYesterday &&
                            (parcelStatus === 'delivered' ||
                                parcelStatus === 'returned' ||
                                parcelStatus === 'returning'));

                    if (!assignee) {
                        return (
                            <button
                                onClick={() => handleAssignToMe(id)}
                                disabled={!canEditAssignee}
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
                            {canEditAssignee && (
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
                cell: ({ row }) => {
                    // Status is editable for today's orders, and for yesterday's
                    // orders only when the parcel was returned, returning, or
                    // delivered.
                    const yesterdayParcelStatus =
                        row.original.parcel_status?.toLowerCase();
                    const canEditStatus =
                        isToday ||
                        canEditPastDay ||
                        (isYesterday &&
                            (yesterdayParcelStatus === 'returned' ||
                                yesterdayParcelStatus === 'returning' ||
                                yesterdayParcelStatus === 'delivered'));

                    return (
                        <RmoStatusPicker
                            currentStatus={row.original.status as OrderStatus}
                            onChangeStatus={(status) =>
                                handleChangeStatus(status, row.original.id)
                            }
                            disabled={!canEditStatus}
                        />
                    );
                },
            },
        ],
        [
            handleAssignToMe,
            handleRemoveAssignee,
            handleChangeStatus,
            handleUpdatePhone,
            isToday,
            isYesterday,
            canEditPastDay,
            canEditPhone,
            selectedOrders,
            allSelected,
            someSelected,
            toggleAll,
            toggleRow,
        ],
    );

    return (
        <div className="min-h-screen overflow-x-hidden bg-stone-50 dark:bg-zinc-950">
            <Toaster position="top-right" richColors closeButton />
            <FormModal
                open={isOpen}
                onOpenChange={(open) => {
                    setIsOpen(open);
                    if (!open) setPendingAssign(null);
                }}
                users={users}
                onSubmit={handleUserSelected}
            />

            <CallLogBreakdownModal
                open={breakdownOpen}
                onOpenChange={setBreakdownOpen}
                workspaceSlug={workspace.slug}
                date={deliveryDate}
            />

            <CallLogModal
                open={!!callLogModal}
                onOpenChange={(open) => {
                    if (!open) setCallLogModal(null);
                }}
                phoneNumber={callLogModal?.phone ?? ''}
                label={callLogModal?.label ?? ''}
                workspaceSlug={workspace.slug}
                date={deliveryDate}
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

            {/* Top bar */}
            <div className="border-b border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                <div className="mx-auto flex w-full items-center justify-between px-4 py-3 md:px-6">
                    <div className="flex items-center gap-3">
                        <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-600">
                            <span className="text-[11px] font-bold text-white">
                                R
                            </span>
                        </div>
                        <div className="h-4 w-px bg-black/8 dark:bg-white/8" />
                        {/* <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                            {new Date().toLocaleDateString('en-US', {
                                weekday: 'short',
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric',
                            })}
                        </span> */}
                    </div>

                    {userName ? (
                        <button
                            onClick={() => setIsOpen(true)}
                            className="group flex items-center gap-2.5 rounded-xl border border-black/6 bg-stone-50 px-3 py-1.5 transition-all hover:border-black/12 hover:bg-white dark:border-white/6 dark:bg-zinc-800 dark:hover:border-white/12 dark:hover:bg-zinc-700"
                        >
                            <span className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-[10px] font-bold text-white">
                                {userName
                                    .split(' ')
                                    .slice(0, 2)
                                    .map((w) => w[0])
                                    .join('')
                                    .toUpperCase()}
                            </span>
                            <div className="flex flex-col items-start">
                                <span className="font-mono text-[9px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    Logged in as
                                </span>
                                <span className="text-[12px] leading-tight font-semibold text-gray-800 dark:text-gray-100">
                                    {userName}
                                </span>
                            </div>
                            <span className="ml-1 text-[10px] font-medium text-gray-400 opacity-0 transition-opacity group-hover:opacity-100 dark:text-gray-500">
                                Change
                            </span>
                        </button>
                    ) : (
                        <Button
                            size="sm"
                            onClick={() => setIsOpen(true)}
                            className="rounded-lg bg-emerald-600 px-4 text-[12px] font-medium text-white hover:bg-emerald-700"
                        >
                            <UserIcon className="mr-1.5 h-3.5 w-3.5" />
                            Set Identity
                        </Button>
                    )}
                </div>
            </div>

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
                            onClick={doExportCallLogs}
                            title="Download every call placed against these orders on this date"
                            className="flex items-center gap-1.5 rounded-lg text-[12px]"
                        >
                            <PhoneCall className="h-3.5 w-3.5" />
                            Export Call Logs
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setShowStats((prev) => {
                                    const next = !prev;
                                    localStorage.setItem(
                                        'rmo_show_stats',
                                        String(next),
                                    );
                                    return next;
                                })
                            }
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
                                if (!dateStr) {
                                    if (deliveryDate !== todayLocal)
                                        handleDateChange(todayLocal);
                                    return;
                                }
                                if (dateStr !== deliveryDate)
                                    handleDateChange(dateStr);
                            }}
                        />
                    </div>
                </div>

                {enable_auto_tag_status && (
                    <div className="mb-4 flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-[12px] text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300">
                        <Tags className="h-3.5 w-3.5 shrink-0" />
                        <span className="font-medium">Auto-tagging is on.</span>
                        <span className="text-sky-700/80 dark:text-sky-300/70">
                            Delivered parcels are set to DELIVERED and returning
                            ones to RETURNING overnight, overwriting manual
                            changes.
                        </span>
                    </div>
                )}

                {showStats && (
                    <div className="mb-6">
                        <div className="mb-2 flex justify-end">
                            <button
                                onClick={() => setBreakdownOpen(true)}
                                className="flex items-center gap-1.5 text-[11px] font-medium text-gray-500 transition-colors hover:text-emerald-600 dark:text-gray-400 dark:hover:text-emerald-400"
                            >
                                <ListChecks className="h-3.5 w-3.5" />
                                Call logs breakdown
                            </button>
                        </div>
                        <RmoStatCards
                            total_for_delivery_today={
                                stats?.total_for_delivery_today ?? 0
                            }
                            called_count={stats?.called_count ?? 0}
                            delivered_count={stats?.delivered_count ?? 0}
                            returning_count={stats?.returning_count ?? 0}
                            problematic_count={stats?.problematic_count ?? 0}
                            total_call_logs_count={
                                stats?.total_call_logs_count ?? 0
                            }
                            total_call_duration={
                                stats?.total_call_duration ?? 0
                            }
                            connected_call_logs_count={
                                stats?.connected_call_logs_count ?? 0
                            }
                            avg_call_duration={stats?.avg_call_duration ?? null}
                            hit_rate={stats?.hit_rate ?? null}
                            loading={statsLoading || stats === null}
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
                                placeholder="Search order #, tracking code, rider, customer — separate multiple with commas"
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

                        <select
                            value={selectedAssigneeId}
                            disabled={showMyAssigneeOnly}
                            onChange={(e) =>
                                handleAssigneeChange(e.target.value)
                            }
                            title={
                                showMyAssigneeOnly
                                    ? 'Turn off "My Assignee Only" to pick a different assignee'
                                    : undefined
                            }
                            className="h-8 rounded-lg border border-black/6 bg-stone-100 px-2 text-[12px]! text-gray-700 outline-none focus:border-emerald-500 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            <option value="">All Assignees</option>
                            {assigneeOptions.map((assignee) => (
                                <option
                                    key={assignee.id}
                                    value={String(assignee.id)}
                                >
                                    {assignee.name}
                                </option>
                            ))}
                        </select>

                        <button
                            type="button"
                            onClick={() =>
                                setShowMyAssigneeOnly((prev) => {
                                    const next = !prev;
                                    localStorage.setItem(
                                        'rmo_show_my_assignee_only',
                                        String(next),
                                    );
                                    return next;
                                })
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
                                setShowMyConfirmeeOnly((prev) => {
                                    const next = !prev;
                                    localStorage.setItem(
                                        'rmo_show_my_confirmee_only',
                                        String(next),
                                    );
                                    return next;
                                })
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

                {selectedCount > 0 && (
                    <div className="mb-3 space-y-2">
                        <div className="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                            <span className="text-[12px] font-semibold text-emerald-700 dark:text-emerald-400">
                                {selectedCount} order
                                {selectedCount !== 1 ? 's' : ''} selected
                                {selectedOffPageCount > 0 && (
                                    <span className="ml-1 font-normal text-emerald-600/80 dark:text-emerald-400/70">
                                        ({selectedOffPageCount} not shown here)
                                    </span>
                                )}
                            </span>
                            <div className="h-3.5 w-px bg-emerald-200 dark:bg-emerald-500/30" />
                            <button
                                onClick={handleBulkAssignToMe}
                                disabled={!isToday && !canEditPastDay}
                                className="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1 text-[12px] font-medium text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <UserPlus className="h-3.5 w-3.5" />
                                Assign to me
                            </button>
                            {enable_bulk_status_update && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger
                                        disabled={
                                            !isToday &&
                                            !isYesterday &&
                                            !canEditPastDay
                                        }
                                        className="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-white px-3 py-1 text-[12px] font-medium text-emerald-700 transition-colors outline-none hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-500/40 dark:bg-transparent dark:text-emerald-400 dark:hover:bg-emerald-500/20"
                                    >
                                        <ListChecks className="h-3.5 w-3.5" />
                                        Set status
                                        <ChevronDown className="h-3 w-3 opacity-60" />
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="start"
                                        className="w-52 overflow-hidden p-1"
                                    >
                                        <p className="px-2 pt-1 pb-1.5 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                            Set {selectedCount} order
                                            {selectedCount !== 1 ? 's' : ''} to
                                        </p>
                                        <div className="max-h-72 overflow-y-auto">
                                            {ORDER_STATUSES.map((s) => (
                                                <DropdownMenuItem
                                                    key={s}
                                                    onClick={() =>
                                                        handleBulkUpdateStatus(
                                                            s,
                                                        )
                                                    }
                                                    className="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-[12px] text-gray-600 dark:text-gray-400"
                                                >
                                                    <span
                                                        className={`h-1.5 w-1.5 shrink-0 rounded-full ${orderStatusConfig[s]?.dot ?? 'bg-gray-400'}`}
                                                    />
                                                    <span className="flex-1">
                                                        {s}
                                                    </span>
                                                </DropdownMenuItem>
                                            ))}
                                        </div>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                            <button
                                onClick={() => {
                                    clearSelection();
                                    setBulkConflict(null);
                                }}
                                className="ml-auto flex items-center gap-1 text-[11px] text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300"
                            >
                                <X className="h-3.5 w-3.5" />
                                Clear
                            </button>
                        </div>

                        {bulkConflict && (
                            <div className="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 dark:border-amber-500/30 dark:bg-amber-500/10">
                                <span className="text-[12px] text-amber-700 dark:text-amber-400">
                                    <span className="font-semibold">
                                        {bulkConflict.already}
                                    </span>{' '}
                                    order{bulkConflict.already !== 1 ? 's' : ''}{' '}
                                    already{' '}
                                    {bulkConflict.already !== 1
                                        ? 'have'
                                        : 'has'}{' '}
                                    an assignee and will be skipped.
                                    {bulkConflict.toAssign > 0
                                        ? ` ${bulkConflict.toAssign} unassigned order${bulkConflict.toAssign !== 1 ? 's' : ''} will be assigned.`
                                        : ' Nothing to assign.'}
                                </span>
                                <div className="ml-auto flex items-center gap-2">
                                    {bulkConflict.toAssign > 0 && (
                                        <button
                                            onClick={() =>
                                                doBulkAssign(currentUserId)
                                            }
                                            className="flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1 text-[12px] font-medium text-white transition-colors hover:bg-amber-700"
                                        >
                                            Proceed
                                        </button>
                                    )}
                                    <button
                                        onClick={() => setBulkConflict(null)}
                                        className="flex items-center gap-1 text-[11px] text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300"
                                    >
                                        <X className="h-3.5 w-3.5" />
                                        Dismiss
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                <div className="max-w-full overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={orders.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(orders, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                publicPage.rmoManagement({ workspace }),
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
        </div>
    );
}

/** Password gate shown before the public RMO page when a password is set. */
function RmoLockScreen({ workspace }: { workspace: Workspace }) {
    const form = useForm({ password: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(
            `/public/workspaces/${workspace.slug}/rts/rmo-management/verify-password`,
            {
                preserveScroll: true,
                onError: () => form.reset('password'),
            },
        );
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-stone-100 p-4 dark:bg-zinc-950">
            <div className="w-full max-w-sm rounded-[16px] border border-black/8 bg-white p-6 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:border-white/8 dark:bg-zinc-900">
                <div className="mb-4 flex flex-col items-center text-center">
                    <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <Lock className="h-5 w-5" />
                    </div>
                    <h1 className="text-[15px] font-semibold text-gray-800 dark:text-gray-100">
                        Protected page
                    </h1>
                    <p className="mt-1 text-[12px] text-gray-500 dark:text-gray-400">
                        Enter the password to access {workspace.name}&apos;s RMO
                        management.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-3">
                    <div className="space-y-1.5">
                        <Label htmlFor="rmo-access-password">Password</Label>
                        <Input
                            id="rmo-access-password"
                            type="password"
                            autoFocus
                            autoComplete="current-password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                        />
                        <InputError message={form.errors.password} />
                    </div>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing || !form.data.password}
                    >
                        {form.processing ? 'Unlocking…' : 'Unlock'}
                    </Button>
                </form>
            </div>
        </div>
    );
}

export default function RmoManagementPage(props: Props) {
    if (props.locked) {
        return <RmoLockScreen workspace={props.workspace} />;
    }

    return <RmoManagement {...props} />;
}
