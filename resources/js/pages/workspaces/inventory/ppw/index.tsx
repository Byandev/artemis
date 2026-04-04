import { DeletePpwDialog } from '@/components/inventory/delete-ppw-dialog';
import { PpwFormDialog } from '@/components/inventory/ppw-form-dialog';
import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Product } from '@/types/models/Product';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit, debounce } from 'lodash';
import { MoreHorizontal, Pencil, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState, useCallback } from 'react';

interface Ppw {
    id: number;
    transaction_date: string;
    count: number;
    product_id: number;
    product?: {
        id: number;
        name: string;
    };
}

interface Props {
    workspace: Workspace;
    ppws: PaginatedData<Ppw>;
    products: Product[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
}

export default function PpwIndex({ workspace, ppws, products, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);

    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [editingPpw, setEditingPpw] = useState<Ppw | null>(null);
    const [ppwToDelete, setPpwToDelete] = useState<Ppw | null>(null);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    const baseUrl = `/workspaces/${workspace.slug}/inventory/ppws`;

    const performQuery = useCallback(
        debounce((search: string) => {
            router.get(
                baseUrl,
                {
                    sort: query?.sort,
                    'filter[search]': search || undefined,
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['ppws'],
                }
            );
        }, 400),
        [baseUrl, query?.sort]
    );

    useEffect(() => {
        performQuery(searchValue);
        return () => performQuery.cancel();
    }, [searchValue, performQuery]);

    const columns: ColumnDef<Ppw>[] = [
        {
            id: 'product_name',
            accessorFn: (row) => row.product?.name,
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Product Name" />,
            cell: ({ row }) => (
                <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                    {row.original.product?.name || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'transaction_date',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Transaction Date" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.transaction_date}
                </span>
            ),
        },
        {
            accessorKey: 'count',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Count" />,
            cell: ({ row }) => (
                <div className="space-y-0.5">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                        {row.original.count.toLocaleString()} units
                    </span>
                </div>
            ),
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const ppw = row.original;
                return (
                    <div className="flex justify-center">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                    <MoreHorizontal className="h-3.5 w-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-36">
                                <DropdownMenuItem onClick={() => setEditingPpw(ppw)}>
                                    <Pencil className="mr-2 h-3.5 w-3.5" />
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    className="text-red-600 focus:text-red-600 dark:text-red-400"
                                    onClick={() => setPpwToDelete(ppw)}
                                >
                                    <Trash2 className="mr-2 h-3.5 w-3.5" />
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Inventory PPW`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Pending Printed Waybill (PPW)"
                    description="Monitor weekly inventory counts for your products."
                >
                    <button
                        onClick={() => setCreateDialogOpen(true)}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        Add PPW Record
                    </button>
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 outline-none transition-all placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search records…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={ppws.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(ppws, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    page: params?.page ?? 1,
                                },
                                { preserveState: true, replace: true, preserveScroll: true }
                            );
                        }}
                    />
                </div>

                <PpwFormDialog
                    open={createDialogOpen || editingPpw !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setCreateDialogOpen(false);
                            setEditingPpw(null);
                        }
                    }}
                    ppw={editingPpw}
                    workspace={workspace}
                    products={products}
                />

                <DeletePpwDialog
                    ppw={ppwToDelete}
                    workspace={workspace}
                    onClose={() => setPpwToDelete(null)}
                />
            </div>
        </AppLayout>
    );
}
