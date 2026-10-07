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
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import { omit } from 'lodash';
import { AlertTriangle, Clock, ListChecks, Search } from 'lucide-react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';

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
    options: Options;
    filters?: FilterState;
    query?: {
        sort?: string | null;
        per_page?: number | string | null;
        page?: number | string | null;
    };
    /** JSON endpoint for the paginated logs table. */
    logsUrl: string;
    /** JSON endpoint for the stat cards. */
    summaryUrl: string;
    showWorkspace?: boolean;
    /** Raw metadata is only surfaced in the global admin view. */
    showMetadata?: boolean;
}

type Params = Record<string, string | number | undefined>;

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

/** Mirror the active params into the address bar so a reload keeps the view. */
const syncUrl = (params: Params) => {
    const search = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== '') search.set(key, String(value));
    });
    const qs = search.toString();
    window.history.replaceState(
        window.history.state,
        '',
        `${window.location.pathname}${qs ? `?${qs}` : ''}`,
    );
};

export default function ActivityLogView({
    options,
    filters,
    query,
    logsUrl,
    summaryUrl,
    showWorkspace = false,
    showMetadata = false,
}: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const [search, setSearch] = useState(filters?.search ?? '');
    const [debouncedSearch, setDebouncedSearch] = useState(search);
    const [logType, setLogType] = useState(filters?.log_type ?? 'all');
    const [category, setCategory] = useState(filters?.category ?? 'all');
    const [status, setStatus] = useState(filters?.status ?? 'all');
    const [sort, setSort] = useState<string | undefined>(
        query?.sort ?? undefined,
    );
    const [page, setPage] = useState(Number(query?.page ?? 1));
    const [perPage, setPerPage] = useState(Number(query?.per_page ?? 20));
    const [selected, setSelected] = useState<ActivityLog | null>(null);

    const [logs, setLogs] = useState<PaginatedData<ActivityLog> | null>(null);
    const [logsLoading, setLogsLoading] = useState(true);
    const [summary, setSummary] = useState<ActivityLogSummary | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (search === debouncedSearch) return;
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 400);
        return () => clearTimeout(timer);
    }, [search, debouncedSearch]);

    /** A changed filter facet always sends the table back to page one. */
    const withPageReset =
        (setter: (value: string) => void) => (value: string) => {
            setter(value);
            setPage(1);
        };

    // The filter context shared by the table and the stat cards.
    const filterParams = useMemo<Params>(
        () => ({
            'filter[search]': debouncedSearch || undefined,
            'filter[log_type]': logType === 'all' ? undefined : logType,
            'filter[category]': category === 'all' ? undefined : category,
            'filter[status]': status === 'all' ? undefined : status,
            'filter[start_date]': filters?.start_date ?? undefined,
            'filter[end_date]': filters?.end_date ?? undefined,
            'filter[workspace_id]': filters?.workspace_id ?? undefined,
        }),
        [
            category,
            debouncedSearch,
            filters?.end_date,
            filters?.start_date,
            filters?.workspace_id,
            logType,
            status,
        ],
    );

    useEffect(() => {
        const controller = new AbortController();
        const params: Params = {
            ...filterParams,
            sort,
            page,
            per_page: perPage,
        };

        syncUrl(params);
        setLogsLoading(true);
        setError(null);

        axios
            .get<PaginatedData<ActivityLog>>(logsUrl, {
                params,
                signal: controller.signal,
            })
            .then((res) => setLogs(res.data))
            .catch((err) => {
                if (axios.isCancel(err)) return;
                setError('Failed to load activity logs.');
            })
            .finally(() => {
                if (!controller.signal.aborted) setLogsLoading(false);
            });

        return () => controller.abort();
    }, [logsUrl, filterParams, page, perPage, sort]);

    useEffect(() => {
        const controller = new AbortController();

        setSummary(null);
        axios
            .get<ActivityLogSummary>(summaryUrl, {
                params: filterParams,
                signal: controller.signal,
            })
            .then((res) => setSummary(res.data))
            .catch(() => {});

        return () => controller.abort();
    }, [summaryUrl, filterParams]);

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
                    value={summary?.total}
                    icon={<ListChecks className="h-4 w-4 text-gray-400" />}
                />
                <SummaryCard
                    label="Failures"
                    value={summary?.failures}
                    accent="text-red-600 dark:text-red-400"
                    icon={<AlertTriangle className="h-4 w-4 text-red-500" />}
                />
                <SummaryCard
                    label="Last 24h"
                    value={summary?.last_24h}
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
                    onChange={withPageReset(setLogType)}
                    placeholder="Type"
                    allLabel="All types"
                    options={options.log_types}
                />
                <FilterSelect
                    value={category}
                    onChange={withPageReset(setCategory)}
                    placeholder="Category"
                    allLabel="All categories"
                    options={options.categories}
                />
                <FilterSelect
                    value={status}
                    onChange={withPageReset(setStatus)}
                    placeholder="Status"
                    allLabel="All statuses"
                    options={options.statuses}
                />
            </div>

            {error && (
                <p className="mb-3 text-sm text-red-600 dark:text-red-400">
                    {error}
                </p>
            )}

            <div className="rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <DataTable
                    columns={columns}
                    data={logs?.data ?? []}
                    loading={logsLoading}
                    enableInternalPagination={false}
                    initialSorting={initialSorting}
                    meta={logs ? omit(logs, ['data']) : undefined}
                    onRowClick={(row) => setSelected(row)}
                    onFetch={(params) => {
                        setSort(
                            params?.sort != null
                                ? String(params.sort)
                                : undefined,
                        );
                        setPage(Number(params?.page ?? 1));
                        setPerPage(Number(params?.per_page ?? perPage));
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
    /** Undefined while the summary is loading. */
    value?: number;
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
                {value === undefined ? (
                    <span className="inline-block h-6 w-12 animate-pulse rounded bg-gray-100 dark:bg-zinc-800" />
                ) : (
                    value.toLocaleString()
                )}
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
