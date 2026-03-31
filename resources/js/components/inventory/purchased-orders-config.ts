import { AddItemForm } from '@/components/inventory/add-item-dialog';
import { StatusId, StatusOption } from '@/components/inventory/purchased-orders-types';

export const STATUS_OPTIONS: StatusOption[] = [
    { value: 1, label: 'For Approval' },
    { value: 2, label: 'Approved' },
    { value: 3, label: 'To Pay' },
    { value: 4, label: 'Paid' },
    { value: 5, label: 'For Purchase' },
    { value: 6, label: 'Waiting For Delivery' },
    { value: 7, label: 'Delivered' },
    { value: 8, label: 'Cancelled' },
];

export const MONTH_OPTIONS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

export const DROPDOWN_PANEL_CLASS = 'absolute left-0 top-[calc(100%+6px)] z-50 rounded-xl border border-black/6 bg-white p-1.5 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:border-white/10 dark:bg-zinc-900';
export const DROPDOWN_OPTION_BASE_CLASS = 'w-full rounded-md px-2 py-1.5 text-left text-[11px] transition-colors hover:bg-black/3 dark:hover:bg-white/5';
export const DATE_DROPDOWN_TRIGGER_CLASS = 'inline-flex h-9 w-full items-center justify-between rounded-[10px] border border-black/6 bg-white px-3 text-xs text-gray-500 outline-none transition-colors hover:bg-black/2 focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-white/5';

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
    status: '1',
};

export const statusLabel = (value: number | string): string => {
    if (typeof value === 'string' && isNaN(Number(value))) {
        return value;
    }

    const numeric = Number(value);
    return STATUS_OPTIONS.find((s) => s.value === numeric)?.label ?? String(value);
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
    const numeric = Number(status);
    switch (numeric) {
        case 7:
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
        case 6:
            return 'bg-sky-500/10 text-sky-600 dark:text-sky-400';
        case 4:
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
        case 8:
            return 'bg-red-500/10 text-red-600 dark:text-red-400';
        case 3:
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
        case 5:
            return 'bg-violet-500/10 text-violet-600 dark:text-violet-400';
        default:
            return 'bg-zinc-500/10 text-zinc-500 dark:text-zinc-400';
    }
};

export const statusOptionTextClass = (status: StatusId | string): string => {
    const numeric = Number(status);
    switch (numeric) {
        case 7:
            return 'text-emerald-600 dark:text-emerald-400';
        case 6:
            return 'text-sky-600 dark:text-sky-400';
        case 5:
            return 'text-violet-600 dark:text-violet-400';
        case 3:
            return 'text-amber-700 dark:text-amber-400';
        case 8:
            return 'text-red-600 dark:text-red-400';
        case 4:
            return 'text-emerald-600 dark:text-emerald-400';
        default:
            return 'text-gray-500 dark:text-gray-300';
    }
};

export const toInputDate = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

export const fromInputDate = (value: string): Date | undefined => {
    if (!value) return undefined;
    const [year, month, day] = value.split('-').map(Number);
    if (!year || !month || !day) return undefined;
    return new Date(year, month - 1, day);
};

export const normalizeDateRange = (first: string, second: string): { start: string; end: string } => {
    if (first <= second) {
        return { start: first, end: second };
    }

    return { start: second, end: first };
};

export const formatDisplayDate = (start: string, end?: string): string => {
    if (!start) return '';

    const toParts = (value: string): { y: number; m: number; d: number } | null => {
        const [year, month, day] = value.split('-').map(Number);
        if (!year || !month || !day) return null;
        return { y: year, m: month, d: day };
    };

    const startParts = toParts(start);
    const endParts = end ? toParts(end) : null;

    if (!startParts) return start;

    const formatOne = ({ y, m, d }: { y: number; m: number; d: number }): string => {
        const monthLabel = MONTH_OPTIONS[m - 1] ?? String(m).padStart(2, '0');
        return `${monthLabel} ${d}, ${y}`;
    };

    if (!endParts || start === end) {
        return formatOne(startParts);
    }

    const sameYear = startParts.y === endParts.y;
    const sameMonth = sameYear && startParts.m === endParts.m;

    const left = sameMonth
        ? `${MONTH_OPTIONS[startParts.m - 1]} ${startParts.d}`
        : formatOne(startParts);

    const right = sameYear
        ? `${MONTH_OPTIONS[endParts.m - 1]} ${endParts.d}, ${endParts.y}`
        : formatOne(endParts);

    return `${left} – ${right}`;
};
