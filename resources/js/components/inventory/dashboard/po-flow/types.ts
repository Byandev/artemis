/**
 * Shapes returned by the PO-flow endpoints
 * (Modules/Inventory/app/Http/Controllers/Api/PurchaseOrderFlowController).
 *
 * Every panel reads days of demand alongside units, because units alone rank
 * wrongly: 3,400 of a slow mover holds up more selling than 13,000 of a fast one.
 */

/** How a step is doing against its target. */
export type FlowState = 'blocked' | 'watch' | 'ok';

/**
 * Whether an order has left the building yet — plus `total`, for measures that
 * span both legs (issue date through to delivery).
 */
export type FlowKind = 'internal' | 'supplier' | 'total';

/** A supporting number under an owner's headline. Value is null when unknown. */
export type OwnerFact = [label: string, value: number | null, unit: string];

export interface FlowOwner {
    key: 'operations' | 'supplier' | 'warehouse';
    name: string;
    question: string;
    state: FlowState;
    value: number;
    unit: string;
    facts: OwnerFact[];
}

export interface BottleneckData {
    owners: FlowOwner[];
    blocked: FlowOwner['key'][];
    internal_units: number;
    supplier_units: number;
    sla_days: number;
    /** Ledger days shippable stock has to leave before it counts as stalled. */
    ship_target_days: number;
    /** The ledger's own latest day, which idle is measured against. */
    idle_as_of: string | null;
}

export interface PipelineStage {
    name: string;
    kind: FlowKind;
    units: number;
    orders: number;
    oldest: number;
    overdue_units: number;
    buckets: Record<string, number>;
}

export interface PipelineData {
    stages: PipelineStage[];
    buckets: { key: string; label: string }[];
    internal_units: number;
    supplier_units: number;
    total_units: number;
    sla_days: number;
    po_needed: number;
}

export interface WorklistLine {
    id: number;
    purchased_order_id: number;
    po: string;
    stage: string;
    status: number;
    age: number;
    overdue: boolean;
    item: string;
    product_name: string | null;
    units: number;
    covers_days: number | null;
}

export interface WorklistData {
    lines: WorklistLine[];
    total_units: number;
    overdue_units: number;
    sla_days: number;
}

export interface SupplierLine {
    id: number;
    purchased_order_id: number;
    po: string;
    supplier: string | null;
    stage: string;
    age: number;
    item: string;
    product_name: string | null;
    ordered: number;
    delivered: number;
    balance: number;
    fill_pct: number;
    last_delivery: string | null;
    covers_days: number | null;
}

/** Orders / lines / units, counted per order so a three-line PO is one call. */
export interface SupplierTally {
    orders: number;
    lines: number;
    units: number;
}

export interface SupplierData {
    lines: SupplierLine[];
    quoted_days: number;
    nothing_arrived: SupplierTally;
    part_delivered: SupplierTally;
    past_quote: SupplierTally;
    total_units: number;
}

export interface TimingStep {
    label: string;
    kind: FlowKind;
    samples: number;
    p50: number | null;
    p90: number | null;
}

export interface ClusteringRow {
    label: string;
    events: number;
    days: number;
    busiest_three_pct: number;
}

export interface StageTimingData {
    /** The internal workflow, one row per transition that takes any time. */
    steps: TimingStep[];
    internal_total: TimingStep;
    /** Release to first arrival — the supplier leg alone, where logged. */
    released_to_delivery: TimingStep;
    /**
     * The delivery curve door to door: first arrival, then how long each share
     * of the order took to land, measured from the issue date. Levels nothing
     * has reached yet are absent rather than zero.
     */
    delivery_steps: TimingStep[];
    fill_levels: number[];
    clustering: ClusteringRow[];
    orders_sampled: number;
}

export interface UnfulfilledRow {
    id: number;
    item: string;
    unfulfilled: number;
    /** Stock is on the shelf — the warehouse could ship this today. */
    here: number;
    /** Nothing behind it — this one is waiting on supply. */
    gone: number;
    here_days: number | null;
    /**
     * Date-only (Y-m-d) of the last purchase-order receipt and the last
     * despatch, from the transaction ledger. Null when the ledger holds no such
     * movement — which for a busy item usually means the ledger does not reach
     * back far enough, not that the item never moved.
     */
    last_in: string | null;
    last_out: string | null;
    /**
     * Ledger days since the last despatch, measured against the ledger's own
     * latest day rather than today — the ERP feed lags, and counting from today
     * would report that lag as idleness on every SKU alike. Null when the group
     * has never shipped: unknown, not idle.
     */
    idle_days: number | null;
}

export interface UnfulfilledSplitData {
    items: UnfulfilledRow[];
    here: number;
    gone: number;
    total: number;
    picking_days: number;
    sitting: { skus: number; units: number };
    worst: UnfulfilledRow | null;
    /** Ledger days without a despatch before shippable stock counts as idle. */
    ship_target_days: number;
    idle: {
        units: number;
        skus: number;
        /** Longest wait across all shippable stock, not just the part over the threshold. */
        longest: number | null;
        worst: UnfulfilledRow | null;
        /** The ledger's latest day. Null when the ledger is empty. */
        as_of: string | null;
    };
    /** Stock received after the last despatch — arrived, and nothing has left since. */
    arrived: { units: number; skus: number };
}
