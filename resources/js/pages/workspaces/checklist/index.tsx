import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { AddTaskDialog } from '@/components/checklist/add-task-dialog';
import { DeleteChecklistDialog } from '@/components/checklist/delete-checklist-dialog';
import { getChecklistColumns } from '@/components/checklist/checklist-columns';
import { ADD_TASK_FORM_INITIAL, AddTaskForm, ChecklistItem } from '@/components/checklist/types';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface Props {
    workspace: Workspace;
}

const initialItems: ChecklistItem[] = [
    { id: 1, title: 'Proof of Delivery Image', target: 'Page', required: true },
    { id: 2, title: 'Receiver Full Name', target: 'Shop', required: true },
    { id: 3, title: 'Customer Contact Number', target: 'Shop', required: false },
    { id: 4, title: 'Delivery Attempt Reason', target: 'Page', required: true },
    { id: 5, title: 'Parcel Condition Notes', target: 'Shop', required: false },
    { id: 6, title: 'Supplier Reference ID', target: 'Page', required: true },
    { id: 7, title: 'Warehouse Received By', target: 'Shop', required: true },
    { id: 8, title: 'Incident Attachment', target: 'Page', required: false },
    { id: 9, title: 'Cancellation Justification', target: 'Shop', required: true },
    { id: 10, title: 'Delivery Confirmation Time', target: 'Page', required: false },
    { id: 11, title: 'Verifier Signature', target: 'Shop', required: true },
    { id: 12, title: 'Alternate Contact', target: 'Page', required: false },
];

export default function ChecklistPage({ workspace }: Props) {
    const [pageLoading, setPageLoading] = useState(true);
    const [addTaskOpen, setAddTaskOpen] = useState(false);
    const [dialogMode, setDialogMode] = useState<'add' | 'edit'>('add');
    const [editingItemId, setEditingItemId] = useState<number | null>(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [itemToDelete, setItemToDelete] = useState<ChecklistItem | null>(null);
    const [addTaskForm, setAddTaskForm] = useState<AddTaskForm>(ADD_TASK_FORM_INITIAL);
    const [items, setItems] = useState<ChecklistItem[]>(initialItems);
    const [page, setPage] = useState(1);
    const [sort, setSort] = useState('title');
    const perPage = 10;

    useEffect(() => {
        const timer = window.setTimeout(() => setPageLoading(false), 550);

        return () => window.clearTimeout(timer);
    }, []);

    const sortedItems = useMemo(() => {
        const [firstSort = 'title'] = sort.split(',').map((part) => part.trim()).filter(Boolean);
        const desc = firstSort.startsWith('-');
        const key = (desc ? firstSort.slice(1) : firstSort) as keyof ChecklistItem;

        return [...items].sort((a, b) => {
            const aVal = a[key];
            const bVal = b[key];

            if (typeof aVal === 'boolean' && typeof bVal === 'boolean') {
                if (aVal === bVal) return 0;
                return desc ? (aVal ? -1 : 1) : (aVal ? 1 : -1);
            }

            const left = String(aVal ?? '').toLowerCase();
            const right = String(bVal ?? '').toLowerCase();
            if (left === right) return 0;
            return desc ? (left < right ? 1 : -1) : (left > right ? 1 : -1);
        });
    }, [items, sort]);

    const lastPage = Math.max(1, Math.ceil(sortedItems.length / perPage));
    const currentPage = Math.min(page, lastPage);

    const pagedRows = useMemo(() => {
        const start = (currentPage - 1) * perPage;
        return sortedItems.slice(start, start + perPage);
    }, [sortedItems, currentPage]);

    const from = sortedItems.length === 0 ? 0 : (currentPage - 1) * perPage + 1;
    const to = sortedItems.length === 0 ? 0 : Math.min(currentPage * perPage, sortedItems.length);

    const isAddTaskValid = addTaskForm.title.trim().length > 0 && addTaskForm.target !== '';

    const resetAddTaskForm = () => setAddTaskForm(ADD_TASK_FORM_INITIAL);

    const submitAddTask = () => {
        if (!isAddTaskValid) {
            return;
        }

        if (dialogMode === 'edit' && editingItemId !== null) {
            setItems((prev) => prev.map((item) => (
                item.id === editingItemId
                    ? {
                        ...item,
                        title: addTaskForm.title.trim(),
                        target: addTaskForm.target as 'Shop' | 'Page',
                        required: addTaskForm.required,
                    }
                    : item
            )));
        } else {
            const nextId = (items.at(-1)?.id ?? 0) + 1;
            const nextTask: ChecklistItem = {
                id: nextId,
                title: addTaskForm.title.trim(),
                target: addTaskForm.target as 'Shop' | 'Page',
                required: addTaskForm.required,
            };

            setItems((prev) => [...prev, nextTask]);
        }

        setPage(1);
        setAddTaskOpen(false);
        setDialogMode('add');
        setEditingItemId(null);
        resetAddTaskForm();
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

        setItems((prev) => {
            const exists = prev.some((item) => item.id === itemToDelete.id);
            if (!exists) {
                return prev;
            }

            return prev.filter((item) => item.id !== itemToDelete.id);
        });

        setDeleteDialogOpen(false);
        setItemToDelete(null);
    }, [itemToDelete]);

    const columns = useMemo(() => getChecklistColumns({ onEdit: openEdit, onDelete: openDelete }), [openDelete, openEdit]);

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Checklist`} />

            {pageLoading && (
                <div className="pointer-events-none fixed inset-x-0 top-0 z-70 h-0.5 overflow-hidden">
                    <div className="h-full w-full animate-pulse bg-linear-to-r from-transparent via-gray-600 to-transparent" />
                </div>
            )}

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Checklist"
                    description="Manage your tasks efficiently and never miss a requirement."
                >
                    <Button
                        type="button"
                        onClick={() => {
                            setDialogMode('add');
                            setEditingItemId(null);
                            resetAddTaskForm();
                            setAddTaskOpen(true);
                        }}
                        className="h-8 rounded-lg bg-emerald-600 px-3.5 text-[12px] font-medium text-white hover:bg-emerald-700"
                    >
                        <Plus className="mr-1.5 h-3.5 w-3.5" />
                        Add Task
                    </Button>
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
                        data={pagedRows}
                        meta={{
                            current_page: currentPage,
                            last_page: lastPage,
                            per_page: perPage,
                            total: sortedItems.length,
                            from,
                            to,
                            links: lastPage > 1
                                ? [{ url: '#', label: String(currentPage), active: true }]
                                : [],
                        }}
                        initialSorting={[{ id: sort.replace('-', ''), desc: sort.startsWith('-') }]}
                        onFetch={(params) => {
                            const nextPage = Number(params?.page ?? currentPage);
                            const nextSort = String(params?.sort ?? sort);
                            setSort(nextSort);
                            setPage(Number.isFinite(nextPage) ? Math.max(1, Math.min(nextPage, lastPage)) : currentPage);
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
