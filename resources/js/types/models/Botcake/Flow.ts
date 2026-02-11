import { Page } from '@/types/models/Page';

export interface Flow {
    id: number;
    page_id: number;
    flow_id: number;
    parent_id: number | null;
    is_removed: boolean;
    delivery: number;
    is_clicked: number;
    seen: number;
    sent: number;
    total_phone_number: number;
    created_at: string;
    updated_at: string;
    name: string;
    page?: Page
}
