import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, ChevronDown, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from './components/confirm-dialog';
import {
    activeProgress,
    StatusGlyph,
    StatusPicker,
} from './components/status-picker';
import { TaskAttachments } from './components/task-attachments';
import { StatusDot } from './components/task-bits';
import { TaskComments } from './components/task-comments';
import {
    AssigneePicker,
    DuePicker,
    LabelPicker,
    PriorityPicker,
} from './components/task-fields';
import { useTaskRoutes, useTaskWorkspace } from './components/task-workspace';
import { TasksShell } from './components/tasks-shell';
import { useTaskDetail } from './hooks/use-task-detail';
import { initials, statusColor } from './lib/task-format';
import type { Task } from './types';

type Props = {
    workspace: { id: number; name: string; slug: string };
    taskId: number;
};

/** One labelled row in the properties rail. */
function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-[7rem_minmax(0,1fr)] items-center gap-3 px-5 py-2.5">
            <span className="font-mono text-[0.6875rem] tracking-[0.08em] text-muted-foreground uppercase">
                {label}
            </span>
            <div className="min-w-0">{children}</div>
        </div>
    );
}

/** Minutes in, `2h 30m` out. */
function formatEstimate(minutes: number | null): string {
    if (minutes === null || minutes === 0) {
        return '';
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return [hours > 0 ? `${hours}h` : '', rest > 0 ? `${rest}m` : '']
        .filter(Boolean)
        .join(' ');
}

function toDateInput(value: string | null): string {
    if (value === null) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

function Description({
    task,
    disabled,
    onSave,
}: {
    task: Task;
    disabled: boolean;
    onSave: (value: string | null) => void;
}) {
    const [draft, setDraft] = useState(task.description ?? '');

    // Adopt the server's copy when it changes underneath the editor.
    useEffect(() => {
        setDraft(task.description ?? '');
    }, [task.description]);

    return (
        <textarea
            value={draft}
            disabled={disabled}
            onChange={(event) => setDraft(event.target.value)}
            onBlur={() => {
                const next = draft.trim();

                if (next !== (task.description ?? '')) {
                    onSave(next === '' ? null : next);
                }
            }}
            placeholder="Add a description — what does done look like?"
            aria-label="Task description"
            rows={8}
            className="min-h-40 w-full resize-y rounded-xl border border-black/8 bg-white px-4 py-3 text-sm leading-relaxed transition-all duration-200 ease-out outline-none placeholder:text-muted-foreground/70 focus-visible:border-emerald-500 focus-visible:ring-4 focus-visible:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900"
        />
    );
}

function Subtasks({
    task,
    readOnly,
    onAdd,
    onToggle,
}: {
    task: Task;
    readOnly: boolean;
    onAdd: (name: string) => void;
    onToggle: (subtask: Task) => void;
}) {
    const routes = useTaskRoutes();
    const [name, setName] = useState('');
    const subtasks = task.subtasks ?? [];
    const done = subtasks.filter((item) => item.completed).length;

    return (
        <section className="overflow-hidden rounded-xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <header className="flex items-center justify-between gap-3 border-b border-black/6 px-5 py-3.5 dark:border-white/6">
                <h2 className="font-mono text-[0.6875rem] tracking-[0.1em] uppercase">
                    Subtasks
                </h2>
                {subtasks.length > 0 && (
                    <span className="text-xs text-muted-foreground">
                        {done} of {subtasks.length} done
                    </span>
                )}
            </header>

            {subtasks.map((subtask) => (
                <div
                    key={subtask.id}
                    className="flex items-center gap-3 border-b border-black/5 px-5 py-2.5 last:border-b-0 dark:border-white/5"
                >
                    <StatusDot
                        status={subtask.status}
                        complete={subtask.completed}
                        onClick={() => onToggle(subtask)}
                        disabled={readOnly}
                        label={
                            subtask.completed
                                ? `Reopen ${subtask.name}`
                                : `Complete ${subtask.name}`
                        }
                    />
                    <Link
                        href={routes.pages.show(subtask.id)}
                        className={cn(
                            'min-w-0 flex-1 truncate text-sm transition-colors hover:text-emerald-700 dark:hover:text-emerald-400',
                            subtask.completed &&
                                'text-muted-foreground line-through decoration-muted-foreground/50',
                        )}
                    >
                        {subtask.name}
                    </Link>
                </div>
            ))}

            {!readOnly && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (name.trim() !== '') {
                            onAdd(name.trim());
                            setName('');
                        }
                    }}
                    className="flex items-center gap-2 px-5 py-3"
                >
                    <Plus className="size-3.5 shrink-0 text-muted-foreground" />
                    <input
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        placeholder="Add a subtask, then Enter"
                        aria-label="New subtask"
                        className="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground/70"
                    />
                </form>
            )}
        </section>
    );
}

function TaskDetail({ taskId }: { taskId: number }) {
    const routes = useTaskRoutes();
    const workspace = useTaskWorkspace();
    const detail = useTaskDetail(taskId);
    const [name, setName] = useState('');
    const [confirming, setConfirming] = useState(false);

    const task = detail.task;
    const taskSpaceId = task?.list?.space_id ?? null;
    const { spaceId, setSpaceId } = workspace;

    useEffect(() => {
        setName(task?.name ?? '');
    }, [task?.name]);

    /*
     * A task reached by direct link may live outside the space the tree has
     * open. Point the workspace at its space — via the setter, not openSpace,
     * which would navigate back to the board — so the rail offers this space's
     * statuses, labels and members rather than another's.
     */
    useEffect(() => {
        if (taskSpaceId !== null && taskSpaceId !== spaceId) {
            setSpaceId(taskSpaceId);
        }
    }, [taskSpaceId, spaceId, setSpaceId]);

    if (detail.loading || task === null) {
        return (
            <>
                <Head title="Task" />
                <div className="mx-auto flex w-full max-w-[1280px] flex-col gap-5 p-4 md:p-7">
                    <Skeleton className="h-4 w-40" />
                    <Skeleton className="h-9 w-2/3" />
                    <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                        <Skeleton className="h-48 w-full rounded-xl" />
                        <Skeleton className="h-64 w-full rounded-xl" />
                    </div>
                </div>
            </>
        );
    }

    const complete = workspace.statuses.find((item) => item.is_complete);
    const open = workspace.statuses.find((item) => !item.is_complete);
    const color = statusColor(task.status?.color);

    /** Space-scoped choices are only trustworthy once its data has landed. */
    const spaceReady =
        taskSpaceId !== null &&
        spaceId === taskSpaceId &&
        !workspace.loadingSpace;

    /*
     * Attaching a file changes the work, so it needs a member; tidying up
     * someone else's file needs an admin. Both mirror what the API enforces --
     * hiding a control the server would refuse, not deciding the permission.
     */
    const role = spaceReady ? (workspace.activeSpace?.role ?? null) : null;
    const canManageAttachments = role === 'owner' || role === 'admin';
    const canAttachFiles = canManageAttachments || role === 'member';

    /** Without Manage Tasks the whole page is read-only. */
    const canManage = workspace.canManageTasks;

    return (
        <>
            <Head title={task.name} />

            <div className="mx-auto flex w-full max-w-[1280px] flex-col gap-5 p-4 md:p-7">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button asChild variant="ghost" size="sm">
                        <Link
                            href={routes.pages.index({
                                query: {
                                    space: task.list?.space_id,
                                    list: task.list_id,
                                },
                            })}
                        >
                            <ArrowLeft />
                            Back to board
                        </Link>
                    </Button>

                    {canManage && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setConfirming(true)}
                            className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                        >
                            <Trash2 />
                            Delete task
                        </Button>
                    )}
                </div>

                <header className="flex flex-col gap-3">
                    <p className="flex flex-wrap items-center gap-1.5 font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground uppercase">
                        {task.ticket !== null && (
                            <>
                                <span className="text-foreground">
                                    {task.ticket}
                                </span>
                                <span className="opacity-50">/</span>
                            </>
                        )}
                        <span>{workspace.activeSpace?.name}</span>
                        {task.list && (
                            <>
                                <span className="opacity-50">/</span>
                                <span className="text-foreground">
                                    {task.list.name}
                                </span>
                            </>
                        )}
                    </p>

                    <div className="flex items-start gap-3">
                        <span className="pt-1">
                            <StatusPicker
                                statuses={workspace.statuses}
                                value={task.status_id}
                                disabled={
                                    !spaceReady || detail.saving || !canManage
                                }
                                align="start"
                                onChange={(statusId) =>
                                    void detail.update({ status_id: statusId })
                                }
                            >
                                <button
                                    type="button"
                                    aria-label={`Status: ${task.status?.name ?? 'none'}`}
                                    title={task.status?.name}
                                    className="flex size-8 items-center justify-center rounded-md transition-colors duration-200 ease-out hover:bg-accent"
                                >
                                    <StatusGlyph
                                        status={task.status}
                                        progress={
                                            task.status
                                                ? activeProgress(
                                                      task.status,
                                                      workspace.statuses,
                                                  )
                                                : undefined
                                        }
                                        className="size-5"
                                    />
                                </button>
                            </StatusPicker>
                        </span>

                        <input
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            onBlur={() => {
                                const next = name.trim();

                                if (next === '') {
                                    setName(task.name);
                                } else if (next !== task.name) {
                                    void detail.update({ name: next });
                                }
                            }}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.currentTarget.blur();
                                }

                                if (event.key === 'Escape') {
                                    setName(task.name);
                                }
                            }}
                            readOnly={!canManage}
                            aria-label="Task name"
                            className={cn(
                                '-mx-2 min-w-0 flex-1 rounded-lg border border-transparent bg-transparent px-2 py-1 text-[22px] leading-tight font-semibold tracking-tight text-gray-800 outline-none focus-visible:border-black/12 dark:text-gray-100 dark:focus-visible:border-white/12',
                                'transition-colors duration-200 ease-out hover:border-black/8 dark:hover:border-white/8',
                                task.completed &&
                                    'text-muted-foreground line-through',
                            )}
                        />
                    </div>
                </header>

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="flex flex-col gap-5">
                        <Description
                            task={task}
                            disabled={detail.saving || !canManage}
                            onSave={(description) =>
                                void detail.update({ description })
                            }
                        />

                        <Subtasks
                            task={task}
                            readOnly={!canManage}
                            onAdd={(value) => void detail.addSubtask(value)}
                            onToggle={(subtask) => {
                                const target = subtask.completed
                                    ? open
                                    : complete;

                                if (target !== undefined) {
                                    void detail.updateSubtask(subtask, {
                                        status_id: target.id,
                                    });
                                }
                            }}
                        />

                        <TaskAttachments
                            taskId={task.id}
                            canAttach={canManage && canAttachFiles}
                            canManage={canManage && canManageAttachments}
                            onError={detail.report}
                        />

                        <TaskComments
                            taskId={task.id}
                            readOnly={!canManage}
                            onError={detail.report}
                        />
                    </div>

                    <aside className="h-fit divide-y divide-black/6 overflow-hidden rounded-xl border border-black/6 bg-white dark:divide-white/6 dark:border-white/6 dark:bg-zinc-900">
                        <Field label="Status">
                            <StatusPicker
                                statuses={workspace.statuses}
                                value={task.status_id}
                                disabled={!spaceReady || !canManage}
                                align="start"
                                onChange={(statusId) =>
                                    void detail.update({ status_id: statusId })
                                }
                            >
                                <button
                                    type="button"
                                    aria-label="Status"
                                    className="flex h-8 w-full items-center gap-2 rounded-lg border border-black/8 bg-white px-2 text-xs font-medium transition-all duration-200 ease-out outline-none hover:border-muted-foreground/40 focus-visible:border-emerald-500 focus-visible:ring-4 focus-visible:ring-emerald-500/15 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-900"
                                >
                                    <StatusGlyph
                                        status={task.status}
                                        progress={
                                            task.status
                                                ? activeProgress(
                                                      task.status,
                                                      workspace.statuses,
                                                  )
                                                : undefined
                                        }
                                        className="size-3.5"
                                    />
                                    <span className="min-w-0 flex-1 truncate text-left tracking-[0.04em] uppercase">
                                        {task.status?.name}
                                    </span>
                                    <ChevronDown className="size-3 shrink-0 text-muted-foreground" />
                                </button>
                            </StatusPicker>
                        </Field>

                        <Field label="Assignees">
                            <AssigneePicker
                                task={task}
                                members={workspace.members}
                                disabled={
                                    detail.saving || !spaceReady || !canManage
                                }
                                onChange={(payload) =>
                                    void detail.update(payload)
                                }
                                className="justify-start"
                            />
                        </Field>

                        <Field label="Priority">
                            <PriorityPicker
                                task={task}
                                disabled={detail.saving || !canManage}
                                onChange={(payload) =>
                                    void detail.update(payload)
                                }
                            />
                        </Field>

                        <Field label="Due">
                            <DuePicker
                                task={task}
                                disabled={detail.saving || !canManage}
                                onChange={(payload) =>
                                    void detail.update(payload)
                                }
                                className="-ml-1.5"
                            />
                        </Field>

                        <Field label="Starts">
                            <input
                                type="date"
                                value={toDateInput(task.start_at)}
                                disabled={detail.saving || !canManage}
                                aria-label="Start date"
                                onChange={(event) =>
                                    void detail.update({
                                        start_at:
                                            event.target.value === ''
                                                ? null
                                                : new Date(
                                                      `${event.target.value}T00:00:00`,
                                                  ).toISOString(),
                                    })
                                }
                                className="h-8 w-full rounded-lg border border-black/8 bg-white px-2 text-xs outline-none focus-visible:border-emerald-500 focus-visible:ring-4 focus-visible:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900"
                            />
                        </Field>

                        <Field label="Estimate">
                            <input
                                type="number"
                                min={0}
                                step={15}
                                defaultValue={task.estimate_minutes ?? ''}
                                disabled={detail.saving || !canManage}
                                aria-label="Estimate in minutes"
                                placeholder="Minutes"
                                onBlur={(event) => {
                                    const raw = event.target.value.trim();
                                    const next =
                                        raw === '' ? null : Number(raw);

                                    if (next !== task.estimate_minutes) {
                                        void detail.update({
                                            estimate_minutes: next,
                                        });
                                    }
                                }}
                                className="h-8 w-full rounded-lg border border-black/8 bg-white px-2 text-xs outline-none focus-visible:border-emerald-500 focus-visible:ring-4 focus-visible:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900"
                            />
                            {formatEstimate(task.estimate_minutes) !== '' && (
                                <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                                    {formatEstimate(task.estimate_minutes)}
                                </p>
                            )}
                        </Field>

                        <Field label="Labels">
                            <LabelPicker
                                task={task}
                                labels={workspace.labels}
                                disabled={
                                    detail.saving || !spaceReady || !canManage
                                }
                                onChange={(payload) =>
                                    void detail.update(payload)
                                }
                                className="-ml-1.5"
                            />
                        </Field>

                        {task.creator && (
                            <Field label="Created by">
                                <span className="flex items-center gap-2 text-xs">
                                    <span className="flex size-5 items-center justify-center rounded-full bg-emerald-600 text-[0.5625rem] font-semibold text-white dark:bg-emerald-500">
                                        {initials(task.creator.name)}
                                    </span>
                                    <span className="truncate">
                                        {task.creator.name}
                                    </span>
                                </span>
                            </Field>
                        )}

                        {task.completed && (
                            <Field label="Completed">
                                <span
                                    className="flex items-center gap-1.5 text-xs"
                                    style={{ color }}
                                >
                                    <Check className="size-3.5" />
                                    Done
                                </span>
                            </Field>
                        )}
                    </aside>
                </div>
            </div>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={`Delete ${task.name}?`}
                description="This removes the task and any subtasks under it. It cannot be undone."
                confirmLabel="Delete task"
                onConfirm={detail.remove}
                onError={detail.report}
            />
        </>
    );
}

export default function TaskShow({ workspace, taskId }: Props) {
    return (
        <TasksShell
            slug={workspace.slug}
            breadcrumbs={[
                { title: 'Tasks', href: `/workspaces/${workspace.slug}/tasks` },
            ]}
            options={{ showsTasks: false }}
        >
            <TaskDetail taskId={taskId} />
        </TasksShell>
    );
}
