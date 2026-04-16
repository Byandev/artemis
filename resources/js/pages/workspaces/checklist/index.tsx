import PageHeader from '@/components/common/PageHeader';
import { AddTaskDialog } from '@/components/checklist/add-task-dialog';
import { DeleteChecklistDialog } from '@/components/checklist/delete-checklist-dialog';
import { getChecklistColumns } from '@/components/checklist/checklist-columns';
import { ADD_TASK_FORM_INITIAL, AddTaskForm, ChecklistItem } from '@/components/checklist/types';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { omit } from 'lodash';
import { useCallback, useMemo, useState } from 'react';

interface Props {
    workspace: Workspace;
    checklists: PaginatedData<ChecklistItem>;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
}

export default function ChecklistPage({ workspace, checklists, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);
    const [addTaskOpen, setAddTaskOpen] = useState(false);
    const [dialogMode, setDialogMode] = useState<'add' | 'edit'>('add');
    const [editingItemId, setEditingItemId] = useState<number | null>(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [itemToDelete, setItemToDelete] = useState<ChecklistItem | null>(null);
    const [addTaskForm, setAddTaskForm] = useState<AddTaskForm>(ADD_TASK_FORM_INITIAL);

    const isAddTaskValid = addTaskForm.title.trim().length > 0 && addTaskForm.target !== '';

    const resetAddTaskForm = () => setAddTaskForm(ADD_TASK_FORM_INITIAL);

    const submitAddTask = () => {
        if (!isAddTaskValid) {
            return;
        }

        const payload = {
            title: addTaskForm.title.trim(),
            target: addTaskForm.target as 'Shop' | 'Page',
            required: addTaskForm.required,
        };

        if (dialogMode === 'edit' && editingItemId !== null) {
            router.put(`/workspaces/${workspace.slug}/checklist/${editingItemId}`, payload, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setAddTaskOpen(false);
                    setDialogMode('add');
                    setEditingItemId(null);
                    resetAddTaskForm();
                },
            });
        } else {
            router.post(`/workspaces/${workspace.slug}/checklist`, payload, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setAddTaskOpen(false);
                    setDialogMode('add');
                    setEditingItemId(null);
                    resetAddTaskForm();
                },
            });
        }
    };

    const openEdit = useCallback((item: ChecklistItem) => {
        setDialogMode('edit');
        setEditingItemId(item.id);
        setAddTaskForm({
            title: item.title,
            target: item.target,
            required: item.required,
        });
        setAddTaskOpen(true);
    }, []);

    const openDelete = useCallback((item: ChecklistItem) => {
        setItemToDelete(item);
        setDeleteDialogOpen(true);
    }, []);

    const confirmDelete = useCallback(() => {
        if (!itemToDelete) {
            setDeleteDialogOpen(false);
            return;
        }

        router.delete(`/workspaces/${workspace.slug}/checklist/${itemToDelete.id}`, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                setDeleteDialogOpen(false);
                setItemToDelete(null);
            },
        });
    }, [itemToDelete, workspace.slug]);

    const columns = useMemo(
        () => getChecklistColumns({
            onEdit: openEdit,
            onDelete: openDelete,
        }),
        [openDelete, openEdit, workspace.slug]
    );

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Checklist`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Checklist"
                    description="Manage your tasks efficiently and never miss a requirement."
                >
                    <button
                        type="button"
                        onClick={() => {
                            setDialogMode('add');
                            setEditingItemId(null);
                            resetAddTaskForm();
                            setAddTaskOpen(true);
                        }}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        <Plus className="mr-1.5 h-3.5 w-3.5" />
                        Add Task
                    </button>
                </PageHeader>

                <AddTaskDialog
                    open={addTaskOpen}
                    onOpenChange={(open) => {
                        setAddTaskOpen(open);
                        if (!open) {
                            setDialogMode('add');
                            setEditingItemId(null);
                            resetAddTaskForm();
                        }
                    }}
                    mode={dialogMode}
                    form={addTaskForm}
                    setForm={setAddTaskForm}
                    onSubmit={submitAddTask}
                    onCancel={() => {
                        setAddTaskOpen(false);
                        setDialogMode('add');
                        setEditingItemId(null);
                        resetAddTaskForm();
                    }}
                />

                <DeleteChecklistDialog
                    open={deleteDialogOpen}
                    onOpenChange={(open) => {
                        setDeleteDialogOpen(open);
                        if (!open) {
                            setItemToDelete(null);
                        }
                    }}
                    item={itemToDelete}
                    onConfirm={confirmDelete}
                />

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white shadow-theme-xs dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={checklists.data || []}
                        meta={{ ...omit(checklists, ['data']) }}
                        initialSorting={initialSorting}
                        onFetch={(params) => {
                            router.get(
                                `/workspaces/${workspace.slug}/checklist`,
                                {
                                    sort: params?.sort,
                                    page: params?.page ?? 1,
                                },
                                {
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                    only: ['checklists', 'query'],
                                }
                            );
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
