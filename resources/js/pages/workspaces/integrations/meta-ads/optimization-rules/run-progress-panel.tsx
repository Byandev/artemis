import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { CheckCircle2, CircleDashed, Loader2, XCircle } from 'lucide-react';
import { useEffect } from 'react';
import { type OptimizationRun } from './types';

type StepState = 'done' | 'active' | 'pending' | 'failed';

const STATUS_BADGE: Record<
    OptimizationRun['status'],
    { label: string; className: string; dot: string }
> = {
    running: {
        label: 'Running',
        className:
            'border-amber-500/20 bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        dot: 'bg-amber-500',
    },
    completed: {
        label: 'Completed',
        className:
            'border-emerald-500/20 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
        dot: 'bg-emerald-500',
    },
    failed: {
        label: 'Failed',
        className:
            'border-rose-500/20 bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
        dot: 'bg-rose-500',
    },
};

function stepStateFor(
    status: OptimizationRun['status'],
    index: number,
    currentIndex: number,
): StepState {
    if (status === 'completed') return 'done';
    if (status === 'failed') {
        if (index < currentIndex) return 'done';
        if (index === currentIndex) return 'failed';
        return 'pending';
    }
    // running
    if (index < currentIndex) return 'done';
    if (index === currentIndex) return 'active';
    return 'pending';
}

function StepIcon({ state }: { state: StepState }) {
    switch (state) {
        case 'done':
            return (
                <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-500" />
            );
        case 'active':
            return (
                <Loader2 className="h-4 w-4 shrink-0 animate-spin text-amber-500" />
            );
        case 'failed':
            return <XCircle className="h-4 w-4 shrink-0 text-rose-500" />;
        default:
            return (
                <CircleDashed className="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" />
            );
    }
}

/** Presentational panel for a single rule's run. */
export function RunProgressPanel({ run }: { run: OptimizationRun }) {
    const badge = STATUS_BADGE[run.status];
    const currentIndex = run.steps.findIndex((s) => s.key === run.current_step);

    const startedAgo = run.started_at
        ? formatDistanceToNow(new Date(run.started_at), { addSuffix: true })
        : null;

    const summary = [
        startedAgo ? `started ${startedAgo}` : null,
        `${run.total_accounts} account${run.total_accounts === 1 ? '' : 's'}`,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-center justify-between border-b border-black/5 px-4 py-3 dark:border-white/5">
                <div className="flex min-w-0 items-center gap-2">
                    <span className="truncate text-sm font-semibold text-gray-800 dark:text-gray-100">
                        {run.rule_name ?? 'Rule run'}
                    </span>
                    <span className="shrink-0 font-mono text-[11px] text-gray-400">
                        #{run.id}
                    </span>
                </div>
                <Badge
                    variant="outline"
                    className={cn(
                        'shrink-0 gap-1.5 text-[11px]',
                        badge.className,
                    )}
                >
                    <span
                        className={cn(
                            'h-1.5 w-1.5 rounded-full',
                            badge.dot,
                            run.status === 'running' && 'animate-pulse',
                        )}
                    />
                    {badge.label}
                </Badge>
            </div>

            <ol className="flex flex-col gap-1.5 px-4 py-3.5">
                {run.steps.map((step, index) => {
                    const state = stepStateFor(run.status, index, currentIndex);
                    return (
                        <li
                            key={step.key}
                            className="flex items-center gap-2.5 text-sm"
                        >
                            <StepIcon state={state} />
                            <span
                                className={cn(
                                    state === 'pending' &&
                                        'text-gray-400 dark:text-gray-500',
                                    state === 'active' &&
                                        'font-medium text-gray-900 dark:text-gray-100',
                                    (state === 'done' || state === 'failed') &&
                                        'text-gray-600 dark:text-gray-300',
                                )}
                            >
                                {step.label}
                            </span>
                            {state === 'active' && (
                                <span className="text-[11px] text-amber-600 dark:text-amber-400">
                                    in progress
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>

            <div className="border-t border-black/5 px-4 py-2.5 dark:border-white/5">
                {run.status === 'failed' && run.error_message ? (
                    <p className="truncate text-[12px] text-rose-600 dark:text-rose-400">
                        {run.error_message}
                    </p>
                ) : (
                    <p className="text-[12px] text-gray-400 dark:text-gray-500">
                        {summary}
                    </p>
                )}
            </div>
        </div>
    );
}

/**
 * Renders one panel per rule's latest run, and polls the page (just the
 * `currentRuns` prop) while any of them is still running so the steps stay live.
 */
export default function RunProgressList({ runs }: { runs: OptimizationRun[] }) {
    const anyRunning = runs.some((r) => r.status === 'running');

    useEffect(() => {
        if (!anyRunning) return;

        const interval = setInterval(() => {
            router.reload({ only: ['currentRuns'] });
        }, 3000);

        return () => clearInterval(interval);
    }, [anyRunning]);

    if (runs.length === 0) return null;

    return (
        <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {runs.map((run) => (
                <RunProgressPanel key={run.id} run={run} />
            ))}
        </div>
    );
}
