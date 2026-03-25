export interface User {
    id: string;
    name: string;
    fb_id: string;
    email: string;
    phone_number: string | null;
    created_at: string;
    orders_count: number;
    sales: number;
}
