import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from 'clsx';
import { omit } from 'lodash';
import { ArrowLeft, RotateCcw } from 'lucide-react';
import { useState } from 'react';

type RunType = 'transaction_history' | 'purchase_order';

interface HistoryRow {
    id: number;
    run_id: number;
    type: RunType;
    trigger: string | null;
    status: string;
    attempts: number;
    item_count: number;
    records_synced: number | null;
    webhook_status: number | null;
    error_message: string | null;
    started_at: string | null;
    dispatched_at: string | null;
    confirmed_at: string | null;
}

interface Item {
    id: number;
    sku: string;
    product_name: string | null;
    remaining_qty: number | null;
    is_active: boolean;
    last_transaction_synced_at: string | null;
    last_purchase_order_synced_at: string | null;
}

interface Props {
    workspace: Workspace;
    item: Item;
    history: PaginatedData<HistoryRow>;
    query?: { perPage?: number | string; page?: number | string };
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
        label: 'Pending',
    },
    failed: {
        cls: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
        dot: 'bg-red-400',
        label: 'Failed',
    },
};

const STATUS_BUCKET: Record<string, string> = {
    confirmed: 'ok',
    sent: 'ok',
    pending: 'running',
    failed: 'failed',
};

const STATUS_LABELS: Record<string, string> = {
    confirmed: 'Confirmed',
    sent: 'Sent',
    pending: 'Pending',
    failed: 'Failed',
};

function StatusPill({ status }: { status: string }) {
    const c = STATUS_STYLES[STATUS_BUCKET[status] ?? 'failed'];
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 font-mono text-[10px] tracking-wide uppercase',
                c.cls,
            )}
        >
            <span className={clsx('h-1 w-1 rounded-full', c.dot)} />
            {STATUS_LABELS[status] ?? status}
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

function SyncTile({
    label,
    ts,
}: {
    label: string;
    ts: string | null;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {label}
            </p>
            <p className="mt-2 text-lg font-semibold tracking-tight text-gray-700 dark:text-gray-200">
                {formatRelative(ts)}
            </p>
            <p className="mt-1 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                {ts ? new Date(ts).toLocaleString() : 'No successful sync recorded'}
            </p>
        </div>
    );
}

export default function SyncMonitoringItem({ workspace, item, history, query }: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/inventory/sync-monitoring`;
    const detailUrl = `${indexUrl}/items/${item.id}`;
    const [retrying, setRetrying] = useState<number | null>(null);

    const retryChunk = (chunkId: number) => {
        setRetrying(chunkId);
        router.post(
            `${indexUrl}/chunks/${chunkId}/retry`,
            {},
            { preserveScroll: true, preserveState: true, onFinish: () => setRetrying(null) },
        );
    };

    const columns: ColumnDef<HistoryRow>[] = [
        {
            accessorKey: 'started_at',
            header: ({ column }) => <SortableHeader column={column} title="When" enabled={false} />,
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                        {formatRelative(row.original.dispatched_at ?? row.original.started_at)}
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
            header: ({ column }) => <SortableHeader column={column} title="Type" enabled={false} />,
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    <span className="text-[12px] text-gray-700 dark:text-gray-300">
                        {TYPE_LABELS[row.original.type]}
                    </span>
                    {row.original.trigger && (
                        <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            {row.original.trigger}
                        </span>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) => <SortableHeader column={column} title="Status" enabled={false} />,
            cell: ({ row }) => <StatusPill status={row.original.status} />,
        },
        {
            accessorKey: 'attempts',
            header: ({ column }) => <SortableHeader column={column} title="Attempts" enabled={false} />,
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                    {row.original.attempts}
                </span>
            ),
        },
        {
            accessorKey: 'records_synced',
            header: ({ column }) => <SortableHeader column={column} title="Records" enabled={false} />,
            cell: ({ row }) =>
                row.original.records_synced != null ? (
                    <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                        {row.original.records_synced.toLocaleString()}
                    </span>
                ) : (
                    <span className="text-gray-300 dark:text-gray-600">—</span>
                ),
        },
        {
            accessorKey: 'error_message',
            header: ({ column }) => <SortableHeader column={column} title="Error" enabled={false} />,
            cell: ({ row }) =>
                row.original.error_message ? (
                    <span
                        className="block max-w-xs truncate font-mono text-[11px] text-red-500 dark:text-red-400"
                        title={row.original.error_message}
                    >
                        {row.original.error_message}
                    </span>
                ) : (
                    <span className="text-gray-300 dark:text-gray-600">—</span>
                ),
        },
        {
            id: 'actions',
            header: ({ column }) => <SortableHeader column={column} title="" enabled={false} />,
            cell: ({ row }) =>
                row.original.status === 'failed' ? (
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={retrying === row.original.id}
                        onClick={() => retryChunk(row.original.id)}
                    >
                        <RotateCcw className="h-3.5 w-3.5" />
                        {retrying === row.original.id ? 'Retrying…' : 'Retry'}
                    </Button>
                ) : null,
        },
    ];

    return (
        <AppLayout>
            <Head title={`Sync — ${item.sku}`} />

            <div className="w-full space-y-6 p-4 md:p-6">
                <Link
                    href={indexUrl}
                    className="inline-flex items-center gap-1.5 font-mono text-[11px] text-gray-500 transition-colors hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <ArrowLeft className="h-3.5 w-3.5" />
                    Back to sync monitoring
                </Link>

                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="font-mono text-xl font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                                {item.sku}
                            </h1>
                            <span
                                className={clsx(
                                    'inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[10px] tracking-wide uppercase',
                                    item.is_active
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                                        : 'bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400',
                                )}
                            >
                                {item.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        {item.product_name && (
                            <p className="mt-1 text-[13px] text-gray-500 dark:text-gray-400">
                                {item.product_name}
                            </p>
                        )}
                    </div>
                    <div className="text-right">
                        <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Current stock
                        </p>
                        <p className="text-lg font-semibold text-gray-700 dark:text-gray-200">
                            {item.remaining_qty?.toLocaleString() ?? '—'}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <SyncTile
                        label="Last transaction sync"
                        ts={item.last_transaction_synced_at}
                    />
                    <SyncTile label="Last PO sync" ts={item.last_purchase_order_synced_at} />
                </div>

                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                            Sync history
                        </h2>
                        <span className="font-mono text-[10px] text-gray-300 dark:text-gray-600">
                            {history.total.toLocaleString()} sync{history.total === 1 ? '' : 's'}
                        </span>
                    </div>
                    <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={columns}
                            data={history.data || []}
                            meta={{ ...omit(history, ['data']) }}
                            onFetch={(params) => {
                                router.get(
                                    detailUrl,
                                    {
                                        page: params?.page ?? 1,
                                        per_page:
                                            params?.per_page ?? query?.perPage ?? history.per_page,
                                    },
                                    { preserveState: true, replace: true, preserveScroll: true },
                                );
                            }}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
