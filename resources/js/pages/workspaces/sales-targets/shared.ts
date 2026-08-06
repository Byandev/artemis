/** Types and date helpers shared by the Sales Targets index and detail pages. */

export interface TeamTarget {
    id: number;
    team_id: number;
    /**
     * decimal(15,2), so it arrives as a string over the wire. Null when the team
     * is on the target for its ad budget alone.
     */
    sales_target: string | null;
    ad_budget: string | null;
    team?: { id: number; name: string };
}

export interface SalesTarget {
    id: number;
    date: string;
    name: string;
    /** The ROAS the day is judged on, when one was set. */
    target_roas: string | null;
    team_targets: TeamTarget[];
}

/**
 * Parse "2026-07-07" as a local date. `new Date(iso)` reads a bare date as UTC
 * midnight, which renders as the previous day anywhere west of Greenwich.
 */
export function parseLocalDate(iso: string): Date {
    const [year, month, day] = iso.split('-').map(Number);
    return new Date(year, month - 1, day);
}

/** Days from today, so a target can say where it stands rather than only when. */
export function dayOffset(iso: string): number {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.round(
        (parseLocalDate(iso).getTime() - today.getTime()) / 86_400_000,
    );
}

const dateFormatter = new Intl.DateTimeFormat('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
});

export function formatTargetDate(iso: string): string {
    return dateFormatter.format(parseLocalDate(iso));
}

const longDateFormatter = new Intl.DateTimeFormat('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
});

/** "July 7, 2026" — the spelled-out form the public board header uses. */
export function formatLongDate(iso: string): string {
    return longDateFormatter.format(parseLocalDate(iso));
}

const pesoFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0,
});

/**
 * Full-digit pesos. The shared `currencyFormatter` compacts anything over 10k
 * to "₱1.24M", which is the wrong trade on a board people read for the exact
 * figure.
 */
export function formatPeso(value: number): string {
    return pesoFormatter.format(value);
}

/**
 * Where a target stands relative to today. Purely a function of its date —
 * nothing here measures actual sales, so "Closed" means the day has passed,
 * not that the target was hit or missed.
 */
export function statusFor(offset: number): { label: string; pill: string } {
    if (offset > 0) {
        return {
            label: 'Coming Soon',
            pill: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        };
    }

    if (offset === 0) {
        return {
            label: 'Today',
            pill: 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400',
        };
    }

    return {
        label: 'Closed',
        pill: 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
    };
}

/** The day's target sale — the per-team amounts summed. */
export function totalOf(target: SalesTarget): number {
    return target.team_targets.reduce(
        (sum, row) => sum + Number(row.sales_target ?? 0),
        0,
    );
}

/** The day's ad budget — the per-team budgets summed. */
export function budgetOf(target: SalesTarget): number {
    return target.team_targets.reduce(
        (sum, row) => sum + Number(row.ad_budget ?? 0),
        0,
    );
}
