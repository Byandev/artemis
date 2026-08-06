import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { CircleHelp } from 'lucide-react';
import type { FlowKind, FlowState } from './types';

/** Locale-aware count; em dash for null/undefined. */
export const num = (v: number | null | undefined) =>
    v == null ? '—' : Math.round(v).toLocaleString('en-PH');

/** One decimal, for day counts where the fraction carries meaning. */
export const days = (v: number | null | undefined) =>
    v == null ? '—' : `${(Math.round(v * 10) / 10).toLocaleString('en-PH')}d`;

export const pct = (part: number, whole: number) =>
    whole > 0 ? Math.round((part / whole) * 100) : 0;

/**
 * Two series across every PO-flow panel: inside the company vs with a supplier.
 * That split is the whole question, so it gets the whole palette — stage
 * identity comes from labels instead. Both hues clear contrast and
 * colour-blind separation against the dashboard's light and dark surfaces.
 */
export const KIND_COLOR: Record<FlowKind, string> = {
    internal: 'var(--po-internal)',
    supplier: 'var(--po-supplier)',
};

/** Severity, kept separate from the two series colours above. */
export const STATE_STYLE: Record<
    FlowState,
    { word: string; chip: string; dot: string; text: string }
> = {
    blocked: {
        word: 'Blocked',
        chip: 'bg-red-500/12 text-red-600 dark:text-red-400',
        dot: 'bg-red-600 dark:bg-red-400',
        text: 'text-red-600 dark:text-red-400',
    },
    watch: {
        word: 'Watch',
        chip: 'bg-amber-500/14 text-amber-600 dark:text-amber-500',
        dot: 'bg-amber-600 dark:bg-amber-500',
        text: 'text-amber-600 dark:text-amber-500',
    },
    ok: {
        word: 'Clear',
        chip: 'bg-emerald-500/14 text-emerald-600 dark:text-emerald-400',
        dot: 'bg-emerald-600 dark:bg-emerald-400',
        text: 'text-emerald-600 dark:text-emerald-400',
    },
};

export const panelClass =
    'overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900';

export const headClass =
    'px-3 py-2 text-left text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

export const cellClass = 'px-3 py-2.5 align-middle';

export const numCellClass = `${cellClass} text-right text-xs tabular-nums whitespace-nowrap`;

/**
 * The longer "what is this and how do I read it" note for a panel, behind a
 * question mark beside the title.
 *
 * Separate from the one-line subtitle on purpose: the subtitle says what the
 * panel shows, and this says how to act on it. Putting both on the page would
 * bury the numbers under prose, and leaving the second one out means every new
 * person has to be told the same thing in person.
 */
export function PanelHelp({ children }: { children: React.ReactNode }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    // Explanatory, not actionable — so it takes an aria-label
                    // rather than visible text, and stays reachable by keyboard
                    // because Radix opens the tooltip on focus as well as hover.
                    aria-label="What this panel shows"
                    className="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-gray-300 transition-colors hover:text-gray-500 focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:outline-none dark:text-gray-600 dark:hover:text-gray-300"
                >
                    <CircleHelp className="h-3.5 w-3.5" />
                </button>
            </TooltipTrigger>
            <TooltipContent
                side="bottom"
                align="start"
                className="max-w-[380px] px-3.5 py-2.5 text-[12px] leading-relaxed"
            >
                {children}
            </TooltipContent>
        </Tooltip>
    );
}

/** Panel header, matching the rest of the inventory dashboard. */
export function PanelHead({
    title,
    children,
    help,
    action,
}: {
    title: string;
    children: React.ReactNode;
    /** The longer explanation, shown behind the question mark. */
    help?: React.ReactNode;
    action?: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3 p-[18px] sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div className="flex items-center gap-1.5">
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {title}
                    </h3>
                    {help && <PanelHelp>{help}</PanelHelp>}
                </div>
                <p className="mt-0.5 max-w-[76ch] text-[11px] leading-relaxed text-gray-400 dark:text-gray-500">
                    {children}
                </p>
            </div>
            {action && (
                <div className="flex shrink-0 items-center gap-3">{action}</div>
            )}
        </div>
    );
}

export function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex h-[150px] items-center justify-center rounded-[12px] border border-dashed border-zinc-200 px-5 text-center dark:border-zinc-800">
            <p className="text-sm text-zinc-500 dark:text-zinc-400">
                {message}
            </p>
        </div>
    );
}

export function TableSkeleton({
    rows = 5,
    cols = 4,
}: {
    rows?: number;
    cols?: number;
}) {
    return (
        <div className="flex flex-col gap-2">
            {Array.from({ length: rows }).map((_, r) => (
                <div key={r} className="flex items-center gap-3">
                    {Array.from({ length: cols }).map((_, c) => (
                        <Skeleton
                            key={c}
                            className="h-8"
                            style={{ flex: c === 0 ? 3 : 1 }}
                        />
                    ))}
                </div>
            ))}
        </div>
    );
}
