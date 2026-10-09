import { cn } from '@/lib/utils';
import { Check, Flag } from 'lucide-react';
import type * as React from 'react';
import { initials, priorityMeta, statusColor, tint } from '../lib/task-format';
import type { Label, TaskPriority, TaskStatus, UserSummary } from '../types';
import { activeProgress, StatusGlyph } from './status-picker';

/** The round status marker that sits at the head of every task. */
export function StatusDot({
    status,
    complete,
    onClick,
    disabled,
    label,
}: {
    status: TaskStatus | undefined;
    complete: boolean;
    onClick: () => void;
    disabled: boolean;
    label: string;
}) {
    const color = statusColor(status?.color);

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={status?.name ?? 'Status'}
            aria-label={label}
            className={cn(
                'group/dot flex size-[18px] shrink-0 items-center justify-center rounded-full border-[1.5px] transition-all duration-200 ease-out',
                complete
                    ? 'border-transparent'
                    : 'hover:scale-110 hover:shadow-[0_0_0_3px_var(--dot-glow)]',
            )}
            style={
                {
                    borderColor: color,
                    backgroundColor: complete ? color : 'transparent',
                    '--dot-glow': tint(color, 0.2),
                } as React.CSSProperties
            }
        >
            <Check
                className={cn(
                    'size-2.5 text-white transition-opacity duration-200',
                    complete
                        ? 'opacity-100'
                        : 'opacity-0 group-hover/dot:opacity-40',
                )}
                strokeWidth={3.5}
            />
        </button>
    );
}

/**
 * Names a status on group headers and board columns. The status colour is
 * carried by the mark rather than the text, so author-chosen colours can never
 * drop the label below a readable contrast.
 */
export function StatusPill({
    status,
    count,
    allStatuses,
}: {
    status: TaskStatus;
    count: number;
    /** Needed to place this status within the run of active states. */
    allStatuses: TaskStatus[];
}) {
    return (
        <div className="flex min-w-0 items-center gap-2">
            <StatusGlyph
                status={status}
                progress={activeProgress(status, allStatuses)}
                className="size-3.5"
            />
            <span className="truncate font-mono text-[0.6875rem] tracking-[0.06em] uppercase">
                {status.name}
            </span>
            <span className="text-[0.6875rem] font-medium text-muted-foreground tabular-nums">
                {count}
            </span>
        </div>
    );
}

export function PriorityFlag({ priority }: { priority: TaskPriority | null }) {
    const meta = priorityMeta(priority);

    if (meta === null) {
        return null;
    }

    return (
        <span title={`${meta.label} priority`}>
            <Flag
                className="size-3.5"
                style={{ color: meta.color, fill: meta.color }}
            />
        </span>
    );
}

export function Assignees({ users }: { users: UserSummary[] | undefined }) {
    if (users === undefined || users.length === 0) {
        return null;
    }

    const shown = users.slice(0, 3);
    const overflow = users.length - shown.length;

    return (
        <div className="flex -space-x-1.5">
            {shown.map((user) => (
                <span
                    key={user.id}
                    title={user.name}
                    className="flex size-6 items-center justify-center rounded-full bg-emerald-600 text-[0.625rem] font-semibold text-white ring-2 ring-white dark:bg-emerald-500 dark:ring-zinc-900"
                >
                    {initials(user.name)}
                </span>
            ))}

            {overflow > 0 && (
                <span
                    title={users
                        .slice(3)
                        .map((user) => user.name)
                        .join(', ')}
                    className="flex size-6 items-center justify-center rounded-full bg-muted text-[0.625rem] font-semibold text-muted-foreground ring-2 ring-white dark:ring-zinc-900"
                >
                    +{overflow}
                </span>
            )}
        </div>
    );
}

export function LabelChips({ labels }: { labels: Label[] | undefined }) {
    if (labels === undefined || labels.length === 0) {
        return null;
    }

    return (
        <>
            {labels.map((label) => {
                const color = statusColor(label.color);

                return (
                    <span
                        key={label.id}
                        className="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[0.625rem] font-semibold tracking-[0.06em] text-foreground/80 uppercase"
                        style={{
                            borderColor: tint(color, 0.35),
                            backgroundColor: tint(color, 0.1),
                        }}
                    >
                        <span
                            aria-hidden="true"
                            className="size-1 rounded-full"
                            style={{ backgroundColor: color }}
                        />
                        {label.name}
                    </span>
                );
            })}
        </>
    );
}
