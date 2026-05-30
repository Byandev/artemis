export interface MonthlyPerf {
    total_orders: number;
    total_sales: number;
    delivered: number;
    returning_count: number;
    rts_rate: number;
    total_called: number;
    total_call_time: number;
}

export interface TodayStats {
    assigned: number;
    called: number;
    delivered: number;
    returning: number;
    pending: number;
}

export interface TopCsr {
    user_id: number;
    csr_name: string;
    total_sales: number;
    total_orders: number;
    delivered: number;
    returning_count: number;
    rts_rate: number;
}

export interface PendingOrder {
    id: number;
    order_id: number;
    rider_name: string | null;
    order: {
        id: number;
        order_number: string;
        tracking_code: string | null;
        final_amount: number;
        shipping_address?: { full_name: string } | null;
    };
}

export interface DailyTrendEntry {
    date: string;
    orders: number;
    sales: number;
    delivered: number;
    returning: number;
    rts_rate: number;
    called: number;
    call_time: number;
}

export interface StatusBreakdownEntry {
    status: string;
    count: number;
}

export interface CsrScheduleEntry {
    id: number;
    pancake_user_id: string;
    name: string;
    date: string;
    shift_start: string;
    shift_end: string;
    notes: string | null;
}

export interface PancakeAccount {
    id: string;
    name: string;
    email: string | null;
    phone_number: string | null;
    status: string;
    fb_id: string | null;
}
