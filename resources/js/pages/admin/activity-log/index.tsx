import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { PaginatedData } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef, SortingState } from '@tanstack/react-table';
import flatpickr from 'flatpickr';
import { omit } from 'lodash';
import {
    Activity,
    Calendar,
    Check,
    RotateCcw,
    Search,
    SlidersHorizontal,
    User,
    X,
} from 'lucide-react';
import moment from 'moment';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import DateOption = flatpickr.Options.DateOption;

const EVENT_TYPES = [
    { value: 'created', label: 'Created' },
    { value: 'updated', label: 'Updated' },
    { value: 'deleted', label: 'Deleted' },
    { value: 'restored', label: 'Restored' },
    { value: 'logged in', label: 'Logged In' },
    { value: 'logged out', label: 'Logged Out' },
];

interface WorkspaceOption {
    id: number;
    name: string;
}

interface ActivityRow {
    id: number;
    event: string | null;
    workspace?: { id: number; name: string } | null;
    description: string;
    causer?: { id: number; name: string } | null;
    subject?: { id: number | null; type: string } | null;
    created_at: string;
}

interface Props {
    activities: PaginatedData<ActivityRow>;
    workspaces?: WorkspaceOption[];
    filters?: {
        workspace?: string;
        event?: string;
        causer?: string;
        from?: string;
        to?: string;
        sort?: string;
        direction?: string;
    };
}

export default function AdminActivityLogPage({
    activities,
    workspaces = [],
    filters = {},
}: Props) {
    const [workspace, setWorkspace] = useState(filters.workspace || '');
    const [eventType, setEventType] = useState(filters.event || '');
    const [causer, setCauser] = useState(filters.causer || '');
    const [fromDate, setFromDate] = useState(filters.from || '');
    const [toDate, setToDate] = useState(filters.to || '');

    const activeFilterCount = useMemo(
        () => [workspace, eventType].filter(Boolean).length,
        [workspace, eventType],
    );
    const hasDateFilter = Boolean(fromDate && toDate);

    const datePickerDefault = useMemo(
        () =>
            fromDate && toDate
                ? ([fromDate, toDate] as never as DateOption)
                : undefined,
        [fromDate, toDate],
    );

    const initialSorting = useMemo<SortingState>(() => {
        if (filters.sort) {
            return [{ id: filters.sort, desc: filters.direction === 'desc' }];
        }

        return [{ id: 'created_at', desc: true }];
    }, [filters.sort, filters.direction]);

    const columns: ColumnDef<ActivityRow>[] = [
        {
            accessorKey: 'created_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Timestamp" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                    <Calendar className="h-4 w-4 text-zinc-400" />
                    {new Date(row.original.created_at).toLocaleString()}
                </div>
            ),
        },
        {
            id: 'workspace',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Workspace" />
            ),
            cell: ({ row }) => (
                <span className="font-semibold text-zinc-900 dark:text-zinc-100">
                    {row.original.workspace?.name ?? 'Global'}
                </span>
            ),
        },
        {
            accessorKey: 'event',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Event" />
            ),
            cell: ({ row }) => (
                <span className="inline-flex rounded-full border border-brand-100 bg-brand-50/50 px-2.5 py-1 text-[11px] font-bold text-brand-600 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">
                    {row.original.event ?? 'event'}
                </span>
            ),
        },
        {
            id: 'causer',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="User" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                    <div className="flex h-7 w-7 items-center justify-center rounded-md bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                        <User className="h-3.5 w-3.5" />
                    </div>
                    {row.original.causer?.name ?? 'System'}
                </div>
            ),
        },
        {
            id: 'subject',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Subject" />
            ),
            cell: ({ row }) =>
                row.original.subject ? (
                    <span className="font-mono text-xs text-zinc-500">
                        {row.original.subject.type} #
                        {row.original.subject.id ?? '-'}
                    </span>
                ) : (
                    <span className="text-zinc-400">-</span>
                ),
        },
        {
            accessorKey: 'description',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Description" />
            ),
            cell: ({ row }) => (
                <span className="text-zinc-700 dark:text-zinc-300">
                    {row.original.description}
                </span>
            ),
        },
    ];

    const applyFilters = (next: {
        workspace?: string;
        event?: string;
        causer?: string;
        from?: string;
        to?: string;
        page?: number;
        per_page?: number;
        sort?: string;
    }) => {
        const params = {
            workspace: workspace || undefined,
            event: eventType || undefined,
            causer: causer || undefined,
            from: fromDate || undefined,
            to: toDate || undefined,
            per_page: activities.per_page,
            page: undefined,
            ...next,
        };

        router.get(
            '/admin/activity-log',
            Object.fromEntries(
                Object.entries(params).filter(
                    ([, value]) => value !== undefined && value !== '',
                ),
            ),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            },
        );
    };

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (causer !== (filters.causer || '')) {
                applyFilters({ causer: causer || undefined, page: 1 });
            }
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [causer]);

    const handleWorkspaceChange = (value: string) => {
        const next = value === workspace ? '' : value;
        setWorkspace(next);
        applyFilters({ workspace: next || undefined, page: 1 });
    };

    const handleEventChange = (value: string) => {
        const next = value === eventType ? '' : value;
        setEventType(next);
        applyFilters({ event: next || undefined, page: 1 });
    };

    const handleClear = () => {
        setWorkspace('');
        setEventType('');
        setCauser('');
        setFromDate('');
        setToDate('');
        router.get('/admin/activity-log');
    };

    return (
        <AdminSidebarLayout>
            <Head title="Admin | Activity Log" />

            <div className="p-4 md:p-6">
                <PageHeader
                    title="Activity Log"
                    description="Audit platform activity across all workspaces."
                    stackActionsOnMobile
                >
                    <div className="inline-flex h-9 items-center gap-2 rounded-md border border-zinc-200 bg-white px-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
                        <Activity className="h-4 w-4" />
                        {activities.total ?? activities.data.length} records
                    </div>
                </PageHeader>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full sm:w-64">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            type="text"
                            placeholder="Search users..."
                            value={causer}
                            onChange={(e) => setCauser(e.target.value)}
                            className="w-full rounded-md border border-zinc-200 bg-white py-2 pr-4 pl-10 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950"
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <ActivityFilterPopover
                            activeFilterCount={activeFilterCount}
                            workspaces={workspaces}
                            workspace={workspace}
                            eventType={eventType}
                            onWorkspaceChange={handleWorkspaceChange}
                            onEventChange={handleEventChange}
                            onClear={() => {
                                setWorkspace('');
                                setEventType('');
                                applyFilters({
                                    workspace: undefined,
                                    event: undefined,
                                    page: 1,
                                });
                            }}
                        />

                        <DatePicker
                            key={`${fromDate || 'start'}-${toDate || 'end'}`}
                            id="admin-activity-log-date-range"
                            mode="range"
                            placeholder="Date range"
                            defaultDate={datePickerDefault}
                            onChange={(dates) => {
                                if (dates.length === 2) {
                                    const from = moment(dates[0]).format(
                                        'YYYY-MM-DD',
                                    );
                                    const to = moment(dates[1]).format(
                                        'YYYY-MM-DD',
                                    );
                                    setFromDate(from);
                                    setToDate(to);
                                    applyFilters({ from, to, page: 1 });
                                }
                            }}
                        />

                        {(activeFilterCount > 0 || hasDateFilter || causer) && (
                            <button
                                type="button"
                                onClick={handleClear}
                                className="inline-flex h-9 items-center gap-1.5 rounded-[10px] border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-900"
                            >
                                <RotateCcw className="h-4 w-4" />
                                Reset
                            </button>
                        )}
                    </div>
                </div>

                <div className="mt-6 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={activities.data || []}
                        enableInternalPagination={false}
                        initialSorting={initialSorting}
                        meta={{ ...omit(activities, ['data']) }}
                        onFetch={(params) => {
                            applyFilters({
                                page:
                                    typeof params?.page === 'number'
                                        ? params.page
                                        : undefined,
                                sort:
                                    typeof params?.sort === 'string'
                                        ? params.sort
                                        : undefined,
                                per_page:
                                    typeof params?.per_page === 'number'
                                        ? params.per_page
                                        : undefined,
                            });
                        }}
                    />
                </div>
            </div>
        </AdminSidebarLayout>
    );
}

function ActivityFilterPopover({
    activeFilterCount,
    workspaces,
    workspace,
    eventType,
    onWorkspaceChange,
    onEventChange,
    onClear,
}: {
    activeFilterCount: number;
    workspaces: WorkspaceOption[];
    workspace: string;
    eventType: string;
    onWorkspaceChange: (value: string) => void;
    onEventChange: (value: string) => void;
    onClear: () => void;
}) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <button
                    className={[
                        'inline-flex h-9 min-w-max shrink-0 items-center overflow-hidden rounded-[10px] border transition-all duration-150',
                        'bg-white dark:bg-zinc-900',
                        'shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] dark:shadow-none',
                        isOpen
                            ? 'border-emerald-500/40 ring-2 ring-emerald-500/10 dark:border-emerald-500/30'
                            : activeFilterCount > 0
                              ? 'border-emerald-500/30 hover:border-emerald-500/50 dark:border-emerald-500/20 dark:hover:border-emerald-500/30'
                              : 'border-black/8 hover:border-black/14 dark:border-white/8 dark:hover:border-white/14',
                    ].join(' ')}
                >
                    <span
                        className={[
                            'flex h-full w-9 shrink-0 items-center justify-center rounded-l-[10px] border-r transition-colors duration-150',
                            activeFilterCount > 0
                                ? 'border-emerald-500/20 bg-emerald-500/[0.07] dark:border-emerald-500/15 dark:bg-emerald-500/10'
                                : 'border-black/6 bg-stone-50 dark:border-white/6 dark:bg-white/3',
                        ].join(' ')}
                    >
                        <SlidersHorizontal
                            className={[
                                'h-3.5 w-3.5 transition-colors duration-150',
                                activeFilterCount > 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-gray-400 dark:text-gray-500',
                            ].join(' ')}
                        />
                    </span>
                    <span className="flex items-center gap-2 px-3">
                        <span
                            className={[
                                'text-xs font-medium transition-colors duration-150',
                                activeFilterCount > 0
                                    ? 'text-gray-700 dark:text-gray-200'
                                    : 'text-gray-500 dark:text-gray-400',
                            ].join(' ')}
                        >
                            Filters
                        </span>
                        {activeFilterCount > 0 && (
                            <span className="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-500/[0.10] px-1 text-[10px] font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                {activeFilterCount}
                            </span>
                        )}
                    </span>
                </button>
            </PopoverTrigger>

            <PopoverContent
                className="w-[calc(100vw-2rem)] overflow-hidden rounded-[14px] border border-black/6 bg-white p-0 shadow-[0_8px_30px_rgba(0,0,0,0.08)] sm:w-72 dark:border-white/6 dark:bg-zinc-900 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
                align="start"
            >
                <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <span className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                        Filters
                    </span>
                    {activeFilterCount > 0 && (
                        <button
                            type="button"
                            onClick={onClear}
                            className="inline-flex items-center gap-1 text-[11px] font-medium text-gray-400 transition-colors hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                        >
                            <X className="h-3 w-3" />
                            Clear
                        </button>
                    )}
                </div>

                <div className="max-h-72 space-y-1 overflow-y-auto p-2">
                    <FilterSection title="Event">
                        {EVENT_TYPES.map((event) => (
                            <FilterOption
                                key={event.value}
                                label={event.label}
                                selected={eventType === event.value}
                                onClick={() => onEventChange(event.value)}
                            />
                        ))}
                    </FilterSection>

                    <FilterSection title="Workspace">
                        <FilterOption
                            label="All workspaces"
                            selected={!workspace}
                            onClick={() => onWorkspaceChange(workspace)}
                        />
                        {workspaces.map((item) => (
                            <FilterOption
                                key={item.id}
                                label={item.name}
                                selected={workspace === String(item.id)}
                                onClick={() =>
                                    onWorkspaceChange(String(item.id))
                                }
                            />
                        ))}
                    </FilterSection>
                </div>
            </PopoverContent>
        </Popover>
    );
}

function FilterSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div>
            <div className="mb-1.5 px-2 text-[10px] font-bold tracking-[0.08em] text-zinc-400 uppercase">
                {title}
            </div>
            <div className="space-y-1">{children}</div>
        </div>
    );
}

function FilterOption({
    label,
    selected,
    onClick,
}: {
    label: string;
    selected: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={[
                'flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left text-[13px] transition-colors',
                selected
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                    : 'text-zinc-700 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800/70',
            ].join(' ')}
        >
            <span className="truncate">{label}</span>
            {selected && <Check className="h-3.5 w-3.5 shrink-0" />}
        </button>
    );
}
