export type StatusId =
    | 'For Approval'
    | 'Approved'
    | 'To Pay'
    | 'Paid'
    | 'For Purchase'
    | 'Waiting For Delivery'
    | 'Delivered'
    | 'Cancelled';

export interface PurchasedOrder {
    id: number;
    issue_date: string;
    delivery_no: string | null;
    cust_po_no: string | null;
    control_no: string | null;
    item: string;
    cog_amount: number;
    delivery_fee: number;
    total_amount: number;
    status: StatusId;
}

export interface PaginatedResponse {
    data: PurchasedOrder[];
    available_years?: number[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface StatusOption {
    value: StatusId;
    label: string;
}
