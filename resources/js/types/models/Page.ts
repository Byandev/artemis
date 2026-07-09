import { User } from '@/types';
import { Shop } from '@/types/models/Shop';

export interface Page {
    id: number;
    shop_id: number;
    workspace_id: number;
    owner_id: number;
    // product_id removed — a page's product now lives on its shop (shops.product_id)
    name: string;
    facebook_url?: string;
    botcake_token?: string;
    sms_provider?: 'infotxt' | 'sendgate';
    infotxt_token?: string;
    infotxt_user_id?: string;
    sendgate_api_key?: string;
    sendgate_sim_id?: string;
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
    latest_budget?: {
        budget: number | string | null;
        date: string;
        updated_at: string;
    } | null;
}
