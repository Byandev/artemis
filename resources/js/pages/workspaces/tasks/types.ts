export type SpaceRole = 'owner' | 'admin' | 'member' | 'viewer';

export type TaskPriority = 'urgent' | 'high' | 'normal' | 'low';

export type TaskStatusType = 'not_started' | 'active' | 'done' | 'closed';

export type Metadata = Record<string, unknown> | null;

export type Space = {
    id: number;
    workspace_id: number;
    owner_id: number;
    name: string;
    description: string | null;
    color: string | null;
    position: number;
    metadata: Metadata;
    archived: boolean;
    archived_at: string | null;
    role: SpaceRole | null;
    created_at: string | null;
    updated_at: string | null;
};

export type Folder = {
    id: number;
    space_id: number;
    name: string;
    /** Prefix every task under this project is numbered with, e.g. `ART`. */
    code: string | null;
    description: string | null;
    position: number;
    metadata: Metadata;
    archived: boolean;
};

export type TaskList = {
    id: number;
    space_id: number;
    folder_id: number | null;
    name: string;
    description: string | null;
    position: number;
    metadata: Metadata;
    archived: boolean;
    tasks_count?: number;
};

export type TaskStatus = {
    id: number;
    space_id: number;
    name: string;
    slug: string;
    color: string | null;
    type: TaskStatusType;
    is_complete: boolean;
    position: number;
    is_default: boolean;
};

/** The writable shape of a status, as the statuses endpoints accept it. */
export type TaskStatusInput = {
    name: string;
    type: TaskStatusType;
    color: string | null;
    is_default?: boolean;
    position?: number;
};

export type Label = {
    id: number;
    space_id: number;
    name: string;
    color: string | null;
};

export type UserSummary = {
    id: number;
    name: string;
    email: string;
};

/** A user seen through their membership of one space. */
export type SpaceMember = UserSummary & {
    role: SpaceRole;
};

/** The roles a member can be given — ownership travels with the space itself. */
export type AssignableSpaceRole = Exclude<SpaceRole, 'owner'>;

export type Task = {
    id: number;
    list_id: number;
    status_id: number;
    parent_id: number | null;
    created_by: number | null;
    name: string;
    /** Short identifier the task is referred to by, e.g. `ART-12`. Frozen once assigned. */
    ticket: string | null;
    ticket_code: string | null;
    ticket_number: number | null;
    description: string | null;
    priority: TaskPriority | null;
    position: number;
    estimate_minutes: number | null;
    start_at: string | null;
    due_at: string | null;
    completed_at: string | null;
    completed: boolean;
    archived: boolean;
    metadata: Metadata;
    status?: TaskStatus;
    list?: TaskList;
    parent?: Task;
    subtasks?: Task[];
    subtasks_count?: number;
    assignees?: UserSummary[];
    labels?: Label[];
    creator?: UserSummary;
    created_at: string | null;
    updated_at: string | null;
};

/** One message in a task's discussion. */
export type Comment = {
    id: number;
    task_id: number;
    user_id: number;
    body: string;
    /** True once the author has changed the body after posting it. */
    edited: boolean;
    edited_at: string | null;
    author?: UserSummary;
    created_at: string | null;
    updated_at: string | null;
};

/** One file stored against a task. */
export type Attachment = {
    id: number;
    task_id: number;
    /** The uploaded file's name without its extension, as the uploader typed it. */
    name: string;
    /** The name the file is stored and downloaded under, sanitised. */
    file_name: string;
    mime_type: string | null;
    /** Size in bytes. */
    size: number;
    /** Our own endpoint, which authorises and then streams the file to be saved. */
    download_url: string;
    /**
     * Our own endpoint, which shows the file in the browser when that is safe
     * and falls back to handing it over when it is not. Safe to open for any
     * file.
     */
    preview_url: string;
    /** Whether `preview_url` will render rather than download. */
    previewable: boolean;
    /** Null once the account that attached the file has been deleted. */
    uploaded_by: number | null;
    uploader?: UserSummary;
    created_at: string | null;
};

export type Paginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

export type TaskFilters = {
    search: string;
    status_id: string;
    priority: string;
    sort: string;
};
