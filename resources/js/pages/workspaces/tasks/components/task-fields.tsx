import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { CalendarDays, Flag, Tag, UserPlus } from 'lucide-react';
import {
    dueMeta,
    initials,
    priorityMeta,
    statusColor,
} from '../lib/task-format';
import type { Label, Task, TaskPriority, UserSummary } from '../types';
import { Assignees, LabelChips, PriorityFlag } from './task-bits';

type Change = (payload: Record<string, unknown>) => void;

const PRIORITIES: TaskPriority[] = ['urgent', 'high', 'normal', 'low'];

/**
 * `<input type="date">` speaks `YYYY-MM-DD` in local time, so slice the local
 * parts rather than the ISO string, which would shift the day for anyone east
 * or west of UTC. Only ever called from an open menu, never during SSR.
 */
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

/** Midnight local on the given day offset, as an ISO instant for the API. */
function daysFromToday(offset: number): string {
    const date = new Date();

    date.setHours(0, 0, 0, 0);
    date.setDate(date.getDate() + offset);

    return date.toISOString();
}

export function PriorityPicker({
    task,
    disabled,
    onChange,
    className,
}: {
    task: Task;
    disabled: boolean;
    onChange: Change;
    className?: string;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    disabled={disabled}
                    aria-label={`Priority for ${task.name}`}
                    className={cn(
                        'flex size-6 items-center justify-center rounded-md transition-colors duration-200 ease-out hover:bg-accent',
                        className,
                    )}
                >
                    {task.priority === null ? (
                        <Flag className="size-3.5 text-muted-foreground/50" />
                    ) : (
                        <PriorityFlag priority={task.priority} />
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuLabel>Priority</DropdownMenuLabel>
                {PRIORITIES.map((priority) => {
                    const meta = priorityMeta(priority);

                    return (
                        <DropdownMenuItem
                            key={priority}
                            onSelect={() => onChange({ priority })}
                        >
                            <Flag
                                style={{
                                    color: meta?.color,
                                    fill: meta?.color,
                                }}
                            />
                            {meta?.label}
                        </DropdownMenuItem>
                    );
                })}
                {task.priority !== null && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => onChange({ priority: null })}
                        >
                            Clear priority
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function DuePicker({
    task,
    disabled,
    onChange,
    className,
}: {
    task: Task;
    disabled: boolean;
    onChange: Change;
    className?: string;
}) {
    const due = dueMeta(task.due_at, task.completed);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    disabled={disabled}
                    aria-label={`Due date for ${task.name}`}
                    className={cn(
                        'flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs transition-colors duration-200 ease-out hover:bg-accent',
                        due ? due.tone : 'text-muted-foreground/60',
                        className,
                    )}
                >
                    {due === null && <CalendarDays className="size-3" />}
                    {due?.label ?? 'Due date'}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuLabel>Due date</DropdownMenuLabel>
                <DropdownMenuItem
                    onSelect={() => onChange({ due_at: daysFromToday(0) })}
                >
                    Today
                </DropdownMenuItem>
                <DropdownMenuItem
                    onSelect={() => onChange({ due_at: daysFromToday(1) })}
                >
                    Tomorrow
                </DropdownMenuItem>
                <DropdownMenuItem
                    onSelect={() => onChange({ due_at: daysFromToday(7) })}
                >
                    Next week
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                {/*
                 * Radix menus swallow typing for typeahead, so the date field
                 * keeps its own keystrokes.
                 */}
                <div
                    className="px-1 py-1"
                    onKeyDown={(event) => event.stopPropagation()}
                >
                    <input
                        type="date"
                        value={toDateInput(task.due_at)}
                        aria-label="Pick a due date"
                        onChange={(event) =>
                            onChange({
                                due_at:
                                    event.target.value === ''
                                        ? null
                                        : new Date(
                                              `${event.target.value}T00:00:00`,
                                          ).toISOString(),
                            })
                        }
                        className="h-8 w-full rounded-lg border border-black/8 bg-white px-2 text-xs outline-none focus-visible:border-emerald-500 focus-visible:ring-4 focus-visible:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900"
                    />
                </div>

                {task.due_at !== null && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => onChange({ due_at: null })}
                        >
                            Clear due date
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function AssigneePicker({
    task,
    members,
    disabled,
    onChange,
    className,
}: {
    task: Task;
    members: UserSummary[];
    disabled: boolean;
    onChange: Change;
    className?: string;
}) {
    const assigned = task.assignees ?? [];
    const assignedIds = assigned.map((user) => user.id);

    function toggle(user: UserSummary) {
        onChange({
            assignee_ids: assignedIds.includes(user.id)
                ? assignedIds.filter((id) => id !== user.id)
                : [...assignedIds, user.id],
        });
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    disabled={disabled}
                    aria-label={`Assignees for ${task.name}`}
                    className={cn(
                        'flex items-center justify-center rounded-full transition-colors duration-200 ease-out',
                        className,
                    )}
                >
                    {assigned.length > 0 ? (
                        <Assignees users={assigned} />
                    ) : (
                        <span className="flex size-6 items-center justify-center rounded-full border border-dashed border-muted-foreground/40 text-muted-foreground/60 transition-colors hover:border-muted-foreground hover:text-foreground">
                            <UserPlus className="size-3" />
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>Assignees</DropdownMenuLabel>

                {members.length === 0 ? (
                    <p className="px-2 py-1.5 text-xs text-muted-foreground">
                        Nobody else has access to this space yet.
                    </p>
                ) : (
                    members.map((user) => (
                        <DropdownMenuCheckboxItem
                            key={user.id}
                            checked={assignedIds.includes(user.id)}
                            onSelect={(event) => {
                                // Keep the menu open for multi-select.
                                event.preventDefault();
                                toggle(user);
                            }}
                        >
                            <span className="mr-1 flex size-5 items-center justify-center rounded-full bg-emerald-600 text-[0.5625rem] font-semibold text-white dark:bg-emerald-500">
                                {initials(user.name)}
                            </span>
                            <span className="truncate">{user.name}</span>
                        </DropdownMenuCheckboxItem>
                    ))
                )}

                {assigned.length > 0 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => onChange({ assignee_ids: [] })}
                        >
                            Clear assignees
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function LabelPicker({
    task,
    labels,
    disabled,
    onChange,
    className,
}: {
    task: Task;
    labels: Label[];
    disabled: boolean;
    onChange: Change;
    className?: string;
}) {
    const attached = task.labels ?? [];
    const attachedIds = attached.map((label) => label.id);

    function toggle(label: Label) {
        onChange({
            label_ids: attachedIds.includes(label.id)
                ? attachedIds.filter((id) => id !== label.id)
                : [...attachedIds, label.id],
        });
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    disabled={disabled}
                    aria-label={`Labels for ${task.name}`}
                    className={cn(
                        'flex min-h-7 flex-wrap items-center gap-1 rounded-md px-1.5 py-1 text-left transition-colors duration-200 ease-out hover:bg-accent',
                        className,
                    )}
                >
                    {attached.length > 0 ? (
                        <LabelChips labels={attached} />
                    ) : (
                        <span className="flex items-center gap-1 text-xs text-muted-foreground">
                            <Tag className="size-3" />
                            Add labels
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-56">
                <DropdownMenuLabel>Labels</DropdownMenuLabel>

                {labels.length === 0 ? (
                    <p className="px-2 py-1.5 text-xs text-muted-foreground">
                        This space has no labels yet.
                    </p>
                ) : (
                    labels.map((label) => (
                        <DropdownMenuCheckboxItem
                            key={label.id}
                            checked={attachedIds.includes(label.id)}
                            onSelect={(event) => {
                                event.preventDefault();
                                toggle(label);
                            }}
                        >
                            <span
                                aria-hidden="true"
                                className="mr-1 size-2 rounded-full"
                                style={{
                                    backgroundColor: statusColor(label.color),
                                }}
                            />
                            <span className="truncate">{label.name}</span>
                        </DropdownMenuCheckboxItem>
                    ))
                )}

                {attached.length > 0 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => onChange({ label_ids: [] })}
                        >
                            Clear labels
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
