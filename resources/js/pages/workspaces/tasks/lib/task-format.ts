import type { TaskPriority } from '../types';

export type PriorityMeta = {
    label: string;
    color: string;
};

/**
 * Priority flags, drawn from a muted palette rather than the neon
 * defaults so they sit against paper without shouting.
 */
const PRIORITY_META: Record<TaskPriority, PriorityMeta> = {
    urgent: { label: 'Urgent', color: '#CC3B2E' },
    high: { label: 'High', color: '#CE9C3E' },
    normal: { label: 'Normal', color: '#2D7CC9' },
    low: { label: 'Low', color: '#8E90A6' },
};

export function priorityMeta(
    priority: TaskPriority | null,
): PriorityMeta | null {
    return priority === null ? null : PRIORITY_META[priority];
}

export type DueMeta = {
    label: string;
    tone: string;
};

/** Describe a due date relative to today, so overdue work reads as urgent. */
export function dueMeta(
    due: string | null,
    completed: boolean,
): DueMeta | null {
    if (due === null) {
        return null;
    }

    const target = new Date(due);
    const startOfToday = new Date();
    startOfToday.setHours(0, 0, 0, 0);

    const days = Math.round(
        (new Date(target).setHours(0, 0, 0, 0) - startOfToday.getTime()) /
            86_400_000,
    );

    const label = target.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });

    if (completed) {
        return { label, tone: 'text-muted-foreground' };
    }

    if (days < 0) {
        return { label, tone: 'text-destructive font-semibold' };
    }

    if (days === 0) {
        return {
            label: 'Today',
            tone: 'text-amber-600 dark:text-amber-400 font-semibold',
        };
    }

    if (days === 1) {
        return {
            label: 'Tomorrow',
            tone: 'text-amber-600 dark:text-amber-400',
        };
    }

    return { label, tone: 'text-muted-foreground' };
}

/** Build up to two initials for an avatar bubble. */
export function initials(name: string): string {
    return name
        .split(' ')
        .filter((part) => part !== '')
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

/** Fade a hex colour to a translucent surface tint. */
export function tint(color: string | null | undefined, alpha = 0.16): string {
    if (!color || !/^#[0-9a-f]{6}$/i.test(color)) {
        return `rgba(142, 144, 166, ${alpha})`;
    }

    const value = Number.parseInt(color.slice(1), 16);

    return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
}

/** Fall back to the cool neutral when a status carries no colour of its own. */
export function statusColor(color: string | null | undefined): string {
    return color && /^#[0-9a-f]{6}$/i.test(color) ? color : '#8E90A6';
}

/**
 * Describe a byte count in the largest unit that keeps it under four digits.
 *
 * Fixed units and a plain `toFixed` rather than `Intl`, so the output does not
 * depend on the viewer's locale.
 */
export function formatBytes(bytes: number): string {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }

    return `${unit === 0 ? size : size.toFixed(size < 10 ? 1 : 0)} ${units[unit]}`;
}
