import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    CalendarDays,
    Circle,
    Pencil,
    Repeat2,
    Send,
    Trash2,
    UserCheck,
    UsersRound,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    formatDate,
    formatTimestamp,
    isOverdue,
    RECURRENCE_LABELS,
    STATUS_OPTIONS,
    statusHeaderStyles,
    statusStyles,
} from './task-utils';
import { Task, TaskStatus } from './types';

interface TaskDetailsDrawerProps {
    commentValue: string;
    onCommentChange: (value: string) => void;
    onDelete: () => void;
    onEdit: () => void;
    onOpenChange: (open: boolean) => void;
    onUpdateSelectedStatuses: (status: TaskStatus, userIds: number[]) => void;
    onSubmitComment: () => void;
    onUpdateStatus: (userId: number, status: TaskStatus) => void;
    open: boolean;
    task: Task | null;
    taskStatus: TaskStatus | null;
}

export function TaskDetailsDrawer({
    commentValue,
    onCommentChange,
    onDelete,
    onEdit,
    onOpenChange,
    onUpdateSelectedStatuses,
    onSubmitComment,
    onUpdateStatus,
    open,
    task,
    taskStatus,
}: TaskDetailsDrawerProps) {
    const [bulkStatus, setBulkStatus] = useState<TaskStatus>('todo');
    const [selectedAssigneeIds, setSelectedAssigneeIds] = useState<number[]>(
        [],
    );

    useEffect(() => {
        setSelectedAssigneeIds([]);
    }, [task?.id]);

    const allAssigneesSelected =
        (task?.assignees.length ?? 0) > 0 &&
        (task?.assignees.every((assignee) =>
            selectedAssigneeIds.includes(assignee.id),
        ) ??
            false);

    const toggleAssigneeSelection = (assigneeId: number) => {
        setSelectedAssigneeIds((current) =>
            current.includes(assigneeId)
                ? current.filter((id) => id !== assigneeId)
                : [...current, assigneeId],
        );
    };

    const toggleAllAssignees = () => {
        if (!task) {
            return;
        }

        setSelectedAssigneeIds(
            allAssigneesSelected
                ? []
                : task.assignees.map((assignee) => assignee.id),
        );
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full border-black/6 bg-white p-0 sm:max-w-2xl dark:border-white/8 dark:bg-zinc-900">
                {task && taskStatus && (
                    <>
                        <SheetHeader className="border-b border-black/6 px-5 py-4 text-left dark:border-white/8">
                            <div className="pr-8">
                                <div className="mb-2 flex flex-wrap items-center gap-2">
                                    <span
                                        className={`inline-flex h-6 items-center rounded-md px-2.5 font-mono text-[11px] font-semibold tracking-wide uppercase ${statusHeaderStyles[taskStatus]}`}
                                    >
                                        {
                                            STATUS_OPTIONS.find(
                                                (status) =>
                                                    status.value === taskStatus,
                                            )?.label
                                        }
                                    </span>
                                    {isOverdue(task.due_date) &&
                                        taskStatus !== 'done' && (
                                            <span className="inline-flex h-6 items-center rounded-md bg-rose-100 px-2.5 font-mono text-[11px] font-medium text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">
                                                Overdue
                                            </span>
                                        )}
                                    <button
                                        type="button"
                                        onClick={onEdit}
                                        className="inline-flex h-6 items-center gap-1 rounded-md bg-stone-100 px-2 font-mono text-[11px] font-medium text-gray-600 transition-colors hover:bg-stone-200 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                                    >
                                        <Pencil className="h-3 w-3" />
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        onClick={onDelete}
                                        className="inline-flex h-6 items-center gap-1 rounded-md bg-rose-50 px-2 font-mono text-[11px] font-medium text-rose-600 transition-colors hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950/70"
                                    >
                                        <Trash2 className="h-3 w-3" />
                                        Delete
                                    </button>
                                </div>
                                <SheetTitle className="text-[18px] leading-6 font-semibold text-gray-900 dark:text-gray-100">
                                    {task.name}
                                </SheetTitle>
                                <SheetDescription className="mt-1 font-mono text-[11px] text-gray-500 dark:text-gray-400">
                                    Created by {task.creator?.name ?? 'Unknown'}
                                </SheetDescription>
                            </div>
                        </SheetHeader>

                        <div className="min-h-0 flex-1 overflow-y-auto">
                            <div className="space-y-4 px-5 py-4">
                                <section className="rounded-xl bg-stone-50/80 p-4 dark:bg-zinc-950/70">
                                    <div className="mb-3 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                        Properties
                                    </div>
                                    <div className="grid gap-x-6 gap-y-4 sm:grid-cols-4">
                                        <div>
                                            <div className="mb-1 flex items-center gap-1.5 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                                <Circle className="h-3.5 w-3.5" />
                                                Status
                                            </div>
                                            <span
                                                className={`inline-flex rounded-full px-2 py-1 font-mono text-[11px] font-medium ${statusStyles[taskStatus]}`}
                                            >
                                                {
                                                    STATUS_OPTIONS.find(
                                                        (status) =>
                                                            status.value ===
                                                            taskStatus,
                                                    )?.label
                                                }
                                            </span>
                                        </div>
                                        <div>
                                            <div className="mb-1 flex items-center gap-1.5 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                                <CalendarDays className="h-3.5 w-3.5" />
                                                Due date
                                            </div>
                                            <p
                                                className={`font-mono text-[12px] ${
                                                    isOverdue(task.due_date) &&
                                                    taskStatus !== 'done'
                                                        ? 'text-rose-600 dark:text-rose-400'
                                                        : 'text-gray-700 dark:text-gray-300'
                                                }`}
                                            >
                                                {formatDate(task.due_date)}
                                            </p>
                                        </div>
                                        <div>
                                            <div className="mb-1 flex items-center gap-1.5 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                                <UsersRound className="h-3.5 w-3.5" />
                                                Assignees
                                            </div>
                                            <p className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                                                {task.assignees.length} total
                                            </p>
                                        </div>
                                        <div>
                                            <div className="mb-1 flex items-center gap-1.5 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                                <Repeat2 className="h-3.5 w-3.5" />
                                                Repeat
                                            </div>
                                            <p className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                                                {
                                                    RECURRENCE_LABELS[
                                                        task.recurrence
                                                    ]
                                                }
                                            </p>
                                            {task.recurrence !== 'none' &&
                                                task.recurring_until && (
                                                    <p className="mt-0.5 font-mono text-[11px] text-gray-400">
                                                        Until{' '}
                                                        {formatDate(
                                                            task.recurring_until,
                                                        )}
                                                    </p>
                                                )}
                                        </div>
                                    </div>
                                </section>

                                <section className="rounded-xl bg-stone-50/80 p-4 dark:bg-zinc-950/70">
                                    <div className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                        Details
                                    </div>
                                    {task.description ? (
                                        <p className="mt-2 text-[13px] leading-5 whitespace-pre-wrap text-gray-600 dark:text-gray-300">
                                            {task.description}
                                        </p>
                                    ) : (
                                        <p className="mt-2 font-mono text-[12px] text-gray-400">
                                            No description added.
                                        </p>
                                    )}
                                </section>

                                <section className="rounded-xl bg-stone-50/80 p-4 dark:bg-zinc-950/70">
                                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <div className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                                Assignees
                                            </div>
                                            {task.assignees.length > 0 && (
                                                <button
                                                    type="button"
                                                    onClick={toggleAllAssignees}
                                                    className="mt-1 font-mono text-[11px] text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
                                                >
                                                    {allAssigneesSelected
                                                        ? 'Clear selection'
                                                        : 'Select all'}
                                                </button>
                                            )}
                                        </div>
                                        {task.assignees.length > 0 && (
                                            <div className="flex items-center gap-1.5">
                                                <span className="font-mono text-[11px] text-gray-400">
                                                    {selectedAssigneeIds.length}{' '}
                                                    selected
                                                </span>
                                                <select
                                                    value={bulkStatus}
                                                    onChange={(event) =>
                                                        setBulkStatus(
                                                            event.target
                                                                .value as TaskStatus,
                                                        )
                                                    }
                                                    className="h-8 rounded-lg border border-black/6 bg-white px-2 font-mono text-[11px] text-gray-600 transition-colors outline-none focus:border-emerald-500 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300"
                                                    aria-label="Bulk assignee status"
                                                >
                                                    {STATUS_OPTIONS.map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        onUpdateSelectedStatuses(
                                                            bulkStatus,
                                                            selectedAssigneeIds,
                                                        )
                                                    }
                                                    disabled={
                                                        selectedAssigneeIds.length ===
                                                        0
                                                    }
                                                    className="inline-flex h-8 items-center gap-1.5 rounded-lg bg-emerald-600 px-2.5 font-mono text-[11px] font-medium text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-45"
                                                >
                                                    <UserCheck className="h-3.5 w-3.5" />
                                                    Apply selected
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                    <div className="divide-y divide-black/5 dark:divide-white/5">
                                        {task.assignees.map((assignee) => (
                                            <div
                                                key={assignee.id}
                                                className="flex items-center justify-between gap-3 py-2"
                                            >
                                                <div className="flex min-w-0 items-center gap-2">
                                                    <Checkbox
                                                        checked={selectedAssigneeIds.includes(
                                                            assignee.id,
                                                        )}
                                                        onCheckedChange={() =>
                                                            toggleAssigneeSelection(
                                                                assignee.id,
                                                            )
                                                        }
                                                        className="border-black/12 data-[state=checked]:border-emerald-600 data-[state=checked]:bg-emerald-600 dark:border-white/12"
                                                        aria-label={`Select ${assignee.name}`}
                                                    />
                                                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white font-mono text-[11px] font-semibold text-gray-500 dark:bg-zinc-900 dark:text-gray-300">
                                                        {assignee.name
                                                            .charAt(0)
                                                            .toUpperCase()}
                                                    </span>
                                                    <span className="min-w-0 truncate font-mono text-[12px] text-gray-700 dark:text-gray-300">
                                                        {assignee.name}
                                                    </span>
                                                </div>
                                                <select
                                                    value={
                                                        assignee.pivot.status
                                                    }
                                                    onChange={(event) =>
                                                        onUpdateStatus(
                                                            assignee.id,
                                                            event.target
                                                                .value as TaskStatus,
                                                        )
                                                    }
                                                    className={`h-8 rounded-lg border-0 px-2 font-mono text-[11px] outline-none ${statusStyles[assignee.pivot.status]}`}
                                                >
                                                    {STATUS_OPTIONS.map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </div>
                                        ))}
                                        {task.assignees.length === 0 && (
                                            <div className="font-mono text-[12px] text-gray-400">
                                                No assignees yet.
                                            </div>
                                        )}
                                    </div>
                                </section>

                                <section className="rounded-xl bg-stone-50/80 p-4 dark:bg-zinc-950/70">
                                    <div className="mb-3 flex items-center justify-between gap-3">
                                        <div className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                            Comments
                                        </div>
                                        <div className="font-mono text-[11px] text-gray-400">
                                            {task.comments.length}
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        {task.comments.map((comment) => (
                                            <div
                                                key={comment.id}
                                                className="flex gap-2"
                                            >
                                                <span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-stone-200 font-mono text-[11px] font-semibold text-gray-500 dark:bg-zinc-800 dark:text-gray-300">
                                                    {(comment.user?.name ?? 'U')
                                                        .charAt(0)
                                                        .toUpperCase()}
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <div className="inline-block max-w-full rounded-2xl bg-stone-100 px-3 py-2 dark:bg-zinc-900">
                                                        <div className="mb-0.5 flex items-center gap-2 font-mono text-[11px]">
                                                            <span className="truncate font-medium text-gray-700 dark:text-gray-300">
                                                                {comment.user
                                                                    ?.name ??
                                                                    'Unknown'}
                                                            </span>
                                                            <span className="shrink-0 text-gray-400">
                                                                {formatTimestamp(
                                                                    comment.created_at,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <p className="text-[12px] leading-5 whitespace-pre-wrap text-gray-600 dark:text-gray-300">
                                                            {comment.body}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                        {task.comments.length === 0 && (
                                            <p className="rounded-lg bg-stone-50 p-3 font-mono text-[12px] text-gray-400 dark:bg-zinc-900">
                                                No comments yet.
                                            </p>
                                        )}
                                    </div>
                                </section>
                            </div>
                        </div>

                        <div className="border-t border-black/6 bg-white px-4 py-3 dark:border-white/8 dark:bg-zinc-900">
                            <div className="flex items-center gap-2">
                                <div className="flex min-w-0 flex-1 items-center rounded-full bg-stone-100 pr-1 pl-3 dark:bg-zinc-800">
                                    <Input
                                        value={commentValue}
                                        onChange={(event) =>
                                            onCommentChange(event.target.value)
                                        }
                                        onKeyDown={(event) => {
                                            if (
                                                event.key === 'Enter' &&
                                                !event.shiftKey
                                            ) {
                                                event.preventDefault();
                                                onSubmitComment();
                                            }
                                        }}
                                        className="h-9 min-w-0 flex-1 border-0 bg-transparent px-0 font-mono! text-[12px]! shadow-none placeholder:text-gray-400 focus-visible:ring-0 dark:text-gray-100"
                                        placeholder="Write a comment..."
                                    />
                                    <button
                                        type="button"
                                        onClick={onSubmitComment}
                                        className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-emerald-600 transition-all hover:bg-emerald-50 hover:text-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-950/60"
                                        aria-label="Send comment"
                                    >
                                        <Send className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
