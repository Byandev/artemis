import clsx from 'clsx';
import {
    Activity,
    Ban,
    CheckCircle2,
    CircleDashed,
    Clock,
    XCircle,
} from 'lucide-react';

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
export function describeBatchWindows(batch: SyncBatch) {
    return batch.sync_types.map((type, i) => ({
        type,
        label: batch.sync_labels[i] ?? type,
        window: describeWindow(
            (batch.parameters?.[type] as Record<string, unknown>) ?? null,
        ),
    }));
}

/** A compact "Transactions, POs +1" style label for a batch in a table cell. */
export function summariseTypes(batch: SyncBatch, max = 2) {
    const shown = batch.sync_labels.slice(0, max).join(', ');
    const rest = batch.sync_labels.length - max;

    return rest > 0 ? `${shown} +${rest}` : shown;
}
