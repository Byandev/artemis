import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from 'clsx';
import { omit } from 'lodash';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock,
    Package,
    RefreshCw,
    Search,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface SyncCell {
    sync_type: string;
    status: string | null;
    rows_received: number | null;
    rows_saved: number | null;
    started_at: string | null;
    finished_at: string | null;
    message: string | null;
}

interface ItemSummary {
    item: {
        id: string;
        sku: string;
        product_name: string | null;
    };
    syncs: SyncCell[];
}

interface RecentRun {
    id: number;
    inventory_item_id: number | null;
    item_sku: string | null;
    item_name: string | null;
    sync_type: string;
    status: string;
    rows_received: number | null;
    rows_saved: number | null;
    started_at: string | null;
    finished_at: string | null;
    duration_seconds: number | null;
    message: string | null;
    n8n_execution_id: number | null;
}

interface SyncBatch {
    id: number;
    trigger: string;
    status: string;
    sync_types: string[] | null;
    total_runs: number;
    completed_runs: number;
    failed_runs: number;
    pending_runs: number;
    message: string | null;
    started_at: string | null;
    finished_at: string | null;
    duration_seconds: number | null;
    can_retry: boolean;
}

interface Props {
    workspace: Workspace;
    summary: PaginatedData<ItemSummary>;
    activeItemsCount: number;
    syncTypes: string[];
    batches: SyncBatch[];
    canRetry: boolean;
    n8nExecutionUrlPrefix: string | null;
    recent: PaginatedData<RecentRun>;
    totalRuns24h: number;
    successRuns24h: number;
    failedRuns24h: number;
    pendingRuns: number;
    lastSuccessfulRun: {
        sync_type: string;
        item_sku: string | null;
        started_at: string | null;
    } | null;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            status?: string;
            sync_type?: string;
        };
    };
    itemsQuery?: {
        page?: number | string;
        perPage?: number | string;
        search?: string | null;
    };
}

const TYPE_LABELS: Record<string, string> = {
    transaction_history: 'Transactions',
    purchase_order: 'Purchase Orders',
    daily_sales_tracker: 'Daily Sales',
};

function StatusPill({ status }: { status: string | null }) {
    if (!status) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] tracking-wide text-stone-500 uppercase dark:bg-zinc-800 dark:text-zinc-500">
                <span className="h-1 w-1 rounded-full bg-stone-300" />
                Never
            </span>
        );
    }
    const config: Record<
        string,
        { Icon: typeof CheckCircle2; cls: string; label: string; dot: string }
    > = {
        success: {
            Icon: CheckCircle2,
            cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
            dot: 'bg-emerald-500',
            label: 'Success',
        },
        failed: {
            Icon: XCircle,
            cls: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
            dot: 'bg-red-400',
            label: 'Failed',
        },
        pending: {
            Icon: Clock,
            cls: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
            dot: 'bg-blue-400',
            label: 'Pending',
        },
    };
    const c = config[status] ?? config.failed;

    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 font-mono text-[10px] tracking-wide uppercase',
                c.cls,
            )}
        >
            <span className={clsx('h-1 w-1 rounded-full', c.dot)} />
            {c.label}
        </span>
    );
}

/**
 * Batch statuses are a different vocabulary to run statuses — `skipped` in
 * particular is not a failure but a deliberate hold, so it reads amber rather
 * than red.
 */
function BatchStatusPill({ status }: { status: string }) {
    const config: Record<string, { cls: string; dot: string; label: string }> =
        {
            running: {
                cls: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
                dot: 'bg-blue-400',
                label: 'Running',
            },
            completed: {
                cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                dot: 'bg-emerald-500',
                label: 'Completed',
            },
            partial: {
                cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                dot: 'bg-amber-400',
                label: 'Partial',
            },
            failed: {
                cls: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
                dot: 'bg-red-400',
                label: 'Failed',
            },
            skipped: {
                cls: 'bg-stone-100 text-stone-600 dark:bg-zinc-800 dark:text-zinc-400',
                dot: 'bg-stone-400',
                label: 'Skipped',
            },
        };
    const c = config[status] ?? config.failed;

    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 font-mono text-[10px] tracking-wide uppercase',
                c.cls,
            )}
        >
            <span className={clsx('h-1 w-1 rounded-full', c.dot)} />
            {c.label}
        </span>
    );
}

/** Clock time a batch started, which is what identifies the sweep. */
function formatClock(ts: string | null) {
    if (!ts) return '—';
    return new Date(ts).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatRelative(ts: string | null) {
    if (!ts) return '—';
    const d = new Date(ts);
    const diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60) return `${Math.round(diff)}s ago`;
    if (diff < 3600) return `${Math.round(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.round(diff / 3600)}h ago`;
    return `${Math.round(diff / 86400)}d ago`;
}

function formatDuration(seconds: number | null) {
    if (seconds == null) return '—';
    if (seconds > 60) return `${(seconds / 60).toFixed(2)}m`;
    return `${seconds}s`;
}

function StatCard({
    label,
    value,
    icon: Icon,
    tone = 'neutral',
}: {
    label: string;
    value: string | number;
    icon: typeof Activity;
    tone?: 'neutral' | 'success' | 'warning' | 'danger';
}) {
    const tones = {
        neutral: 'text-gray-700 dark:text-gray-200',
        success: 'text-emerald-600 dark:text-emerald-400',
        warning: 'text-amber-600 dark:text-amber-400',
        danger: 'text-red-600 dark:text-red-400',
    };
    const iconBg = {
        neutral:
            'bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400',
        success:
            'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
        warning:
            'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
        danger: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
    };
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-5 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10">
            <div className="flex items-start justify-between">
                <div>
                    <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        {label}
                    </p>
                    <p
                        className={clsx(
                            'mt-2 text-2xl font-semibold tracking-tight',
                            tones[tone],
                        )}
                    >
                        {value}
                    </p>
                </div>
                <div
                    className={clsx(
                        'flex h-9 w-9 items-center justify-center rounded-xl',
                        iconBg[tone],
                    )}
                >
                    <Icon className="h-4 w-4" />
                </div>
            </div>
        </div>
    );
}

function SyncCellView({ cell }: { cell: SyncCell | undefined }) {
    if (!cell) return <StatusPill status={null} />;
    return (
        <div className="flex flex-col gap-1">
            <StatusPill status={cell.status} />
            <div className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                {formatRelative(cell.started_at)}
            </div>
            {cell.rows_received != null && (
                <div
                    className="font-mono text-[10px] text-gray-300 dark:text-gray-600"
                    title={`${(cell.rows_saved ?? 0).toLocaleString()} saved`}
                >
                    {cell.rows_received.toLocaleString()} rows
                </div>
            )}
            {cell.message && (
                <div
                    className="max-w-[200px] truncate font-mono text-[10px] text-red-500 dark:text-red-400"
                    title={cell.message}
                >
                    {cell.message}
                </div>
            )}
        </div>
    );
}

export default function InventorySyncHealth({
    workspace,
    summary,
    activeItemsCount,
    syncTypes,
    batches,
    canRetry,
    n8nExecutionUrlPrefix,
    recent,
    totalRuns24h,
    successRuns24h,
    failedRuns24h,
    pendingRuns,
    lastSuccessfulRun,
    query,
    itemsQuery,
}: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/inventory/sync-health`;

    // Guards the buttons against a double-submit while the replay is in flight.
    const [retrying, setRetrying] = useState<string | null>(null);

    const retry = (url: string, key: string) => {
        setRetrying(key);
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setRetrying(null),
            },
        );
    };

    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const statusFilter = query?.filter?.status ?? '';
    const typeFilter = query?.filter?.sync_type ?? '';
    const appliedItemSearch = itemsQuery?.search ?? '';
    const [itemSearch, setItemSearch] = useState(appliedItemSearch);

    // Both tables paginate independently off the same URL, so every navigation
    // carries the other table's current state to keep it from resetting.
    const recentParams = {
        sort: query?.sort,
        'filter[status]': statusFilter || undefined,
        'filter[sync_type]': typeFilter || undefined,
        page: recent.current_page,
        per_page: recent.per_page,
    };
    const itemsParams = {
        items_search: appliedItemSearch || undefined,
        items_page: summary.current_page,
        items_per_page: summary.per_page,
    };

    const navigateRecent = (overrides: Record<string, unknown> = {}) => {
        router.get(
            indexUrl,
            { ...recentParams, ...itemsParams, ...overrides },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['recent', 'query'],
            },
        );
    };

    const navigateItems = (overrides: Record<string, unknown> = {}) => {
        router.get(
            indexUrl,
            { ...recentParams, ...itemsParams, ...overrides },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['summary', 'itemsQuery'],
            },
        );
    };

    // Debounced per-item search.
    useEffect(() => {
        if (itemSearch === appliedItemSearch) return;
        const timer = setTimeout(() => {
            navigateItems({
                items_search: itemSearch || undefined,
                items_page: 1,
            });
        }, 400);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [itemSearch]);

    const successRate24h =
        totalRuns24h > 0
            ? Math.round((successRuns24h / totalRuns24h) * 100)
            : 0;

    const summaryColumns: ColumnDef<ItemSummary>[] = [
        {
            id: 'item',
            header: ({ column }) => (
                <SortableHeader column={column} title="Item" enabled={false} />
            ),
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    <span className="font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">
                        {row.original.item.sku}
                    </span>
                    {row.original.item.product_name && (
                        <span className="text-[11px] text-gray-400 dark:text-gray-500">
                            {row.original.item.product_name}
                        </span>
                    )}
                </div>
            ),
        },
        ...syncTypes.map<ColumnDef<ItemSummary>>((type) => ({
            id: type,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title={TYPE_LABELS[type] ?? type}
                    enabled={false}
                />
            ),
            cell: ({ row }) => (
                <SyncCellView
                    cell={row.original.syncs.find((s) => s.sync_type === type)}
                />
            ),
        })),
    ];

    const recentColumns: ColumnDef<RecentRun>[] = [
        {
            accessorKey: 'started_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="When" />
            ),
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                        {formatRelative(row.original.started_at)}
                    </span>
                    <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                        {row.original.started_at
                            ? new Date(row.original.started_at).toLocaleString()
                            : '—'}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'item_sku',
            header: ({ column }) => (
                <SortableHeader column={column} title="Item" enabled={false} />
            ),
            cell: ({ row }) =>
                row.original.item_sku ? (
                    <div className="flex flex-col">
                        <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                            {row.original.item_sku}
                        </span>
                        {row.original.item_name && (
                            <span className="text-[10px] text-gray-400 dark:text-gray-500">
                                {row.original.item_name}
                            </span>
                        )}
                    </div>
                ) : (
                    <span className="text-[11px] text-gray-300 italic dark:text-gray-600">
                        —
                    </span>
                ),
        },
        {
            accessorKey: 'sync_type',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Type" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {TYPE_LABELS[row.original.sync_type] ??
                        row.original.sync_type}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => <StatusPill status={row.original.status} />,
        },
        {
            accessorKey: 'rows_received',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Rows" />
            ),
            cell: ({ row }) =>
                row.original.rows_received != null ? (
                    <span
                        className="font-mono text-[12px] text-gray-700 dark:text-gray-300"
                        title={`${(row.original.rows_saved ?? 0).toLocaleString()} saved`}
                    >
                        {row.original.rows_received.toLocaleString()}
                    </span>
                ) : (
                    <span className="text-gray-300 dark:text-gray-600">—</span>
                ),
        },
        {
            accessorKey: 'duration_seconds',
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Duration"
                    enabled={false}
                />
            ),
            cell: ({ row }) =>
                row.original.duration_seconds != null ? (
                    <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                        {formatDuration(row.original.duration_seconds)}
                    </span>
                ) : (
                    <span className="text-gray-300 dark:text-gray-600">—</span>
                ),
        },
        {
            accessorKey: 'message',
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Detail"
                    enabled={false}
                />
            ),
            cell: ({ row }) =>
                row.original.message ? (
                    <span
                        className="block max-w-md truncate font-mono text-[11px] text-red-500 dark:text-red-400"
                        title={row.original.message}
                    >
                        {row.original.message}
                    </span>
                ) : (
                    <span className="text-gray-300 dark:text-gray-600">—</span>
                ),
        },
        {
            id: 'n8n_execution_id',
            header: () => (
                <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                    n8n
                </span>
            ),
            cell: ({ row }) => {
                const id = row.original.n8n_execution_id;
                if (!id)
                    return (
                        <span className="text-gray-300 dark:text-gray-600">
                            —
                        </span>
                    );

                // Clickable only when the n8n base URL and workflow id are both
                // configured; otherwise the id is still useful to paste into n8n.
                return n8nExecutionUrlPrefix ? (
                    <a
                        href={`${n8nExecutionUrlPrefix}${id}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="font-mono text-[11px] text-blue-600 hover:underline dark:text-blue-400"
                    >
                        {id}
                    </a>
                ) : (
                    <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                        {id}
                    </span>
                );
            },
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) => {
                // Only unresolved runs are worth replaying.
                if (
                    !canRetry ||
                    !['failed', 'pending'].includes(row.original.status)
                ) {
                    return null;
                }

                const key = `run-${row.original.id}`;

                return (
                    <button
                        type="button"
                        disabled={retrying === key}
                        onClick={() =>
                            retry(
                                `${indexUrl}/runs/${row.original.id}/retry`,
                                key,
                            )
                        }
                        className="inline-flex items-center gap-1.5 rounded-lg border border-black/8 px-2 py-1 font-mono text-[10px] tracking-wide text-gray-600 uppercase transition-colors hover:bg-stone-50 disabled:opacity-40 dark:border-white/10 dark:text-gray-300 dark:hover:bg-zinc-800"
                    >
                        <RefreshCw
                            className={clsx(
                                'h-3 w-3',
                                retrying === key && 'animate-spin',
                            )}
                        />
                        Retry
                    </button>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title="Inventory Sync Health" />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Inventory Sync Health"
                    description="Track whether each inventory item synced from Gencys ERP — transaction history and purchase orders."
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Active Items"
                        value={activeItemsCount}
                        icon={Package}
                    />
                    <StatCard
                        label="Runs (24h)"
                        value={totalRuns24h}
                        icon={Activity}
                    />
                    <StatCard
                        label="Success rate (24h)"
                        value={`${successRate24h}%`}
                        icon={CheckCircle2}
                        tone={
                            totalRuns24h === 0
                                ? 'neutral'
                                : successRate24h >= 95
                                  ? 'success'
                                  : successRate24h >= 80
                                    ? 'warning'
                                    : 'danger'
                        }
                    />
                    <StatCard
                        label="Failed (24h)"
                        value={failedRuns24h}
                        icon={AlertTriangle}
                        tone={
                            failedRuns24h === 0
                                ? 'success'
                                : failedRuns24h < 5
                                  ? 'warning'
                                  : 'danger'
                        }
                    />
                </div>

                {lastSuccessfulRun && (
                    <div className="rounded-[14px] border border-emerald-100 bg-emerald-50/40 px-4 py-3 dark:border-emerald-500/15 dark:bg-emerald-500/5">
                        <p className="font-mono text-[11px] text-emerald-700 dark:text-emerald-400">
                            Last successful sync:{' '}
                            {TYPE_LABELS[lastSuccessfulRun.sync_type] ??
                                lastSuccessfulRun.sync_type}
                            {lastSuccessfulRun.item_sku &&
                                ` · ${lastSuccessfulRun.item_sku}`}{' '}
                            · {formatRelative(lastSuccessfulRun.started_at)}
                        </p>
                    </div>
                )}

                {pendingRuns > 0 && (
                    <div className="rounded-[14px] border border-blue-100 bg-blue-50/40 px-4 py-3 dark:border-blue-500/15 dark:bg-blue-500/5">
                        <p className="font-mono text-[11px] text-blue-700 dark:text-blue-400">
                            {pendingRuns.toLocaleString()} sync
                            {pendingRuns === 1 ? '' : 's'} awaiting results from
                            Gencys ERP.
                        </p>
                    </div>
                )}

                {batches.length > 0 && (
                    <div>
                        <h2 className="mb-3 font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                            Scheduled sweeps
                        </h2>
                        <div className="divide-y divide-black/5 overflow-hidden rounded-[14px] border border-black/6 bg-white dark:divide-white/5 dark:border-white/6 dark:bg-zinc-900">
                            {batches.map((batch) => {
                                const done =
                                    batch.completed_runs + batch.failed_runs;
                                const pct = batch.total_runs
                                    ? Math.round(
                                          (done / batch.total_runs) * 100,
                                      )
                                    : 100;

                                return (
                                    <div
                                        key={batch.id}
                                        className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-mono text-[11px] font-medium text-gray-700 dark:text-gray-200">
                                                    {formatClock(
                                                        batch.started_at,
                                                    )}
                                                </span>
                                                {batch.trigger !==
                                                    'schedule' && (
                                                    <span className="font-mono text-[10px] tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                                        {batch.trigger}
                                                    </span>
                                                )}
                                                <BatchStatusPill
                                                    status={batch.status}
                                                />
                                                <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                                    #{batch.id} ·{' '}
                                                    {formatRelative(
                                                        batch.started_at,
                                                    )}
                                                    {batch.duration_seconds !=
                                                        null &&
                                                        ` · ${formatDuration(batch.duration_seconds)}`}
                                                </span>
                                            </div>

                                            {batch.total_runs > 0 && (
                                                <div className="mt-2 flex items-center gap-2">
                                                    <div className="h-1 w-full max-w-[220px] overflow-hidden rounded-full bg-stone-100 dark:bg-zinc-800">
                                                        <div
                                                            className={clsx(
                                                                'h-full rounded-full transition-all',
                                                                batch.failed_runs >
                                                                    0
                                                                    ? 'bg-amber-400'
                                                                    : 'bg-emerald-500',
                                                            )}
                                                            style={{
                                                                width: `${pct}%`,
                                                            }}
                                                        />
                                                    </div>
                                                    <span className="font-mono text-[10px] whitespace-nowrap text-gray-400 dark:text-gray-500">
                                                        {done}/
                                                        {batch.total_runs}
                                                        {batch.failed_runs >
                                                            0 &&
                                                            ` · ${batch.failed_runs} failed`}
                                                    </span>
                                                </div>
                                            )}

                                            {batch.message && (
                                                <p className="mt-1.5 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                                    {batch.message}
                                                </p>
                                            )}
                                        </div>

                                        {canRetry && batch.can_retry && (
                                            <button
                                                type="button"
                                                disabled={
                                                    retrying ===
                                                    `batch-${batch.id}`
                                                }
                                                onClick={() =>
                                                    retry(
                                                        `${indexUrl}/batches/${batch.id}/retry`,
                                                        `batch-${batch.id}`,
                                                    )
                                                }
                                                className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-black/8 px-2.5 py-1.5 font-mono text-[10px] tracking-wide text-gray-600 uppercase transition-colors hover:bg-stone-50 disabled:opacity-40 dark:border-white/10 dark:text-gray-300 dark:hover:bg-zinc-800"
                                            >
                                                <RefreshCw
                                                    className={clsx(
                                                        'h-3 w-3',
                                                        retrying ===
                                                            `batch-${batch.id}` &&
                                                            'animate-spin',
                                                    )}
                                                />
                                                Retry
                                            </button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div>
                    <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                            Per-item status
                        </h2>
                        <div className="flex items-center gap-3">
                            <div className="relative w-full sm:w-[260px]">
                                <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Search by SKU or product..."
                                    value={itemSearch}
                                    onChange={(e) =>
                                        setItemSearch(e.target.value)
                                    }
                                    className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 font-mono! text-[11px]! text-gray-800 outline-none focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600"
                                />
                            </div>
                            <span className="font-mono text-[10px] whitespace-nowrap text-gray-300 dark:text-gray-600">
                                {summary.total} item
                                {summary.total === 1 ? '' : 's'}
                            </span>
                        </div>
                    </div>
                    <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={summaryColumns}
                            data={summary.data || []}
                            meta={{ ...omit(summary, ['data']) }}
                            onFetch={(params) =>
                                navigateItems({
                                    items_page: params?.page ?? 1,
                                    items_per_page:
                                        params?.per_page ?? summary.per_page,
                                })
                            }
                        />
                    </div>
                </div>

                <div>
                    <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                            Recent runs
                        </h2>
                        <div className="flex items-center gap-2">
                            <Select
                                value={typeFilter || 'all'}
                                onValueChange={(v) =>
                                    navigateRecent({
                                        'filter[sync_type]':
                                            v === 'all' ? undefined : v,
                                        page: 1,
                                    })
                                }
                            >
                                <SelectTrigger className="h-9 w-[160px] font-mono text-[11px]">
                                    <SelectValue placeholder="All types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All types
                                    </SelectItem>
                                    {syncTypes.map((t) => (
                                        <SelectItem key={t} value={t}>
                                            {TYPE_LABELS[t] ?? t}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={statusFilter || 'all'}
                                onValueChange={(v) =>
                                    navigateRecent({
                                        'filter[status]':
                                            v === 'all' ? undefined : v,
                                        page: 1,
                                    })
                                }
                            >
                                <SelectTrigger className="h-9 w-[140px] font-mono text-[11px]">
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    {['success', 'failed', 'pending'].map(
                                        (s) => (
                                            <SelectItem key={s} value={s}>
                                                {s[0].toUpperCase() +
                                                    s.slice(1)}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={recentColumns}
                            data={recent.data || []}
                            initialSorting={initialSorting}
                            meta={{ ...omit(recent, ['data']) }}
                            onFetch={(params) => {
                                navigateRecent({
                                    sort: params?.sort,
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query?.perPage ??
                                        recent.per_page,
                                });
                            }}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
