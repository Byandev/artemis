import PageHeader from '@/components/common/PageHeader';
import { DeleteItemDialog } from '@/components/inventory/delete-item-dialog';
import { ItemFormDialog } from '@/components/inventory/item-form-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Switch } from '@/components/ui/switch';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Product } from '@/types/models/Product';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef, RowSelectionState } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import { Download, MoreHorizontal, Pencil, Search, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

interface Item {
    id: number;
    sku: string;
    is_active: boolean;
    product_id: number;
    sales_keywords: string;
    transaction_keywords: string;
    lead_time: number;
    unfulfilled_count: number;
    product?: { id: number; name: string };
    remaining_qty: number | null;
    unfulfilled: number | null;
    waiting_for_delivery_stocks: number | null;
    three_days_average: number | null;
    remaining_after_fulfillment: number | null;
    days_it_can_last: number | null;
    po_needed: number | null;
    current_stocks: number | null;
}

interface Props {
    workspace: Workspace;
    items: PaginatedData<Item>;
    products: Product[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string; is_active?: string | number | boolean };
    };
}

const num = (v: number | null | undefined, decimals = 0) =>
    v == null
        ? '—'
        : Number(v).toLocaleString('en-PH', {
              minimumFractionDigits: decimals,
              maximumFractionDigits: decimals,
          });

const MetricCell = ({
    value,
    color,
    decimals = 0,
}: {
    value: number | null | undefined;
    color?: string;
    decimals?: number;
}) => (
    <span
        className={`font-mono text-[12px] font-medium ${color ?? 'text-gray-700 dark:text-gray-300'}`}
    >
        {num(value, decimals)}
    </span>
);

export default function ItemIndex({
    workspace,
    items,
    products,
    query,
}: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [syncingGencys, setSyncingGencys] = useState(false);
    const [editingItem, setEditingItem] = useState<Item | null>(null);
    const [itemToDelete, setItemToDelete] = useState<Item | null>(null);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    // Default to active-only; only an explicit `all` shows inactive items too.
    const [activeOnly, setActiveOnly] = useState(
        query?.filter?.is_active !== 'all',
    );
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [bulkProcessing, setBulkProcessing] = useState(false);

    const canCreateItems = usePermission(PERMISSIONS.CreateInventoryItems);
    const canEditItems = usePermission(PERMISSIONS.EditInventoryItems);
    const canDeleteItems = usePermission(PERMISSIONS.DeleteInventoryItems);
    const canUseItemActions = canEditItems || canDeleteItems;

    const baseUrl = `/workspaces/${workspace.slug}/inventory/items`;

    const selectedIds = useMemo(
        () => Object.keys(rowSelection).filter((id) => rowSelection[id]),
        [rowSelection],
    );

    // Logic for searching (Resets to page 1)
    const performQuery = useCallback(
        debounce((search: string) => {
            router.get(
                baseUrl,
                {
                    sort: query?.sort,
                    'filter[search]': search || undefined,
                    'filter[is_active]': activeOnly ? 1 : 'all',
                    page: 1,
                    per_page: query?.perPage ?? items.per_page,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['items'],
                },
            );
        }, 400),
        [baseUrl, query?.sort, query?.perPage, items.per_page, activeOnly],
    );

    const handleActiveOnlyChange = (checked: boolean) => {
        setActiveOnly(checked);
        router.get(
            baseUrl,
            {
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                'filter[is_active]': checked ? 1 : 'all',
                page: 1,
                per_page: query?.perPage ?? items.per_page,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['items'],
            },
        );
    };

    const handleBulkStatus = (isActive: boolean) => {
        router.post(
            `${baseUrl}/bulk-status`,
            { ids: selectedIds.map(Number), is_active: isActive },
            {
                preserveScroll: true,
                onStart: () => setBulkProcessing(true),
                onFinish: () => setBulkProcessing(false),
                onSuccess: () => setRowSelection({}),
                onError: () =>
                    toast.error('Failed to update inventory item status.'),
            },
        );
    };

    // Skip the query on the very first render (initial load/pagination), but
    // fire on every subsequent input change — including clearing the search
    // back to empty, which must reload the full list.
    const isFirstRender = useRef(true);
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        performQuery(searchValue);
        return () => performQuery.cancel();
    }, [searchValue]);

    const columns: ColumnDef<Item>[] = [
        ...(canEditItems
            ? [
                  {
                      id: 'select',
                      enableSorting: false,
                      header: ({ table }) => (
                          <Checkbox
                              checked={
                                  table.getIsAllPageRowsSelected() ||
                                  (table.getIsSomePageRowsSelected() &&
                                      'indeterminate')
                              }
                              onCheckedChange={(value) =>
                                  table.toggleAllPageRowsSelected(!!value)
                              }
                              aria-label="Select all"
                          />
                      ),
                      cell: ({ row }) => (
                          <Checkbox
                              checked={row.getIsSelected()}
                              onCheckedChange={(value) =>
                                  row.toggleSelected(!!value)
                              }
                              aria-label="Select row"
                              onClick={(e) => e.stopPropagation()}
                          />
                      ),
                  } as ColumnDef<Item>,
              ]
            : []),
        {
            accessorKey: 'sku',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="SKU / Product" />
            ),
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    <span className="font-mono text-[11px] font-medium text-gray-700 dark:text-gray-300">
                        {row.original.sku}
                    </span>
                    {row.original.product && (
                        <span className="text-[10px] text-gray-400 dark:text-gray-500">
                            {row.original.product.name}
                        </span>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'is_active',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Status"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    <span
                        className={`inline-flex items-center rounded-full px-2.5 py-1 font-mono text-[10px] font-medium tracking-wider uppercase ${
                            row.original.is_active
                                ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400'
                                : 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400'
                        }`}
                    >
                        {row.original.is_active ? 'Active' : 'Inactive'}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'lead_time',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Lead Time (days)"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    <span className="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                        {row.original.lead_time ?? 0}d
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'unfulfilled_count',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Unfulfilled"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    <MetricCell
                        value={row.original.unfulfilled_count}
                        color="text-red-500 dark:text-red-400"
                    />
                </div>
            ),
        },
        {
            accessorKey: 'current_stocks',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Remaining Qty"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    <MetricCell
                        value={row.original.current_stocks}
                        color="text-violet-600 dark:text-violet-400"
                    />
                </div>
            ),
        },
        {
            accessorKey: 'remaining_after_fulfillment',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Remaining After Fulfillment"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    <MetricCell
                        value={row.original.remaining_after_fulfillment}
                        color="text-amber-600 dark:text-amber-400"
                    />
                </div>
            ),
        },
        {
            accessorKey: 'waiting_for_delivery_stocks',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Waiting for Delivery"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    <MetricCell
                        value={row.original.waiting_for_delivery_stocks}
                        color="text-blue-500 dark:text-blue-400"
                    />
                </div>
            ),
        },
        {
            accessorKey: 'three_days_average',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="3-Day Avg"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    <MetricCell
                        value={row.original.three_days_average}
                        decimals={1}
                    />
                </div>
            ),
        },
        {
            accessorKey: 'days_it_can_last',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Days It Can Last"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    <MetricCell
                        value={row.original.days_it_can_last}
                        decimals={1}
                        color="text-emerald-600 dark:text-emerald-400"
                    />
                </div>
            ),
        },
        {
            accessorKey: 'po_needed',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="PO Needed"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => {
                const v = row.original.po_needed;
                const color =
                    v != null && v > 0
                        ? 'text-amber-500 dark:text-amber-400'
                        : 'text-gray-400 dark:text-gray-500';
                return (
                    <div className="text-center">
                        <MetricCell value={v} color={color} />
                    </div>
                );
            },
        },
        ...(canUseItemActions
            ? [
                  {
                      id: 'actions',
                      header: () => (
                          <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                              Actions
                          </div>
                      ),
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
                                      <DropdownMenuContent
                                          align="end"
                                          className="w-36"
                                      >
                                          {canEditItems && (
                                              <DropdownMenuItem
                                                  onClick={() =>
                                                      setEditingItem(item)
                                                  }
                                              >
                                                  <Pencil className="mr-2 h-3.5 w-3.5" />
                                                  Edit
                                              </DropdownMenuItem>
                                          )}
                                          {canEditItems && canDeleteItems && (
                                              <DropdownMenuSeparator />
                                          )}
                                          {canDeleteItems && (
                                              <DropdownMenuItem
                                                  className="text-red-600 focus:text-red-600 dark:text-red-400"
                                                  onClick={() =>
                                                      setItemToDelete(item)
                                                  }
                                              >
                                                  <Trash2 className="mr-2 h-3.5 w-3.5" />
                                                  Delete
                                              </DropdownMenuItem>
                                          )}
                                      </DropdownMenuContent>
                                  </DropdownMenu>
                              </div>
                          );
                      },
                  } as ColumnDef<Item>,
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Inventory Item`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Inventory Items"
                    description="Manage your inventory items and stock levels."
                >
                    <div className="flex items-center gap-2">
                        <a
                            href={`${baseUrl}/export?${new URLSearchParams(
                                Object.entries({
                                    'filter[search]': searchValue || '',
                                    'filter[is_active]': activeOnly
                                        ? '1'
                                        : 'all',
                                    sort: query?.sort ?? '',
                                }).filter(([, v]) => v !== ''),
                            ).toString()}`}
                            className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-zinc-800"
                        >
                            <Download className="h-3.5 w-3.5" />
                            Export
                        </a>
                        {canCreateItems && workspace.is_gencys_partner && (
                            <button
                                onClick={() =>
                                    router.post(
                                        `${baseUrl}/sync-gencys`,
                                        {},
                                        {
                                            preserveScroll: true,
                                            onStart: () =>
                                                setSyncingGencys(true),
                                            onFinish: () =>
                                                setSyncingGencys(false),
                                        },
                                    )
                                }
                                disabled={syncingGencys}
                                className="flex h-8 items-center rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 transition-all hover:bg-stone-50 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-700"
                            >
                                {syncingGencys
                                    ? 'Syncing…'
                                    : 'Sync from Gencys'}
                            </button>
                        )}
                        {canCreateItems && (
                            <button
                                onClick={() => setCreateDialogOpen(true)}
                                className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                            >
                                Add Item Record
                            </button>
                        )}
                    </div>
                </PageHeader>

                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search records…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>

                    <label className="flex h-9 cursor-pointer items-center gap-2 rounded-[10px] border border-black/6 bg-stone-100 px-3 dark:border-white/6 dark:bg-zinc-800">
                        <Switch
                            checked={activeOnly}
                            onCheckedChange={handleActiveOnlyChange}
                        />
                        <span className="font-mono text-[12px] font-medium text-gray-600 dark:text-gray-300">
                            Active only
                        </span>
                    </label>
                </div>

                {canEditItems && selectedIds.length > 0 && (
                    <div className="mb-3 flex flex-wrap items-center gap-3 rounded-[12px] border border-emerald-500/20 bg-emerald-50/60 px-4 py-2.5 dark:border-emerald-400/20 dark:bg-emerald-500/5">
                        <span className="font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">
                            {selectedIds.length} selected
                        </span>
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => handleBulkStatus(true)}
                                disabled={bulkProcessing}
                                className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                            >
                                Set Active
                            </button>
                            <button
                                onClick={() => handleBulkStatus(false)}
                                disabled={bulkProcessing}
                                className="flex h-8 items-center rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 transition-all hover:bg-stone-50 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-700"
                            >
                                Set Inactive
                            </button>
                            <button
                                onClick={() => setRowSelection({})}
                                disabled={bulkProcessing}
                                className="flex h-8 items-center rounded-lg px-2 font-mono! text-[12px]! font-medium text-gray-500 transition-all hover:text-gray-700 disabled:opacity-50 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                )}

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={items.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(items, ['data']) }}
                        getRowId={(row) => String(row.id)}
                        {...(canEditItems
                            ? {
                                  rowSelection,
                                  onRowSelectionChange: setRowSelection,
                              }
                            : {})}
                        // FIXED: This now properly handles page changes without getting reset
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    'filter[is_active]': activeOnly ? 1 : 'all',
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query?.perPage ??
                                        items.per_page,
                                },
                                {
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                },
                            );
                        }}
                    />
                </div>

                {(canCreateItems || canEditItems) && (
                    <ItemFormDialog
                        open={createDialogOpen || editingItem !== null}
                        onOpenChange={(open: boolean) => {
                            if (!open) {
                                setCreateDialogOpen(false);
                                setEditingItem(null);
                            }
                        }}
                        // FIXED: Using "as any" to bypass strict number type mismatch
                        item={editingItem as any}
                        workspace={workspace}
                        products={products}
                    />
                )}

                {canDeleteItems && (
                    <DeleteItemDialog
                        item={itemToDelete}
                        workspace={workspace}
                        onClose={() => setItemToDelete(null)}
                    />
                )}
            </div>
        </AppLayout>
    );
}
