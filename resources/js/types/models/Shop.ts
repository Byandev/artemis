export interface Shop {
    id: number;
    workspace_id: number;
    avatar_url: string;
    name: string;
    created_at: string;
    updated_at: string;
    pending_required_checklists_count?: number;
}
