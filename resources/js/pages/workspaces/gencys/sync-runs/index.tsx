import PageHeader from '@/components/common/PageHeader';
import {
    BatchStatusPill,
    ExecutionId,
    ProgressBar,
    RunActions,
    RunStatusPill,
    SyncBatchRun,
    describeBatchWindows,
    formatDuration,
    formatRelative,
    isBatchActive,
    summariseTypes,
} from '@/components/gencys/sync-batch-ui';
import Pagination from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';
import { ChevronRight, ExternalLink, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';

/** A batch row on this page: the batch, plus this workspace's slice of it. */
interface BatchRow {
    id: number;
    sync_types: string[];
    sync_labels: string[];
    status: string;
    source: string;
    parameters: Record<string, unknown> | null;
    total_runs: number;
    succeeded_runs: number;
    failed_runs: number;
    cancelled_runs: number;
    progress: number;
    queued_at: string | null;
    started_at: string | null;
    finished_at: string | null;
    /** This workspace's run tallies, keyed by status. */
    counts: Record<string, number>;
    run_total: number;
    /** Only present once the batch has been expanded. */
    runs: SyncBatchRun[] | null;
    runs_truncated: boolean;
}

interface Props {
    workspace: Workspace;
    batches: PaginatedData<BatchRow>;
    syncTypes: { value: string; label: string }[];
    inlineRunLimit: number;
    n8nApiConfigured: boolean;
    /** True while a batch holds the ERP — retries wait until it's clear. */
    queueBusy: boolean;
    query?: {
        syncType?: string | null;
        status?: string | null;
        expanded?: number[];
        page?: number | string;
        perPage?: number | string;
    };
}

const RUN_STATUSES = [
    { value: 'queued', label: 'Queued' },
    { value: 'pending', label: 'In flight' },
    { value: 'success', label: 'Success' },
    { value: 'failed', label: 'Failed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const POLL_MS = 8000;

export default function GencysSyncRuns({
    workspace,
    batches,
    syncTypes,
    inlineRunLimit,
    n8nApiConfigured,
    queueBusy,
    query,
}: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/gencys/sync-runs`;

    const syncTypeFilter = query?.syncType ?? '';
    const statusFilter = query?.status ?? '';
    const expanded = query?.expanded ?? [];

    // Which batch we're waiting on runs for, so the row can show a spinner
    // rather than sitting inert during the round-trip.
    const [loadingId, setLoadingId] = useState<number | null>(null);

    const navigate = (
        overrides: Record<string, unknown> = {},
        options: Record<string, unknown> = {},
    ) => {
        router.get(
            indexUrl,
            {
                sync_type: syncTypeFilter || undefined,
                status: statusFilter || undefined,
                expanded,
                page: batches.current_page,
                per_page: batches.per_page,
                ...overrides,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                ...options,
            },
        );
    };

    // A batch's runs are fetched when it opens, not shipped with the page — one
    // batch can hold hundreds, and most rows are never opened.
    const toggle = (batch: BatchRow) => {
        const isOpen = expanded.includes(batch.id);
        const next = isOpen
            ? expanded.filter((id) => id !== batch.id)
            : [...expanded, batch.id];

        if (!isOpen && batch.runs === null) {
            setLoadingId(batch.id);
        }

        navigate(
            { expanded: next },
            {
                only: ['batches', 'query'],
                onFinish: () => setLoadingId(null),
            },
        );
    };

    // Keep the page live while anything can still change on its own.
    const hasActive = batches.data.some((b) => isBatchActive(b.status));

    useEffect(() => {
        if (!hasActive) return;

        const timer = setInterval(
            () => router.reload({ only: ['batches'] }),
            POLL_MS,
        );

        return () => clearInterval(timer);
    }, [hasActive]);

    return (
        <AppLayout>
            <Head title="Sync Runs" />

            <div className="px-6 py-6">
                <PageHeader
                    title="Sync Runs"
                    description="Every ERP sync run for this workspace, under the batch that sent it. Open a batch to see its runs."
                    stackActionsOnMobile
                >
                    <Select
                        value={syncTypeFilter || 'all'}
                        onValueChange={(v) =>
                            navigate({
                                sync_type: v === 'all' ? undefined : v,
                                page: 1,
                            })
                        }
                    >
                        <SelectTrigger className="h-9 w-[180px] font-mono! text-[11px]!">
                            <SelectValue placeholder="All syncs" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All syncs</SelectItem>
                            {syncTypes.map((t) => (
                                <SelectItem key={t.value} value={t.value}>
                                    {t.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={statusFilter || 'all'}
                        onValueChange={(v) =>
                            navigate({
                                status: v === 'all' ? undefined : v,
                                page: 1,
                            })
                        }
                    >
                        <SelectTrigger className="h-9 w-[170px] font-mono! text-[11px]!">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {RUN_STATUSES.map((s) => (
                                <SelectItem key={s.value} value={s.value}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </PageHeader>

                {batches.data.length === 0 ? (
                    <div className="rounded-[14px] border border-dashed border-black/8 bg-stone-50/60 px-6 py-14 text-center dark:border-white/8 dark:bg-zinc-900/40">
                        <p className="text-[13px] text-gray-500 dark:text-gray-400">
                            No sync runs match these filters.
                        </p>
                        <p className="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                            Batches appear here once a scheduled or manual ERP
                            sync has run for this workspace.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {batches.data.map((batch) => (
                            <BatchAccordion
                                key={batch.id}
                                batch={batch}
                                workspaceSlug={workspace.slug}
                                open={expanded.includes(batch.id)}
                                loading={loadingId === batch.id}
                                inlineRunLimit={inlineRunLimit}
                                n8nApiConfigured={n8nApiConfigured}
                                queueBusy={queueBusy}
                                onToggle={() => toggle(batch)}
                            />
                        ))}
                    </div>
                )}

                {batches.last_page > 1 && (
                    <div className="mt-5">
                        <Pagination
                            currentPage={batches.current_page}
                            totalPages={batches.last_page}
                            onPageChange={(page) => navigate({ page })}
                        />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function BatchAccordion({
    batch,
    workspaceSlug,
    open,
    loading,
    inlineRunLimit,
    n8nApiConfigured,
    queueBusy,
    onToggle,
}: {
    batch: BatchRow;
    workspaceSlug: string;
    open: boolean;
    loading: boolean;
    inlineRunLimit: number;
    n8nApiConfigured: boolean;
    /** True while a batch holds the ERP — retries wait until it's clear. */
    queueBusy: boolean;
    onToggle: () => void;
}) {
    const ok = batch.counts.success ?? 0;
    const failed = batch.counts.failed ?? 0;
    const cancelled = batch.counts.cancelled ?? 0;
    const inFlight = batch.counts.pending ?? 0;
    const queued = batch.counts.queued ?? 0;

    // This page is about *this* workspace's runs, and a scheduled batch spans
    // every workspace — so the bar is drawn from the same scoped tallies as the
    // numbers beside it. Using the batch's own totals here would show a bar that
    // disagrees with the counts right next to it.
    const done = ok + failed + cancelled;
    const scopedProgress = {
        total_runs: batch.run_total,
        succeeded_runs: ok,
        failed_runs: failed,
        cancelled_runs: cancelled,
        progress:
            batch.run_total > 0
                ? Math.round((done / batch.run_total) * 100)
                : 0,
    };

    return (
        <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={open}
                className="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-stone-50/70 dark:hover:bg-zinc-800/40"
            >
                {loading ? (
                    <Loader2 className="h-3.5 w-3.5 shrink-0 animate-spin text-gray-400" />
                ) : (
                    <ChevronRight
                        className={clsx(
                            'h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform',
                            open && 'rotate-90',
                        )}
                    />
                )}

                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">
                            #{batch.id}
                        </span>
                        <span className="text-[12px] text-gray-700 dark:text-gray-200">
                            {summariseTypes(batch, 3)}
                        </span>
                        <BatchStatusPill status={batch.status} />
                        {batch.source === 'manual' && (
                            <span className="font-mono text-[9px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                manual
                            </span>
                        )}
                    </div>
                    <span className="truncate font-mono text-[10px] text-gray-400 dark:text-gray-500">
                        {describeBatchWindows(batch)
                            .map((w) => `${w.label} ${w.window}`)
                            .join(' · ')}
                    </span>
                </div>

                <div className="hidden shrink-0 items-center gap-3 font-mono text-[11px] sm:flex">
                    <span className="text-gray-500 dark:text-gray-400">
                        {batch.run_total} run
                        {batch.run_total === 1 ? '' : 's'}
                    </span>
                    {ok > 0 && (
                        <span className="text-emerald-600 dark:text-emerald-400">
                            {ok} ok
                        </span>
                    )}
                    {failed > 0 && (
                        <span className="text-red-500 dark:text-red-400">
                            {failed} failed
                        </span>
                    )}
                    {inFlight > 0 && (
                        <span className="text-blue-600 dark:text-blue-400">
                            {inFlight} in flight
                        </span>
                    )}
                    {queued > 0 && (
                        <span className="text-gray-400 dark:text-gray-500">
                            {queued} queued
                        </span>
                    )}
                </div>

                <div className="hidden w-32 shrink-0 sm:block">
                    <ProgressBar batch={scopedProgress} />
                </div>

                <span className="hidden w-20 shrink-0 text-right font-mono text-[10px] text-gray-400 lg:block dark:text-gray-500">
                    {formatRelative(batch.started_at ?? batch.queued_at)}
                </span>
            </button>

            {open && (
                <div className="border-t border-black/6 dark:border-white/6">
                    <RunTable
                        runs={batch.runs}
                        showSyncColumn={batch.sync_types.length > 1}
                        workspaceSlug={workspaceSlug}
                        n8nApiConfigured={n8nApiConfigured}
                        queueBusy={queueBusy}
                    />

                    {batch.runs_truncated && (
                        <div className="border-t border-black/6 px-4 py-2.5 text-center dark:border-white/6">
                            <a
                                href={`/workspaces/${workspaceSlug}/gencys/sync-batches/${batch.id}`}
                                className="inline-flex items-center gap-1 font-mono text-[11px] text-gray-500 hover:underline dark:text-gray-400"
                            >
                                Showing the first {inlineRunLimit} of{' '}
                                {batch.run_total} — open batch #{batch.id}
                                <ExternalLink className="h-3 w-3" />
                            </a>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function RunTable({
    runs,
    showSyncColumn,
    workspaceSlug,
    n8nApiConfigured,
    queueBusy,
}: {
    runs: SyncBatchRun[] | null;
    showSyncColumn: boolean;
    workspaceSlug: string;
    n8nApiConfigured: boolean;
    queueBusy: boolean;
}) {
    if (runs === null) {
        return (
            <p className="px-4 py-6 text-center font-mono text-[11px] text-gray-400 dark:text-gray-500">
                Loading runs…
            </p>
        );
    }

    if (runs.length === 0) {
        return (
            <p className="px-4 py-6 text-center font-mono text-[11px] text-gray-400 dark:text-gray-500">
                No runs in this batch match the current filters.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[760px]">
                <thead>
                    <tr className="border-b border-black/6 dark:border-white/6">
                        <Th>Run</Th>
                        <Th>n8n</Th>
                        {showSyncColumn && <Th>Sync</Th>}
                        <Th>Subject</Th>
                        <Th>Status</Th>
                        <Th>Rows</Th>
                        <Th>Sent</Th>
                        <Th>Took</Th>
                        <Th>Note</Th>
                        <Th> </Th>
                    </tr>
                </thead>
                <tbody>
                    {runs.map((run) => (
                        <tr
                            key={run.id}
                            className="border-b border-black/4 last:border-0 dark:border-white/4"
                        >
                            <Td className="font-mono text-gray-400 dark:text-gray-500">
                                #{run.id}
                            </Td>
                            <Td>
                                <ExecutionId id={run.n8n_execution_id} />
                            </Td>
                            {showSyncColumn && (
                                <Td className="text-gray-500 dark:text-gray-400">
                                    {run.sync_label}
                                </Td>
                            )}
                            <Td className="font-mono font-medium text-gray-700 dark:text-gray-200">
                                {run.subject ?? '—'}
                            </Td>
                            <Td>
                                <div className="flex items-center gap-2">
                                    <RunStatusPill status={run.status} />
                                    {run.attempt > 0 && (
                                        <span
                                            className="font-mono text-[10px] text-amber-600 dark:text-amber-400"
                                            title="Re-sent on its own after a timeout or a failed handshake"
                                        >
                                            retry {run.attempt}
                                        </span>
                                    )}
                                </div>
                            </Td>
                            <Td className="font-mono text-gray-500 dark:text-gray-400">
                                {run.rows_received == null ? (
                                    '—'
                                ) : (
                                    <span
                                        title={`${(run.rows_saved ?? 0).toLocaleString()} saved`}
                                    >
                                        {run.rows_received.toLocaleString()}
                                    </span>
                                )}
                            </Td>
                            <Td className="font-mono text-gray-400 dark:text-gray-500">
                                {formatRelative(run.sent_at)}
                            </Td>
                            <Td className="font-mono text-gray-400 dark:text-gray-500">
                                {run.status === 'pending'
                                    ? `times out ${formatRelative(run.timeout_at)}`
                                    : formatDuration(run.duration_seconds)}
                            </Td>
                            <Td>
                                <span
                                    className="line-clamp-2 max-w-[280px] text-gray-400 dark:text-gray-500"
                                    title={run.message ?? undefined}
                                >
                                    {run.message ?? '—'}
                                </span>
                            </Td>
                            <Td>
                                <RunActions
                                    run={run}
                                    workspaceSlug={workspaceSlug}
                                    n8nApiConfigured={n8nApiConfigured}
                                    queueBusy={queueBusy}
                                />
                            </Td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Th({ children }: { children: React.ReactNode }) {
    return (
        <th className="px-4 py-2 text-left font-mono text-[9px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
            {children}
        </th>
    );
}

function Td({
    children,
    className,
}: {
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <td className={clsx('px-4 py-2 text-[11px]', className)}>{children}</td>
    );
}
