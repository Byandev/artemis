import { Shop } from '@/types/models/Shop';
import { User } from '@/types/models/User';
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
    deleted_at: string | null; // SoftDeletes column

    shop?: Shop;
    owner?: User;
    product?: Product;
    current_budget?: number;
}
