import { Page } from '@/types/models/Page';
import { Shop } from '@/types/models/Shop';
import { Team } from '@/types/models/Team';
import { User } from '@/types';

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
    created_at: string;
    updated_at: string;

    pages?: Page[]
    shops?: Shop[]
    teams?: Team[]
    page_owners?: User[]
}
