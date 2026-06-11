import { User } from '@/types';
import { Page } from '@/types/models/Page';
import { Shop } from '@/types/models/Shop';
import { Team } from '@/types/models/Team';

export interface Workspace {
    id: number;
    name: string;
    slug: string;
    owner_id: number;
    inventory_module_enabled: boolean;
    finance_module_enabled: boolean;
    products_module_enabled: boolean;
    teams_module_enabled: boolean;
    checklist_module_enabled: boolean;
    csr_module_enabled: boolean;
    rmo_module_enabled: boolean;
    leaderboard_module_enabled: boolean;
    botcake_module_enabled: boolean;
    creatives_module_enabled: boolean;
    meta_ads_module_enabled: boolean;
    sales_marketing_dashboard_module_enabled: boolean;
    video_editor_dashboard_module_enabled: boolean;
    /** Whether a public-pages access password is configured. */
    public_password_set?: boolean;
    created_at: string;
    updated_at: string;

    pages?: Page[];
    shops?: Shop[];
    teams?: Team[];
    page_owners?: User[];
}
