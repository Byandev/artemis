/**
 * Shapes returned by the PO-flow endpoints
 * (Modules/Inventory/app/Http/Controllers/Api/PurchaseOrderFlowController).
 *
 * Every panel reads days of demand alongside units, because units alone rank
 * wrongly: 3,400 of a slow mover holds up more selling than 13,000 of a fast one.
 */

/** How a step is doing against its target. */
export type FlowState = 'blocked' | 'watch' | 'ok';

/** Whether an order has left the building yet. */
export type FlowKind = 'internal' | 'supplier';

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
    /**
     * The supplier leg: first arrival, then how long each share of the order
     * took to land. Levels nothing has reached yet are absent rather than zero.
     */
    supplier_steps: TimingStep[];
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
}

export interface UnfulfilledSplitData {
    items: UnfulfilledRow[];
    here: number;
    gone: number;
    total: number;
    picking_days: number;
    sitting: { skus: number; units: number };
    worst: UnfulfilledRow | null;
}
