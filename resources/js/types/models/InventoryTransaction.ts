export interface InventoryTransaction {
    id: number;
    inventory_item_id: number | null;
    inventory_item?: {
        id: number;
        sku: string;
        product?: {
            id: number;
            name: string;
        };
    };
    date: string | null;
    ref_no: string | null;
    po_qty_in: number;
    po_qty_out: number;
    rts_goods_out: number;
    rts_goods_in: number;
    rts_bad: number;
    lost: number;
    remaining_qty: number;
}
