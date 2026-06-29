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
    XCircle,
} from 'lucide-react';
import { useMemo } from 'react';

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
}

interface Props {
    workspace: Workspace;
    summary: ItemSummary[];
    syncTypes: string[];
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
}

const TYPE_LABELS: Record<string, string> = {
    transaction_history: 'Transactions',
    purchase_order: 'Purchase Orders',
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
        skipped: {
            Icon: AlertTriangle,
            cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
            dot: 'bg-amber-400',
            label: 'Skipped',
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
    syncTypes,
    recent,
    totalRuns24h,
    successRuns24h,
    failedRuns24h,
    pendingRuns,
    lastSuccessfulRun,
    query,
}: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/inventory/sync-health`;

    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const statusFilter = query?.filter?.status ?? '';
    const typeFilter = query?.filter?.sync_type ?? '';

    const navigate = (overrides: Record<string, unknown> = {}) => {
        router.get(
            indexUrl,
            {
                sort: query?.sort,
                'filter[status]': statusFilter || undefined,
                'filter[sync_type]': typeFilter || undefined,
                page: 1,
                per_page: query?.perPage ?? recent.per_page,
                ...overrides,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

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
                <SortableHeader column={column} title="Detail" enabled={false} />
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
                        value={summary.length}
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

                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                            Per-item status
                        </h2>
                        <span className="font-mono text-[10px] text-gray-300 dark:text-gray-600">
                            {summary.length} item
                            {summary.length === 1 ? '' : 's'}
                        </span>
                    </div>
                    <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable columns={summaryColumns} data={summary} />
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
                                    navigate({
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
                                    navigate({
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
                                    {[
                                        'success',
                                        'failed',
                                        'pending',
                                        'skipped',
                                    ].map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s[0].toUpperCase() + s.slice(1)}
                                        </SelectItem>
                                    ))}
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
                                navigate({
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
