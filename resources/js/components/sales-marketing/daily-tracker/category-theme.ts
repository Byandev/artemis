/**
 * The tint a category's band is drawn in.
 *
 * Categories are free text on the server, so colour is assigned by the order a
 * category first appears on the board rather than by name — a workspace that
 * renames "Self care" keeps its green, and one that invents a seventh category
 * still gets a colour instead of a blank band.
 *
 * Nothing on the page rests on colour alone: every band carries its name and
 * its done/total count.
 */
export interface CategoryTheme {
    /** The band behind a category heading. */
    band: string;
    /** The heading text on that band. */
    heading: string;
    /** The muted count on the right of the band. */
    count: string;
}

const THEMES: CategoryTheme[] = [
    {
        band: 'bg-emerald-50/80 dark:bg-emerald-500/10',
        heading: 'text-emerald-700 dark:text-emerald-300',
        count: 'text-emerald-600/60 dark:text-emerald-300/50',
    },
    {
        band: 'bg-blue-50/80 dark:bg-blue-500/10',
        heading: 'text-blue-700 dark:text-blue-300',
        count: 'text-blue-600/60 dark:text-blue-300/50',
    },
    {
        band: 'bg-amber-50/80 dark:bg-amber-500/10',
        heading: 'text-amber-700 dark:text-amber-300',
        count: 'text-amber-600/60 dark:text-amber-300/50',
    },
    {
        band: 'bg-rose-50/80 dark:bg-rose-500/10',
        heading: 'text-rose-700 dark:text-rose-300',
        count: 'text-rose-600/60 dark:text-rose-300/50',
    },
    {
        band: 'bg-violet-50/80 dark:bg-violet-500/10',
        heading: 'text-violet-700 dark:text-violet-300',
        count: 'text-violet-600/60 dark:text-violet-300/50',
    },
    {
        band: 'bg-pink-50/80 dark:bg-pink-500/10',
        heading: 'text-pink-700 dark:text-pink-300',
        count: 'text-pink-600/60 dark:text-pink-300/50',
    },
];

/**
 * Category name -> tint, keyed by first appearance and cycling once the palette
 * runs out.
 */
export function assignCategoryThemes(
    categories: string[],
): Map<string, CategoryTheme> {
    return new Map(
        categories.map((name, index) => [name, THEMES[index % THEMES.length]]),
    );
}

/** The tint used where a category has none of its own (an unknown name). */
export const FALLBACK_THEME: CategoryTheme = {
    band: 'bg-stone-100 dark:bg-white/5',
    heading: 'text-gray-600 dark:text-gray-300',
    count: 'text-gray-400 dark:text-gray-500',
};
