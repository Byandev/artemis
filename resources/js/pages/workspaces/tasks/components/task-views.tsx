import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { ChevronRight, ClipboardList } from 'lucide-react';
import { useMemo, useState } from 'react';
import { statusColor } from '../lib/task-format';
import type { Task, TaskStatus, UserSummary } from '../types';
import { StatusPill } from './task-bits';
import { TaskCard } from './task-card';
import { TaskGroupAdder } from './task-group-adder';
import { TaskRow } from './task-row';

type Props = {
    tasks: Task[];
    statuses: TaskStatus[];
    members: UserSummary[];
    loading: boolean;
    canCreate: boolean;
    newTaskStatusId: number | null;
    onNewTaskOpened: () => void;
    onCreate: (payload: Record<string, unknown>) => Promise<void>;
    onUpdate: (task: Task, payload: Record<string, unknown>) => Promise<void>;
    onDelete: (task: Task) => Promise<void>;
    onError: (cause: unknown) => void;
};

function useGroups(tasks: Task[], statuses: TaskStatus[]) {
    return useMemo(
        () =>
            statuses.map((status) => ({
                status,
                tasks: tasks.filter((task) => task.status_id === status.id),
            })),
        [tasks, statuses],
    );
}

/**
 * Fixed widths, never random ones — SSR runs in dev and a width that differs
 * between the server and client render breaks hydration.
 */
const SKELETON_WIDTHS = ['w-2/3', 'w-1/2', 'w-5/6', 'w-3/5', 'w-3/4'] as const;

export function TaskLoadingState() {
    return (
        <div className="flex flex-col gap-4 rounded-xl border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            {SKELETON_WIDTHS.map((width) => (
                <div key={width} className="flex items-center gap-3">
                    <Skeleton className="size-[18px] shrink-0 rounded-full" />
                    <Skeleton className={cn('h-3.5 rounded-full', width)} />
                </div>
            ))}
        </div>
    );
}

export function TaskEmptyState({ filtered }: { filtered: boolean }) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed border-black/12 px-6 py-20 text-center dark:border-white/12">
            <span className="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <ClipboardList className="size-5" />
            </span>
            <p className="text-xl font-semibold tracking-[-0.022em] text-foreground">
                {filtered ? 'Nothing matches those filters' : 'No tasks yet'}
            </p>
            <p className="max-w-xs text-sm leading-relaxed text-muted-foreground">
                {filtered
                    ? 'Try clearing the filters above to see the rest of this list.'
                    : 'Use “New task” under any status to add the first one.'}
            </p>
        </div>
    );
}

export function TaskListView({
    tasks,
    statuses,
    members,
    loading,
    canCreate,
    newTaskStatusId,
    onNewTaskOpened,
    onCreate,
    onUpdate,
    onDelete,
    onError,
}: Props) {
    const groups = useGroups(tasks, statuses);
    const [collapsed, setCollapsed] = useState<number[]>([]);

    if (loading && tasks.length === 0) {
        return <TaskLoadingState />;
    }

    /*
     * One continuous table, not a card per status. Statuses are sections inside
     * the list — headers that scroll with it and stick — so the eye runs down a
     * single column of work instead of stepping over three floating panels.
     */
    return (
        <div className="rounded-xl border border-border bg-white dark:bg-zinc-900 [&>section:first-child>header]:rounded-t-xl [&>section:last-child>*:last-child]:rounded-b-xl">
            {groups.map((group) => {
                const isOpen = !collapsed.includes(group.status.id);
                const empty = group.tasks.length === 0;

                return (
                    <section key={group.status.id}>
                        <header className="group/head sticky top-0 z-10 flex h-9 items-center gap-2.5 border-b border-black/6 bg-muted px-3 dark:border-white/6">
                            <button
                                type="button"
                                onClick={() =>
                                    setCollapsed((current) =>
                                        current.includes(group.status.id)
                                            ? current.filter(
                                                  (id) =>
                                                      id !== group.status.id,
                                              )
                                            : [...current, group.status.id],
                                    )
                                }
                                aria-expanded={isOpen}
                                aria-label={`Toggle ${group.status.name}`}
                                /*
                                 * size-5 and gap-2.5 above are the row's
                                 * geometry, not this header's own taste: the
                                 * status pill has to start where a row's ticket
                                 * code starts, and the chevron has to start
                                 * where a row's completion marker starts. Both
                                 * fall out of matching the leading box and the
                                 * gap rather than from a hand-tuned offset.
                                 */
                                className="flex size-5 shrink-0 items-center justify-center text-muted-foreground transition-colors duration-200 ease-out hover:text-foreground"
                            >
                                <ChevronRight
                                    className={cn(
                                        'size-4 transition-transform duration-200 ease-out',
                                        isOpen && 'rotate-90',
                                    )}
                                />
                            </button>

                            <StatusPill
                                status={group.status}
                                count={group.tasks.length}
                                allStatuses={statuses}
                            />

                            {isOpen && (
                                <div
                                    className={cn(
                                        'transition-opacity duration-200 ease-out',
                                        !empty &&
                                            'opacity-0 group-hover/head:opacity-100 focus-within:opacity-100',
                                    )}
                                >
                                    <TaskGroupAdder
                                        status={group.status}
                                        disabled={!canCreate}
                                        indent={false}
                                        autoOpen={
                                            newTaskStatusId === group.status.id
                                        }
                                        onOpened={onNewTaskOpened}
                                        onCreate={onCreate}
                                        onError={onError}
                                    />
                                </div>
                            )}
                        </header>

                        {isOpen &&
                            group.tasks.map((task) => (
                                <TaskRow
                                    key={task.id}
                                    task={task}
                                    statuses={statuses}
                                    members={members}
                                    onUpdate={onUpdate}
                                    onDelete={onDelete}
                                    onError={onError}
                                />
                            ))}
                    </section>
                );
            })}
        </div>
    );
}

export function TaskBoardView({
    tasks,
    statuses,
    members,
    loading,
    canCreate,
    newTaskStatusId,
    onNewTaskOpened,
    onCreate,
    onUpdate,
    onDelete,
    onError,
}: Props) {
    const groups = useGroups(tasks, statuses);

    if (loading && tasks.length === 0) {
        return <TaskLoadingState />;
    }

    return (
        <div className="flex h-full gap-4 overflow-x-auto pb-4">
            {groups.map((group) => (
                <div
                    key={group.status.id}
                    className="flex w-[19rem] shrink-0 flex-col overflow-hidden rounded-xl border border-black/6 bg-muted/40 dark:border-white/6"
                >
                    <header
                        className="border-b border-black/5 px-3.5 py-3 dark:border-white/5"
                        style={{
                            boxShadow: `inset 0 3px 0 ${statusColor(group.status.color)}`,
                        }}
                    >
                        <StatusPill
                            status={group.status}
                            count={group.tasks.length}
                            allStatuses={statuses}
                        />
                    </header>

                    <div className="flex min-h-0 flex-1 flex-col gap-2.5 overflow-y-auto p-2.5">
                        {group.tasks.map((task) => (
                            <TaskCard
                                key={task.id}
                                task={task}
                                statuses={statuses}
                                members={members}
                                onUpdate={onUpdate}
                                onDelete={onDelete}
                                onError={onError}
                            />
                        ))}

                        <TaskGroupAdder
                            status={group.status}
                            disabled={!canCreate}
                            indent={false}
                            autoOpen={newTaskStatusId === group.status.id}
                            onOpened={onNewTaskOpened}
                            onCreate={onCreate}
                            onError={onError}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}
