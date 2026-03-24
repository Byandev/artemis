export const ORDER_STATUSES = [
    'PENDING',
    'DELIVERED',
    'RIDER OTW',
    'RETURNING',
    'RESCHEDULED',
    'CX CBR',
    'RIDER CBR',
    'CANCELLED',
    'WRONG SEGMENT CODE',
    'CX RINGING',
    'RIDER RINGING',
    'IN TRANSIT'
] as const;

export type orderStatus = (typeof ORDER_STATUSES)[number];


export interface OrderForDelivery {
    id: number;
    order_id: number;
    page_id: number;
    shop_id: number;
    workspace_id: number;
    status: string;
    rider_name: string;
    rider_phone: string;
    caller_id: string | null;
    conferrer_id: string | null;
    delivery_date: string; // ISO date
    created_at: string; // ISO datetime
    updated_at: string; // ISO datetime;

    order: Order;
    conferrer: any | null;
    page: Page;
}

interface Order {
    id: number;
    order_number: string;
    status_name: string;
    final_amount: number;
    parcel_status: string;
    tracking_code: string;
    delivery_attempts: number;
    shipping_address: ShippingAddress;
}

interface ShippingAddress {
    id: number;
    order_id: number;
    province_name: string;
    district_name: string;
    commune_name: string;
    address: string;
    full_address: string;
    full_name: string;
    phone_number: string;
    created_at: string;
    updated_at: string;
}

interface Page {
    id: number;
    name: string;
}
