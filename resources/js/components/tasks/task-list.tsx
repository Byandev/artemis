import { CalendarDays, MessageSquareText, Plus, Search } from 'lucide-react';
import {
    formatDate,
    isOverdue,
    statusAccentStyles,
    statusDotStyles,
    statusHeaderStyles,
    statusIcons,
} from './task-utils';
import { Task, TaskStatus } from './types';

interface TaskGroup {
    label: string;
    value: TaskStatus;
    tasks: Task[];
}

interface TaskListProps {
    groupedTasks: TaskGroup[];
    hasAnyTasks: boolean;
    hasVisibleTasks: boolean;
    selectedTaskId: number | null;
    onCreateTask: () => void;
    onSelectTask: (taskId: number) => void;
}

export function TaskList({
    groupedTasks,
    hasAnyTasks,
    hasVisibleTasks,
    selectedTaskId,
    onCreateTask,
    onSelectTask,
}: TaskListProps) {
    return (
        <div className="overflow-hidden rounded-[14px] bg-white shadow-theme-xs ring-1 ring-black/6 dark:bg-zinc-900 dark:ring-white/6">
            <div className="flex items-center justify-between gap-3 px-4 py-3">
                <div>
                    <p className="font-mono text-[12px] font-semibold tracking-wide text-gray-800 uppercase dark:text-gray-200">
                        Tasks
                    </p>
                    <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                        Open a row to view details, comments, and assignee
                        options.
                    </p>
                </div>
            </div>
            <div className="hidden grid-cols-[minmax(320px,1fr)_220px_140px_120px] bg-stone-50 px-4 py-2 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase lg:grid dark:bg-zinc-950 dark:text-gray-500">
                <div>Task</div>
                <div>Assignees</div>
                <div>Due Date</div>
                <div>Comments</div>
            </div>

            <div className="divide-y divide-black/5 dark:divide-white/5">
                {!hasVisibleTasks && (
                    <div className="flex flex-col items-center justify-center px-4 py-12 text-center">
                        <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-stone-100 text-gray-400 dark:bg-zinc-800 dark:text-gray-500">
                            <Search className="h-4 w-4" />
                        </div>
                        <p className="font-mono text-[13px] font-medium text-gray-700 dark:text-gray-300">
                            {hasAnyTasks ? 'No matching tasks' : 'No tasks yet'}
                        </p>
                        <p className="mt-1 max-w-sm text-[12px] text-gray-400 dark:text-gray-500">
                            {hasAnyTasks
                                ? 'Try a different search or switch status filters.'
                                : 'Create the first task to start tracking work here.'}
                        </p>
                        {!hasAnyTasks && (
                            <button
                                type="button"
                                onClick={onCreateTask}
                                className="mt-4 flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                            >
                                <Plus className="mr-1.5 h-3.5 w-3.5" />
                                Add Task
                            </button>
                        )}
                    </div>
                )}

                {hasVisibleTasks &&
                    groupedTasks.map((group) => {
                        const StatusIcon = statusIcons[group.value];

                        return (
                            <section key={group.value}>
                                <div className="flex items-center gap-2 bg-white px-4 pt-4 pb-2 dark:bg-zinc-900">
                                    <span
                                        className={`inline-flex h-6 items-center gap-1.5 rounded-md px-2.5 font-mono text-[11px] font-semibold tracking-wide uppercase ${statusHeaderStyles[group.value]}`}
                                    >
                                        <StatusIcon className="h-3.5 w-3.5" />
                                        {group.label}
                                    </span>
                                    <span className="font-mono text-[11px] text-gray-400">
                                        {group.tasks.length}
                                    </span>
                                </div>

                                <div className="divide-y divide-black/5 dark:divide-white/5">
                                    {group.tasks.length === 0 && (
                                        <div className="px-4 py-6 font-mono text-[12px] text-gray-400">
                                            No tasks in this group.
                                        </div>
                                    )}
                                    {group.tasks.map((task) => (
                                        <div
                                            key={task.id}
                                            className={`border-l-3 ${statusAccentStyles[group.value]}`}
                                        >
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onSelectTask(task.id)
                                                }
                                                className={`grid w-full gap-3 px-4 py-3 text-left transition-colors hover:bg-stone-50 focus:bg-stone-50 focus:outline-none lg:grid-cols-[minmax(320px,1fr)_220px_140px_120px] dark:hover:bg-zinc-800/60 dark:focus:bg-zinc-800/60 ${
                                                    selectedTaskId === task.id
                                                        ? 'bg-emerald-50/80 dark:bg-emerald-950/20'
                                                        : ''
                                                }`}
                                            >
                                                <div className="min-w-0">
                                                    <div className="flex min-w-0 items-center gap-2">
                                                        <span
                                                            className={`h-2.5 w-2.5 shrink-0 rounded-full ${statusDotStyles[group.value]}`}
                                                        />
                                                        <span className="truncate text-[13px] font-medium text-gray-900 dark:text-gray-100">
                                                            {task.name}
                                                        </span>
                                                    </div>
                                                    {task.description && (
                                                        <p className="mt-1 line-clamp-1 pl-4 text-[12px] text-gray-500 dark:text-gray-400">
                                                            {task.description}
                                                        </p>
                                                    )}
                                                </div>

                                                <div className="flex min-w-0 items-center">
                                                    {task.assignees
                                                        .slice(0, 5)
                                                        .map((assignee) => (
                                                            <span
                                                                key={
                                                                    assignee.id
                                                                }
                                                                title={
                                                                    assignee.name
                                                                }
                                                                className="-ml-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-stone-100 font-mono text-[10px] font-semibold text-gray-500 ring-2 ring-white first:ml-0 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-900"
                                                            >
                                                                {assignee.name
                                                                    .charAt(0)
                                                                    .toUpperCase()}
                                                            </span>
                                                        ))}
                                                    {task.assignees.length >
                                                        5 && (
                                                        <span className="ml-1 font-mono text-[11px] text-gray-400">
                                                            +
                                                            {task.assignees
                                                                .length - 5}
                                                        </span>
                                                    )}
                                                    {task.assignees.length ===
                                                        0 && (
                                                        <span className="font-mono text-[11px] text-gray-400">
                                                            Unassigned
                                                        </span>
                                                    )}
                                                </div>

                                                <div
                                                    className={`flex items-center gap-1.5 font-mono text-[12px] ${
                                                        isOverdue(
                                                            task.due_date,
                                                        ) &&
                                                        group.value !== 'done'
                                                            ? 'text-rose-600 dark:text-rose-400'
                                                            : 'text-gray-500 dark:text-gray-400'
                                                    }`}
                                                >
                                                    <CalendarDays className="h-3.5 w-3.5 text-current" />
                                                    {formatDate(task.due_date)}
                                                </div>

                                                <div className="flex items-center gap-1.5 font-mono text-[12px] text-gray-500 dark:text-gray-400">
                                                    <MessageSquareText className="h-3.5 w-3.5 text-gray-400" />
                                                    {task.comments.length}
                                                </div>
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        );
                    })}
            </div>
        </div>
    );
}
