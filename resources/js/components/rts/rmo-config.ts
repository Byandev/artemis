export type ParcelStatusEntry = {
    label: string;
    dot: string;
    pill: string;
};

/** Order-status dot + text colours — shared between authenticated and public views. */
export const orderStatusConfig: Record<string, { dot: string; text: string }> = {
    'PENDING':            { dot: 'bg-yellow-400',  text: 'text-yellow-700 dark:text-yellow-400' },
    'DELIVERED':          { dot: 'bg-emerald-500', text: 'text-emerald-700 dark:text-emerald-400' },
    'RIDER OTW':          { dot: 'bg-blue-500',    text: 'text-blue-700 dark:text-blue-400' },
    'RETURNING':          { dot: 'bg-orange-400',  text: 'text-orange-700 dark:text-orange-400' },
    'RESCHEDULED':        { dot: 'bg-purple-400',  text: 'text-purple-700 dark:text-purple-400' },
    'CX CBR':             { dot: 'bg-red-500',     text: 'text-red-700 dark:text-red-400' },
    'RIDER CBR':          { dot: 'bg-red-500',     text: 'text-red-700 dark:text-red-400' },
    'CANCELLED':          { dot: 'bg-gray-400',    text: 'text-gray-500 dark:text-gray-400' },
    'WRONG SEGMENT CODE': { dot: 'bg-rose-500',    text: 'text-rose-700 dark:text-rose-400' },
    'CX RINGING':         { dot: 'bg-amber-400',   text: 'text-amber-700 dark:text-amber-400' },
    'RIDER RINGING':      { dot: 'bg-amber-400',   text: 'text-amber-700 dark:text-amber-400' },
    'IN TRANSIT':         { dot: 'bg-cyan-500',    text: 'text-cyan-700 dark:text-cyan-400' },
};

/**
 * Authenticated-view parcel status config — keys are snake_case (from J&T API).
 * e.g. row.original.order.parcel_status?.toLowerCase()
 */
export const authParcelStatusConfig: Record<string, ParcelStatusEntry> = {
    delivered:        { label: 'Delivered',        dot: 'bg-emerald-500', pill: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' },
    returning:        { label: 'Returning',        dot: 'bg-orange-400',  pill: 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400' },
    returned:         { label: 'Returned',         dot: 'bg-red-500',     pill: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400' },
    undeliverable:    { label: 'Undeliverable',    dot: 'bg-rose-500',    pill: 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' },
    on_the_way:       { label: 'On the Way',       dot: 'bg-blue-500',    pill: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' },
    out_for_delivery: { label: 'Out for Delivery', dot: 'bg-violet-500',  pill: 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400' },
};

/**
 * Public-view parcel status config — keys are UPPERCASE (legacy public API).
 * e.g. row.original.order.parcel_status?.toUpperCase()
 */
export const publicParcelStatusConfig: Record<string, ParcelStatusEntry> = {
    delivered:        { label: 'Delivered',        dot: 'bg-emerald-500', pill: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' },
    pending:          { label: 'Pending',          dot: 'bg-yellow-400',  pill: 'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-400' },
    returned:         { label: 'Returned',         dot: 'bg-red-500',     pill: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400' },
    undeliverable:    { label: 'Undeliverable',    dot: 'bg-rose-500',    pill: 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' },
    cancelled:        { label: 'Cancelled',        dot: 'bg-gray-400',    pill: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' },
    shipped:          { label: 'Shipped',          dot: 'bg-blue-500',    pill: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' },
    out_for_delivery: { label: 'Out for Delivery', dot: 'bg-violet-500',  pill: 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400' },
};
