import { CheckCircle2, Circle, Clock3 } from 'lucide-react';
import { Task, TaskStatus } from './types';

export const STATUS_OPTIONS: { label: string; value: TaskStatus }[] = [
    { label: 'To do', value: 'todo' },
    { label: 'In progress', value: 'in_progress' },
    { label: 'Done', value: 'done' },
];

export const statusStyles: Record<TaskStatus, string> = {
    todo: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    in_progress:
        'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
    done: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
};

export const statusAccentStyles: Record<TaskStatus, string> = {
    todo: 'border-l-slate-400',
    in_progress: 'border-l-violet-500',
    done: 'border-l-emerald-500',
};

export const statusDotStyles: Record<TaskStatus, string> = {
    todo: 'bg-slate-400',
    in_progress: 'bg-violet-500',
    done: 'bg-emerald-500',
};

export const statusHeaderStyles: Record<TaskStatus, string> = {
    todo: 'bg-slate-500 text-white',
    in_progress: 'bg-violet-600 text-white',
    done: 'bg-emerald-600 text-white',
};

export const statusIcons: Record<TaskStatus, typeof Circle> = {
    todo: Circle,
    in_progress: Clock3,
    done: CheckCircle2,
};

export const getTaskStatus = (task: Task): TaskStatus => {
    if (
        task.assignees.length > 0 &&
        task.assignees.every((assignee) => assignee.pivot.status === 'done')
    ) {
        return 'done';
    }

    if (
        task.assignees.some(
            (assignee) => assignee.pivot.status === 'in_progress',
        )
    ) {
        return 'in_progress';
    }

    return 'todo';
};

export const formatDate = (value: string | null) => {
    if (!value) {
        return 'No due date';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
};

export const formatTimestamp = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));

export const isOverdue = (value: string | null) => {
    if (!value) {
        return false;
    }

    const dueDate = new Date(value);
    dueDate.setHours(23, 59, 59, 999);

    return dueDate < new Date();
};
