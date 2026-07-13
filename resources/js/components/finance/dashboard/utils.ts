import { format, parseISO } from 'date-fns';

/** Full peso amount with grouping and 2 decimals. */
export const money = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

/** Compact peso amount for dense axes/legends (e.g. 12.3k, 1.2M). */
export const compact = (v: number) => {
    const abs = Math.abs(v);
    if (abs >= 1_000_000) return `${(v / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `${(v / 1_000).toFixed(1)}k`;
    return `${Math.round(v)}`;
};

/** Human-friendly label for a period bucket key coming from the backend. */
export const periodLabel = (period: string, unit: string) => {
    if (unit === 'day') {
        try {
            return format(parseISO(period), 'd MMM');
        } catch {
            return period;
        }
    }
    if (unit === 'month') {
        try {
            return format(parseISO(`${period}-01`), 'MMM yyyy');
        } catch {
            return period;
        }
    }
    // Weekly keys arrive as "YYYY-Www" — already compact enough.
    return period;
};

/** Prettify a snake_case transaction_type into a readable label. */
export const humanizeCategory = (category: string | null) => {
    if (!category) return 'Uncategorized';
    return category
        .split('_')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
};
