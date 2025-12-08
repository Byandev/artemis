export interface Product {
    id: number;
    workspace_id: number;
    owner_id: number;
    title: string;
    name: string;
    code: string;
    category: string;
    ad_budget_today: number;
    status: 'Scaling' | 'Testing' | 'Failed' | 'Inactive';
    description: string | null;
    created_at: string;
    updated_at: string;
    owner?: {
        id: number;
        name: string;
    };
}
