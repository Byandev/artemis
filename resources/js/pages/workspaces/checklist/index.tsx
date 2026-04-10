import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Eye, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

type ChecklistItem = {
    id: number;
    title: string;
    target: string;
    required: boolean;
};

interface Props {
    workspace: Workspace;
}

const initialItems: ChecklistItem[] = [
    { id: 1, title: 'Proof of Delivery Image', target: 'RTS Management', required: true },
    { id: 2, title: 'Receiver Full Name', target: 'PO Management', required: true },
    { id: 3, title: 'Customer Contact Number', target: 'PO Management', required: false },
    { id: 4, title: 'Delivery Attempt Reason', target: 'RTS Management', required: true },
    { id: 5, title: 'Parcel Condition Notes', target: 'Transaction Logs', required: false },
    { id: 6, title: 'Supplier Reference ID', target: 'Purchased Orders', required: true },
    { id: 7, title: 'Warehouse Received By', target: 'Purchased Orders', required: true },
    { id: 8, title: 'Incident Attachment', target: 'RTS Management', required: false },
    { id: 9, title: 'Cancellation Justification', target: 'PO Management', required: true },
    { id: 10, title: 'Delivery Confirmation Time', target: 'Transaction Logs', required: false },
    { id: 11, title: 'Verifier Signature', target: 'Purchased Orders', required: true },
    { id: 12, title: 'Alternate Contact', target: 'RTS Management', required: false },
];

export default function ChecklistPage({ workspace }: Props) {
    const [pageLoading, setPageLoading] = useState(true);
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

    const columns = useMemo<ColumnDef<ChecklistItem>[]>(() => [
        {
            accessorKey: 'title',
            header: ({ column }) => (
                <div className="w-[340px]">
                    <SortableHeader column={column} title="Title" />
                </div>
            ),
            cell: ({ row }) => (
                <span className="block w-[340px] truncate text-[12px] text-gray-800 dark:text-gray-100" title={row.original.title}>
                    {row.original.title}
                </span>
            ),
        },
        {
            accessorKey: 'target',
            header: ({ column }) => (
                <div className="w-[220px]">
                    <SortableHeader column={column} title="Target" />
                </div>
            ),
            cell: ({ row }) => (
                <span className="block w-[220px] truncate whitespace-nowrap text-[12px] text-gray-700 dark:text-gray-200" title={row.original.target}>
                    {row.original.target}
                </span>
            ),
        },
        {
            accessorKey: 'required',
            header: () => <div className="w-[140px] text-center">Required</div>,
            cell: ({ row }) => (
                <div className="flex w-[140px] justify-center">
                    <span
                        className={[
                            'inline-flex min-w-14 justify-center rounded-2xl px-2 py-0.5 text-[11px] font-medium',
                            row.original.required
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                : 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
                        ].join(' ')}
                    >
                        {row.original.required ? 'Yes' : 'No'}
                    </span>
                </div>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="w-[120px] text-center">Actions</div>,
            cell: ({ row }) => (
                <div className="flex w-[120px] justify-center">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-black/4 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/6 dark:hover:text-gray-300"
                                aria-label={`Open actions for ${row.original.title}`}
                            >
                                <MoreHorizontal className="h-4 w-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-[165px] p-1.5">
                            <DropdownMenuItem>
                                <Eye className="mr-1.5 h-3.5 w-3.5" />
                                View
                            </DropdownMenuItem>
                            <DropdownMenuItem>
                                <Pencil className="mr-1.5 h-3.5 w-3.5" />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                className="text-red-500 focus:text-red-500"
                                onClick={() => {
                                    setItems((prev) => prev.filter((item) => item.id !== row.original.id));
                                }}
                            >
                                <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ),
        },
    ], []);

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
                            const nextId = (items.at(-1)?.id ?? 0) + 1;
                            const nextTask: ChecklistItem = {
                                id: nextId,
                                title: `Checklist Item ${String(nextId).padStart(3, '0')}`,
                                target: nextId % 2 === 0 ? 'PO Management' : 'RTS Management',
                                required: false,
                            };

                            setItems((prev) => [...prev, nextTask]);
                            setPage(1);
                        }}
                        className="h-8 rounded-lg bg-emerald-600 px-3.5 text-[12px] font-medium text-white hover:bg-emerald-700"
                    >
                        <Plus className="mr-1.5 h-3.5 w-3.5" />
                        Add Task
                    </Button>
                </PageHeader>

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
