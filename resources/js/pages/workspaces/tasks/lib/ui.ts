/**
 * Class strings for the task module, in Artemis's design language (see
 * pages/workspaces/courses/lib/ui.ts). Matrix leaned on its own theme tokens;
 * these are their Artemis equivalents, light and dark.
 */

/** A raised surface: rows, cards, panels. No shadow, per the guide. */
export const SURFACE =
    'border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900';

/** Text input / textarea / date field. */
export const FIELD_INPUT =
    'border border-black/8 bg-white text-gray-800 outline-none transition-all placeholder:text-gray-400 focus-visible:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus-visible:border-emerald-400';

/** An input that is already in the act of being written, e.g. an inline composer. */
export const FIELD_ACTIVE =
    'border border-emerald-500 bg-white text-gray-800 outline-none ring-2 ring-emerald-500/15 dark:border-emerald-400 dark:bg-zinc-900 dark:text-gray-100';

/** Section heading inside a card: mono, uppercase, tracked out. */
export const SECTION_LABEL =
    'font-mono text-[11px] font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400';

/** Avatar bubble for a person. */
export const AVATAR =
    'flex items-center justify-center rounded-full bg-emerald-600 font-semibold text-white dark:bg-emerald-500';

/** A text link that picks up the accent on hover. */
export const LINK_HOVER =
    'transition-colors hover:text-emerald-700 dark:hover:text-emerald-400';

/** Dashed empty-state panel. */
export const EMPTY_PANEL =
    'flex flex-col items-center gap-3 rounded-xl border border-dashed border-black/12 bg-stone-50 px-6 py-20 text-center dark:border-white/12 dark:bg-zinc-900';
