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

export function TaskCard({
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
        <article
            className={cn(
                'group rounded-xl border border-black/6 bg-white p-3 transition-all duration-200 ease-out hover:-translate-y-px hover:border-emerald-500/40 dark:border-white/6 dark:bg-zinc-900',
                busy && 'opacity-60',
            )}
        >
            <div className="flex items-start gap-2.5">
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
                        className="flex size-6 shrink-0 items-center justify-center rounded-md transition-colors duration-200 ease-out hover:bg-accent"
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

                <Link
                    href={routes.pages.show(task.id)}
                    className={cn(
                        'min-w-0 flex-1 text-sm leading-[1.45] tracking-[-0.006em] transition-colors duration-200 ease-out hover:text-emerald-700 dark:hover:text-emerald-400',
                        task.completed &&
                            'text-muted-foreground line-through decoration-muted-foreground/50',
                    )}
                >
                    {task.name}
                </Link>

                {canManage && (
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => void run(() => onDelete(task))}
                        aria-label={`Delete ${task.name}`}
                        className="flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground opacity-0 transition-all duration-200 ease-out group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive focus-visible:opacity-100"
                    >
                        <Trash2 className="size-3" />
                    </button>
                )}
            </div>

            {(task.labels?.length ?? 0) > 0 && (
                <div className="mt-2.5 flex flex-wrap gap-1 pl-[28px]">
                    <LabelChips labels={task.labels} />
                </div>
            )}

            <div className="mt-2.5 flex items-center gap-1 border-t border-black/5 pt-2.5 pl-[28px] dark:border-white/5">
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

                <div className="ml-auto">
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
            </div>
        </article>
    );
}
