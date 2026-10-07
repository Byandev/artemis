import { Can } from '@/components/can';
import { AddTaskDialog } from '@/components/checklist/add-task-dialog';
import { getChecklistColumns } from '@/components/checklist/checklist-columns';
import { DeleteChecklistDialog } from '@/components/checklist/delete-checklist-dialog';
import {
    ADD_TASK_FORM_INITIAL,
    AddTaskForm,
    ChecklistItem,
} from '@/components/checklist/types';
import PageHeader from '@/components/common/PageHeader';
import { DataTable } from '@/components/ui/data-table';
import { PERMISSIONS } from '@/constants/permissions';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import checklistApi from '@/routes/api/workspaces/checklist';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { omit } from 'lodash';
import { Plus } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

type FetchParams = {
    sort?: string | null;
    page?: number | string;
    per_page?: number | string;
};

interface Props {
    workspace: Workspace;
    query?: {
        sort?: string | null;
        per_page?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
}

interface PageProps {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

export default function ChecklistPage({ workspace, query }: Props) {
    const { flash } = usePage().props as PageProps;
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [addTaskOpen, setAddTaskOpen] = useState(false);
    const [dialogMode, setDialogMode] = useState<'add' | 'edit'>('add');
    const [editingItemId, setEditingItemId] = useState<number | null>(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [itemToDelete, setItemToDelete] = useState<ChecklistItem | null>(
        null,
    );
    const [addTaskForm, setAddTaskForm] = useState<AddTaskForm>(
        ADD_TASK_FORM_INITIAL,
    );

    const [checklists, setChecklists] =
        useState<PaginatedData<ChecklistItem> | null>(null);
    const [loading, setLoading] = useState(true);

    // The params of the last fetch, so a refetch after a save or delete keeps
    // the page and sort the user was looking at.
    const paramsRef = useRef<FetchParams>({
        sort: query?.sort ?? null,
        page: query?.page ?? 1,
        per_page: query?.per_page,
    });
    // Drops responses from fetches a newer one has superseded.
    const requestIdRef = useRef(0);

    const fetchChecklists = useCallback(
        async (next: FetchParams = {}) => {
            const params = { ...paramsRef.current, ...next };
            paramsRef.current = params;

            const queryParams = {
                sort: params.sort || undefined,
                page: params.page || undefined,
                per_page: params.per_page || undefined,
            };

            // Mirror the params into the address bar so a reload lands on the
            // same view.
            const pageUrl = new URL(window.location.href);
            pageUrl.search = '';
            Object.entries(queryParams).forEach(([key, value]) => {
                if (value !== undefined) {
                    pageUrl.searchParams.set(key, String(value));
                }
            });
            window.history.replaceState(window.history.state, '', pageUrl);

            const requestId = ++requestIdRef.current;
            setLoading(true);
            try {
                const res = await axios.get<PaginatedData<ChecklistItem>>(
                    checklistApi.index.url({ workspace }),
                    { params: queryParams },
                );
                if (requestId === requestIdRef.current) {
                    setChecklists(res.data);
                }
            } catch {
                if (requestId === requestIdRef.current) {
                    toast.error('Failed to load checklist.');
                }
            } finally {
                if (requestId === requestIdRef.current) {
                    setLoading(false);
                }
            }
        },
        [workspace],
    );

    useEffect(() => {
        fetchChecklists();
    }, [fetchChecklists]);

    const isAddTaskValid =
        addTaskForm.title.trim().length > 0 && addTaskForm.target !== '';

    const resetAddTaskForm = () => setAddTaskForm(ADD_TASK_FORM_INITIAL);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

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
            router.put(
                `/workspaces/${workspace.slug}/checklist/${editingItemId}`,
                payload,
                {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        setAddTaskOpen(false);
                        setDialogMode('add');
                        setEditingItemId(null);
                        resetAddTaskForm();
                        fetchChecklists();
                    },
                    onError: () => {
                        toast.error('Failed to update checklist');
                    },
                },
            );
        } else {
            router.post(`/workspaces/${workspace.slug}/checklist`, payload, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setAddTaskOpen(false);
                    setDialogMode('add');
                    setEditingItemId(null);
                    resetAddTaskForm();
                    fetchChecklists();
                },
                onError: () => {
                    toast.error('Failed to create checklist');
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

        router.delete(
            `/workspaces/${workspace.slug}/checklist/${itemToDelete.id}`,
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setDeleteDialogOpen(false);
                    setItemToDelete(null);
                    // Step back a page when the last row on it went.
                    fetchChecklists(
                        checklists &&
                            checklists.data.length === 1 &&
                            checklists.current_page > 1
                            ? { page: checklists.current_page - 1 }
                            : {},
                    );
                },
                onError: () => {
                    toast.error('Failed to delete checklist');
                },
            },
        );
    }, [itemToDelete, workspace.slug, checklists, fetchChecklists]);

    const columns = useMemo(
        () =>
            getChecklistColumns({
                onEdit: openEdit,
                onDelete: openDelete,
            }),
        [openDelete, openEdit, workspace.slug],
    );

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Checklist`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Checklist"
                    description="Manage your tasks efficiently and never miss a requirement."
                >
                    <Can permission={PERMISSIONS.EditChecklist}>
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
                    </Can>
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
                        data={checklists?.data ?? []}
                        meta={
                            checklists ? omit(checklists, ['data']) : undefined
                        }
                        initialSorting={initialSorting}
                        loading={loading}
                        onFetch={(params) => {
                            fetchChecklists({
                                sort: params?.sort as string | null,
                                page: params?.page ?? 1,
                                per_page: params?.per_page ?? undefined,
                            });
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
