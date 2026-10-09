import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { dueMeta } from '../lib/task-format';
import type { Task, TaskStatus, UserSummary } from '../types';
import { activeProgress, StatusGlyph, StatusPicker } from './status-picker';
import { LabelChips } from './task-bits';
import { AssigneePicker, DuePicker, PriorityPicker } from './task-fields';
import { useTaskRoutes } from './task-workspace';

type Props = {
    task: Task;
    statuses: TaskStatus[];
    members: UserSummary[];
    onUpdate: (task: Task, payload: Record<string, unknown>) => Promise<void>;
    onDelete: (task: Task) => Promise<void>;
    onError: (cause: unknown) => void;
};

export function TaskRow({
    task,
    statuses,
    members,
    onUpdate,
    onDelete,
    onError,
}: Props) {
    const routes = useTaskRoutes();
    const [busy, setBusy] = useState(false);
    // Without Manage Tasks the row is read-only: pickers show, but do nothing.
    const canManage = usePermission(PERMISSIONS.ManageTasks);

    const due = dueMeta(task.due_at, task.completed);
    const status = statuses.find((item) => item.id === task.status_id);
    async function run(action: () => Promise<void>) {
        setBusy(true);

        try {
            await action();
        } catch (cause) {
            onError(cause);
        } finally {
            setBusy(false);
        }
    }

    return (
        <div
            className={cn(
                'group relative flex h-11 items-center gap-2.5 border-b border-black/5 px-3 transition-colors duration-200 ease-out last:border-b-0 hover:bg-accent/50 dark:border-white/5',
                'before:absolute before:inset-y-0 before:left-0 before:w-0.5 before:origin-center before:scale-y-0 before:bg-emerald-500 before:transition-transform before:duration-200 before:ease-out hover:before:scale-y-100',
                busy && 'opacity-60',
            )}
        >
            <StatusPicker
                statuses={statuses}
                value={task.status_id}
                disabled={busy || !canManage}
                align="start"
                onChange={(statusId) =>
                    void run(() => onUpdate(task, { status_id: statusId }))
                }
            >
                <button
                    type="button"
                    aria-label={`Status for ${task.name}: ${status?.name ?? 'none'}`}
                    title={status?.name}
                    className="flex size-5 shrink-0 items-center justify-center rounded transition-colors duration-200 ease-out hover:bg-accent"
                >
                    <StatusGlyph
                        status={status}
                        progress={
                            status
                                ? activeProgress(status, statuses)
                                : undefined
                        }
                    />
                </button>
            </StatusPicker>

            {/*
             * Fixed width, and rendered even when there is no code. Sized to
             * its content it only looks aligned because every code today is
             * five characters -- MAT-30 exists, and a longer one would shift
             * the name on that row alone. Omitting the span when the code is
             * null would collapse the column the same way.
             */}
            <span
                title={task.ticket ?? undefined}
                className="w-16 shrink-0 truncate font-mono text-[0.6875rem] tracking-[0.06em] text-muted-foreground"
            >
                {task.ticket}
            </span>

            <Link
                href={routes.pages.show(task.id)}
                className={cn(
                    'min-w-0 flex-1 truncate text-sm tracking-[-0.006em] transition-colors duration-200 ease-out hover:text-emerald-700 dark:hover:text-emerald-400',
                    task.completed &&
                        'text-muted-foreground line-through decoration-muted-foreground/50',
                )}
            >
                {task.name}
            </Link>

            <div className="hidden items-center gap-1.5 sm:flex">
                <LabelChips labels={task.labels} />
            </div>

            <div className="flex w-8 shrink-0 justify-center">
                <AssigneePicker
                    task={task}
                    members={members}
                    disabled={busy || !canManage}
                    onChange={(payload) =>
                        void run(() => onUpdate(task, payload))
                    }
                    className={cn(
                        'transition-opacity duration-200 ease-out',
                        (task.assignees?.length ?? 0) === 0 &&
                            'opacity-0 group-focus-within:opacity-100 group-hover:opacity-100',
                    )}
                />
            </div>

            <div className="hidden w-20 shrink-0 justify-end sm:flex">
                <DuePicker
                    task={task}
                    disabled={busy || !canManage}
                    onChange={(payload) =>
                        void run(() => onUpdate(task, payload))
                    }
                    className={cn(
                        'transition-opacity duration-200 ease-out',
                        due === null &&
                            'opacity-0 group-focus-within:opacity-100 group-hover:opacity-100',
                    )}
                />
            </div>

            <div className="flex w-6 shrink-0 justify-center">
                <PriorityPicker
                    task={task}
                    disabled={busy || !canManage}
                    onChange={(payload) =>
                        void run(() => onUpdate(task, payload))
                    }
                    className={cn(
                        'transition-opacity duration-200 ease-out',
                        task.priority === null &&
                            'opacity-0 group-focus-within:opacity-100 group-hover:opacity-100',
                    )}
                />
            </div>

            {canManage && (
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => void run(() => onDelete(task))}
                    aria-label={`Delete ${task.name}`}
                    className="flex size-6 shrink-0 items-center justify-center rounded text-muted-foreground opacity-0 transition-all duration-200 ease-out group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive focus-visible:opacity-100"
                >
                    <Trash2 className="size-3.5" />
                </button>
            )}
        </div>
    );
}
