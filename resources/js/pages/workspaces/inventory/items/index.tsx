import { DeleteItemDialog } from '@/components/inventory/delete-item-dialog';
import { ItemFormDialog } from '@/components/inventory/item-form-dialog';
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

interface Item {
    id: number;
    sku: string;
    product_id: number;
    sales_keywords: string;
    transaction_keywords: string;
    product?: {
        id: number;
        name: string;
    };
}

interface Props {
    workspace: Workspace;
    items: PaginatedData<Item>;
    products: Product[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
}

export default function ItemIndex({ workspace, items, products, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);

    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<Item | null>(null);
    const [itemToDelete, setItemToDelete] = useState<Item | null>(null);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    const baseUrl = `/workspaces/${workspace.slug}/inventory/items`;

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
                    only: ['items'],
                }
            );
        }, 400),
        [baseUrl, query?.sort]
    );

    useEffect(() => {
        performQuery(searchValue);
        return () => performQuery.cancel();
    }, [searchValue, performQuery]);

    const columns: ColumnDef<Item>[] = [
        {
            accessorKey: 'id',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Id" />,
            cell: ({ row }) => (
                <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                    {row.original.id} { }
                </span>
            ),
        },
        {
            accessorKey: 'sku',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="sku" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.sku}
                </span>
            ),
        },
        {
            accessorKey: 'product_id',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Product" />,
            cell: ({ row }) => (
                <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                    {row.original.product?.name ?? row.original.product_id}
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Actions</div>,
            cell: ({ row }) => {
                const item = row.original;
                return (
                    <div className="flex justify-center">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                    <MoreHorizontal className="h-3.5 w-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-36">
                                <DropdownMenuItem onClick={() => setEditingItem(item)}>
                                    <Pencil className="mr-2 h-3.5 w-3.5" />
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    className="text-red-600 focus:text-red-600 dark:text-red-400"
                                    onClick={() => setItemToDelete(item)}
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
            <Head title={`${workspace.name} - Inventory Item`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Inventory Items"
                    description="Manage you Inventory Items"
                >
                    <button
                        onClick={() => setCreateDialogOpen(true)}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        Add Item Record
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
                        data={items.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(items, ['data']) }}
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

                <ItemFormDialog
                    open={createDialogOpen || editingItem !== null}
                    onOpenChange={(open: boolean) => {
                        if (!open) {
                            setCreateDialogOpen(false);
                            setEditingItem(null);
                        }
                    }}
                    item={editingItem}
                    workspace={workspace}
                    products={products}
                />

                <DeleteItemDialog
                    item={itemToDelete}
                    workspace={workspace}
                    onClose={() => setItemToDelete(null)}
                />
            </div>
        </AppLayout>
    );
}
