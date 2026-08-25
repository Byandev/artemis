import { router } from '@inertiajs/react';
import clsx from 'clsx';
import {
    Activity,
    Ban,
    CheckCircle2,
    CircleDashed,
    Clock,
    ExternalLink,
    Loader2,
    RotateCcw,
    SearchCheck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

/** A batch as the SyncBatchController presents it. */
export interface SyncBatch {
    id: number;
    sync_types: string[];
    sync_labels: string[];
    status: string;
    source: string;
    workspace_id: number | null;
    created_by: string | null;
    /** Keyed by sync type: { transaction_history: { dates: [...] }, ... }. */
    parameters: Record<string, unknown> | null;
    group_size: number | null;
    timeout_seconds: number;
    max_retries: number;
    total_runs: number;
    succeeded_runs: number;
    failed_runs: number;
    cancelled_runs: number;
    progress: number;
    message: string | null;
    queued_at: string | null;
    started_at: string | null;
    finished_at: string | null;
}

export interface SyncBatchRun {
    id: number;
    sync_type: string;
    sync_label: string;
    status: string;
    attempt: number;
    /** The n8n execution that handled this run, for tracing it in n8n. */
    n8n_execution_id: string | null;
    group_key: string | null;
    subject: string | null;
    rows_received: number | null;
    rows_saved: number | null;
    sent_at: string | null;
    timeout_at: string | null;
    finished_at: string | null;
    duration_seconds: number | null;
    message: string | null;
}

type PillConfig = {
    Icon: typeof CheckCircle2;
    cls: string;
    dot: string;
    label: string;
};

const BATCH_STATUS: Record<string, PillConfig> = {
    queued: {
        Icon: CircleDashed,
        cls: 'bg-stone-100 text-stone-600 dark:bg-zinc-800 dark:text-zinc-400',
        dot: 'bg-stone-400',
        label: 'Queued',
    },
    running: {
        Icon: Activity,
        cls: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
        dot: 'bg-blue-400 animate-pulse',
        label: 'Running',
    },
    completed: {
        Icon: CheckCircle2,
        cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        dot: 'bg-emerald-500',
        label: 'Completed',
    },
    completed_with_failures: {
        Icon: XCircle,
        cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        dot: 'bg-amber-500',
        label: 'With failures',
    },
    cancelled: {
        Icon: Ban,
        cls: 'bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-500',
        dot: 'bg-stone-300',
        label: 'Cancelled',
    },
};

const RUN_STATUS: Record<string, PillConfig> = {
    queued: {
        Icon: CircleDashed,
        cls: 'bg-stone-100 text-stone-600 dark:bg-zinc-800 dark:text-zinc-400',
        dot: 'bg-stone-400',
        label: 'Queued',
    },
    pending: {
        Icon: Clock,
        cls: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
        dot: 'bg-blue-400 animate-pulse',
        label: 'In flight',
    },
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
    cancelled: {
        Icon: Ban,
        cls: 'bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-500',
        dot: 'bg-stone-300',
        label: 'Cancelled',
    },
};

function Pill({ config }: { config: PillConfig }) {
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 font-mono text-[10px] tracking-wide uppercase',
                config.cls,
            )}
        >
            <span className={clsx('h-1 w-1 rounded-full', config.dot)} />
            {config.label}
        </span>
    );
}

export function BatchStatusPill({ status }: { status: string }) {
    return <Pill config={BATCH_STATUS[status] ?? BATCH_STATUS.queued} />;
}

export function RunStatusPill({ status }: { status: string }) {
    return <Pill config={RUN_STATUS[status] ?? RUN_STATUS.queued} />;
}

/** A batch is only worth polling while it can still change on its own. */
export function isBatchActive(status: string) {
    return status === 'queued' || status === 'running';
}

export function ProgressBar({
    batch,
    className,
}: {
    batch: Pick<
        SyncBatch,
        | 'total_runs'
        | 'succeeded_runs'
        | 'failed_runs'
        | 'cancelled_runs'
        | 'progress'
    >;
    className?: string;
}) {
    const total = Math.max(1, batch.total_runs);
    const width = (n: number) => `${(n / total) * 100}%`;

    return (
        <div className={clsx('flex flex-col gap-1', className)}>
            <div className="flex h-1.5 w-full overflow-hidden rounded-full bg-stone-100 dark:bg-zinc-800">
                <div
                    className="bg-emerald-500 transition-all"
                    style={{ width: width(batch.succeeded_runs) }}
                />
                <div
                    className="bg-red-400 transition-all"
                    style={{ width: width(batch.failed_runs) }}
                />
                <div
                    className="bg-stone-300 transition-all dark:bg-zinc-600"
                    style={{ width: width(batch.cancelled_runs) }}
                />
            </div>
            <div className="flex items-center justify-between font-mono text-[10px] text-gray-400 dark:text-gray-500">
                <span>
                    {batch.succeeded_runs +
                        batch.failed_runs +
                        batch.cancelled_runs}
                    {' / '}
                    {batch.total_runs} runs
                </span>
                <span>{batch.progress}%</span>
            </div>
        </div>
    );
}

export function formatRelative(ts: string | null) {
    if (!ts) return '—';
    const diff = (Date.now() - new Date(ts).getTime()) / 1000;
    if (diff < 0) return `in ${Math.round(-diff)}s`;
    if (diff < 60) return `${Math.round(diff)}s ago`;
    if (diff < 3600) return `${Math.round(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.round(diff / 3600)}h ago`;
    return `${Math.round(diff / 86400)}d ago`;
}

export function formatDuration(seconds: number | null) {
    if (seconds == null) return '—';
    if (seconds >= 3600) return `${(seconds / 3600).toFixed(1)}h`;
    if (seconds >= 60) return `${(seconds / 60).toFixed(1)}m`;
    return `${seconds}s`;
}

/** The window one sync type was asked for, as a readable string. */
export function describeWindow(parameters: Record<string, unknown> | null) {
    if (!parameters) return '—';

    const dates = parameters.dates as string[] | undefined;
    if (dates?.length) {
        return dates.length === 1
            ? dates[0]
            : `${dates[0]} → ${dates[dates.length - 1]}`;
    }

    if (parameters.start_date) {
        return `${parameters.start_date} → ${parameters.end_date}`;
    }

    return '—';
}

/**
 * A batch's parameters are keyed by sync type, so pair each type up with the
 * window it was asked for.
 */
export function describeBatchWindows(
    batch: Pick<SyncBatch, 'sync_types' | 'sync_labels' | 'parameters'>,
) {
    return batch.sync_types.map((type, i) => ({
        type,
        label: batch.sync_labels[i] ?? type,
        window: describeWindow(
            (batch.parameters?.[type] as Record<string, unknown>) ?? null,
        ),
    }));
}

/** A compact "Transactions, POs +1" style label for a batch in a table cell. */
export function summariseTypes(batch: Pick<SyncBatch, 'sync_labels'>, max = 2) {
    const shown = batch.sync_labels.slice(0, max).join(', ');
    const rest = batch.sync_labels.length - max;

    return rest > 0 ? `${shown} +${rest}` : shown;
}

/**
 * The n8n execution behind a run, click-to-copy.
 *
 * The whole point of storing it is pasting it into n8n when something looks
 * wrong, so copying is one click rather than a careful drag-select.
 */
export function ExecutionId({ id }: { id: string | null }) {
    const [copied, setCopied] = useState(false);

    if (!id) {
        return (
            <span className="font-mono text-[10px] text-gray-300 dark:text-gray-600">
                —
            </span>
        );
    }

    const copy = () => {
        navigator.clipboard
            ?.writeText(id)
            .then(() => {
                setCopied(true);
                setTimeout(() => setCopied(false), 1200);
            })
            .catch(() => {
                // Clipboard access can be refused; the id is on screen anyway.
            });
    };

    return (
        <button
            type="button"
            onClick={copy}
            title={`n8n execution ${id} — click to copy`}
            className={clsx(
                'font-mono text-[10px] transition-colors',
                copied
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300',
            )}
        >
            {copied ? 'copied' : id}
        </button>
    );
}

/** What the execution-lookup endpoint answers with. */
export interface ExecutionLookup {
    state:
        | 'found'
        | 'unknown'
        | 'not_found'
        | 'unconfigured'
        | 'unauthorized'
        | 'unreachable';
    message?: string | null;
    execution?: {
        id: string;
        status: string;
        finished: boolean;
        started_at: string | null;
        stopped_at: string | null;
        workflow_id: string | null;
        mode: string | null;
        error: string | null;
        url: string | null;
    };
}

const N8N_STATUS_TONE: Record<string, string> = {
    success: 'text-emerald-600 dark:text-emerald-400',
    error: 'text-red-500 dark:text-red-400',
    crashed: 'text-red-500 dark:text-red-400',
    canceled: 'text-stone-500 dark:text-zinc-500',
    running: 'text-blue-600 dark:text-blue-400',
    waiting: 'text-amber-600 dark:text-amber-400',
};

/**
 * Per-run actions: ask n8n how its execution went, and send the work again.
 *
 * The lookup is a plain fetch rather than an Inertia visit — it's a question
 * about a third-party system for one row, it can be slow, and a miss shouldn't
 * disturb the page.
 */
export function RunActions({
    run,
    workspaceSlug,
    n8nApiConfigured,
    queueBusy,
}: {
    run: SyncBatchRun;
    workspaceSlug: string;
    n8nApiConfigured: boolean;
    queueBusy: boolean;
}) {
    const [lookup, setLookup] = useState<ExecutionLookup | null>(null);
    const [checking, setChecking] = useState(false);
    const [retrying, setRetrying] = useState(false);

    const base = `/workspaces/${workspaceSlug}/gencys/sync-runs/${run.id}`;

    const check = async () => {
        setChecking(true);
        try {
            const response = await fetch(`${base}/execution`, {
                headers: { Accept: 'application/json' },
            });
            setLookup(await response.json());
        } catch {
            setLookup({
                state: 'not_found',
                message: 'Could not reach the server.',
            });
        } finally {
            setChecking(false);
        }
    };

    // Only a failed run is worth sending again — a success has its data and a
    // cancellation was deliberate. And since one batch holds the ERP at a time,
    // a retry raised now would just queue behind whatever is working.
    const retryable = run.status === 'failed';
    const retryBlockedReason = queueBusy
        ? 'A sync is already in progress — retry once the queue is clear'
        : null;

    const retry = () => {
        setRetrying(true);
        router.post(
            `${base}/retry`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setRetrying(false),
            },
        );
    };

    return (
        <div className="flex flex-col items-end gap-1">
            <div className="flex items-center gap-1">
                {n8nApiConfigured && (
                    <button
                        type="button"
                        onClick={check}
                        disabled={checking}
                        title={
                            run.n8n_execution_id
                                ? `Ask n8n how execution ${run.n8n_execution_id} went`
                                : 'No n8n execution was recorded for this run'
                        }
                        className="inline-flex items-center gap-1 rounded-md px-1.5 py-1 font-mono text-[10px] text-gray-400 transition-colors hover:bg-stone-100 hover:text-gray-600 disabled:opacity-50 dark:text-gray-500 dark:hover:bg-zinc-800 dark:hover:text-gray-300"
                    >
                        {checking ? (
                            <Loader2 className="h-3 w-3 animate-spin" />
                        ) : (
                            <SearchCheck className="h-3 w-3" />
                        )}
                        check
                    </button>
                )}

                {retryable && (
                    <button
                        type="button"
                        onClick={retry}
                        disabled={retrying || !!retryBlockedReason}
                        title={
                            retryBlockedReason ??
                            'Send this work to n8n again as a new batch'
                        }
                        className="inline-flex items-center gap-1 rounded-md px-1.5 py-1 font-mono text-[10px] text-gray-400 transition-colors hover:bg-stone-100 hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-gray-500 dark:hover:bg-zinc-800 dark:hover:text-gray-300"
                    >
                        {retrying ? (
                            <Loader2 className="h-3 w-3 animate-spin" />
                        ) : (
                            <RotateCcw className="h-3 w-3" />
                        )}
                        retry
                    </button>
                )}
            </div>

            {lookup && <ExecutionResult lookup={lookup} />}
        </div>
    );
}

function ExecutionResult({ lookup }: { lookup: ExecutionLookup }) {
    if (lookup.state !== 'found' || !lookup.execution) {
        // A rejected API key is something someone has to go and fix, so it reads
        // as a warning rather than as a shrug like the other empty answers.
        const needsAttention = lookup.state === 'unauthorized';

        return (
            <span
                className={clsx(
                    'max-w-[220px] text-right text-[10px]',
                    needsAttention
                        ? 'text-amber-600 dark:text-amber-400'
                        : 'text-gray-400 dark:text-gray-500',
                )}
            >
                {lookup.message ?? 'No answer from n8n.'}
            </span>
        );
    }

    const { status, error, url, stopped_at } = lookup.execution;

    return (
        <div className="flex flex-col items-end gap-0.5 text-right">
            <span
                className={clsx(
                    'font-mono text-[10px] tracking-wide uppercase',
                    N8N_STATUS_TONE[status] ??
                        'text-gray-500 dark:text-gray-400',
                )}
            >
                n8n: {status}
                {stopped_at && ` · ${formatRelative(stopped_at)}`}
            </span>

            {error && (
                <span
                    className="line-clamp-2 max-w-[220px] text-[10px] text-red-500 dark:text-red-400"
                    title={error}
                >
                    {error}
                </span>
            )}

            {url && (
                <a
                    href={url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 font-mono text-[10px] text-gray-400 hover:underline dark:text-gray-500"
                >
                    open in n8n
                    <ExternalLink className="h-2.5 w-2.5" />
                </a>
            )}
        </div>
    );
}
