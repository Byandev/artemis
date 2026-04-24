import { Shop } from '@/types/models/Shop';
import { User } from '@/types';
import { Product } from '@/types/models/Product';

export interface Page {
    id: number;
    shop_id: number;
    workspace_id: number;
    owner_id: number;
    name: string;
    facebook_url?: string;
    pos_token?: string;
    botcake_token?: string;
    infotxt_token?: string;
    infotxt_user_id?: string;
    orders_last_synced_at: string;
    status: 'active' | 'inactive';
    deleted_at: string | null; // SoftDeletes column
    pancake_token: string | null;
    parcel_journey_flow_id: number | null;
    parcel_journey_custom_field_id: number | null;
    parcel_journey_enabled: boolean | null;
    is_sync_logic_updated: boolean | null;
    pending_required_checklists_count?: number;

    shop?: Shop;
    owner?: User;
    product?: Product;
    current_budget?: number;
}
