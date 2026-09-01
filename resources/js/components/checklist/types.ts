export type ChecklistItem = {
    id: number;
    title: string;
    target: 'Shop' | 'Page';
    required: boolean;
};

export type ChecklistProof = {
    file_name: string;
    mime_type: string;
    size: number;
    url: string;
};

export type ChecklistProgressItem = ChecklistItem & {
    requires_proof: boolean;
    is_completed: boolean;
    checked_by_name?: string;
    checked_at?: string;
    note?: string | null;
    proof?: ChecklistProof | null;
};

export type AddTaskForm = {
    title: string;
    target: 'Shop' | 'Page' | '';
    required: boolean;
};

export const ADD_TASK_FORM_INITIAL: AddTaskForm = {
    title: '',
    target: '',
    required: true,
};

export const PROOF_ACCEPT =
    'image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf';

export const PROOF_MAX_MB = 10;

export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
