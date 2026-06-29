import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
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
    Boxes,
    CheckCircle2,
    Clock,
    PackageX,
    RotateCcw,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';

type RunType = 'transaction_history' | 'purchase_order';

interface RunRow {
    id: number;
    type: RunType;
    status: string;
    trigger: string;
    total_items: number;
    items_synced: number;
    chunks_total: number;
    chunks_sent: number;
    chunks_failed: number;
    chunks_pending: number;
    started_at: string | null;
    finished_at: string | null;
    duration_seconds: number | null;
}

interface CoverageRow {
    id: number;
    sku: string;
    remaining_qty: number | null;
    tx_status: string | null;
    tx_synced_at: string | null;
    po_status: string | null;
    po_synced_at: string | null;
}

interface RunSummary {
    id: number;
    status: string;
    started_at: string | null;
    items_synced: number;
    total_items: number;
}

interface Props {
    workspace: Workspace;
    runs: PaginatedData<RunRow>;
    coverage: CoverageRow[];
    stats: {
        active_items: number;
        items_not_synced: number;
        failed_chunks: number;
        last_transaction_run: RunSummary | null;
        last_purchase_order_run: RunSummary | null;
    };
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { type?: string; status?: string };
    };
}

const TYPE_LABELS: Record<RunType, string> = {
    transaction_history: 'Transactions',
    purchase_order: 'Purchase orders',
};

// Normalise every run/chunk/coverage status onto one of four visual buckets.
const STATUS_STYLES: Record<
    string,
    { cls: string; dot: string; label: string }
> = {
    ok: {
        cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        dot: 'bg-emerald-500',
        label: 'OK',
    },
    running: {
        cls: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
        dot: 'bg-blue-400',
        label: 'Running',
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
};

const STATUS_BUCKET: Record<string, keyof typeof STATUS_STYLES & string> = {
    completed: 'ok',
    confirmed: 'ok',
    sent: 'ok',
    running: 'running',
    pending: 'running',
    partial: 'partial',
    failed: 'failed',
};

function StatusPill({
    status,
    label,
}: {
    status: string | null;
    label?: string;
}) {
    if (!status) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] tracking-wide text-stone-500 uppercase dark:bg-zinc-800 dark:text-zinc-500">
                <span className="h-1 w-1 rounded-full bg-stone-300" />
                Never
            </span>
        );
    }
    const bucket = STATUS_BUCKET[status] ?? 'failed';
    const c = STATUS_STYLES[bucket];
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 font-mono text-[10px] tracking-wide uppercase',
                c.cls,
            )}
        >
            <span className={clsx('h-1 w-1 rounded-full', c.dot)} />
            {label ?? c.label}
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
    if (seconds > 60) return `${(seconds / 60).toFixed(1)}m`;
    return `${seconds}s`;
}

function StatCard({
    label,
    value,
    sub,
    icon: Icon,
    tone = 'neutral',
}: {
    label: string;
    value: string | number;
    sub?: string;
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
                    {sub && (
                        <p className="mt-1 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            {sub}
                        </p>
                    )}
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

export default function SyncMonitoring({
    workspace,
    runs,
    coverage,
    stats,
    query,
}: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/inventory/sync-monitoring`;
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [typeFilter, setTypeFilter] = useState(query?.filter?.type ?? '');
    const [retrying, setRetrying] = useState<number | null>(null);

    const navigate = (overrides: Record<string, unknown> = {}) => {
        router.get(
            indexUrl,
            {
                sort: query?.sort,
                'filter[type]': typeFilter || undefined,
                page: 1,
                per_page: query?.perPage ?? runs.per_page,
                ...overrides,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    const handleTypeChange = (value: string) => {
        const next = value === 'all' ? '' : value;
        setTypeFilter(next);
        navigate({ 'filter[type]': next || undefined, page: 1 });
    };

    const retryRun = (runId: number) => {
        setRetrying(runId);
        router.post(
            `${indexUrl}/runs/${runId}/retry`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setRetrying(null),
            },
        );
    };

    const runColumns: ColumnDef<RunRow>[] = [
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
            accessorKey: 'type',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Type" />
            ),
            cell: ({ row }) => (
                <span className="text-[12px] text-gray-700 dark:text-gray-300">
                    {TYPE_LABELS[row.original.type] ?? row.original.type}
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
            id: 'chunks',
            header: ({ column }) => (
                <SortableHeader column={column} title="Chunks" enabled={false} />
            ),
            cell: ({ row }) => {
                const r = row.original;
                return (
                    <div className="flex flex-wrap items-center gap-1 font-mono text-[10px]">
                        <span className="text-gray-500 dark:text-gray-400">
                            {r.chunks_sent}/{r.chunks_total} ok
                        </span>
                        {r.chunks_failed > 0 && (
                            <span className="text-red-500 dark:text-red-400">
                                · {r.chunks_failed} failed
                            </span>
                        )}
                        {r.chunks_pending > 0 && (
                            <span className="text-blue-500 dark:text-blue-400">
                                · {r.chunks_pending} pending
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'items_synced',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Records" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                    {row.original.items_synced.toLocaleString()}
                </span>
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
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {formatDuration(row.original.duration_seconds)}
                </span>
            ),
        },
        {
            id: 'actions',
            header: ({ column }) => (
                <SortableHeader column={column} title="" enabled={false} />
            ),
            cell: ({ row }) =>
                row.original.chunks_failed > 0 ? (
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={retrying === row.original.id}
                        onClick={() => retryRun(row.original.id)}
                    >
                        <RotateCcw className="h-3.5 w-3.5" />
                        {retrying === row.original.id
                            ? 'Retrying…'
                            : `Retry ${row.original.chunks_failed}`}
                    </Button>
                ) : null,
        },
    ];

    const coverageColumns: ColumnDef<CoverageRow>[] = [
        {
            accessorKey: 'sku',
            header: ({ column }) => (
                <SortableHeader column={column} title="SKU" enabled={false} />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">
                    {row.original.sku}
                </span>
            ),
        },
        {
            accessorKey: 'remaining_qty',
            header: ({ column }) => (
                <SortableHeader column={column} title="Stock" enabled={false} />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                    {row.original.remaining_qty?.toLocaleString() ?? '—'}
                </span>
            ),
        },
        {
            id: 'tx',
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Transactions"
                    enabled={false}
                />
            ),
            cell: ({ row }) => (
                <div className="flex flex-col gap-1">
                    <StatusPill status={row.original.tx_status} />
                    <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                        {formatRelative(row.original.tx_synced_at)}
                    </span>
                </div>
            ),
        },
        {
            id: 'po',
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Purchase orders"
                    enabled={false}
                />
            ),
            cell: ({ row }) => (
                <div className="flex flex-col gap-1">
                    <StatusPill status={row.original.po_status} />
                    <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                        {formatRelative(row.original.po_synced_at)}
                    </span>
                </div>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Inventory Sync Monitoring" />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Sync Monitoring"
                    description="Track the ERP transaction-history and purchase-order syncs, spot gaps, and retry anything that failed."
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Active items"
                        value={stats.active_items}
                        icon={Boxes}
                    />
                    <StatCard
                        label="Not synced (last run)"
                        value={stats.items_not_synced}
                        sub="items missing from the latest transaction run"
                        icon={PackageX}
                        tone={
                            stats.items_not_synced === 0 ? 'success' : 'warning'
                        }
                    />
                    <StatCard
                        label="Failed chunks"
                        value={stats.failed_chunks}
                        sub="retryable"
                        icon={AlertTriangle}
                        tone={stats.failed_chunks === 0 ? 'success' : 'danger'}
                    />
                    <StatCard
                        label="Last transaction sync"
                        value={formatRelative(
                            stats.last_transaction_run?.started_at ?? null,
                        )}
                        sub={
                            stats.last_transaction_run
                                ? `${stats.last_transaction_run.items_synced.toLocaleString()} records`
                                : 'never run'
                        }
                        icon={
                            stats.last_transaction_run?.status === 'failed'
                                ? XCircle
                                : stats.last_transaction_run?.status ===
                                    'running'
                                  ? Clock
                                  : CheckCircle2
                        }
                        tone={
                            stats.last_transaction_run?.status === 'completed'
                                ? 'success'
                                : stats.last_transaction_run?.status ===
                                    'failed'
                                  ? 'danger'
                                  : stats.last_transaction_run?.status ===
                                      'partial'
                                    ? 'warning'
                                    : 'neutral'
                        }
                    />
                </div>

                {(stats.last_transaction_run || stats.last_purchase_order_run) && (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {(
                            [
                                ['Transactions', stats.last_transaction_run],
                                ['Purchase orders', stats.last_purchase_order_run],
                            ] as const
                        ).map(([label, run]) => (
                            <div
                                key={label}
                                className="flex items-center justify-between rounded-[14px] border border-black/6 bg-white px-4 py-3 dark:border-white/6 dark:bg-zinc-900"
                            >
                                <div className="flex flex-col">
                                    <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                        {label}
                                    </span>
                                    <span className="mt-1 text-[12px] text-gray-600 dark:text-gray-300">
                                        {run
                                            ? `${run.items_synced.toLocaleString()} records · ${formatRelative(run.started_at)}`
                                            : 'No runs yet'}
                                    </span>
                                </div>
                                <StatusPill status={run?.status ?? null} />
                            </div>
                        ))}
                    </div>
                )}

                <div>
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                            Recent runs
                        </h2>
                        <Select
                            value={typeFilter || 'all'}
                            onValueChange={handleTypeChange}
                        >
                            <SelectTrigger className="h-9 w-[180px] font-mono text-[11px]">
                                <SelectValue placeholder="All types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All types</SelectItem>
                                <SelectItem value="transaction_history">
                                    Transactions
                                </SelectItem>
                                <SelectItem value="purchase_order">
                                    Purchase orders
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={runColumns}
                            data={runs.data || []}
                            initialSorting={initialSorting}
                            meta={{ ...omit(runs, ['data']) }}
                            onFetch={(params) => {
                                navigate({
                                    sort: params?.sort,
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query?.perPage ??
                                        runs.per_page,
                                });
                            }}
                        />
                    </div>
                </div>

                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                            Item coverage (latest run)
                        </h2>
                        <span className="font-mono text-[10px] text-gray-300 dark:text-gray-600">
                            {coverage.length} active item
                            {coverage.length === 1 ? '' : 's'}
                        </span>
                    </div>
                    <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable columns={coverageColumns} data={coverage} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
