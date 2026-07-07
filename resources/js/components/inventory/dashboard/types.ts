export interface InventoryKpis {
    active_skus: number;
    total_stock_on_hand: number;
    low_stock_count: number;
    reorder_needed_count: number;
    incoming_units: number;
    open_pos: number;
    overdue_pos: number;
    shrinkage_units: number;
}

export interface MovementData {
    categories: string[];
    po_qty_in: number[];
    rts_goods_in: number[];
    po_qty_out: number[];
    rts_goods_out: number[];
    rts_bad: number[];
    lost: number[];
    remaining_stock: number[];
}

export interface PoStatusSlice {
    status: number;
    label: string;
    count: number;
}

export interface Fulfillment {
    waiting: number;
    partial: number;
    delivered: number;
}

export interface TrendData {
    categories: string[];
    values: number[];
}

export interface StockHealthRow {
    id: number;
    sku: string;
    product_name: string | null;
    current_stocks: number | null;
    three_days_average: number;
    days_it_can_last: number;
    lead_time: number;
    waiting_for_delivery_stocks: number | null;
    po_needed: number;
    at_risk: boolean;
}

export interface UpcomingDelivery {
    id: number;
    reference: string;
    delivery_no: string | null;
    expected_delivery_date: string | null;
    status_label: string;
    delayed: boolean;
}

export interface RecentAdjustment {
    id: number;
    date: string;
    sku: string;
    expected_qty: number;
    counted_qty: number;
    discrepancy: number;
}

export interface TopDiscrepancy {
    sku: string;
    discrepancy: number;
}

export type AlertSeverity = 'critical' | 'warning' | 'info';

export interface InventoryAlert {
    type: string;
    severity: AlertSeverity;
    title: string;
    description: string;
}
