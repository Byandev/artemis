export type WalletType = 'main' | 'backup';

/** Mirrors Modules\Finance\Enums\WalletType. */
export const WALLET_TYPES: { value: WalletType; label: string }[] = [
    { value: 'main', label: 'Main' },
    { value: 'backup', label: 'Backup' },
];

/** How a wallet's row is labelled in the grid's Account column. */
export const ACCOUNT_LABELS: Record<WalletType, string> = {
    main: 'Main Account',
    backup: 'Back up account',
};

export interface GridDate {
    date: string;
    label: string;
    is_today: boolean;
}

export interface WalletRow {
    id: number;
    name: string;
    currency: string;
    notes: string | null;
    is_active: boolean;
    is_user_wallet: boolean;
    opening_balance: number;
    wallet_type: WalletType | null;
    /**
     * Y-m-d the row's history starts — the wallet's creation day, or an earlier
     * back-dated entry. The row reads blank before it, though those days can
     * still be typed into.
     */
    opened_on: string;
    /** Liquidation entries against it — what a delete would take with it. */
    entry_count: number;
    /**
     * The wallet's balance at the close of each Y-m-d in the window, derived
     * from its ledger. Null before `opened_on`; from that day on it always
     * carries a figure, starting at the opening balance.
     */
    days: Record<string, number | null>;
}

export const peso = (v: number) =>
    `₱${v.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

/** finance_transactions.type — "in" is a credit, "out" a debit. */
export type EntryType = 'in' | 'out';

/**
 * One line of the liquidation ledger. These are rows of finance_transactions
 * against the wallet, so the fields carry that table's column names even where
 * the ledger labels them differently (Transaction, Type Expenses, Remarks).
 */
export interface LiquidationEntry {
    id: number;
    date: string;
    /** The ledger's "Transaction" column — Funds, Ad Spent, Pondo. */
    transaction_type_id: number;
    transaction_type_name: string | null;
    /** The ledger's "Type Expenses" column — FB ADS, Funds from ate MY. */
    description: string;
    department: string | null;
    type: EntryType;
    amount: number;
    notes: string | null;
    running_balance: number;
}

export interface TransactionTypeOption {
    id: number;
    name: string;
}

/** The ledger's totals, aggregated server-side over the whole ledger. */
export interface LiquidationTotals {
    total_credit: number;
    credit_count: number;
    total_debit: number;
    debit_count: number;
    actual_balance: number;
    entry_count: number;
    first_date: string | null;
    last_date: string | null;
}

/** "Jun 4,2025" — the compact form the liquidation table uses. */
export const postedDate = (date: string) => {
    const d = new Date(`${date}T00:00:00`);

    return `${d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })},${d.getFullYear()}`;
};
