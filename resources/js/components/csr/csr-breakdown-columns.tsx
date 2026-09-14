import { type ColumnOption } from '@/components/ui/columns-dropdown';
import { SortableHeader } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';

/**
 * Every figure column of the two nightly CSR rollups, as the CSR dashboard's
 * breakdown lists them — against a date, for one CSR's own days.
 *
 * The CSR analytics breakdown lists the same figures against a CSR's name and
 * defines its own copy of these columns inside analytics.tsx. The grain differs;
 * the figures do not, so the two must stay in step — a column added to one
 * table wants adding to the other.
 *
 * The names are the aliases the controllers select, not the raw rollup column
 * names: `total_called` is the RMO assignments and `total_all_called` is the
 * report's own `total_called`.
 */
export interface CsrRollupFigures {
    // pancake_user_pos_daily_reports
    total_orders: number;
    total_sales: number;
    total_delivered: number;
    total_delivered_count: number;
    total_returning: number;
    total_returning_count: number;
    rts_rate: number;
    // pancake_user_daily_call_reports
    total_confirmed: number;
    total_called: number;
    total_rmo_call_attempts: number;
    total_rmo_orders: number;
    rmo_percentage: number;
    total_call_time: number;
    total_rmo_connected_called: number;
    total_rmo_real_called: number;
    longest_rmo_call_time: number;
    total_rmo_customer_called: number;
    total_rmo_customer_call_time: number;
    total_rmo_rider_called: number;
    total_rmo_rider_call_time: number;
    total_verification_called: number;
    total_verification_call_time: number;
    total_verification_real_called: number;
    total_verified_orders: number;
    total_all_called: number;
    total_all_call_time: number;
}

export const peso = (n: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(Number(n) || 0);

export const formatCallTime = (seconds: number) => {
    const s = Math.max(0, Math.floor(Number(seconds) || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = (n: number) => n.toString().padStart(2, '0');
    return h > 0 ? `${h}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
};

// All but a handful of these columns are the same three shapes: a count, a peso
// figure, or a span of call time. Building those from one place keeps two dozen
// columns readable and stops a new one from being formatted differently by
// accident.
const countColumn = <T extends CsrRollupFigures>(
    accessorKey: keyof CsrRollupFigures,
    title: string,
): ColumnDef<T> => ({
    accessorKey,
    header: ({ column }) => <SortableHeader column={column} title={title} />,
    cell: ({ row }) => Number(row.original[accessorKey]).toLocaleString(),
});

const moneyColumn = <T extends CsrRollupFigures>(
    accessorKey: keyof CsrRollupFigures,
    title: string,
): ColumnDef<T> => ({
    accessorKey,
    header: ({ column }) => <SortableHeader column={column} title={title} />,
    cell: ({ row }) => peso(Number(row.original[accessorKey])),
});

const durationColumn = <T extends CsrRollupFigures>(
    accessorKey: keyof CsrRollupFigures,
    title: string,
): ColumnDef<T> => ({
    accessorKey,
    header: ({ column }) => <SortableHeader column={column} title={title} />,
    cell: ({ row }) => formatCallTime(Number(row.original[accessorKey])),
});

/**
 * The figure columns, in the order both tables show them. The caller prepends
 * whatever names its rows — a CSR on the analytics page, a date on the
 * dashboard.
 */
export function rollupColumns<T extends CsrRollupFigures>(): ColumnDef<T>[] {
    return [
        // pancake_user_pos_daily_reports — the sales side of the period.
        // Delivered and Returning are money; the two Parcels columns beside
        // them are the counts behind that money, which only the POS rollup
        // carries (ERP reads zero).
        countColumn<T>('total_orders', 'Orders'),
        moneyColumn<T>('total_sales', 'Sales'),
        moneyColumn<T>('total_delivered', 'Delivered'),
        countColumn<T>('total_delivered_count', 'Delivered Parcels'),
        moneyColumn<T>('total_returning', 'Returning'),
        countColumn<T>('total_returning_count', 'Returning Parcels'),
        {
            accessorKey: 'rts_rate',
            header: ({ column }) => (
                <SortableHeader column={column} title="RTS Rate" />
            ),
            cell: ({ row }) => `${Number(row.original.rts_rate).toFixed(2)}%`,
        },
        // pancake_user_daily_call_reports — the calling side.
        countColumn<T>('total_confirmed', 'RMO Confirmed'),
        countColumn<T>('total_called', 'RMO Assigned'),
        countColumn<T>('total_rmo_call_attempts', 'RMO Called'),
        // The same calls counted by delivery rather than by call: a parcel
        // rung three times is three above and one here.
        countColumn<T>('total_rmo_orders', 'RMO Orders Called'),
        {
            accessorKey: 'rmo_percentage',
            header: ({ column }) => (
                <SortableHeader column={column} title="RMO %" />
            ),
            cell: ({ row }) => {
                // RMO % = RMO assigned / RMO confirmed (computed on the backend).
                const confirmed = Number(row.original.total_confirmed) || 0;
                if (confirmed === 0) return '—';
                return `${Number(row.original.rmo_percentage).toFixed(2)}%`;
            },
        },
        durationColumn<T>('total_call_time', 'RMO Call Time'),
        // How far the RMO calls got: one that joined at all, and one that
        // lasted past the shared five-second mark. Longest is a max over the
        // range, not a sum.
        countColumn<T>('total_rmo_connected_called', 'RMO Answered'),
        countColumn<T>('total_rmo_real_called', 'RMO Real Conversations'),
        durationColumn<T>('longest_rmo_call_time', 'Longest RMO Call'),
        // The same RMO calls split by who was on the other end.
        countColumn<T>('total_rmo_customer_called', 'RMO Customer Called'),
        durationColumn<T>(
            'total_rmo_customer_call_time',
            'RMO Customer Call Time',
        ),
        countColumn<T>('total_rmo_rider_called', 'RMO Rider Called'),
        durationColumn<T>('total_rmo_rider_call_time', 'RMO Rider Call Time'),
        // Calls against an order with no delivery behind it — confirming the
        // order rather than chasing the parcel.
        countColumn<T>('total_verification_called', 'Verification Called'),
        durationColumn<T>(
            'total_verification_call_time',
            'Verification Call Time',
        ),
        countColumn<T>(
            'total_verification_real_called',
            'Verification Real Conversations',
        ),
        // The same verification work counted by order rather than by call: an
        // order rung three times is three above and one here.
        countColumn<T>('total_verified_orders', 'Total Verified Orders'),
        // The report's own totals: RMO work and verification added together,
        // which is every call the CSR placed.
        countColumn<T>('total_all_called', 'Total Called'),
        durationColumn<T>('total_all_call_time', 'Total Call Time'),
    ];
}

/**
 * The same columns as entries for the columns dropdown. The ids are the column
 * accessorKeys, which are also the sort keys the server takes, and the groups
 * are the two rollups the figures come from.
 */
export const ROLLUP_COLUMN_OPTIONS: ColumnOption[] = [
    { id: 'total_orders', label: 'Orders', group: 'Sales report' },
    { id: 'total_sales', label: 'Sales', group: 'Sales report' },
    { id: 'total_delivered', label: 'Delivered', group: 'Sales report' },
    {
        id: 'total_delivered_count',
        label: 'Delivered Parcels',
        group: 'Sales report',
    },
    { id: 'total_returning', label: 'Returning', group: 'Sales report' },
    {
        id: 'total_returning_count',
        label: 'Returning Parcels',
        group: 'Sales report',
    },
    { id: 'rts_rate', label: 'RTS Rate', group: 'Sales report' },

    { id: 'total_confirmed', label: 'RMO Confirmed', group: 'Call report' },
    { id: 'total_called', label: 'RMO Assigned', group: 'Call report' },
    {
        id: 'total_rmo_call_attempts',
        label: 'RMO Called',
        group: 'Call report',
    },
    {
        id: 'total_rmo_orders',
        label: 'RMO Orders Called',
        group: 'Call report',
    },
    { id: 'rmo_percentage', label: 'RMO %', group: 'Call report' },
    { id: 'total_call_time', label: 'RMO Call Time', group: 'Call report' },
    {
        id: 'total_rmo_connected_called',
        label: 'RMO Answered',
        group: 'Call report',
    },
    {
        id: 'total_rmo_real_called',
        label: 'RMO Real Conversations',
        group: 'Call report',
    },
    {
        id: 'longest_rmo_call_time',
        label: 'Longest RMO Call',
        group: 'Call report',
    },
    {
        id: 'total_rmo_customer_called',
        label: 'RMO Customer Called',
        group: 'Call report',
    },
    {
        id: 'total_rmo_customer_call_time',
        label: 'RMO Customer Call Time',
        group: 'Call report',
    },
    {
        id: 'total_rmo_rider_called',
        label: 'RMO Rider Called',
        group: 'Call report',
    },
    {
        id: 'total_rmo_rider_call_time',
        label: 'RMO Rider Call Time',
        group: 'Call report',
    },
    {
        id: 'total_verification_called',
        label: 'Verification Called',
        group: 'Call report',
    },
    {
        id: 'total_verification_call_time',
        label: 'Verification Call Time',
        group: 'Call report',
    },
    {
        id: 'total_verification_real_called',
        label: 'Verification Real Conversations',
        group: 'Call report',
    },
    {
        id: 'total_verified_orders',
        label: 'Total Verified Orders',
        group: 'Call report',
    },
    { id: 'total_all_called', label: 'Total Called', group: 'Call report' },
    {
        id: 'total_all_call_time',
        label: 'Total Call Time',
        group: 'Call report',
    },
];
