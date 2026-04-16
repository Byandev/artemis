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
    lead_time: number;
    unfulfilled_count: number;
    three_days_average: number;
    product?: { id: number; name: string };
    // aggregated (unfulfilled is mapped from unfulfilled_count by the server)
    unfulfilled: number | null;
    current_stocks: number | null;
    waiting_for_delivery_stocks: number | null;
    three_days_average: number | null;
    // computed
    remaining_after_fulfillment: number | null;
    days_it_can_last: number | null;
    po_needed: number | null;
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

const num = (v: number | null | undefined, decimals = 0) =>
    v == null ? '—' : Number(v).toLocaleString('en-PH', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

const MetricCell = ({ value, color }: { value: number | null | undefined; color?: string }) => (
    <span className={`font-mono text-[12px] font-medium ${color ?? 'text-gray-700 dark:text-gray-300'}`}>
        {num(value)}
    </span>
);

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
                { sort: query?.sort, 'filter[search]': search || undefined, page: 1 },
                { preserveState: true, replace: true, preserveScroll: true, only: ['items'] }
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
            accessorKey: 'sku',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="SKU / Product" />,
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    <span className="font-mono text-[11px] font-medium text-gray-700 dark:text-gray-300">{row.original.sku}</span>
                    {row.original.product && (
                        <span className="text-[10px] text-gray-400 dark:text-gray-500">{row.original.product.name}</span>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'lead_time',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Lead Time (days)" className="justify-center" />,
            cell: ({ row }) => (
                <div className="text-center">
                    <span className="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                        {row.original.lead_time ?? 0}d
                    </span>
                </div>
            ),
        },
        {
            id: 'unfulfilled',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Unfulfilled" className="justify-center" />,
            cell: ({ row }) => (
                <div className="text-center"><MetricCell value={row.original.unfulfilled} color="text-red-500 dark:text-red-400" /></div>
            ),
        },
        {
            id: 'current_stocks',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Current Stocks" className="justify-center" />,
            cell: ({ row }) => (
                <div className="text-center"><MetricCell value={row.original.current_stocks} color="text-emerald-600 dark:text-emerald-400" /></div>
            ),
        },
        {
            id: 'waiting_for_delivery_stocks',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Waiting for Delivery" className="justify-center" />,
            cell: ({ row }) => (
                <div className="text-center"><MetricCell value={row.original.waiting_for_delivery_stocks} color="text-blue-500 dark:text-blue-400" /></div>
            ),
        },
        {
            id: 'three_days_average',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="3-Day Avg" className="justify-center" />,
            cell: ({ row }) => (
                <div className="text-center"><MetricCell value={row.original.three_days_average} decimals={1} /></div>
            ),
        },
        {
            id: 'remaining_after_fulfillment',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Remaining After Fulfillment" className="justify-center" />,
            cell: ({ row }) => {
                const v = row.original.remaining_after_fulfillment;
                const color = v == null ? '' : v < 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-700 dark:text-gray-300';
                return <div className="text-center"><MetricCell value={v} color={color} /></div>;
            },
        },
        {
            id: 'days_it_can_last',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Days It Can Last" className="justify-center" />,
            cell: ({ row }) => {
                const v = row.original.days_it_can_last;
                const color = v == null ? '' : v < 3 ? 'text-red-500 dark:text-red-400' : v < 7 ? 'text-amber-500 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400';
                return (
                    <div className="text-center">
                        <span className={`font-mono text-[12px] font-medium ${color}`}>
                            {v == null ? '—' : `${num(v, 1)} days`}
                        </span>
                    </div>
                );
            },
        },
        {
            id: 'po_needed',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="PO Needed" className="justify-center" />,
            cell: ({ row }) => {
                const v = row.original.po_needed;
                const color = v != null && v > 0 ? 'text-amber-500 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500';
                return <div className="text-center"><MetricCell value={v} color={color} /></div>;
            },
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
                <PageHeader title="Inventory Items" description="Manage your inventory items and stock levels.">
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
                                { sort: params?.sort, 'filter[search]': searchValue || undefined, page: params?.page ?? 1 },
                                { preserveState: true, replace: true, preserveScroll: true }
                            );
                        }}
                    />
                </div>

                <ItemFormDialog
                    open={createDialogOpen || editingItem !== null}
                    onOpenChange={(open: boolean) => {
                        if (!open) { setCreateDialogOpen(false); setEditingItem(null); }
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
