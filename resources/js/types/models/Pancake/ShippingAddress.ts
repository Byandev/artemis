export interface ShippingAddress {
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
    city_order_summary?: {
        rts_rate: number | null;
    };
}
