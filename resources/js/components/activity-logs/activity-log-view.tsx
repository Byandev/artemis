import { Badge } from '@/components/ui/badge';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { toFrontendSort } from '@/lib/sort';
import { cn } from '@/lib/utils';
import { PaginatedData } from '@/types';
import {
    ActivityLog,
    ActivityLogSummary,
    LogStatus,
} from '@/types/models/ActivityLog';
import { router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import debounce from 'lodash/debounce';
import { AlertTriangle, Clock, ListChecks, Search } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';

interface FilterState {
    search?: string | null;
    log_type?: string | null;
    category?: string | null;
    status?: string | null;
    start_date?: string | null;
    end_date?: string | null;
    workspace_id?: string | null;
}

interface Options {
    log_types: string[];
    statuses: string[];
    categories: string[];
    trigger_types: string[];
}

interface Props {
    logs: PaginatedData<ActivityLog>;
    summary: ActivityLogSummary;
    options: Options;
    filters?: FilterState;
    query?: {
        sort?: string | null;
        per_page?: number | string;
        page?: number | string;
    };
    baseUrl: string;
    showWorkspace?: boolean;
    /** Raw metadata is only surfaced in the global admin view. */
    showMetadata?: boolean;
}

const STATUS_STYLES: Record<LogStatus, string> = {
    success:
        'border-emerald-200/60 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400',
    failure:
        'border-red-200/60 bg-red-50 text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-400',
    warning:
        'border-amber-200/60 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-400',
    info: 'border-sky-200/60 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-400',
};

const titleCase = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export default function ActivityLogView({
    logs,
    summary,
    options,
    filters,
    query,
    baseUrl,
    showWorkspace = false,
    showMetadata = false,
}: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const [search, setSearch] = useState(filters?.search ?? '');
    const [logType, setLogType] = useState(filters?.log_type ?? 'all');
    const [category, setCategory] = useState(filters?.category ?? 'all');
    const [status, setStatus] = useState(filters?.status ?? 'all');
    const [selected, setSelected] = useState<ActivityLog | null>(null);

    const fetchLogs = useCallback(
        (overrides: Record<string, string | number | undefined> = {}) => {
            router.get(
                baseUrl,
                {
                    sort: query?.sort ?? undefined,
                    page: 1,
                    per_page: query?.per_page,
                    'filter[search]': search || undefined,
                    'filter[log_type]': logType === 'all' ? undefined : logType,
                    'filter[category]':
                        category === 'all' ? undefined : category,
                    'filter[status]': status === 'all' ? undefined : status,
                    ...overrides,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [
            baseUrl,
            category,
            logType,
            query?.per_page,
            query?.sort,
            search,
            status,
        ],
    );

    const debouncedSearch = useMemo(
        () => debounce(() => fetchLogs(), 400),
        [fetchLogs],
    );

    useEffect(() => {
        if (search !== (filters?.search ?? '')) debouncedSearch();
        return () => debouncedSearch.cancel();
    }, [debouncedSearch, filters?.search, search]);

    useEffect(() => {
        if (
            logType === (filters?.log_type ?? 'all') &&
            category === (filters?.category ?? 'all') &&
            status === (filters?.status ?? 'all')
        ) {
            return;
        }
        fetchLogs();
    }, [logType, category, status]); // eslint-disable-line react-hooks/exhaustive-deps

    const columns: ColumnDef<ActivityLog>[] = [
        {
            accessorKey: 'created_at',
            header: ({ column }) => (
                <SortableHeader column={column} title="When" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] whitespace-nowrap text-gray-500 dark:text-gray-400">
                    {new Date(row.original.created_at).toLocaleString()}
                </span>
            ),
        },
        {
            accessorKey: 'log_type',
            header: ({ column }) => (
                <SortableHeader column={column} title="Type" />
            ),
            cell: ({ row }) => (
                <Badge variant="outline" className="font-mono text-[10px]">
                    {titleCase(row.original.log_type)}
                </Badge>
            ),
        },
        {
            accessorKey: 'category',
            header: ({ column }) => (
                <SortableHeader column={column} title="Category" />
            ),
            cell: ({ row }) => titleCase(row.original.category),
        },
        {
            accessorKey: 'action_type',
            header: () => <HeaderLabel>Action</HeaderLabel>,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-300">
                    {row.original.action_type ?? '—'}
                </span>
            ),
        },
        {
            id: 'actor',
            header: () => <HeaderLabel>Actor</HeaderLabel>,
            cell: ({ row }) =>
                row.original.user ? (
                    <div className="space-y-0.5">
                        <p className="text-[12px] font-medium text-gray-800 dark:text-gray-100">
                            {row.original.user.name}
                        </p>
                        <p className="font-mono text-[10px] text-gray-400">
                            {row.original.user.email}
                        </p>
                    </div>
                ) : (
                    <span className="font-mono text-[11px] text-gray-400">
                        System
                    </span>
                ),
        },
        ...(showWorkspace
            ? [
                  {
                      id: 'workspace',
                      header: () => <HeaderLabel>Workspace</HeaderLabel>,
                      cell: ({ row }) =>
                          row.original.workspace ? (
                              <span className="text-[12px] text-gray-700 dark:text-gray-300">
                                  {row.original.workspace.name}
                              </span>
                          ) : (
                              <span className="font-mono text-[11px] text-gray-400">
                                  —
                              </span>
                          ),
                  } as ColumnDef<ActivityLog>,
              ]
            : []),
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <Badge className={STATUS_STYLES[row.original.status]}>
                    {titleCase(row.original.status)}
                </Badge>
            ),
        },
        {
            accessorKey: 'message',
            header: () => <HeaderLabel>Message</HeaderLabel>,
            cell: ({ row }) => (
                <span className="line-clamp-1 max-w-[280px] text-[12px] text-gray-600 dark:text-gray-300">
                    {row.original.message ?? '—'}
                </span>
            ),
        },
    ];

    return (
        <>
            <div className="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <SummaryCard
                    label="Total logs"
                    value={summary.total}
                    icon={<ListChecks className="h-4 w-4 text-gray-400" />}
                />
                <SummaryCard
                    label="Failures"
                    value={summary.failures}
                    accent="text-red-600 dark:text-red-400"
                    icon={<AlertTriangle className="h-4 w-4 text-red-500" />}
                />
                <SummaryCard
                    label="Last 24h"
                    value={summary.last_24h}
                    icon={<Clock className="h-4 w-4 text-gray-400" />}
                />
            </div>

            <div className="mb-3 flex flex-wrap items-center gap-2">
                <div className="relative w-full sm:w-64">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        type="text"
                        placeholder="Search message, action, job…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-md border border-zinc-200 bg-white py-2 pr-4 pl-10 text-sm outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-800 dark:bg-zinc-950"
                    />
                </div>

                <FilterSelect
                    value={logType}
                    onChange={setLogType}
                    placeholder="Type"
                    allLabel="All types"
                    options={options.log_types}
                />
                <FilterSelect
                    value={category}
                    onChange={setCategory}
                    placeholder="Category"
                    allLabel="All categories"
                    options={options.categories}
                />
                <FilterSelect
                    value={status}
                    onChange={setStatus}
                    placeholder="Status"
                    allLabel="All statuses"
                    options={options.statuses}
                />
            </div>

            <div className="rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <DataTable
                    columns={columns}
                    data={logs.data || []}
                    enableInternalPagination={false}
                    initialSorting={initialSorting}
                    meta={{ ...omit(logs, ['data']) }}
                    onRowClick={(row) => setSelected(row)}
                    onFetch={(params) => {
                        const sortStr =
                            params?.sort && params.sort !== null
                                ? String(params.sort)
                                : null;
                        fetchLogs({
                            sort: sortStr ?? undefined,
                            page: Number(params?.page ?? 1),
                            per_page: Number(
                                params?.per_page ??
                                    query?.per_page ??
                                    logs.per_page,
                            ),
                        });
                    }}
                />
            </div>

            <ActivityLogDetail
                log={selected}
                showWorkspace={showWorkspace}
                showMetadata={showMetadata}
                onClose={() => setSelected(null)}
            />
        </>
    );
}

function ActivityLogDetail({
    log,
    showWorkspace,
    showMetadata,
    onClose,
}: {
    log: ActivityLog | null;
    showWorkspace: boolean;
    showMetadata: boolean;
    onClose: () => void;
}) {
    return (
        <Sheet open={!!log} onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="overflow-y-auto border-black/6 bg-white sm:max-w-xl dark:border-white/8 dark:bg-zinc-900">
                <SheetHeader className="border-b border-black/6 px-5 py-4 text-left dark:border-white/8">
                    <SheetTitle className="font-mono text-[13px] font-semibold tracking-wide text-gray-800 uppercase dark:text-gray-100">
                        {log ? titleCase(log.action_type ?? log.category) : ''}
                    </SheetTitle>
                    <SheetDescription className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                        {log ? new Date(log.created_at).toLocaleString() : ''}
                    </SheetDescription>
                </SheetHeader>

                {log && (
                    <div className="space-y-5 px-5 py-4 text-sm">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className={STATUS_STYLES[log.status]}>
                                {titleCase(log.status)}
                            </Badge>
                            <Badge variant="outline">
                                {titleCase(log.log_type)}
                            </Badge>
                            <Badge variant="outline">
                                {titleCase(log.category)}
                            </Badge>
                        </div>

                        {log.message && (
                            <Detail label="Message">
                                <p className="text-[13px] whitespace-pre-wrap text-gray-700 dark:text-gray-200">
                                    {log.message}
                                </p>
                            </Detail>
                        )}

                        <Detail label="Actor">
                            {log.user ? (
                                <>
                                    <p className="text-[13px] font-medium text-gray-800 dark:text-gray-100">
                                        {log.user.name}
                                    </p>
                                    <p className="text-[12px] text-gray-500 dark:text-gray-400">
                                        {log.user.email}
                                    </p>
                                </>
                            ) : (
                                <p className="text-[13px] text-gray-500">
                                    System (no user)
                                </p>
                            )}
                        </Detail>

                        {showWorkspace && log.workspace && (
                            <Detail label="Workspace">
                                <p className="text-[13px] font-medium text-gray-800 dark:text-gray-100">
                                    {log.workspace.name}
                                </p>
                                <p className="font-mono text-[12px] text-gray-500">
                                    /{log.workspace.slug}
                                </p>
                            </Detail>
                        )}

                        {log.log_type === 'system' && (
                            <div className="grid grid-cols-2 gap-4">
                                <Detail label="Trigger">
                                    {log.trigger_type
                                        ? titleCase(log.trigger_type)
                                        : '—'}
                                </Detail>
                                <Detail label="Schedule">
                                    <span className="font-mono text-[12px]">
                                        {log.schedule ?? '—'}
                                    </span>
                                </Detail>
                                <Detail label="Job">
                                    <span className="font-mono text-[12px] wrap-break-word">
                                        {log.job_name ?? '—'}
                                    </span>
                                </Detail>
                            </div>
                        )}

                        {log.ip_address && (
                            <div className="grid grid-cols-2 gap-4">
                                <Detail label="IP address">
                                    <span className="font-mono text-[12px]">
                                        {log.ip_address}
                                    </span>
                                </Detail>
                            </div>
                        )}

                        {log.error_detail && (
                            <Detail label="Error detail">
                                <pre className="mt-1 max-h-64 overflow-auto rounded-md bg-red-50 p-3 font-mono text-[11px] whitespace-pre-wrap text-red-700 dark:bg-red-500/10 dark:text-red-300">
                                    {log.error_detail}
                                </pre>
                            </Detail>
                        )}

                        {showMetadata &&
                            log.metadata &&
                            Object.keys(log.metadata).length > 0 && (
                                <Detail label="Metadata">
                                    <pre className="mt-1 max-h-64 overflow-auto rounded-md bg-stone-50 p-3 font-mono text-[11px] whitespace-pre-wrap text-gray-700 dark:bg-zinc-800 dark:text-gray-300">
                                        {JSON.stringify(log.metadata, null, 2)}
                                    </pre>
                                </Detail>
                            )}
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}

function FilterSelect({
    value,
    onChange,
    placeholder,
    allLabel,
    options,
}: {
    value: string;
    onChange: (v: string) => void;
    placeholder: string;
    allLabel: string;
    options: string[];
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger className="h-9 w-[160px]">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">{allLabel}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option} value={option}>
                        {titleCase(option)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function SummaryCard({
    label,
    value,
    icon,
    accent = 'text-gray-800 dark:text-gray-100',
}: {
    label: string;
    value: number;
    icon: ReactNode;
    accent?: string;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-center gap-2 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {icon}
                <span>{label}</span>
            </div>
            <div
                className={cn(
                    'mt-2 font-mono text-[20px] font-semibold',
                    accent,
                )}
            >
                {value.toLocaleString()}
            </div>
        </div>
    );
}

function HeaderLabel({ children }: { children: ReactNode }) {
    return (
        <p className="font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
            {children}
        </p>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {label}
            </p>
            <div className="mt-1">{children}</div>
        </div>
    );
}
