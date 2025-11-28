import { Workspace } from "./Workspace";

export interface WorkspaceInvitation {
    id: number;
    workspace_id: number;
    workspace: Workspace;
    email: string;
    token: string;
    role: string;
    accepted_at: string | null;
    expires_at: string;
    invited_by: number;
    inviter?: {
        id: number;
        name: string;
        email: string;
    };
    created_at: string;
    updated_at: string;
}