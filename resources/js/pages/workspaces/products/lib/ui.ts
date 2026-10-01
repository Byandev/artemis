/**
 * The Artemis design tokens the product pages share.
 *
 * Every value here is taken from resources/views/design-guidelines.html, with
 * the section it comes from named, so a page can be checked against the guide
 * without reading the guide. Kept in one place so the tables and dialogs
 * across Product Forms, Target Market and the RDP Builder can't drift.
 */

/* ── Typography (§03) ───────────────────────────────────────────────────── */

/** Label / header. The guide's size is 11px; only table headers go smaller. */
export const LABEL =
    'font-mono text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500';

/** Section heading. Deliberately quiet — the guide reserves 600 weight for
 *  page titles and large metric values. */
export const SECTION_HEADING =
    'text-sm font-medium text-gray-800 dark:text-gray-100';

/** Body copy. */
export const BODY = 'text-[13px] text-gray-500 dark:text-gray-400';

/** Muted body copy, for hints under a heading. */
export const MUTED = 'text-[13px] text-gray-400 dark:text-gray-500';

/** Numeric data. The guide requires mono tabular figures for every number,
 *  count, price, id and timestamp. */
export const NUM =
    'font-mono text-[13px] tabular-nums text-gray-500 dark:text-gray-400';

/* ── Surfaces (§05) ─────────────────────────────────────────────────────── */

/** The primary container. No drop shadow: the guide reserves those for
 *  floating panels. */
export const CARD =
    'rounded-[14px] border border-black/6 bg-white transition-colors dark:border-white/6 dark:bg-zinc-900';

/** A card that reacts to hover, for ones that are themselves links. */
export const CARD_HOVER = `${CARD} hover:border-black/10 dark:hover:border-white/10`;

/** Card padding (§04) — 24px. */
export const CARD_PAD = 'p-6';

/** Border between a card's sections, and the rule beside a section heading. */
export const SECTION_BORDER = 'border-black/6 dark:border-white/6';

/** Divider between rows inside a card. */
export const ROW_DIVIDE = 'divide-y divide-black/6 dark:divide-white/6';

/** Inset surface, for a nested row or a sub-list inside a card. The guide's
 *  third level, --surface-2 (#F2F2EF); stone-100 is the nearest Tailwind step
 *  and is the same fill the inputs rest on. */
export const INSET = 'bg-stone-100 dark:bg-zinc-800/40';

/* ── Tables (§09) ───────────────────────────────────────────────────────── */

/** Minimal borders, uppercase headers, one step lighter than a LABEL. */
export const TH =
    'px-4 py-2.5 text-left font-mono text-[10px] font-medium uppercase tracking-wider text-gray-300 dark:text-gray-600';

export const TD = 'px-4 py-3 text-[13px] text-gray-500 dark:text-gray-400';

/** The first cell of a row — the thing the row is about. */
export const TD_PRIMARY =
    'px-4 py-3 text-[13px] font-medium text-gray-800 dark:text-gray-100';

/** Numeric cell. */
export const TD_NUM = `${TD} font-mono tabular-nums`;

/** Hover tint on rows. */
export const ROW_HOVER = 'transition-colors hover:bg-emerald-500/[0.03]';

/* ── Buttons (§07) ──────────────────────────────────────────────────────── */

const BTN_BASE =
    'inline-flex items-center gap-1.5 rounded-lg text-xs font-medium transition-colors disabled:pointer-events-none disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500';

export const BTN_PRIMARY = `${BTN_BASE} bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-400`;

export const BTN_SECONDARY = `${BTN_BASE} border border-black/6 bg-white px-3.5 py-1.5 text-gray-500 hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10`;

export const BTN_GHOST = `${BTN_BASE} px-3.5 py-1.5 text-gray-400 hover:bg-black/[0.03] hover:text-gray-600 dark:hover:bg-white/[0.04] dark:hover:text-gray-300`;

export const BTN_DANGER = `${BTN_BASE} bg-red-50 px-4 py-2 text-red-600 hover:bg-red-100 dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500/15`;

/** Square icon button for a row action, sized to sit beside BTN_SECONDARY. */
export const ICON_BTN =
    'flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-black/6 text-gray-400 transition-colors hover:border-black/10 hover:text-gray-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:border-white/6 dark:hover:border-white/10 dark:hover:text-gray-300';

/** The same, for the destructive one. */
export const ICON_BTN_DANGER =
    'flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-red-500 transition-colors hover:bg-red-50 hover:text-red-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:text-red-400 dark:hover:bg-red-500/10';

/* ── Badges (§10) ───────────────────────────────────────────────────────── */

/** Pill with a dot indicator. Pair with a colour from the guide's map. */
export const BADGE =
    'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-medium';

export const BADGE_NEUTRAL = `${BADGE} bg-gray-500/8 text-gray-400 dark:text-gray-500`;

export const BADGE_ACCENT = `${BADGE} bg-emerald-500/8 text-emerald-600 dark:text-emerald-400`;

/* ── Inputs (§08) ───────────────────────────────────────────────────────── */

/** Subtle resting fill, emerald focus ring. Not monospaced: the guide keeps
 *  mono for data, not for what the user types. */
export const INPUT =
    'w-full rounded-[10px] border border-black/6 bg-stone-100 px-3 py-2.5 text-[13px] text-gray-900 outline-none transition-all placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';

export const TEXTAREA = `${INPUT} resize-y`;

/** The label sitting directly above a control. */
export const FIELD_LABEL =
    'mb-1.5 block text-[13px] font-medium text-gray-700 dark:text-gray-200';

export const FIELD = 'space-y-1.5';

export const FIELD_ERROR = 'mt-1.5 text-[11px] text-red-500';

/* ── Affordances ────────────────────────────────────────────────────────── */

/** Dashed "add another one of these". */
export const ADD_ROW = `${BTN_BASE} border border-dashed border-emerald-600/30 px-3.5 py-1.5 text-emerald-700 hover:border-emerald-600/60 hover:bg-emerald-50 dark:border-emerald-400/30 dark:text-emerald-400 dark:hover:bg-emerald-950/30`;

/** Dashed empty state panel. */
export const EMPTY =
    'flex flex-col items-center justify-center gap-2 rounded-[14px] border border-dashed border-black/10 bg-stone-100 py-14 text-center dark:border-white/10 dark:bg-zinc-900';

/* ── Spacing (§04) ──────────────────────────────────────────────────────── */

/** Page padding — 28px, stepped down on a phone. */
export const PAGE = 'p-4 md:p-7';

/** Between sections. */
export const SECTION_GAP = 'space-y-5';
