/**
 * Which side of cost-of-goods a statement shows.
 *
 * The two answer different questions — what the goods that sold cost, or what
 * was bought into stock this month — and putting both up at once made the rows
 * hard to read, so a statement shows one at a time.
 *
 * Delivered COGS is currently switched off everywhere. It is only meaningful
 * where the orders themselves carry a cost figure; on the pancake side they
 * carry none, so that view reported a gross profit with no goods cost taken off
 * at all, which reads as a much healthier month than it was.
 *
 * To bring it back, put its entry back in COGS_VIEWS below. Nothing else needs
 * changing: every statement page reads this list, the toggle reappears on its
 * own once there is more than one thing to pick, and all the delivered-basis
 * figures are still computed and stored.
 */
export type CogsView = 'delivered' | 'bought';

export const COGS_VIEWS: { value: CogsView; label: string }[] = [
    // { value: 'delivered', label: 'Delivered COGS' },
    { value: 'bought', label: 'Bought COGS' },
];

/** The view a statement opens on — the first one still enabled. */
export const DEFAULT_COGS_VIEW: CogsView = COGS_VIEWS[0].value;

/** A picker is only worth showing when there is a choice to make. */
export const COGS_VIEW_IS_CHOOSABLE = COGS_VIEWS.length > 1;
