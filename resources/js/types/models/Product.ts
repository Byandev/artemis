import { Shop } from '@/types/models/Shop';

export interface Product {
    id: number;
    workspace_id: number;
    owner_id: number;
    title: string;
    name: string;
    code: string;
    category: string;
    status: 'Scaling' | 'Testing' | 'Failed' | 'Inactive';
    /** Y-m-d date the product was marked a winning item. */
    winning_date: string | null;
    description: string | null;
    created_at: string;
    updated_at: string;
    owner?: {
        id: number;
        name: string;
    };
    shops?: Shop[];
    advertising_sales?: number;
    sales?: number;
    ad_spent?: number;
    roas?: number;
    rts?: number;
}
