export interface FinanceKpiDeltas {
    cash_in: number | null;
    cash_out: number | null;
    net_cash_flow: number | null;
}

export interface FinanceKpis {
    total_balance: number;
    cash_in: number;
    cash_out: number;
    net_cash_flow: number;
    unreconciled_count: number;
    unreconciled_amount: number;
    deltas: FinanceKpiDeltas;
    range: { start: string; end: string };
}

export interface CashFlowPoint {
    period: string;
    in: number;
    out: number;
    net: number;
}

export interface CashFlowSeries {
    unit: string;
    points: CashFlowPoint[];
}

export interface BalancePoint {
    period: string;
    balance: number;
}

export interface BalanceHistory {
    unit: string;
    points: BalancePoint[];
}

export interface CategorySlice {
    category: string;
    amount: number;
}

export interface TopMovement {
    id: number;
    date: string;
    description: string;
    type: 'in' | 'out';
    transaction_type: string | null;
    amount: number;
}

export interface ReconciliationBucket {
    label: string;
    count: number;
    amount: number;
}

export interface ReconciliationItem {
    id: number;
    soa_number: string;
    courier: string;
    billing_date_to: string;
    net_amount: number;
    days_outstanding: number;
}

export interface Reconciliation {
    total_count: number;
    total_amount: number;
    buckets: ReconciliationBucket[];
    items: ReconciliationItem[];
}

export interface Profitability {
    revenue: number;
    ad_spend: number;
    gross_profit: number;
    margin_pct: number | null;
    mer: number | null;
    assumptions: string[];
    range: { start: string; end: string };
}
