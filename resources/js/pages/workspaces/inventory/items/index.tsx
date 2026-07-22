import PageHeader from '@/components/common/PageHeader';
import { AdjustCountDialog } from '@/components/inventory/adjust-count-dialog';
import { DeleteItemDialog } from '@/components/inventory/delete-item-dialog';
import { ItemFormDialog } from '@/components/inventory/item-form-dialog';
import { WaitingForDeliveryDialog } from '@/components/inventory/waiting-for-delivery-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
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
import { format, parseISO } from 'date-fns';
import { debounce, omit } from 'lodash';
import {
    ChevronsUpDown,
    ClipboardCheck,
    Download,
    Layers,
    MoreHorizontal,
    Package,
    Pencil,
    Search,
    Trash2,
    Ungroup,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

interface Item {
    id: number;
    sku: string;
    is_active: boolean;
    product_id: number;
    parent_id?: number | null;
    is_parent?: boolean;
    // Flat view: the parent's SKU when this item is a child. Summary view:
    // is_group flags a rolled-up parent row and child_count is how many SKUs it sums.
    parent_sku?: string | null;
    is_group?: boolean | number;
    child_count?: number;
    sales_keywords: string;
    transaction_keywords: string;
    lead_time: number;
    unfulfilled_count: number;
    product?: { id: number; name: string; winning_date?: string | null };
    product_name?: string | null;
    // Summary view: the group's product winning_date (flat view reads product.winning_date).
    product_winning_date?: string | null;
    remaining_qty: number | null;
    unfulfilled: number | null;
    waiting_for_delivery_stocks: number | null;
    three_days_average: number | null;
    remaining_after_fulfillment: number | null;
    days_it_can_last: number | null;
    po_needed: number | null;
    current_stocks: number | null;
    discrepancy: number | null;
    discrepancy_counted_qty: number | null;
    discrepancy_date: string | null;
}

interface ParentOption {
    id: number;
    sku: string;
}

interface Props {
    workspace: Workspace;
    items: PaginatedData<Item>;
    products: Product[];
    parents: ParentOption[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        summarize?: boolean;
        filter?: {
            search?: string;
            is_active?: string | number | boolean;
            unassigned?: string | number | boolean;
        };
    };
}

const num = (v: number | null | undefined, decimals = 0) =>
    v == null
        ? '—'
        : Number(v).toLocaleString('en-PH', {
              minimumFractionDigits: decimals,
              maximumFractionDigits: decimals,
          });

// Short "30 Jun" label for the last-counted date; tolerant of a plain date string.
const shortDate = (d: string | null | undefined) => {
    if (!d) return '';
    try {
        return format(parseISO(d), 'd MMM');
    } catch {
        return d;
    }
};

// "JUNE 25, 2026" label for the date a product was marked a winning item.
const winningDate = (d: string | null | undefined) => {
    if (!d) return '';
    try {
        return format(parseISO(d), 'MMMM d, yyyy').toUpperCase();
    } catch {
        return d;
    }
};

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
    parents,
    query,
}: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [syncingGencys, setSyncingGencys] = useState(false);
    const [editingItem, setEditingItem] = useState<Item | null>(null);
    const [adjustingItem, setAdjustingItem] = useState<Item | null>(null);
    const [itemToDelete, setItemToDelete] = useState<Item | null>(null);
    const [waitingItem, setWaitingItem] = useState<Item | null>(null);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    // Default to active-only; only an explicit `all` shows inactive items too.
    const [activeOnly, setActiveOnly] = useState(
        query?.filter?.is_active !== 'all',
    );
    // "Unassigned only": show just the items that have no linked product yet.
    const [unassignedOnly, setUnassignedOnly] = useState(
        !!query?.filter?.unassigned && query?.filter?.unassigned !== '0',
    );
    // Summarize rolls SKU variants up under their parent item and sums the values.
    const [summarize, setSummarize] = useState(!!query?.summarize);
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [bulkProcessing, setBulkProcessing] = useState(false);
    const [productPickerOpen, setProductPickerOpen] = useState(false);
    const [productSearch, setProductSearch] = useState('');
    const [groupPickerOpen, setGroupPickerOpen] = useState(false);
    const [parentSearch, setParentSearch] = useState('');
    const [newParentSku, setNewParentSku] = useState('');

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
                    'filter[unassigned]': unassignedOnly ? 1 : undefined,
                    summarize: summarize ? 1 : undefined,
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
        [
            baseUrl,
            query?.sort,
            query?.perPage,
            items.per_page,
            activeOnly,
            unassignedOnly,
            summarize,
        ],
    );

    const handleActiveOnlyChange = (checked: boolean) => {
        setActiveOnly(checked);
        router.get(
            baseUrl,
            {
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                'filter[is_active]': checked ? 1 : 'all',
                'filter[unassigned]': unassignedOnly ? 1 : undefined,
                summarize: summarize ? 1 : undefined,
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

    const handleUnassignedChange = (checked: boolean) => {
        setUnassignedOnly(checked);
        router.get(
            baseUrl,
            {
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                'filter[is_active]': activeOnly ? 1 : 'all',
                'filter[unassigned]': checked ? 1 : undefined,
                summarize: summarize ? 1 : undefined,
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

    const handleSummarizeChange = (checked: boolean) => {
        setSummarize(checked);
        // Selection/bulk actions only make sense on the flat list.
        setRowSelection({});
        router.get(
            baseUrl,
            {
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                'filter[is_active]': activeOnly ? 1 : 'all',
                'filter[unassigned]': unassignedOnly ? 1 : undefined,
                summarize: checked ? 1 : undefined,
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

    const filteredProducts = useMemo(() => {
        const q = productSearch.trim().toLowerCase();
        if (!q) return products;
        return products.filter((p) => p.name?.toLowerCase().includes(q));
    }, [products, productSearch]);

    const handleBulkProduct = (productId: number | null) => {
        setProductPickerOpen(false);
        setProductSearch('');
        router.post(
            `${baseUrl}/bulk-product`,
            { ids: selectedIds.map(Number), product_id: productId },
            {
                preserveScroll: true,
                onStart: () => setBulkProcessing(true),
                onFinish: () => setBulkProcessing(false),
                onSuccess: () => setRowSelection({}),
                onError: () =>
                    toast.error('Failed to update inventory item product.'),
            },
        );
    };

    const filteredParents = useMemo(() => {
        const q = parentSearch.trim().toLowerCase();
        if (!q) return parents;
        return parents.filter((p) => p.sku?.toLowerCase().includes(q));
    }, [parents, parentSearch]);

    // Group the selected items under a parent: pass an existing parentId, a
    // newParentSku to create one, or neither (null) to ungroup.
    const handleBulkGroup = (
        parentId: number | null,
        newSku?: string | null,
    ) => {
        setGroupPickerOpen(false);
        setParentSearch('');
        setNewParentSku('');
        router.post(
            `${baseUrl}/bulk-group`,
            {
                ids: selectedIds.map(Number),
                parent_id: parentId,
                new_parent_sku: newSku || null,
            },
            {
                preserveScroll: true,
                onStart: () => setBulkProcessing(true),
                onFinish: () => setBulkProcessing(false),
                onSuccess: () => setRowSelection({}),
                onError: () => toast.error('Failed to group inventory items.'),
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
        ...(canEditItems && !summarize
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
            cell: ({ row }) => {
                const item = row.original;
                const productName = item.product?.name ?? item.product_name;
                const won = winningDate(
                    item.product?.winning_date ?? item.product_winning_date,
                );
                const isGroup = !!item.is_group || !!item.is_parent;
                return (
                    <div className="flex flex-col gap-0.5">
                        <span className="flex items-center gap-1.5 font-mono text-[11px] font-medium text-gray-700 dark:text-gray-300">
                            {item.sku}
                            {isGroup && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-1.5 py-0.5 font-mono text-[9px] font-medium tracking-wider text-indigo-600 uppercase dark:bg-indigo-500/10 dark:text-indigo-400">
                                    <Layers className="h-2.5 w-2.5" />
                                    {summarize && item.child_count
                                        ? `${item.child_count} SKUs`
                                        : 'Parent'}
                                </span>
                            )}
                        </span>
                        {productName && (
                            <span className="text-[10px] text-gray-400 dark:text-gray-500">
                                {productName}
                                {won && ` (${won})`}
                            </span>
                        )}
                        {!summarize && item.parent_sku && (
                            <span className="font-mono text-[9px] text-indigo-500 dark:text-indigo-400">
                                ↳ under {item.parent_sku}
                            </span>
                        )}
                    </div>
                );
            },
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
            accessorKey: 'discrepancy',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Discrepancy"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => {
                const d = row.original.discrepancy;
                if (d == null) {
                    return (
                        <div className="text-center">
                            <MetricCell value={null} />
                        </div>
                    );
                }
                const color =
                    d > 0
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : d < 0
                          ? 'text-red-500 dark:text-red-400'
                          : 'text-gray-500 dark:text-gray-400';
                const counted = row.original.discrepancy_counted_qty;
                const date = shortDate(row.original.discrepancy_date);
                const sub = [counted != null ? `cnt ${num(counted)}` : '', date]
                    .filter(Boolean)
                    .join(' · ');
                return (
                    <div className="flex flex-col items-center gap-0.5">
                        <span
                            className={`font-mono text-[12px] font-medium ${color}`}
                        >
                            {d > 0 ? '+' : ''}
                            {num(d)}
                        </span>
                        {sub && (
                            <span className="font-mono text-[9px] text-gray-400 dark:text-gray-500">
                                {sub}
                            </span>
                        )}
                    </div>
                );
            },
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
            // Clickable when there's anything outstanding — opens the PO breakdown.
            cell: ({ row }) => (
                <div className="text-center">
                    {row.original.waiting_for_delivery_stocks == null ? (
                        <MetricCell
                            value={null}
                            color="text-blue-500 dark:text-blue-400"
                        />
                    ) : (
                        <button
                            type="button"
                            onClick={() => setWaitingItem(row.original)}
                            title="View pending purchase orders"
                            className="cursor-pointer rounded px-1 underline decoration-dotted underline-offset-4 transition-colors hover:bg-blue-500/10"
                        >
                            <MetricCell
                                value={row.original.waiting_for_delivery_stocks}
                                color="text-blue-500 dark:text-blue-400"
                            />
                        </button>
                    )}
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
        ...(canUseItemActions && !summarize
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
                                                      setAdjustingItem(item)
                                                  }
                                              >
                                                  <ClipboardCheck className="mr-2 h-3.5 w-3.5" />
                                                  Adjust count
                                              </DropdownMenuItem>
                                          )}
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
                                    'filter[unassigned]': unassignedOnly
                                        ? '1'
                                        : '',
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
                                    : 'Sync from Unit Codes'}
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

                    <label className="flex h-9 cursor-pointer items-center gap-2 rounded-[10px] border border-black/6 bg-stone-100 px-3 dark:border-white/6 dark:bg-zinc-800">
                        <Switch
                            checked={unassignedOnly}
                            onCheckedChange={handleUnassignedChange}
                        />
                        <span className="font-mono text-[12px] font-medium text-gray-600 dark:text-gray-300">
                            No product assigned
                        </span>
                    </label>

                    <label className="flex h-9 cursor-pointer items-center gap-2 rounded-[10px] border border-black/6 bg-stone-100 px-3 dark:border-white/6 dark:bg-zinc-800">
                        <Switch
                            checked={summarize}
                            onCheckedChange={handleSummarizeChange}
                        />
                        <span className="font-mono text-[12px] font-medium text-gray-600 dark:text-gray-300">
                            Summarize by parent
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
                            <Popover
                                open={productPickerOpen}
                                onOpenChange={setProductPickerOpen}
                            >
                                <PopoverTrigger asChild>
                                    <button
                                        disabled={bulkProcessing}
                                        className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 transition-all hover:bg-stone-50 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-700"
                                    >
                                        <Package className="h-3.5 w-3.5" />
                                        Set Product
                                        <ChevronsUpDown className="h-3.5 w-3.5 opacity-50" />
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent
                                    align="start"
                                    className="w-64 p-0"
                                >
                                    <div className="flex items-center gap-2 border-b border-black/6 px-3 dark:border-white/6">
                                        <Search className="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-gray-500" />
                                        <input
                                            autoFocus
                                            value={productSearch}
                                            onChange={(e) =>
                                                setProductSearch(e.target.value)
                                            }
                                            placeholder="Search products…"
                                            className="h-9 w-full bg-transparent font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 dark:text-gray-100 dark:placeholder:text-gray-600"
                                        />
                                    </div>
                                    <div className="max-h-64 overflow-y-auto p-1">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleBulkProduct(null)
                                            }
                                            className="flex w-full items-center rounded-md px-2 py-1.5 text-left font-mono! text-[12px]! text-gray-500 transition-colors hover:bg-stone-100 dark:text-gray-400 dark:hover:bg-zinc-800"
                                        >
                                            No product (clear)
                                        </button>
                                        {filteredProducts.length === 0 ? (
                                            <p className="px-2 py-3 text-center font-mono text-[11px] text-gray-400 dark:text-gray-600">
                                                No products found.
                                            </p>
                                        ) : (
                                            filteredProducts.map((product) => (
                                                <button
                                                    type="button"
                                                    key={product.id}
                                                    onClick={() =>
                                                        handleBulkProduct(
                                                            product.id,
                                                        )
                                                    }
                                                    className="flex w-full items-center rounded-md px-2 py-1.5 text-left font-mono! text-[12px]! text-gray-700 transition-colors hover:bg-stone-100 dark:text-gray-200 dark:hover:bg-zinc-800"
                                                >
                                                    {product.name}
                                                </button>
                                            ))
                                        )}
                                    </div>
                                </PopoverContent>
                            </Popover>
                            <Popover
                                open={groupPickerOpen}
                                onOpenChange={setGroupPickerOpen}
                            >
                                <PopoverTrigger asChild>
                                    <button
                                        disabled={bulkProcessing}
                                        className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 transition-all hover:bg-stone-50 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-700"
                                    >
                                        <Layers className="h-3.5 w-3.5" />
                                        Group under parent
                                        <ChevronsUpDown className="h-3.5 w-3.5 opacity-50" />
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent
                                    align="start"
                                    className="w-72 p-0"
                                >
                                    <div className="border-b border-black/6 p-2 dark:border-white/6">
                                        <p className="mb-1 px-1 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                            Create new parent
                                        </p>
                                        <div className="flex items-center gap-1.5">
                                            <input
                                                value={newParentSku}
                                                onChange={(e) =>
                                                    setNewParentSku(
                                                        e.target.value,
                                                    )
                                                }
                                                onKeyDown={(e) => {
                                                    if (
                                                        e.key === 'Enter' &&
                                                        newParentSku.trim()
                                                    ) {
                                                        handleBulkGroup(
                                                            null,
                                                            newParentSku.trim(),
                                                        );
                                                    }
                                                }}
                                                placeholder="New parent SKU / name"
                                                className="h-8 w-full rounded-md border border-black/8 bg-stone-50 px-2 font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 focus:border-emerald-500 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600"
                                            />
                                            <button
                                                type="button"
                                                disabled={
                                                    !newParentSku.trim() ||
                                                    bulkProcessing
                                                }
                                                onClick={() =>
                                                    handleBulkGroup(
                                                        null,
                                                        newParentSku.trim(),
                                                    )
                                                }
                                                className="flex h-8 shrink-0 items-center rounded-md bg-emerald-600 px-2.5 font-mono! text-[11px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                                            >
                                                Create
                                            </button>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 border-b border-black/6 px-3 dark:border-white/6">
                                        <Search className="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-gray-500" />
                                        <input
                                            value={parentSearch}
                                            onChange={(e) =>
                                                setParentSearch(e.target.value)
                                            }
                                            placeholder="Search existing parents…"
                                            className="h-9 w-full bg-transparent font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 dark:text-gray-100 dark:placeholder:text-gray-600"
                                        />
                                    </div>
                                    <div className="max-h-56 overflow-y-auto p-1">
                                        {filteredParents.length === 0 ? (
                                            <p className="px-2 py-3 text-center font-mono text-[11px] text-gray-400 dark:text-gray-600">
                                                No parent items yet.
                                            </p>
                                        ) : (
                                            filteredParents.map((parent) => (
                                                <button
                                                    type="button"
                                                    key={parent.id}
                                                    onClick={() =>
                                                        handleBulkGroup(
                                                            parent.id,
                                                        )
                                                    }
                                                    className="flex w-full items-center rounded-md px-2 py-1.5 text-left font-mono! text-[12px]! text-gray-700 transition-colors hover:bg-stone-100 dark:text-gray-200 dark:hover:bg-zinc-800"
                                                >
                                                    {parent.sku}
                                                </button>
                                            ))
                                        )}
                                    </div>
                                </PopoverContent>
                            </Popover>
                            <button
                                onClick={() => handleBulkGroup(null)}
                                disabled={bulkProcessing}
                                className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 transition-all hover:bg-stone-50 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-700"
                            >
                                <Ungroup className="h-3.5 w-3.5" />
                                Ungroup
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
                        {...(canEditItems && !summarize
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
                                    'filter[unassigned]': unassignedOnly
                                        ? 1
                                        : undefined,
                                    summarize: summarize ? 1 : undefined,
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

                {canEditItems && (
                    <AdjustCountDialog
                        open={adjustingItem !== null}
                        item={adjustingItem}
                        workspace={workspace}
                        onClose={() => setAdjustingItem(null)}
                    />
                )}

                {canDeleteItems && (
                    <DeleteItemDialog
                        item={itemToDelete}
                        workspace={workspace}
                        onClose={() => setItemToDelete(null)}
                    />
                )}

                <WaitingForDeliveryDialog
                    open={waitingItem !== null}
                    item={waitingItem}
                    workspace={workspace}
                    onClose={() => setWaitingItem(null)}
                />
            </div>
        </AppLayout>
    );
}
