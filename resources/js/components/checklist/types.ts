export type ChecklistItem = {
    id: number;
    title: string;
    target: 'Shop' | 'Page';
    required: boolean;
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
