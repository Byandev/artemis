/**
 * The purchase-order workflow stages, mirroring `PurchasedOrder::STATUSES` in
 * Modules/Inventory. The numeric keys are what `inventory_purchased_orders.status`
 * actually stores, so they must stay in lockstep with the PHP constant —
 * PurchasedOrderStatusParityTest fails the build if the two drift apart.
 *
 * This is deliberately the only copy on the frontend: the index, create and edit
 * pages all read from here.
 */
export const PURCHASED_ORDER_STATUSES: Record<
    number,
    { label: string; color: string }
> = {
    1: {
        label: 'For Approval',
        color: 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400',
    },
    2: {
        label: 'Approved',
        color: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400',
    },
    3: {
        label: 'To Pay',
        color: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    },
    4: {
        label: 'Paid',
        color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400',
    },
    5: {
        label: 'For Purchase',
        color: 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-400',
    },
    6: {
        label: 'Waiting For Delivery',
        color: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-400',
    },
    7: {
        label: 'Delivered',
        color: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400',
    },
    8: {
        label: 'Cancelled',
        color: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400',
    },
};

/** Terminal stages — the order no longer owes stock. Mirrors `PurchasedOrder::CLOSED_STATUSES`. */
export const CLOSED_PURCHASED_ORDER_STATUSES = [7, 8];

/** Ordered list for `<select>` inputs. */
export const PURCHASED_ORDER_STATUS_OPTIONS = Object.entries(
    PURCHASED_ORDER_STATUSES,
).map(([value, { label }]) => ({ value: Number(value), label }));

export const purchasedOrderStatusLabel = (status: number): string =>
    PURCHASED_ORDER_STATUSES[status]?.label ?? 'Unknown';
