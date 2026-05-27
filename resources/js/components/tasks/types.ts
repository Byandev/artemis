import { User } from '@/types';
import { Workspace } from '@/types/models/Workspace';

export type TaskStatus = 'todo' | 'in_progress' | 'done';
export type TaskFilter = 'all' | TaskStatus;

export interface TaskComment {
    id: number;
    body: string;
    created_at: string;
    user: Pick<User, 'id' | 'name' | 'email'> | null;
}

export interface TaskAssignee extends Pick<User, 'id' | 'name' | 'email'> {
    pivot: {
        status: TaskStatus;
    };
}

export interface Task {
    id: number;
    name: string;
    description: string | null;
    due_date: string | null;
    created_at: string;
    creator: Pick<User, 'id' | 'name' | 'email'> | null;
    assignees: TaskAssignee[];
    comments: TaskComment[];
}

export interface TaskFormState {
    name: string;
    description: string;
    dueDate: string;
    assignees: number[];
}

export interface TaskIndexQuery {
    filter?: {
        search?: string;
        status?: TaskStatus;
    };
}

export interface TaskStatusCounts {
    todo: number;
    in_progress: number;
    done: number;
}

export type WorkspaceMember = Pick<User, 'id' | 'name' | 'email'>;

export interface TaskWorkspaceProps {
    workspace: Workspace;
}
