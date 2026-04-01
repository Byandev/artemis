import { Page } from '@/types/models/Page';
import { Shop } from '@/types/models/Shop';
import { User } from '@/types';

export interface Workspace {
    id: number;
    name: string;
    slug: string;
    owner_id: number;
    created_at: string;
    updated_at: string;

    pages?: Page[]
    shops?: Shop[]
    page_owners?: User[]
}
