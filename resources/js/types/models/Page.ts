import { Shop } from '@/types/models/Shop';

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

    shop?: Shop
}
