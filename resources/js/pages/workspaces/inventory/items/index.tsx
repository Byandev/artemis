import PageHeader from '@/components/common/PageHeader';
import { AdjustCountDialog } from '@/components/inventory/adjust-count-dialog';
import { DeleteItemDialog } from '@/components/inventory/delete-item-dialog';
import { ItemFormDialog } from '@/components/inventory/item-form-dialog';
import { WaitingForDeliveryDialog } from '@/components/inventory/waiting-for-delivery-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
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
import { PRODUCT_STATUSES } from '@/constants/product-statuses';
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
    History,
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
    /** Everything still owed on open purchase orders, at any stage. */
    waiting_for_delivery_stocks: number | null;
    three_days_average: number | null;
    po_qty: number | null;
    remaining_after_fulfillment: number | null;
    stocks_needed_for_lead_time: number | null;
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
    /** The day being shown, or null when the list is live. Resolved server-side. */
    snapshotDate?: string | null;
    /** Days that actually have a snapshot, newest first. */
    snapshotDates?: string[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        summarize?: boolean;
        filter?: {
            search?: string;
            is_active?: string | number | boolean;
            unassigned?: string | number | boolean;
            product_status?: string;
            date?: string;
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

// Lead-time cell with inline editing. Read-only users see the badge; editors can
// click it to type a new value. Works in the summarize view too — there the row id
// is the group's parent, so the PATCH updates the parent's lead time. Saves on Enter
// or blur (only when changed), Escape cancels.
const LeadTimeCell = ({
    item,
    editable,
    baseUrl,
}: {
    item: Item;
    editable: boolean;
    baseUrl: string;
}) => {
    const [editing, setEditing] = useState(false);
    const [value, setValue] = useState(String(item.lead_time ?? 0));
    const [saving, setSaving] = useState(false);

    // Resync when the row's value changes (e.g. after a reload).
    useEffect(() => {
        setValue(String(item.lead_time ?? 0));
    }, [item.lead_time]);

    const save = () => {
        setEditing(false);
        const parsed = parseInt(value, 10);
        const next = Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
        if (next === (item.lead_time ?? 0)) {
            setValue(String(next));
            return;
        }
        router.patch(
            `${baseUrl}/${item.id}/lead-time`,
            { lead_time: next },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onError: () => {
                    toast.error('Failed to update lead time.');
                    setValue(String(item.lead_time ?? 0));
                },
            },
        );
    };

    if (!editable) {
        return (
            <div className="text-center">
                <span className="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                    {item.lead_time ?? 0}d
                </span>
            </div>
        );
    }

    if (editing) {
        return (
            <div className="flex justify-center">
                <input
                    type="number"
                    min={0}
                    autoFocus
                    value={value}
                    disabled={saving}
                    onChange={(e) => setValue(e.target.value)}
                    onBlur={save}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') save();
                        if (e.key === 'Escape') {
                            setValue(String(item.lead_time ?? 0));
                            setEditing(false);
                        }
                    }}
                    onClick={(e) => e.stopPropagation()}
                    className="h-7 w-16 rounded-md border border-emerald-500 bg-white px-2 text-center font-mono text-[11px] text-gray-800 outline-none focus:ring-2 focus:ring-emerald-500/15 dark:bg-zinc-900 dark:text-gray-100"
                />
            </div>
        );
    }

    return (
        <div className="text-center">
            <button
                type="button"
                title="Edit lead time"
                onClick={(e) => {
                    e.stopPropagation();
                    setEditing(true);
                }}
                className="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 transition-colors hover:bg-stone-200 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700"
            >
                {item.lead_time ?? 0}d
                <Pencil className="h-2.5 w-2.5 opacity-50" />
            </button>
        </div>
    );
};

export default function ItemIndex({
    workspace,
    items,
    products,
    parents,
    snapshotDate = null,
    snapshotDates = [],
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
    // Lifecycle stage of the linked product; '' means every stage.
    const [productStatus, setProductStatus] = useState(
        query?.filter?.product_status
            ? String(query.filter.product_status)
            : '',
    );
    // Summarize rolls SKU variants up under their parent item and sums the values.
    const [summarize, setSummarize] = useState(!!query?.summarize);
    // A date pins the list to that day's saved snapshot; '' is live data.
    const [dateValue, setDateValue] = useState(
        query?.filter?.date ? String(query.filter.date) : '',
    );
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [bulkProcessing, setBulkProcessing] = useState(false);
    const [productPickerOpen, setProductPickerOpen] = useState(false);
    const [productSearch, setProductSearch] = useState('');
    const [groupPickerOpen, setGroupPickerOpen] = useState(false);
    const [parentSearch, setParentSearch] = useState('');
    const [newParentSku, setNewParentSku] = useState('');

    // A pinned date renders historical rows. Every mutating action still targets
    // today's items, so editing from a snapshot would silently change a different
    // thing than the one on screen — hide those affordances entirely while pinned.
    const viewingSnapshot = !!snapshotDate;

    const canCreateItems =
        usePermission(PERMISSIONS.CreateInventoryItems) && !viewingSnapshot;
    const canEditItems =
        usePermission(PERMISSIONS.EditInventoryItems) && !viewingSnapshot;
    const canDeleteItems =
        usePermission(PERMISSIONS.DeleteInventoryItems) && !viewingSnapshot;
    const canUseItemActions = canEditItems || canDeleteItems;

    const baseUrl = `/workspaces/${workspace.slug}/inventory/items`;

    const selectedIds = useMemo(
        () => Object.keys(rowSelection).filter((id) => rowSelection[id]),
        [rowSelection],
    );

    /**
     * The list's full query string. Every navigation — search, each toggle,
     * sorting, pagination — has to resend all of the filters, because anything
     * left out silently resets. Building them in one place keeps a new filter
     * from surviving a search but vanishing on a sort. A toggle handler passes
     * its new value as an override, since its own state has not landed yet.
     */
    const buildParams = (
        overrides: Record<string, string | number | undefined> = {},
    ): Record<string, string | number | undefined> => ({
        sort: query?.sort ?? undefined,
        'filter[search]': searchValue || undefined,
        'filter[is_active]': activeOnly ? 1 : 'all',
        'filter[unassigned]': unassignedOnly ? 1 : undefined,
        'filter[product_status]': productStatus || undefined,
        'filter[date]': dateValue || undefined,
        // Always explicit: the server rolls up by default, so an omitted param
        // reads as "on" and the toggle would spring back the next time the URL
        // is read (a refresh, or any navigation that rebuilds these params).
        summarize: summarize ? 1 : 0,
        page: 1,
        per_page: query?.perPage ?? items.per_page,
        ...overrides,
    });

    const visitOptions = {
        preserveState: true,
        replace: true,
        preserveScroll: true,
        // The snapshot props have to come back with the rows: they decide whether the
        // banner shows and whether editing is allowed for what is now on screen.
        only: ['items', 'snapshotDate', 'snapshotDates'],
    };

    // Logic for searching (Resets to page 1)
    const performQuery = useCallback(
        debounce((search: string) => {
            router.get(
                baseUrl,
                buildParams({ 'filter[search]': search || undefined }),
                visitOptions,
            );
        }, 400),
        [
            baseUrl,
            query?.sort,
            query?.perPage,
            items.per_page,
            activeOnly,
            unassignedOnly,
            productStatus,
            summarize,
            dateValue,
        ],
    );

    const handleActiveOnlyChange = (checked: boolean) => {
        setActiveOnly(checked);
        router.get(
            baseUrl,
            buildParams({ 'filter[is_active]': checked ? 1 : 'all' }),
            visitOptions,
        );
    };

    const handleUnassignedChange = (checked: boolean) => {
        setUnassignedOnly(checked);
        // Unassigned items have no product, so the two filters can never both
        // match. Drop the status rather than leaving an empty list behind.
        if (checked) {
            setProductStatus('');
        }
        router.get(
            baseUrl,
            buildParams({
                'filter[unassigned]': checked ? 1 : undefined,
                ...(checked ? { 'filter[product_status]': undefined } : {}),
            }),
            visitOptions,
        );
    };

    const handleProductStatusChange = (value: string) => {
        setProductStatus(value);
        router.get(
            baseUrl,
            buildParams({ 'filter[product_status]': value || undefined }),
            visitOptions,
        );
    };

    // Pin the list to a saved day, or pass '' to go back to live data. The row
    // selection is dropped because a snapshot's rows are historical — bulk edits
    // would be applied to today's items, not the ones on screen.
    const handleDateChange = (value: string) => {
        setDateValue(value);
        setRowSelection({});
        router.get(
            baseUrl,
            buildParams({ 'filter[date]': value || undefined }),
            visitOptions,
        );
    };

    const handleSummarizeChange = (checked: boolean) => {
        setSummarize(checked);
        // Selection/bulk actions only make sense on the flat list.
        setRowSelection({});
        router.get(
            baseUrl,
            buildParams({ summarize: checked ? 1 : 0 }),
            visitOptions,
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
                <LeadTimeCell
                    item={row.original}
                    editable={canEditItems}
                    baseUrl={baseUrl}
                />
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
            accessorKey: 'po_qty',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="PO QTY"
                    className="justify-center"
                />
            ),
            // Days-of-coverage buffer: days_of_coverage × 3-day average.
            cell: ({ row }) => (
                <div className="text-center">
                    <MetricCell
                        value={row.original.po_qty}
                        color="text-amber-600 dark:text-amber-400"
                    />
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
            accessorKey: 'stocks_needed_for_lead_time',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Stocks Needed (Lead Time)"
                    className="justify-center"
                />
            ),
            // 3-day average × lead time — expected demand over the lead-time window.
            cell: ({ row }) => (
                <div className="text-center">
                    <MetricCell
                        value={row.original.stocks_needed_for_lead_time}
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
                return (
                    <div className="flex flex-col items-center gap-0.5">
                        <span
                            className={`font-mono text-[12px] font-medium ${color}`}
                        >
                            {d > 0 ? '+' : ''}
                            {num(d)}
                        </span>
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
                // Items that actually need a PO get the whole cell flagged red —
                // the negative margins cancel TableCell's px-4 py-3 so the tint
                // fills the cell edge to edge.
                const needsPo = v != null && v > 0;
                return (
                    <div
                        className={`-mx-4 -my-3 px-4 py-3 text-center ${
                            needsPo ? 'bg-red-50 dark:bg-red-500/10' : ''
                        }`}
                    >
                        <MetricCell
                            value={v}
                            color={
                                needsPo
                                    ? 'text-red-600 dark:text-red-400'
                                    : 'text-gray-400 dark:text-gray-500'
                            }
                        />
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
                        {/* Pin the list to a saved day. Only days with a stored
                            snapshot are selectable — anything else has no data to
                            show. Sits next to Export because the export follows
                            whichever day is pinned. */}
                        <DatePicker
                            id="inventory-items-snapshot-date"
                            // The picker seeds its display from defaultDate once,
                            // on mount — changing the prop afterwards leaves the
                            // old date on screen. Keying on the pinned day remounts
                            // it so "Back to live data" actually clears the field
                            // instead of only clearing the list underneath it.
                            key={dateValue || 'live'}
                            compact
                            placeholder="Live (pick a date)"
                            defaultDate={dateValue || undefined}
                            enable={snapshotDates}
                            onChange={(dates) => {
                                handleDateChange(
                                    dates.length
                                        ? format(dates[0], 'yyyy-MM-dd')
                                        : '',
                                );
                            }}
                        />
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
                                    'filter[product_status]':
                                        productStatus || '',
                                    sort: query?.sort ?? '',
                                    // Export what's on screen: grouped rows when
                                    // the summarize toggle is on, and the pinned
                                    // day's snapshot rather than today when a date
                                    // is selected.
                                    'filter[date]': dateValue || '',
                                    // the summarize toggle is on. Explicit '0'
                                    // when off — '' is stripped below and the
                                    // export would fall back to grouped.
                                    summarize: summarize ? '1' : '0',
                                }).filter(([, v]) => v !== ''),
                            ).toString()}`}
                            className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-zinc-800"
                        >
                            <Download className="h-3.5 w-3.5" />
                            Export
                        </a>
                        {/* The planning report. Always grouped — a parent and
                            its children share one reorder decision — but it does
                            follow the pinned day, which the snapshot froze these
                            figures into. */}
                        <a
                            href={`${baseUrl}/report?${new URLSearchParams(
                                Object.entries({
                                    'filter[search]': searchValue || '',
                                    'filter[is_active]': activeOnly
                                        ? '1'
                                        : 'all',
                                    'filter[unassigned]': unassignedOnly
                                        ? '1'
                                        : '',
                                    'filter[product_status]':
                                        productStatus || '',
                                    sort: query?.sort ?? '',
                                    'filter[date]': dateValue || '',
                                }).filter(([, v]) => v !== ''),
                            ).toString()}`}
                            className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-zinc-800"
                        >
                            <Download className="h-3.5 w-3.5" />
                            Report
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

                    <select
                        value={productStatus}
                        onChange={(e) =>
                            handleProductStatusChange(e.target.value)
                        }
                        disabled={unassignedOnly}
                        title={
                            unassignedOnly
                                ? 'Unavailable while showing items with no product assigned'
                                : undefined
                        }
                        className="h-9 rounded-[10px] border border-black/6 bg-stone-100 px-3 font-mono! text-[12px]! text-gray-800 transition-all outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:focus:border-emerald-400"
                    >
                        <option value="">All product statuses</option>
                        {PRODUCT_STATUSES.map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </select>

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

                {viewingSnapshot && (
                    <div className="mb-3 flex flex-wrap items-center gap-3 rounded-[12px] border border-amber-500/25 bg-amber-50/70 px-4 py-2.5 dark:border-amber-400/25 dark:bg-amber-500/5">
                        <History className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                        <span className="font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">
                            Showing the saved snapshot for{' '}
                            {winningDate(snapshotDate)} — read-only.
                        </span>
                        <button
                            onClick={() => handleDateChange('')}
                            className="ml-auto flex h-7 items-center rounded-lg border border-black/8 bg-white px-3 font-mono! text-[11px]! font-medium text-gray-700 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-700"
                        >
                            Back to live data
                        </button>
                    </div>
                )}

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
                                buildParams({
                                    sort: params?.sort ?? undefined,
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query?.perPage ??
                                        items.per_page,
                                }),
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
