import { Page } from '@/types/models/Page';

export interface Sequence {
    id: number;
    page_id: number;
    sequence_id: number;
    name: string;
    created_at: string;
    updated_at: string;
    total_sent: number;
    total_phone_number: number;
    success_rate: number;
    page?: Page;
}
