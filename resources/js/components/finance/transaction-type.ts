export type TransactionType =
    | 'funds' | 'profit_share' | 'expenses' | 'transfer' | 'remittance'
    | 'loan' | 'loan_payment' | 'refund' | 'voided' | 'courier_damaged_settlement';

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
    { value: 'courier_damaged_settlement', label: 'Courier Damaged Settlement' },
];

export const TRANSACTION_TYPE_LABEL: Record<TransactionType, string> = Object.fromEntries(
    TRANSACTION_TYPES.map((t) => [t.value, t.label]),
) as Record<TransactionType, string>;

export const TRANSACTION_TYPE_STYLE: Record<TransactionType, { cls: string }> = {
    funds: { cls: 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400' },
    profit_share: { cls: 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400' },
    expenses: { cls: 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400' },
    transfer: { cls: 'bg-slate-100 text-slate-600 dark:bg-slate-500/10 dark:text-slate-300' },
    remittance: { cls: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' },
    loan: { cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' },
    loan_payment: { cls: 'bg-teal-50 text-teal-700 dark:bg-teal-500/10 dark:text-teal-400' },
    refund: { cls: 'bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400' },
    voided: { cls: 'bg-gray-100 text-gray-500 line-through dark:bg-white/5 dark:text-gray-400' },
    courier_damaged_settlement: { cls: 'bg-fuchsia-50 text-fuchsia-600 dark:bg-fuchsia-500/10 dark:text-fuchsia-400' },
};
