export type HeatGroupBy = 'region' | 'province';

export interface HeatRow {
    /**
     * The polygons this row shades. A region has no boundary of its own, so it
     * lists every province in it; a province or city is always exactly one.
     */
    gids: string[];
    /** Island group — set only when grouping by region. */
    region: string | null;
    /** Pancake's province id ("63_598") — set only when grouping by province. */
    province_id: string | null;
    province_name: string | null;
    orders: number;
    sales: number;
    delivered_count: number;
    delivered_amount: number;
    returning_count: number;
    returning_amount: number;
    /** Value-based: returning_amount over delivered_amount + returning_amount. */
    rts_rate_percentage: number;
}

/** An area the aggregation returned that has no matching polygon on the map. */
export type UnmappedRow = Omit<HeatRow, 'gids' | 'region' | 'province_id'>;

export interface HeatMapResponse {
    group_by: HeatGroupBy;
    rows: HeatRow[];
    unmapped: UnmappedRow[];
    /** The span the rollup covers for this workspace, ignoring the selected dates. */
    available: { first: string | null; last: string | null };
    totals: {
        orders: number;
        sales: number;
        delivered_count: number;
        delivered_amount: number;
        returning_count: number;
        returning_amount: number;
        rts_rate_percentage: number;
        mapped_areas: number;
        unmapped_areas: number;
    };
}
