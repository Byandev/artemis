export interface Role {
    id: number;
    display_name: string;
    role: string;
    description: string;
    deleted_at?: string | null;
}
