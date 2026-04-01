export interface InventoryTransaction {
    id: number;
    date: string | null;
    ref_no: string | null;
    po_qty_in: number;
    po_qty_out: number;
    rts_goods_out: number;
    rts_goods_in: number;
    rts_bad: number;
    remaining_qty: number;
}