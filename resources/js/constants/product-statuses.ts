/**
 * The product lifecycle stages, in order. Mirrors `Product::STATUSES` in PHP and
 * the `status` enum on the products table — ProductStatusParityTest fails the
 * build if this list and the PHP constant drift apart.
 *
 * This is the only copy on the frontend: the product create/edit pages, the
 * product form dialog and the inventory-items filter all read from here.
 */
export const PRODUCT_STATUSES = [
    'New',
    'Testing',
    'Scaling',
    'Maintaining',
    'Failed',
    'Inactive',
] as const;

export type ProductStatus = (typeof PRODUCT_STATUSES)[number];

/** Badge colours per stage, roughly early → thriving → wound down. */
export const PRODUCT_STATUS_COLORS: Record<ProductStatus, string> = {
    New: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-400',
    Testing:
        'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    Scaling:
        'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400',
    Maintaining:
        'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400',
    Failed: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400',
    Inactive: 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400',
};
