export interface Product {
    id: number;
    workspace_id: number;
    owner_id: number;
    title: string;
    name: string;
    code: string;
    category: string;
    status: 'Scaling' | 'Testing' | 'Failed' | 'Inactive';
    description: string | null;
    created_at: string;
    updated_at: string;
    owner?: {
        id: number;
        name: string;
    };
    advertising_sales?: number;
    sales?: number;
    ad_spent?: number;
    roas?: number;
    rts?: number
}
