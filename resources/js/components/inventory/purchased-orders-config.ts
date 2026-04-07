import { AddItemForm } from '@/components/inventory/add-item-dialog';
import { StatusId, StatusOption } from '@/types/models/PurchasedOrder';

const STATUS_VALUES: StatusId[] = [
    'For Approval',
    'Approved',
    'To Pay',
    'Paid',
    'For Purchase',
    'Waiting For Delivery',
    'Delivered',
    'Cancelled',
];

export const STATUS_OPTIONS: StatusOption[] = STATUS_VALUES.map((value) => ({ value, label: value }));

const normalizeStatus = (value: StatusId | number | string): StatusId => {
    const key = String(value);
    const direct = STATUS_VALUES.find((status) => status === key);
    if (direct) return direct;
    return 'For Approval';
};

export const normalizeStatusLabel = normalizeStatus;

export const SANS_FONT = "'DM Sans', system-ui, sans-serif";
export const MONO_FONT = "'DM Mono', monospace";

export const ADD_ITEM_FORM_INITIAL: AddItemForm = {
    issue_date: '',
    delivery_no: '',
    cust_po_no: '',
    control_no: '',
    item: '',
    cog_amount: '',
    delivery_fee: '',
    total_amount: '',
    status: 'For Approval',
};

export const statusLabel = (value: StatusId | number | string): string => {
    const normalized = normalizeStatus(value);
    return STATUS_OPTIONS.find((s) => s.value === normalized)?.label ?? normalized;
};

export const formatMoney = (amount: number): string => {
    return new Intl.NumberFormat('en-PH', {
        maximumFractionDigits: 0,
    }).format(amount || 0);
};

export const formatIssueDate = (value: string): string => {
    if (!value) return '';

    const isoMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (isoMatch) {
        const [, year, month, day] = isoMatch;
        return `${month}/${day}/${year}`;
    }

    const slashMatch = value.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
    if (slashMatch) {
        const [, month, day, year] = slashMatch;
        return `${month}/${day}/${year}`;
    }

    return value;
};

export const statusBadgeClass = (status: StatusId | string): string => {
    const normalized = normalizeStatus(status);
    switch (normalized) {
        case 'Delivered':
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
        case 'Waiting For Delivery':
            return 'bg-sky-500/10 text-sky-600 dark:text-sky-400';
        case 'Paid':
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
        case 'Cancelled':
            return 'bg-red-500/10 text-red-600 dark:text-red-400';
        case 'To Pay':
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
        case 'For Purchase':
            return 'bg-violet-500/10 text-violet-600 dark:text-violet-400';
        default:
            return 'bg-zinc-500/10 text-zinc-500 dark:text-zinc-400';
    }
};

export const statusOptionTextClass = (status: StatusId | string): string => {
    const normalized = normalizeStatus(status);
    switch (normalized) {
        case 'Delivered':
            return 'text-emerald-600 dark:text-emerald-400';
        case 'Waiting For Delivery':
            return 'text-sky-600 dark:text-sky-400';
        case 'For Purchase':
            return 'text-violet-600 dark:text-violet-400';
        case 'To Pay':
            return 'text-amber-700 dark:text-amber-400';
        case 'Cancelled':
            return 'text-red-600 dark:text-red-400';
        case 'Paid':
            return 'text-emerald-600 dark:text-emerald-400';
        default:
            return 'text-gray-500 dark:text-gray-300';
    }
};

