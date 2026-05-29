import { User } from '@/types';
import { Workspace } from '@/types/models/Workspace';

export interface SupportTicket {
    id: number;
    workspace_id: number;
    user_id: number;
    reference: string;
    category: 'question' | 'bug' | 'feature_request' | 'billing' | 'other';
    subject: string;
    description: string;
    current_url: string | null;
    user_agent: string | null;
    status: 'open' | 'in_progress' | 'resolved' | 'closed';
    created_at: string;
    updated_at: string;
    user?: User;
    workspace?: Workspace;
}
