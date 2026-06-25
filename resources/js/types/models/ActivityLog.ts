import { User } from '@/types';
import { Workspace } from '@/types/models/Workspace';

export type LogType = 'user' | 'system';
export type LogStatus = 'success' | 'failure' | 'warning' | 'info';
export type LogCategory =
    | 'auth'
    | 'data'
    | 'security'
    | 'scheduled_job'
    | 'integration'
    | 'system';
export type TriggerType = 'scheduled' | 'event' | 'manual';

export interface ActivityLog {
    id: number;
    log_type: LogType;
    category: LogCategory;
    action_type: string | null;
    user_id: number | null;
    workspace_id: number | null;
    status: LogStatus;
    message: string | null;
    metadata: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    trigger_type: TriggerType | null;
    schedule: string | null;
    job_name: string | null;
    error_detail: string | null;
    created_at: string;

    user?: Pick<User, 'id' | 'name' | 'email'> | null;
    workspace?: Pick<Workspace, 'id' | 'name' | 'slug'> | null;
}

export interface ActivityLogSummary {
    total: number;
    failures: number;
    last_24h: number;
}
