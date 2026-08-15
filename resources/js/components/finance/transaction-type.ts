export type TransactionType =
    | 'funds'
    | 'profit_share'
    | 'expenses'
    | 'transfer'
    | 'remittance'
    | 'loan'
    | 'loan_payment'
    | 'refund'
    | 'voided'
    | 'courier_damaged_settlement'
    | 'capex'
    | 'interest'
    | 'interest_fee';

export const TRANSACTION_TYPES: { value: TransactionType; label: string }[] = [
    { value: 'funds', label: 'Funds' },
    { value: 'profit_share', label: 'Profit Share' },
    { value: 'expenses', label: 'Expenses' },
    { value: 'transfer', label: 'Transfer' },
    { value: 'remittance', label: 'Remittance' },
    { value: 'loan', label: 'Loan' },
    { value: 'loan_payment', label: 'Loan Payment' },
    { value: 'refund', label: 'Refund' },
    { value: 'voided', label: 'Voided' },
    {
        value: 'courier_damaged_settlement',
        label: 'Courier Damaged Settlement',
    },
    { value: 'capex', label: 'CapEx' },
    { value: 'interest', label: 'Interest' },
    { value: 'interest_fee', label: 'Interest Fee' },
];

export const TRANSACTION_TYPE_LABEL: Record<TransactionType, string> =
    Object.fromEntries(
        TRANSACTION_TYPES.map((t) => [t.value, t.label]),
    ) as Record<TransactionType, string>;

// --- Dynamic (DB-backed) transaction types --------------------------------
// Types are now managed per-workspace on the Transaction Types page and are
// referenced on a transaction via `transaction_type_id`. The helpers below let
// the finance UI render/select those dynamic types while the styling palette
// still comes from the known legacy names (with a neutral fallback for custom
// types). The legacy `transaction_type` enum column is untouched.

export interface TransactionTypeItem {
    id: number;
    name: string;
    // Account nature: 'credit' = money in, 'debit' = money out. Drives the
    // transaction's in/out direction so it no longer has to be picked by hand.
    nature: 'debit' | 'credit';
}

export interface TransactionTypeOption {
    value: string;
    label: string;
}

/** Humanize a stored type name for display: 'profit_share' -> 'Profit Share'. */
export function transactionTypeLabel(value?: string | null): string {
    if (!value) return '';
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

/** Turn workspace types into {value,label} select options keyed by id. */
export function buildTransactionTypeOptions(
    types: TransactionTypeItem[],
): TransactionTypeOption[] {
    return types.map((t) => ({
        value: String(t.id),
        label: transactionTypeLabel(t.name),
    }));
}

const FALLBACK_STYLE = {
    cls: 'bg-slate-100 text-slate-600 dark:bg-slate-500/10 dark:text-slate-300',
};

/** Badge style for a transaction type name, with a neutral fallback. */
export function transactionTypeStyle(value?: string | null): { cls: string } {
    if (!value) return FALLBACK_STYLE;
    return TRANSACTION_TYPE_STYLE[value as TransactionType] ?? FALLBACK_STYLE;
}

export const TRANSACTION_TYPE_STYLE: Record<TransactionType, { cls: string }> =
    {
        funds: {
            cls: 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400',
        },
        profit_share: {
            cls: 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400',
        },
        expenses: {
            cls: 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400',
        },
        transfer: {
            cls: 'bg-slate-100 text-slate-600 dark:bg-slate-500/10 dark:text-slate-300',
        },
        remittance: {
            cls: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
        },
        loan: {
            cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        },
        loan_payment: {
            cls: 'bg-teal-50 text-teal-700 dark:bg-teal-500/10 dark:text-teal-400',
        },
        refund: {
            cls: 'bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400',
        },
        voided: {
            cls: 'bg-gray-100 text-gray-500 line-through dark:bg-white/5 dark:text-gray-400',
        },
        courier_damaged_settlement: {
            cls: 'bg-fuchsia-50 text-fuchsia-600 dark:bg-fuchsia-500/10 dark:text-fuchsia-400',
        },
        capex: {
            cls: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400',
        },
        interest: {
            cls: 'bg-lime-50 text-lime-700 dark:bg-lime-500/10 dark:text-lime-400',
        },
        interest_fee: {
            cls: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
        },
    };
