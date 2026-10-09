import { Product } from '@/types/models/Product';

export interface Shop {
    id: number;
    workspace_id: number;
    product_id?: number | null;
    avatar_url: string;
    name: string;
    pos_token?: string | null;
    orders_last_synced_at: string | null;
    /** New orders get their address read from the conversation and written back to Pancake. */
    auto_fill_address?: boolean;
    /** Sent by Pancake in the X-Artemis-Secret webhook header. Only shown to shop editors. */
    webhook_secret?: string | null;
    created_at: string;
    updated_at: string;
    pending_required_checklists_count?: number;
    teams?: { id: number; name: string }[];
    product?: Product | null;
}
