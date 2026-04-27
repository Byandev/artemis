export interface Role {
    id: number;
    name: string;
    description: string;
    deleted_at?: string | null;
    permissions?: { id: number; category: string; name: string }[];
}
