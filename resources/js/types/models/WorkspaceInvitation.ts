import { Workspace } from "./Workspace";

export interface WorkspaceInvitation {
  id: number;
  workspace_id: number;
  workspace: Workspace;
  invited_by: number;
  email: string;
  token: string;
  role: 'admin' | 'member';
  expires_at: string;
  accepted_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}
