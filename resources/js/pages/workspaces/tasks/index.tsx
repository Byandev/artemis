import PageHeader from '@/components/common/PageHeader';
import { TaskCreateDialog } from '@/components/tasks/task-create-dialog';
import { TaskDeleteDialog } from '@/components/tasks/task-delete-dialog';
import { TaskDetailsDrawer } from '@/components/tasks/task-details-drawer';
import { TaskList } from '@/components/tasks/task-list';
import { TaskToolbar } from '@/components/tasks/task-toolbar';
import { getTaskStatus, STATUS_OPTIONS } from '@/components/tasks/task-utils';
import {
    Task,
    TaskFilter,
    TaskFormState,
    TaskIndexQuery,
    TaskStatus,
    TaskStatusCounts,
    WorkspaceMember,
} from '@/components/tasks/types';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface Props {
    workspace: Workspace;
    tasks: Task[];
    taskStatusCounts: TaskStatusCounts;
    workspaceMembers: WorkspaceMember[];
    query?: TaskIndexQuery;
}

interface PageProps {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

const EMPTY_TASK_FORM: TaskFormState = {
    name: '',
    description: '',
    dueDate: '',
    recurrence: 'none',
    recurringUntil: '',
    assignees: [],
};

export default function TasksIndex({
    workspace,
    tasks,
    taskStatusCounts,
    workspaceMembers,
    query,
}: Props) {
    const { flash } = usePage().props as PageProps;
    const [taskForm, setTaskForm] = useState<TaskFormState>(EMPTY_TASK_FORM);
    const [comments, setComments] = useState<Record<number, string>>({});
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [editingTaskId, setEditingTaskId] = useState<number | null>(null);
    const [deletingTaskId, setDeletingTaskId] = useState<number | null>(null);
    const [selectedTaskId, setSelectedTaskId] = useState<number | null>(null);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [statusFilter, setStatusFilter] = useState<TaskFilter>(
        query?.filter?.status ?? 'all',
    );
    const [isCreating, setIsCreating] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const groupedTasks = useMemo(
        () =>
            STATUS_OPTIONS.map((status) => ({
                ...status,
                tasks: tasks.filter(
                    (task) => getTaskStatus(task) === status.value,
                ),
            })).filter((group) => {
                if (statusFilter !== 'all') {
                    return group.value === statusFilter;
                }

                return group.tasks.length > 0;
            }),
        [statusFilter, tasks],
    );

    const selectedTask = useMemo(
        () => tasks.find((task) => task.id === selectedTaskId) ?? null,
        [selectedTaskId, tasks],
    );

    const selectedTaskStatus = selectedTask
        ? getTaskStatus(selectedTask)
        : null;

    const editingTask = useMemo(
        () => tasks.find((task) => task.id === editingTaskId) ?? null,
        [editingTaskId, tasks],
    );

    const deletingTask = useMemo(
        () => tasks.find((task) => task.id === deletingTaskId) ?? null,
        [deletingTaskId, tasks],
    );

    const totalTasks =
        taskStatusCounts.todo +
        taskStatusCounts.in_progress +
        taskStatusCounts.done;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/tasks`,
                {
                    'filter[search]': searchValue || undefined,
                    'filter[status]':
                        statusFilter === 'all' ? undefined : statusFilter,
                },
                {
                    only: ['tasks', 'taskStatusCounts', 'query'],
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [searchValue, statusFilter, workspace.slug]);

    const resetTaskForm = () => setTaskForm(EMPTY_TASK_FORM);

    const taskToForm = (task: Task): TaskFormState => ({
        name: task.name,
        description: task.description ?? '',
        dueDate: task.due_date ? task.due_date.slice(0, 10) : '',
        recurrence: task.recurrence ?? 'none',
        recurringUntil: task.recurring_until
            ? task.recurring_until.slice(0, 10)
            : '',
        assignees: task.assignees.map((assignee) => assignee.id),
    });

    const createTask = () => {
        if (taskForm.name.trim().length === 0) {
            toast.error('Task name is required.');
            return;
        }

        if (taskForm.recurrence !== 'none' && !taskForm.dueDate) {
            toast.error('Due date is required for recurring tasks.');
            return;
        }

        setIsCreating(true);

        router.post(
            `/workspaces/${workspace.slug}/tasks`,
            {
                name: taskForm.name.trim(),
                description: taskForm.description.trim() || null,
                due_date: taskForm.dueDate || null,
                recurrence: taskForm.recurrence,
                recurring_until:
                    taskForm.recurrence === 'none'
                        ? null
                        : taskForm.recurringUntil || null,
                assignees: taskForm.assignees,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    resetTaskForm();
                    setCreateDialogOpen(false);
                },
                onError: () => toast.error('Unable to create task.'),
                onFinish: () => setIsCreating(false),
            },
        );
    };

    const updateTask = () => {
        if (!editingTask) {
            return;
        }

        if (taskForm.name.trim().length === 0) {
            toast.error('Task name is required.');
            return;
        }

        if (taskForm.recurrence !== 'none' && !taskForm.dueDate) {
            toast.error('Due date is required for recurring tasks.');
            return;
        }

        setIsCreating(true);

        router.put(
            `/workspaces/${workspace.slug}/tasks/${editingTask.id}`,
            {
                name: taskForm.name.trim(),
                description: taskForm.description.trim() || null,
                due_date: taskForm.dueDate || null,
                recurrence: taskForm.recurrence,
                recurring_until:
                    taskForm.recurrence === 'none'
                        ? null
                        : taskForm.recurringUntil || null,
                assignees: taskForm.assignees,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    resetTaskForm();
                    setEditingTaskId(null);
                },
                onError: () => toast.error('Unable to update task.'),
                onFinish: () => setIsCreating(false),
            },
        );
    };

    const updateStatus = (
        taskId: number,
        userId: number,
        status: TaskStatus,
    ) => {
        router.patch(
            `/workspaces/${workspace.slug}/tasks/${taskId}/status`,
            { user_id: userId, status },
            {
                preserveScroll: true,
                onError: () => toast.error('Unable to update status.'),
            },
        );
    };

    const updateSelectedStatuses = (
        taskId: number,
        status: TaskStatus,
        userIds: number[],
    ) => {
        router.patch(
            `/workspaces/${workspace.slug}/tasks/${taskId}/status/all`,
            { status, user_ids: userIds },
            {
                preserveScroll: true,
                onError: () => toast.error('Unable to update assignees.'),
            },
        );
    };

    const submitComment = (taskId: number) => {
        const body = comments[taskId]?.trim();

        if (!body) {
            return;
        }

        router.post(
            `/workspaces/${workspace.slug}/tasks/${taskId}/comments`,
            { body },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setComments((current) => ({ ...current, [taskId]: '' })),
                onError: () => toast.error('Unable to add comment.'),
            },
        );
    };

    const deleteTask = () => {
        if (!deletingTask) {
            return;
        }

        setIsDeleting(true);

        router.delete(
            `/workspaces/${workspace.slug}/tasks/${deletingTask.id}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (selectedTaskId === deletingTask.id) {
                        setSelectedTaskId(null);
                    }

                    if (editingTaskId === deletingTask.id) {
                        setEditingTaskId(null);
                        resetTaskForm();
                    }

                    setDeleteDialogOpen(false);
                    setDeletingTaskId(null);
                },
                onError: () => toast.error('Unable to delete task.'),
                onFinish: () => setIsDeleting(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Tasks`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Tasks"
                    description="Create work, assign members, and keep comments attached to the task."
                >
                    <button
                        type="button"
                        onClick={() => setCreateDialogOpen(true)}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        <Plus className="mr-1.5 h-3.5 w-3.5" />
                        Add Task
                    </button>
                </PageHeader>

                <TaskCreateDialog
                    form={taskForm}
                    isCreating={isCreating}
                    mode={editingTask ? 'edit' : 'create'}
                    onCancel={() => {
                        resetTaskForm();
                        setCreateDialogOpen(false);
                        setEditingTaskId(null);
                    }}
                    onChange={setTaskForm}
                    onOpenChange={(open) => {
                        if (editingTask) {
                            if (!open) {
                                resetTaskForm();
                                setEditingTaskId(null);
                            }

                            return;
                        }

                        setCreateDialogOpen(open);
                        if (!open) {
                            resetTaskForm();
                        }
                    }}
                    onSubmit={editingTask ? updateTask : createTask}
                    open={createDialogOpen || editingTask !== null}
                    workspaceMembers={workspaceMembers}
                />

                <TaskDeleteDialog
                    isDeleting={isDeleting}
                    onConfirm={deleteTask}
                    onOpenChange={(open) => {
                        setDeleteDialogOpen(open);

                        if (!open) {
                            setDeletingTaskId(null);
                        }
                    }}
                    open={deleteDialogOpen}
                    task={deletingTask}
                />

                <TaskToolbar
                    searchValue={searchValue}
                    statusCounts={taskStatusCounts}
                    statusFilter={statusFilter}
                    totalTasks={totalTasks}
                    onSearchChange={setSearchValue}
                    onStatusFilterChange={setStatusFilter}
                />

                <TaskList
                    groupedTasks={groupedTasks}
                    hasAnyTasks={totalTasks > 0}
                    hasVisibleTasks={tasks.length > 0}
                    selectedTaskId={selectedTaskId}
                    onCreateTask={() => setCreateDialogOpen(true)}
                    onSelectTask={setSelectedTaskId}
                />

                <TaskDetailsDrawer
                    commentValue={
                        selectedTask ? (comments[selectedTask.id] ?? '') : ''
                    }
                    onCommentChange={(value) => {
                        if (!selectedTask) {
                            return;
                        }

                        setComments((current) => ({
                            ...current,
                            [selectedTask.id]: value,
                        }));
                    }}
                    onEdit={() => {
                        if (!selectedTask) {
                            return;
                        }

                        setTaskForm(taskToForm(selectedTask));
                        setEditingTaskId(selectedTask.id);
                    }}
                    onDelete={() => {
                        if (!selectedTask) {
                            return;
                        }

                        setDeletingTaskId(selectedTask.id);
                        setDeleteDialogOpen(true);
                    }}
                    onOpenChange={(open) => {
                        if (!open) {
                            setSelectedTaskId(null);
                        }
                    }}
                    onSubmitComment={() => {
                        if (selectedTask) {
                            submitComment(selectedTask.id);
                        }
                    }}
                    onUpdateStatus={(userId, status) => {
                        if (selectedTask) {
                            updateStatus(selectedTask.id, userId, status);
                        }
                    }}
                    onUpdateSelectedStatuses={(status, userIds) => {
                        if (selectedTask) {
                            updateSelectedStatuses(
                                selectedTask.id,
                                status,
                                userIds,
                            );
                        }
                    }}
                    open={selectedTask !== null}
                    task={selectedTask}
                    taskStatus={selectedTaskStatus}
                />
            </div>
        </AppLayout>
    );
}
