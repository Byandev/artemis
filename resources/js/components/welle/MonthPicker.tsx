import { ChevronLeft, ChevronRight } from 'lucide-react';

/** This month as the endpoints name it — `YYYY-MM`. */
export function currentMonth(): string {
    return monthValue(new Date());
}

/**
 * The month the URL asks for, or this one.
 *
 * The month lives in the address bar rather than in state alone, so a reload
 * comes back to the month that was being read and a link to it carries the
 * month with it. Anything unreadable falls back to this month — the same thing
 * the endpoints do with a `?month=` they cannot parse.
 */
export function monthFromUrl(): string {
    if (typeof window === 'undefined') return currentMonth();

    const asked = new URLSearchParams(window.location.search).get('month');

    return asked && /^\d{4}-\d{2}$/.test(asked) ? asked : currentMonth();
}

/**
 * Put the month in the address bar, without a visit.
 *
 * `replaceState` rather than Inertia's router: the figures are fetched over
 * XHR, so there is nothing for the server to re-render — the URL just has to
 * agree with what is on screen. The existing history state is passed back
 * untouched so Inertia's own record of the page survives.
 */
export function rememberMonth(month: string): void {
    if (typeof window === 'undefined') return;

    const url = new URL(window.location.href);
    url.searchParams.set('month', month);

    window.history.replaceState(window.history.state, '', url);
}

/**
 * Which month every figure on the page is read over — a step back, a step
 * forward, and the month between them.
 *
 * Forward stops at this month rather than wrapping or running on: there is
 * nothing to read in a month that has not happened, so the step that would ask
 * for one is simply not offered.
 */
export function MonthPicker({
    value,
    onChange,
}: {
    /** `YYYY-MM`. */
    value: string;
    onChange: (month: string) => void;
}) {
    const atCurrentMonth = value >= currentMonth();

    return (
        <div className="flex h-8 items-center gap-0.5 rounded-[10px] border border-black/6 bg-stone-100 px-1 dark:border-white/6 dark:bg-zinc-800">
            <button
                type="button"
                onClick={() => onChange(shiftMonth(value, -1))}
                aria-label="Previous month"
                title="Previous month"
                className={STEP}
            >
                <ChevronLeft className="h-3.5 w-3.5" />
            </button>

            <span className="min-w-[108px] text-center text-[12px] text-gray-800 dark:text-gray-200">
                {monthLabel(value)}
            </span>

            <button
                type="button"
                onClick={() => onChange(shiftMonth(value, 1))}
                disabled={atCurrentMonth}
                aria-label="Next month"
                title="Next month"
                className={STEP}
            >
                <ChevronRight className="h-3.5 w-3.5" />
            </button>
        </div>
    );
}

/** Either step. Disabled is the forward one at this month, and says so. */
const STEP =
    'flex h-6 w-6 items-center justify-center rounded-[7px] text-gray-500 transition-colors hover:bg-white hover:text-gray-800 disabled:pointer-events-none disabled:opacity-30 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-gray-100';

/** `YYYY-MM`, `$months` along. The Date does the year rollover. */
function shiftMonth(value: string, months: number): string {
    const [year, month] = value.split('-').map(Number);

    return monthValue(new Date(year, month - 1 + months, 1));
}

/** "September 2026" — the month named in plain words. */
function monthLabel(value: string): string {
    const [year, month] = value.split('-').map(Number);

    return new Date(year, month - 1, 1).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });
}

/** A date as `YYYY-MM`, read in the browser's own timezone. */
function monthValue(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}
