import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
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
    AlertTriangle,
    Boxes,
    CheckCircle2,
    ChevronRight,
    Clock,
    PackageX,
    RotateCcw,
    Search,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

type RunType = 'transaction_history' | 'purchase_order';

interface ItemRow {
    id: number;
    sku: string;
    product_name: string | null;
    remaining_qty: number | null;
    last_transaction_synced_at: string | null;
    last_purchase_order_synced_at: string | null;
    tx_latest_status: string | null;
    po_latest_status: string | null;
    retryable_chunk_id: number | null;
    is_stale: boolean;
}

interface RecentRun {
    id: number;
    type: RunType;
    status: string;
    total_items: number;
    items_synced: number;
    chunks_total: number;
    chunks_failed: number;
    chunks_pending: number;
    started_at: string | null;
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
    items: PaginatedData<ItemRow>;
    recentRuns: RecentRun[];
    stats: {
        active_items: number;
        stale_items: number;
        failed_chunks: number;
        last_transaction_run: RunSummary | null;
        last_purchase_order_run: RunSummary | null;
        stale_after_days: number;
    };
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string; attention?: string };
    };
}

const TYPE_LABELS: Record<RunType, string> = {
    transaction_history: 'Transactions',
    purchase_order: 'Purchase orders',
};

const STATUS_STYLES: Record<string, { cls: string; dot: string; label: string }> = {
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

const STATUS_BUCKET: Record<string, string> = {
    completed: 'ok',
    confirmed: 'ok',
    sent: 'ok',
    running: 'running',
    pending: 'running',
    partial: 'partial',
    failed: 'failed',
};

function StatusPill({ status, label }: { status: string | null; label?: string }) {
    if (!status) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] tracking-wide text-stone-400 uppercase dark:bg-zinc-800 dark:text-zinc-500">
                <span className="h-1 w-1 rounded-full bg-stone-300" />
                Not in last run
            </span>
        );
    }
    const c = STATUS_STYLES[STATUS_BUCKET[status] ?? 'failed'];
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
    if (!ts) return 'Never';
    const d = new Date(ts);
    const diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60) return `${Math.round(diff)}s ago`;
    if (diff < 3600) return `${Math.round(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.round(diff / 3600)}h ago`;
    return `${Math.round(diff / 86400)}d ago`;
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
    icon: typeof Boxes;
    tone?: 'neutral' | 'success' | 'warning' | 'danger';
}) {
    const tones = {
        neutral: 'text-gray-700 dark:text-gray-200',
        success: 'text-emerald-600 dark:text-emerald-400',
        warning: 'text-amber-600 dark:text-amber-400',
        danger: 'text-red-600 dark:text-red-400',
    };
    const iconBg = {
        neutral: 'bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400',
        success: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
        warning: 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
        danger: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
    };
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-5 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10">
            <div className="flex items-start justify-between">
                <div>
                    <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        {label}
                    </p>
                    <p className={clsx('mt-2 text-2xl font-semibold tracking-tight', tones[tone])}>
                        {value}
                    </p>
                    {sub && (
                        <p className="mt-1 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            {sub}
                        </p>
                    )}
                </div>
                <div className={clsx('flex h-9 w-9 items-center justify-center rounded-xl', iconBg[tone])}>
                    <Icon className="h-4 w-4" />
                </div>
            </div>
        </div>
    );
}

export default function SyncMonitoring({
    workspace,
    items,
    recentRuns,
    stats,
    query,
}: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/inventory/sync-monitoring`;
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);
    const [search, setSearch] = useState(query?.filter?.search ?? '');
    const [attention, setAttention] = useState(query?.filter?.attention ?? '');
    const [retrying, setRetrying] = useState<number | null>(null);

    const navigate = (overrides: Record<string, unknown> = {}) => {
        router.get(
            indexUrl,
            {
                sort: query?.sort,
                'filter[search]': search || undefined,
                'filter[attention]': attention || undefined,
                page: 1,
                per_page: query?.perPage ?? items.per_page,
                ...overrides,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    // Debounce the SKU search so typing doesn't fire a request per keystroke.
    useEffect(() => {
        const current = query?.filter?.search ?? '';
        if (search === current) return;
        const t = setTimeout(() => navigate({ 'filter[search]': search || undefined, page: 1 }), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const handleAttentionChange = (value: string) => {
        const next = value === 'attention' ? '1' : '';
        setAttention(next);
        navigate({ 'filter[attention]': next || undefined, page: 1 });
    };

    const retryChunk = (chunkId: number) => {
        setRetrying(chunkId);
        router.post(
            `${indexUrl}/chunks/${chunkId}/retry`,
            {},
            { preserveScroll: true, preserveState: true, onFinish: () => setRetrying(null) },
        );
    };

    const columns: ColumnDef<ItemRow>[] = [
        {
            accessorKey: 'sku',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Item" />,
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    <span className="font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">
                        {row.original.sku}
                    </span>
                    {row.original.product_name && (
                        <span className="text-[11px] text-gray-400 dark:text-gray-500">
                            {row.original.product_name}
                        </span>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'remaining_qty',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Stock" />,
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                    {row.original.remaining_qty?.toLocaleString() ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'last_transaction_synced_at',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Last transaction sync" />,
            cell: ({ row }) => (
                <div className="flex flex-col gap-1">
                    <span
                        className={clsx(
                            'text-[12px] font-medium',
                            row.original.is_stale
                                ? 'text-red-500 dark:text-red-400'
                                : 'text-gray-700 dark:text-gray-300',
                        )}
                    >
                        {formatRelative(row.original.last_transaction_synced_at)}
                    </span>
                    <StatusPill status={row.original.tx_latest_status} />
                </div>
            ),
        },
        {
            accessorKey: 'last_purchase_order_synced_at',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Last PO sync" />,
            cell: ({ row }) => (
                <div className="flex flex-col gap-1">
                    <span className="text-[12px] text-gray-700 dark:text-gray-300">
                        {formatRelative(row.original.last_purchase_order_synced_at)}
                    </span>
                    <StatusPill status={row.original.po_latest_status} />
                </div>
            ),
        },
        {
            id: 'actions',
            header: ({ column }) => <SortableHeader column={column} title="" enabled={false} />,
            cell: ({ row }) => (
                <div className="flex items-center justify-end gap-2">
                    {row.original.retryable_chunk_id && (
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={retrying === row.original.retryable_chunk_id}
                            onClick={(e) => {
                                e.stopPropagation();
                                retryChunk(row.original.retryable_chunk_id!);
                            }}
                        >
                            <RotateCcw className="h-3.5 w-3.5" />
                            {retrying === row.original.retryable_chunk_id ? 'Retrying…' : 'Retry'}
                        </Button>
                    )}
                    <ChevronRight className="h-4 w-4 text-gray-300 dark:text-gray-600" />
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
                    description="Track the ERP sync per inventory item — see when each last synced, spot gaps, and retry anything that failed."
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Active items" value={stats.active_items} icon={Boxes} />
                    <StatCard
                        label="Stale items"
                        value={stats.stale_items}
                        sub={`no transaction sync in ${stats.stale_after_days}d`}
                        icon={PackageX}
                        tone={stats.stale_items === 0 ? 'success' : 'warning'}
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
                        value={formatRelative(stats.last_transaction_run?.started_at ?? null)}
                        sub={
                            stats.last_transaction_run
                                ? `${stats.last_transaction_run.items_synced.toLocaleString()} records`
                                : 'never run'
                        }
                        icon={
                            stats.last_transaction_run?.status === 'failed'
                                ? XCircle
                                : stats.last_transaction_run?.status === 'running'
                                  ? Clock
                                  : CheckCircle2
                        }
                        tone={
                            stats.last_transaction_run?.status === 'completed'
                                ? 'success'
                                : stats.last_transaction_run?.status === 'failed'
                                  ? 'danger'
                                  : stats.last_transaction_run?.status === 'partial'
                                    ? 'warning'
                                    : 'neutral'
                        }
                    />
                </div>

                <div>
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                            Item coverage
                        </h2>
                        <div className="flex items-center gap-2">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search SKU…"
                                    className="h-9 w-[200px] pl-8 font-mono text-[11px]"
                                />
                            </div>
                            <Select
                                value={attention ? 'attention' : 'all'}
                                onValueChange={handleAttentionChange}
                            >
                                <SelectTrigger className="h-9 w-[170px] font-mono text-[11px]">
                                    <SelectValue placeholder="All items" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All items</SelectItem>
                                    <SelectItem value="attention">Needs attention</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={columns}
                            data={items.data || []}
                            initialSorting={initialSorting}
                            meta={{ ...omit(items, ['data']) }}
                            onRowClick={(row) => router.get(`${indexUrl}/items/${row.id}`)}
                            onFetch={(params) => {
                                navigate({
                                    sort: params?.sort,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page ?? query?.perPage ?? items.per_page,
                                });
                            }}
                        />
                    </div>
                </div>

                <div>
                    <h2 className="mb-3 font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                        Recent runs
                    </h2>
                    <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        {recentRuns.length === 0 ? (
                            <p className="px-4 py-6 text-center font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                No sync runs recorded yet.
                            </p>
                        ) : (
                            recentRuns.map((run) => (
                                <div
                                    key={run.id}
                                    className="flex flex-wrap items-center justify-between gap-3 border-b border-black/5 px-4 py-3 last:border-b-0 dark:border-white/5"
                                >
                                    <div className="flex items-center gap-3">
                                        <StatusPill status={run.status} />
                                        <span className="text-[12px] text-gray-700 dark:text-gray-300">
                                            {TYPE_LABELS[run.type]}
                                        </span>
                                        <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                            {formatRelative(run.started_at)}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-3 font-mono text-[10px]">
                                        <span className="text-gray-500 dark:text-gray-400">
                                            {run.chunks_total - run.chunks_failed - run.chunks_pending}/
                                            {run.chunks_total} ok
                                            {run.chunks_failed > 0 && (
                                                <span className="text-red-500 dark:text-red-400">
                                                    {' '}
                                                    · {run.chunks_failed} failed
                                                </span>
                                            )}
                                        </span>
                                        {run.chunks_failed > 0 && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                disabled={retrying === -run.id}
                                                onClick={() => {
                                                    setRetrying(-run.id);
                                                    router.post(
                                                        `${indexUrl}/runs/${run.id}/retry`,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                            preserveState: true,
                                                            onFinish: () => setRetrying(null),
                                                        },
                                                    );
                                                }}
                                            >
                                                <RotateCcw className="h-3.5 w-3.5" />
                                                {retrying === -run.id
                                                    ? 'Retrying…'
                                                    : `Retry ${run.chunks_failed}`}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
