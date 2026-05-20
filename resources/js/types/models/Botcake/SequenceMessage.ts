import { Sequence } from '@/types/models/Botcake/Sequence';

export interface SequenceMessage {
    id: number;
    sequence_id: number;
    name: string;
    delivery: number;
    is_clicked?: number;
    seen: number;
    sent: number;
    total_phone_number: number;
    success_rate: number;
    created_at: string;
    updated_at: string;
    sequence?: Sequence;
}
