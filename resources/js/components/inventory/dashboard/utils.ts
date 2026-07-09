import { format, parseISO } from 'date-fns';

/** Compact, locale-aware number; em dash for null/undefined. */
export const num = (v: number | null | undefined, decimals = 0) =>
    v == null
        ? '—'
        : Number(v).toLocaleString('en-PH', {
              minimumFractionDigits: decimals,
              maximumFractionDigits: decimals,
          });

/** "30 Jun" short date; tolerant of a plain YYYY-MM-DD string. */
export const shortDate = (d: string | null | undefined) => {
    if (!d) return '—';
    try {
        return format(parseISO(d), 'd MMM');
    } catch {
        return d;
    }
};
