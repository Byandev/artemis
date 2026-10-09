import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { statusColor } from '../lib/task-format';
import type { TaskStatus, TaskStatusType } from '../types';

/** The three sections a workflow reads as, in the order work moves through. */
const GROUPS: { label: string; types: TaskStatusType[] }[] = [
    { label: 'Not started', types: ['not_started'] },
    { label: 'Active', types: ['active'] },
    { label: 'Closed', types: ['done', 'closed'] },
];

/**
 * An arc from twelve o'clock, clockwise, covering `fraction` of the circle.
 * Used to show progress through the active states: the further along a status
 * sits, the fuller its glyph.
 */
function wedgePath(fraction: number, cx = 8, cy = 8, r = 5): string {
    const clamped = Math.min(Math.max(fraction, 0.08), 0.995);
    const angle = clamped * 2 * Math.PI;
    const x = cx + r * Math.sin(angle);
    const y = cy - r * Math.cos(angle);
    const largeArc = clamped > 0.5 ? 1 : 0;

    return `M ${cx} ${cy} L ${cx} ${cy - r} A ${r} ${r} 0 ${largeArc} 1 ${x} ${y} Z`;
}

/**
 * The status glyph, shaped by the status type the way a workflow reads it:
 * a dashed ring for work not begun, a filling dial for work under way, and a
 * solid tick once it is finished.
 */
export function StatusGlyph({
    status,
    progress = 0.5,
    className,
}: {
    status: TaskStatus | undefined;
    /** Position within the active run, 0–1. Ignored by the other types. */
    progress?: number;
    className?: string;
}) {
    const color = statusColor(status?.color);
    const type = status?.type ?? 'not_started';

    return (
        <svg
            viewBox="0 0 16 16"
            aria-hidden="true"
            className={cn('size-4 shrink-0', className)}
        >
            {type === 'not_started' && (
                <circle
                    cx="8"
                    cy="8"
                    r="5.6"
                    fill="none"
                    stroke={color}
                    strokeWidth="2"
                    strokeDasharray="2.6 2.3"
                    strokeLinecap="round"
                />
            )}

            {type === 'active' && (
                <>
                    <circle
                        cx="8"
                        cy="8"
                        r="5.6"
                        fill="none"
                        stroke={color}
                        strokeWidth="2"
                    />
                    <path d={wedgePath(progress)} fill={color} />
                </>
            )}

            {(type === 'done' || type === 'closed') && (
                <>
                    <circle cx="8" cy="8" r="6.6" fill={color} />
                    <path
                        d="M5 8.2 L7.1 10.3 L11 6.1"
                        fill="none"
                        stroke="#fff"
                        strokeWidth="1.9"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </>
            )}
        </svg>
    );
}

/** Where a status sits within its space's run of active states. */
export function activeProgress(
    status: TaskStatus,
    statuses: TaskStatus[],
): number {
    const active = statuses.filter((item) => item.type === 'active');
    const index = active.findIndex((item) => item.id === status.id);

    return index === -1 ? 0.5 : (index + 1) / (active.length + 1);
}

type Props = {
    statuses: TaskStatus[];
    value: number;
    disabled?: boolean;
    align?: 'start' | 'end';
    onChange: (statusId: number) => void;
    children: ReactNode;
};

/**
 * Below this many statuses the group headings describe more than they organise
 * — three labels to sort three items is chrome, not structure — so the menu
 * falls back to a plain list that still runs in type order.
 */
const GROUPING_THRESHOLD = 5;

/** The status menu, grouped by type once a workflow is big enough to need it. */
export function StatusPicker({
    statuses,
    value,
    disabled = false,
    align = 'end',
    onChange,
    children,
}: Props) {
    const groups = GROUPS.map((group) => ({
        label: group.label,
        items: statuses.filter((status) => group.types.includes(status.type)),
    })).filter((group) => group.items.length > 0);

    const grouped = statuses.length >= GROUPING_THRESHOLD && groups.length > 1;

    const row = (status: TaskStatus) => {
        const selected = status.id === value;

        return (
            <DropdownMenuItem
                key={status.id}
                onSelect={() => onChange(status.id)}
                className="gap-2.5"
            >
                <StatusGlyph
                    status={status}
                    progress={activeProgress(status, statuses)}
                />
                <span
                    className={cn(
                        'min-w-0 flex-1 truncate font-mono text-xs tracking-[0.02em] uppercase',
                        selected && 'font-semibold',
                    )}
                >
                    {status.name}
                </span>
                {selected && <Check className="size-4 shrink-0" />}
            </DropdownMenuItem>
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild disabled={disabled}>
                {children}
            </DropdownMenuTrigger>
            <DropdownMenuContent align={align} className="w-60">
                {grouped
                    ? groups.map((group) => (
                          <DropdownMenuGroup key={group.label}>
                              {/*
                               * Indented to the item text, so the menu has one
                               * text column rather than a heading hanging off
                               * to the left of everything it labels.
                               */}
                              <DropdownMenuLabel className="pt-2 pl-[calc(0.5rem+1rem+0.625rem)] text-xs font-normal text-muted-foreground">
                                  {group.label}
                              </DropdownMenuLabel>
                              {group.items.map(row)}
                          </DropdownMenuGroup>
                      ))
                    : groups.flatMap((group) => group.items).map(row)}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
