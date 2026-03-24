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


export const STATUS_COLORS: Record<
    OrderStatus,
    { bg: string; text: string; border?: string }
> = {
    PENDING: {
        bg: 'bg-yellow-100',
        text: 'text-yellow-800',
        border: 'border-yellow-200',
    },
    DELIVERED: {
        bg: 'bg-green-100',
        text: 'text-green-800',
        border: 'border-green-200',
    },
    'RIDER OTW': {
        bg: 'bg-blue-100',
        text: 'text-blue-800',
        border: 'border-blue-200',
    },
    RETURNING: {
        bg: 'bg-orange-100',
        text: 'text-orange-800',
        border: 'border-orange-200',
    },
    RESCHEDULED: {
        bg: 'bg-purple-100',
        text: 'text-purple-800',
        border: 'border-purple-200',
    },
    'CX CBR': {
        bg: 'bg-red-100',
        text: 'text-red-800',
        border: 'border-red-200',
    },
    'RIDER CBR': {
        bg: 'bg-red-100',
        text: 'text-red-800',
        border: 'border-red-200',
    },
    CANCELLED: {
        bg: 'bg-gray-100',
        text: 'text-gray-800',
        border: 'border-gray-200',
    },
    'WRONG SEGMENT CODE': {
        bg: 'bg-red-100',
        text: 'text-red-800',
        border: 'border-red-200',
    },
    'CX RINGING': {
        bg: 'bg-amber-100',
        text: 'text-amber-800',
        border: 'border-amber-200',
    },
    'RIDER RINGING': {
        bg: 'bg-amber-100',
        text: 'text-amber-800',
        border: 'border-amber-200',
    },
    'IN TRANSIT': {
        bg: 'bg-cyan-100',
        text: 'text-cyan-800',
        border: 'border-cyan-200',
    },
};

export function getStatusBadgeClass(status: OrderStatus): string {
    const colors = STATUS_COLORS[status];
    return `${colors.bg} ${colors.text} px-2 py-1 rounded-full text-xs font-medium`;
}

export function getStatusPillClass(status: OrderStatus): string {
    const colors = STATUS_COLORS[status];
    return `${colors.bg} ${colors.text} ${colors.border} border px-3 py-1 rounded-full text-xs font-medium`;
}
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
