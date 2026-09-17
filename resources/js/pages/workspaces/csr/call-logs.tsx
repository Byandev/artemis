import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { type BreadcrumbItem, type PaginatedData } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import flatpickr from 'flatpickr';
import { omit } from 'lodash';
import { PhoneOff, Search } from 'lucide-react';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

/** One row as CSRController@callLogs sends it. */
interface CallLogRow {
    id: number;
    phone_number: string;
    type: string;
    duration: number;
    call_date: string;
    call_time: string;
    /** Who the call reached. Null when the number matched nothing that day,
     *  which is an answer rather than a gap — see App\Support\CallLogPersona. */
    persona: string | null;
    /** The Pancake order the call was matched to, by `pancake_orders.id`. Null
     *  when it matched none, and when the order has since gone from the synced
     *  table. */
    order_id: number | null;
}

/**
 * Who a call reached, as the call log records it.
 *
 * The same keys and labels the workspace register uses, so one call reads the
 * same wherever it is listed. A null persona is deliberately not covered — it
 * means the number matched no delivery and no order confirmed that day, which
 * the table calls "Unmatched" rather than a fourth kind of call.
 */
const PERSONA_CONFIG: Record<string, { label: string; pill: string }> = {
    customer: {
        label: 'RMO Customer',
        pill: 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400',
    },
    rider: {
        label: 'RMO Rider',
        pill: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
    },
    verification: {
        label: 'Order Verification',
        pill: 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
    },
};

/**
 * Where a call's order opens: the Pancake order list, narrowed to the one
 * order. There is no per-order page to link to, so the link carries
 * `pancake_orders.id` as the list's exact `order_id` filter rather than as a
 * search — search is a `like` across order number, phone and address, which for
 * a short run of digits answers with every order that merely contains it.
 */
const orderUrl = (workspaceSlug: string, orderId: number) =>
    `/workspaces/${workspaceSlug}/pancake/orders?filter[order_id]=${encodeURIComponent(orderId)}`;

interface Props {
    workspace: { id: number; name: string; slug: string };
    logs: PaginatedData<CallLogRow>;
    /** Calls, talk time and connected calls over everything the filters left —
     *  the whole selection, not the page of it in front of you. */
    totals: { calls: number; duration: number; connected: number };
    /** Filterable persona values, server-supplied so the two lists can't drift. */
    personas: string[];
    query?: {
        sort?: string | null;
        page?: number | string;
        perPage?: number | string;
        filter?: {
            search?: string;
            start_date?: string;
            end_date?: string;
            persona?: string;
            type?: string;
        };
    };
}

const TYPES = ['outgoing', 'incoming', 'missed'];

const typePill = (type: string) =>
    type === 'outgoing'
        ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
        : type === 'incoming'
          ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400'
          : 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400';

/** One call, where minutes are as long as it ever gets. */
function formatDuration(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return m > 0 ? `${m}m ${s}s` : `${s}s`;
}

/** A period's worth of them, where hours are the reading. */
function formatTalkTime(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m ${seconds % 60}s`;
}

/** Every filter but the one being changed, so a fetch never drops the others. */
const ALL = 'all';

/**
 * The signed-in CSR's own call register.
 *
 * The workspace register under RTS lists everyone and names a caller against
 * each row; this is the same table from one person's side, so there is no User
 * column and the rows are narrowed instead — every call synced under any of
 * their Pancake logins, however the mobile app identified them.
 */
export default function MyCallLogs({
    workspace,
    logs,
    totals,
    personas,
    query,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'CSR Dashboard',
            href: `/workspaces/${workspace.slug}/csr/dashboard`,
        },
        {
            title: 'My Calls',
            href: `/workspaces/${workspace.slug}/csr/call-logs`,
        },
    ];

    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const [search, setSearch] = useState(query?.filter?.search ?? '');
    const [dateRange, setDateRange] = useState<string[]>(() => [
        query?.filter?.start_date ?? '',
        query?.filter?.end_date ?? '',
    ]);
    const [persona, setPersona] = useState(query?.filter?.persona ?? ALL);
    const [type, setType] = useState(query?.filter?.type ?? ALL);

    const buildFilter = () => ({
        search: search || undefined,
        start_date: dateRange[0] || undefined,
        end_date: dateRange[1] || undefined,
        persona: persona === ALL ? undefined : persona,
        type: type === ALL ? undefined : type,
    });

    // Debounced because `search` changes on every keystroke; the selects and the
    // date range settle immediately, and the same timer covers them harmlessly.
    useEffect(() => {
        const filter = buildFilter();
        const current = query?.filter ?? {};
        const unchanged =
            (filter.search ?? '') === (current.search ?? '') &&
            (filter.start_date ?? '') === (current.start_date ?? '') &&
            (filter.end_date ?? '') === (current.end_date ?? '') &&
            (filter.persona ?? '') === (current.persona ?? '') &&
            (filter.type ?? '') === (current.type ?? '');
        if (unchanged) return;

        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/csr/call-logs`,
                {
                    filter,
                    page: 1,
                    sort: query?.sort,
                    per_page: query?.perPage ?? logs.per_page,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    // `totals` comes along: it describes the filtered rows, so
                    // leaving it behind would strand the strip on the old ones.
                    only: ['logs', 'totals', 'query'],
                },
            );
        }, 400);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, dateRange, persona, type]);

    const columns: ColumnDef<CallLogRow>[] = useMemo(
        () => [
            {
                accessorKey: 'call_date',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="When" />
                ),
                cell: ({ row }) => (
                    <div className="flex h-10 flex-col justify-center">
                        <span className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                            {moment(row.original.call_date).format(
                                'DD MMM YYYY',
                            )}
                        </span>
                        <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            {row.original.call_time}
                        </span>
                    </div>
                ),
            },
            {
                accessorKey: 'phone_number',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Number" />
                ),
                cell: ({ row }) => (
                    <div className="flex h-10 items-center">
                        <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                            {row.original.phone_number}
                        </span>
                    </div>
                ),
            },
            {
                accessorKey: 'persona',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Persona" />
                ),
                cell: ({ row }) => {
                    const entry = row.original.persona
                        ? PERSONA_CONFIG[row.original.persona]
                        : undefined;

                    return (
                        <div className="flex h-10 items-center">
                            {entry ? (
                                <span
                                    className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium ${entry.pill}`}
                                >
                                    {entry.label}
                                </span>
                            ) : (
                                <span className="text-[11px] text-gray-400 dark:text-gray-500">
                                    Unmatched
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'type',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Type" />
                ),
                cell: ({ row }) => (
                    <div className="flex h-10 items-center">
                        <span
                            className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium ${typePill(row.original.type)}`}
                        >
                            {row.original.type}
                        </span>
                    </div>
                ),
            },
            {
                accessorKey: 'duration',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Duration" />
                ),
                cell: ({ row }) => (
                    <div className="flex h-10 items-center">
                        <span className="font-mono text-[12px] text-gray-600 tabular-nums dark:text-gray-300">
                            {formatDuration(row.original.duration)}
                        </span>
                    </div>
                ),
            },
            {
                id: 'order',
                enableSorting: false,
                header: () => (
                    <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                        Order
                    </div>
                ),
                cell: ({ row }) => (
                    <div className="flex h-10 items-center">
                        {row.original.order_id ? (
                            <a
                                href={orderUrl(
                                    workspace.slug,
                                    row.original.order_id,
                                )}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-mono text-[11px] text-emerald-600 hover:underline dark:text-emerald-400"
                            >
                                {row.original.order_id}
                            </a>
                        ) : (
                            <span className="text-[11px] text-gray-400 dark:text-gray-500">
                                —
                            </span>
                        )}
                    </div>
                ),
            },
        ],
        [workspace.slug],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Calls" />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="My Calls"
                    description="Every call synced from the mobile app under your Pancake logins in this workspace."
                />

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Stat
                        label="Calls"
                        value={totals.calls.toLocaleString()}
                        hint="Over the current filters"
                    />
                    <Stat
                        label="Talk time"
                        value={formatTalkTime(totals.duration)}
                        hint="Every call added up"
                    />
                    <Stat
                        label="Connected"
                        value={totals.connected.toLocaleString()}
                        hint={
                            totals.calls > 0
                                ? `${Math.round((totals.connected / totals.calls) * 100)}% of calls lasted 3s or more`
                                : 'Calls that lasted 3s or more'
                        }
                    />
                </div>

                <div className="mb-3 flex flex-col items-stretch gap-2 md:flex-row md:items-center">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search by phone or order ID..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 font-mono! text-[12px]! text-gray-800 outline-none focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                        />
                    </div>

                    <DatePicker
                        id="my-call-logs-date-range"
                        mode="range"
                        placeholder="Filter by date"
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

                    <Select value={persona} onValueChange={setPersona}>
                        <SelectTrigger className="h-9 w-[160px]">
                            <SelectValue placeholder="Persona" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All personas</SelectItem>
                            {personas.map((value) => (
                                <SelectItem key={value} value={value}>
                                    {PERSONA_CONFIG[value]?.label ??
                                        'Unmatched'}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={type} onValueChange={setType}>
                        <SelectTrigger className="h-9 w-[150px]">
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All types</SelectItem>
                            {TYPES.map((value) => (
                                <SelectItem key={value} value={value}>
                                    {value}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {logs.total === 0 && !hasFilters(query) ? (
                    <EmptyState />
                ) : (
                    <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={columns}
                            data={logs.data ?? []}
                            enableInternalPagination={false}
                            initialSorting={initialSorting}
                            meta={{ ...omit(logs, ['data']) }}
                            onFetch={(params) => {
                                router.get(
                                    `/workspaces/${workspace.slug}/csr/call-logs`,
                                    {
                                        sort: params?.sort,
                                        filter: buildFilter(),
                                        page: params?.page ?? 1,
                                        per_page:
                                            params?.per_page ??
                                            query?.perPage ??
                                            logs.per_page,
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
                )}
            </div>
        </AppLayout>
    );
}

/** Whether anything has been narrowed — an empty table means two different
 *  things either side of this, and only one of them is worth explaining. */
function hasFilters(query: Props['query']): boolean {
    const filter = query?.filter ?? {};
    return Boolean(
        filter.search ||
        filter.start_date ||
        filter.end_date ||
        filter.persona ||
        filter.type,
    );
}

function Stat({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint: string;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <span className="block font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {label}
            </span>
            <span className="mt-1 block text-[22px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                {value}
            </span>
            <span className="mt-0.5 block text-[11px] text-gray-400 dark:text-gray-500">
                {hint}
            </span>
        </div>
    );
}

/**
 * No calls at all, with nothing filtered out — which is usually a matter of
 * identity rather than of work, so this points at the page that shows it.
 */
function EmptyState() {
    return (
        <div className="rounded-[14px] border border-dashed border-black/10 bg-white p-10 text-center dark:border-white/10 dark:bg-zinc-900">
            <span className="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-stone-100 dark:bg-zinc-800">
                <PhoneOff className="h-5 w-5 text-gray-400 dark:text-gray-500" />
            </span>
            <p className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                No calls synced under your account yet
            </p>
            <p className="mx-auto mt-1 max-w-md text-[12px] text-gray-400 dark:text-gray-500">
                Calls arrive from the mobile app and are matched to whichever
                Pancake login placed them. If you have been calling, check My
                Pancake Users — a login that is not linked to you records its
                calls against nobody here.
            </p>
        </div>
    );
}
