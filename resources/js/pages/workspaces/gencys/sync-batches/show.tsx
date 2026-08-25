import PageHeader from '@/components/common/PageHeader';
import {
    BatchStatusPill,
    ExecutionId,
    ProgressBar,
    RunActions,
    RunStatusPill,
    SyncBatch,
    SyncBatchRun,
    describeBatchWindows,
    formatDuration,
    formatRelative,
    isBatchActive,
    summariseTypes,
} from '@/components/gencys/sync-batch-ui';
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
import { omit } from 'lodash';
import { ArrowLeft, Ban } from 'lucide-react';
import { useEffect, useMemo } from 'react';

interface Props {
    workspace: Workspace;
    batch: SyncBatch;
    runs: PaginatedData<SyncBatchRun>;
    n8nApiConfigured: boolean;
    queueBusy: boolean;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        filter?: { status?: string; sync_type?: string };
    };
}

const POLL_MS = 8000;

export default function SyncBatchShow({
    workspace,
    batch,
    runs,
    n8nApiConfigured,
    queueBusy,
    query,
}: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/gencys/sync-batches`;
    const showUrl = `${indexUrl}/${batch.id}`;

    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const statusFilter = query?.filter?.status ?? '';
    const typeFilter = query?.filter?.sync_type ?? '';
    const isMultiType = batch.sync_types.length > 1;

    const navigate = (overrides: Record<string, unknown> = {}) => {
        router.get(
            showUrl,
            {
                sort: query?.sort,
                'filter[status]': statusFilter || undefined,
                'filter[sync_type]': typeFilter || undefined,
                page: runs.current_page,
                per_page: runs.per_page,
                ...overrides,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    // Keep the view live while the batch can still move on its own.
    useEffect(() => {
        if (!isBatchActive(batch.status)) return;

        const timer = setInterval(
            () => router.reload({ only: ['batch', 'runs'] }),
            POLL_MS,
        );

        return () => clearInterval(timer);
    }, [batch.status]);

    const columns: ColumnDef<SyncBatchRun>[] = [
        {
            accessorKey: 'id',
            header: ({ column }) => (
                <SortableHeader column={column} title="Run" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    #{row.original.id}
                </span>
            ),
        },
        ...(isMultiType
            ? [
                  {
                      accessorKey: 'sync_type',
                      header: ({ column }) => (
                          <SortableHeader column={column} title="Sync" />
                      ),
                      cell: ({ row }) => (
                          <span className="text-[11px] text-gray-500 dark:text-gray-400">
                              {row.original.sync_label}
                          </span>
                      ),
                  } as ColumnDef<SyncBatchRun>,
              ]
            : []),
        {
            id: 'n8n',
            header: () => (
                <span className="font-mono text-[10px] tracking-wider uppercase">
                    n8n
                </span>
            ),
            cell: ({ row }) => (
                <ExecutionId id={row.original.n8n_execution_id} />
            ),
        },
        {
            id: 'subject',
            header: () => (
                <span className="font-mono text-[10px] tracking-wider uppercase">
                    Subject
                </span>
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">
                    {row.original.subject ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <RunStatusPill status={row.original.status} />
                    {row.original.attempt > 0 && (
                        <span
                            className="font-mono text-[10px] text-amber-600 dark:text-amber-400"
                            title="Re-sent on its own after a timeout or a failed handshake"
                        >
                            retry {row.original.attempt}
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: 'rows',
            header: () => (
                <span className="font-mono text-[10px] tracking-wider uppercase">
                    Rows
                </span>
            ),
            cell: ({ row }) =>
                row.original.rows_received == null ? (
                    <span className="font-mono text-[11px] text-gray-300 dark:text-gray-600">
                        —
                    </span>
                ) : (
                    <span
                        className="font-mono text-[11px] text-gray-500 dark:text-gray-400"
                        title={`${(row.original.rows_saved ?? 0).toLocaleString()} saved`}
                    >
                        {row.original.rows_received.toLocaleString()}
                    </span>
                ),
        },
        {
            accessorKey: 'sent_at',
            header: ({ column }) => (
                <SortableHeader column={column} title="Sent" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                    {formatRelative(row.original.sent_at)}
                </span>
            ),
        },
        {
            id: 'duration',
            header: () => (
                <span className="font-mono text-[10px] tracking-wider uppercase">
                    Took
                </span>
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                    {row.original.status === 'pending'
                        ? `times out ${formatRelative(row.original.timeout_at)}`
                        : formatDuration(row.original.duration_seconds)}
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => null,
            cell: ({ row }) => (
                <RunActions
                    run={row.original}
                    workspaceSlug={workspace.slug}
                    n8nApiConfigured={n8nApiConfigured}
                    queueBusy={queueBusy}
                />
            ),
        },
        {
            accessorKey: 'message',
            header: () => (
                <span className="font-mono text-[10px] tracking-wider uppercase">
                    Note
                </span>
            ),
            cell: ({ row }) => (
                <span
                    className="line-clamp-2 max-w-[320px] text-[11px] text-gray-400 dark:text-gray-500"
                    title={row.original.message ?? undefined}
                >
                    {row.original.message ?? '—'}
                </span>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title={`Sync Batch #${batch.id}`} />

            <div className="px-6 py-6">
                <PageHeader
                    title={`Batch #${batch.id} · ${summariseTypes(batch, 3)}`}
                    description={`${Math.round(batch.timeout_seconds / 60)} min timeout · ${batch.max_retries} retries per run`}
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-9 text-[12px]"
                        onClick={() => router.visit(indexUrl)}
                    >
                        <ArrowLeft className="mr-1 h-3.5 w-3.5" />
                        All batches
                    </Button>
                    {isBatchActive(batch.status) && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-9 text-[12px] text-red-500 hover:text-red-600"
                            onClick={() =>
                                router.post(
                                    `${showUrl}/cancel`,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Ban className="mr-1 h-3.5 w-3.5" />
                            Cancel batch
                        </Button>
                    )}
                </PageHeader>

                <div className="mb-6 rounded-[14px] border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
                    <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                        <BatchStatusPill status={batch.status} />
                        <Detail
                            label="Raised by"
                            value={
                                batch.source === 'manual'
                                    ? (batch.created_by ?? 'manual')
                                    : 'schedule'
                            }
                        />
                        <Detail
                            label="Queued"
                            value={formatRelative(batch.queued_at)}
                        />
                        <Detail
                            label="Started"
                            value={formatRelative(batch.started_at)}
                        />
                        <Detail
                            label="Finished"
                            value={formatRelative(batch.finished_at)}
                        />
                        <Detail
                            label="Scope"
                            value={
                                batch.workspace_id
                                    ? 'This workspace'
                                    : 'All workspaces'
                            }
                        />
                    </div>

                    <div className="mt-4 flex flex-col gap-1">
                        {describeBatchWindows(batch).map((w) => (
                            <div
                                key={w.type}
                                className="flex items-center gap-2 font-mono text-[11px]"
                            >
                                <span className="text-gray-600 dark:text-gray-300">
                                    {w.label}
                                </span>
                                <span className="text-gray-400 dark:text-gray-500">
                                    {w.window}
                                </span>
                                <span className="text-gray-300 dark:text-gray-600">
                                    {
                                        runs.data.filter(
                                            (r) => r.sync_type === w.type,
                                        ).length
                                    }{' '}
                                    on this page
                                </span>
                            </div>
                        ))}
                    </div>

                    <ProgressBar batch={batch} className="mt-4" />

                    {batch.message && (
                        <p className="mt-3 text-[11px] text-gray-400 dark:text-gray-500">
                            {batch.message}
                        </p>
                    )}
                </div>

                <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                        Runs in this workspace
                    </h2>
                    <div className="flex items-center gap-2">
                        {isMultiType && (
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
                                <SelectTrigger className="h-9 w-[180px] font-mono! text-[11px]!">
                                    <SelectValue placeholder="All syncs" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All syncs
                                    </SelectItem>
                                    {batch.sync_types.map((type, i) => (
                                        <SelectItem key={type} value={type}>
                                            {batch.sync_labels[i] ?? type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
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
                            <SelectTrigger className="h-9 w-[180px] font-mono! text-[11px]!">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                <SelectItem value="queued">Queued</SelectItem>
                                <SelectItem value="pending">
                                    In flight
                                </SelectItem>
                                <SelectItem value="success">Success</SelectItem>
                                <SelectItem value="failed">Failed</SelectItem>
                                <SelectItem value="cancelled">
                                    Cancelled
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={runs.data || []}
                        meta={{ ...omit(runs, ['data']) }}
                        initialSorting={initialSorting}
                        onFetch={(params) =>
                            navigate({
                                page: params?.page ?? 1,
                                per_page: params?.per_page ?? runs.per_page,
                                sort: params?.sort ?? query?.sort,
                            })
                        }
                    />
                </div>
            </div>
        </AppLayout>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-col">
            <span className="font-mono text-[9px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {label}
            </span>
            <span className="font-mono text-[11px] text-gray-600 dark:text-gray-300">
                {value}
            </span>
        </div>
    );
}
