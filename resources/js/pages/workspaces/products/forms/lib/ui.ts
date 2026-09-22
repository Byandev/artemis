/**
 * The class strings this page shares between its table and its dialog. Kept in
 * one place so the two can't drift on border radius, label size or button
 * height — the same reasoning as resources/js/pages/workspaces/courses/lib/ui.ts.
 */

/** Label / column header: mono, uppercase, tracked out. */
export const LABEL =
    'font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

/** Numeric data — mono tabular figures. */
export const NUM =
    'font-mono text-[13px] text-gray-600 tabular-nums dark:text-gray-300';

export const CARD =
    'rounded-2xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900';

export const SECTION_BORDER = 'border-black/6 dark:border-white/6';

export const ROW_DIVIDE = 'divide-y divide-black/4 dark:divide-white/4';

const BTN_BASE =
    'flex h-9 items-center gap-1.5 rounded-lg px-4 font-mono! text-[12px]! font-medium transition-all disabled:pointer-events-none disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500';

export const BTN_PRIMARY = `${BTN_BASE} bg-emerald-600 text-white hover:bg-emerald-700`;

export const BTN_SECONDARY = `${BTN_BASE} border border-black/8 bg-stone-100 text-gray-700 hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-700`;

/** A tiny uppercase row action, e.g. EDIT / DELETE. */
const PILL_BASE =
    'flex h-7 shrink-0 items-center gap-1.5 rounded-md px-2.5 font-mono! text-[10px]! font-medium tracking-wider uppercase transition-all focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500';

export const PILL_OUTLINE = `${PILL_BASE} border border-black/8 text-gray-600 hover:bg-stone-100 dark:border-white/8 dark:text-gray-300 dark:hover:bg-zinc-800`;

export const PILL_DANGER = `${PILL_BASE} border border-red-600/20 text-red-600 hover:bg-red-50 dark:border-red-400/20 dark:text-red-400 dark:hover:bg-red-950/30`;

/** Dashed affordance for "add another one of these". */
export const ADD_ROW =
    'flex items-center gap-1.5 rounded-lg border border-dashed border-emerald-600/30 px-3 py-1.5 font-mono! text-[11px]! font-medium text-emerald-700 transition-all hover:border-emerald-600/60 hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-400/30 dark:text-emerald-400 dark:hover:bg-emerald-950/30';

export const EMPTY =
    'flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-black/12 bg-stone-50 py-14 text-center dark:border-white/12 dark:bg-zinc-900';

export const INPUT =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';

export const FIELD = 'space-y-1.5';

export const FIELD_ERROR = 'font-mono text-[11px] text-red-500';
