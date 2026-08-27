/**
 * The Artemis design tokens this module uses, in one place.
 *
 * These were scattered as literal class strings across six files and had
 * already drifted — the card border, button height, and label size each had
 * more than one spelling. Naming them keeps the pages aligned with
 * resources/views/design-guidelines.html and with each other.
 */

/** Label / column header: mono, uppercase, tracked out. */
export const LABEL =
    'font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

/** Body copy. */
export const BODY = 'text-[13px] text-gray-600 dark:text-gray-300';

/** Muted body copy, for descriptions and counts under a heading. */
export const MUTED = 'text-[13px] text-gray-500 dark:text-gray-400';

/** Numeric data — the guide requires mono tabular figures throughout. */
export const NUM =
    'font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500';

/** Page title. */
export const TITLE =
    'text-[22px] font-semibold tracking-tight text-gray-800 dark:text-gray-100';

/** The primary container. No shadow: the guide reserves those for overlays. */
export const CARD =
    'rounded-xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900';

/** A card that responds to hover, for cards that are themselves links. */
export const CARD_HOVER = `${CARD} transition-all hover:border-black/12 dark:hover:border-white/12`;

/** Divider between rows inside a card. */
export const ROW_DIVIDE = 'divide-y divide-black/4 dark:divide-white/4';

/** Border between a card's sections. */
export const SECTION_BORDER = 'border-black/6 dark:border-white/6';

const BTN_BASE =
    'flex h-9 items-center gap-1.5 rounded-lg px-4 font-mono! text-[12px]! font-medium transition-all disabled:pointer-events-none disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500';

export const BTN_PRIMARY = `${BTN_BASE} bg-emerald-600 text-white hover:bg-emerald-700`;

export const BTN_SECONDARY = `${BTN_BASE} border border-black/8 bg-stone-100 text-gray-700 hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-700`;

export const BTN_OUTLINE = `${BTN_BASE} border border-black/8 text-gray-600 hover:bg-stone-100 dark:border-white/8 dark:text-gray-300 dark:hover:bg-zinc-800`;

export const BTN_GHOST = `${BTN_BASE} text-gray-500 hover:bg-stone-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-zinc-800 dark:hover:text-gray-100`;

/** Small square icon button, for row-level actions. */
export const ICON_BTN =
    'flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400 transition-all hover:bg-stone-100 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:hover:bg-zinc-800 dark:hover:text-gray-200';

/** Small pill, e.g. a category or status. */
const CHIP =
    'rounded-md px-2 py-1 font-mono text-[10px] font-medium tracking-wider uppercase';

export const CHIP_NEUTRAL = `${CHIP} bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400`;
export const CHIP_ACCENT = `${CHIP} bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400`;
export const CHIP_CATEGORY = `${CHIP} bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400`;

/** A tiny uppercase action, e.g. PLAY / EDIT on a row. */
const PILL_BASE =
    'flex h-7 shrink-0 items-center gap-1.5 rounded-md px-2.5 font-mono! text-[10px]! font-medium tracking-wider uppercase transition-all focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500';

export const PILL_ACCENT = `${PILL_BASE} border border-emerald-600/20 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/20 dark:bg-emerald-950/40 dark:text-emerald-400`;
export const PILL_OUTLINE = `${PILL_BASE} border border-black/8 text-gray-600 hover:bg-stone-100 dark:border-white/8 dark:text-gray-300 dark:hover:bg-zinc-800`;
export const PILL_DISABLED = `${PILL_BASE} border border-black/8 text-gray-300 dark:border-white/8 dark:text-gray-600`;

/** Dashed affordance for "add another one of these". */
export const ADD_ROW =
    'flex w-full items-center gap-1.5 rounded-xl border border-dashed border-black/12 px-4 py-3 font-mono text-[12px] text-gray-400 transition-all hover:border-emerald-500/40 hover:text-emerald-600 disabled:opacity-50 dark:border-white/12 dark:hover:border-emerald-400/40 dark:hover:text-emerald-400';

/** Empty state panel. */
export const EMPTY =
    'flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-black/12 bg-stone-50 py-14 text-center dark:border-white/12 dark:bg-zinc-900';

/** Progress bar track. Put a `BAR` div inside at `width: n%`. */
export const TRACK =
    'h-1.5 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-zinc-800';

export const TRACK_THIN =
    'h-1 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-zinc-800';

export const BAR = 'h-full rounded-full bg-emerald-600 transition-all';

/* ── Form controls ─────────────────────────────────────────────────────── */

export const INPUT =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';

export const TEXTAREA =
    'w-full resize-none rounded-[10px] border border-black/8 bg-stone-50 px-3 py-2.5 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';

export const FIELD = 'space-y-1.5';

export const FIELD_ERROR = 'font-mono text-[11px] text-red-500';

/** Page shell padding — 28px per the guide's page-padding token. */
export const PAGE = 'p-4 md:p-7';
