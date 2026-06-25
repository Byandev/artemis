import PageHeader from '@/components/common/PageHeader';
import InventoryFormDialog from '@/components/inventory/inventory-form-dialog';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { InventoryTransaction } from '@/types/models/InventoryTransaction';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import flatpickr from 'flatpickr';
import { omit } from 'lodash';
import { Edit, MoreHorizontal, Search, Trash2 } from 'lucide-react';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import { toast, Toaster } from 'sonner';
import DateOption = flatpickr.Options.DateOption;

interface InventoryItem {
    id: number;
    sku: string;
    product?: {
        id: number;
        name: string;
    };
}

interface Props {
    inventory: PaginatedData<InventoryTransaction>;
    workspace: Workspace;
    items?: InventoryItem[];
    query?: {
        sort?: string | null;
        page?: number | string;
        perPage?: number | string;
        filter?: { search?: string; start_date?: string; end_date?: string };
        summarize?: boolean;
    };
}

// A table row is either a real transaction or a summed-per-item synthetic row
// (transaction_count is only set on the summarized rows).
type TableRow = InventoryTransaction & { transaction_count?: number };

const sumCell = (value: number) => (
    <div className="flex h-10 items-center justify-center">
        <p className="text-[12px] font-medium text-gray-600 dark:text-gray-300">
            {value || 0}
        </p>
    </div>
);

// Inline-editable remaining qty. Saves to its own transaction row.
function RemainingQtyCell({
    transaction,
    workspace,
    canEdit,
}: {
    transaction: InventoryTransaction;
    workspace: Workspace;
    canEdit: boolean;
}) {
    const [value, setValue] = useState(
        transaction.remaining_qty?.toString() ?? '',
    );
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setValue(transaction.remaining_qty?.toString() ?? '');
    }, [transaction.remaining_qty]);

    const save = () => {
        const original = transaction.remaining_qty?.toString() ?? '';
        if (value === original || value === '') {
            setValue(original);
            return;
        }

        setSaving(true);
        router.patch(
            `/workspaces/${workspace.slug}/inventory/transactions/${transaction.id}/remaining-qty`,
            { remaining_qty: parseInt(value, 10) },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['inventory'],
                onSuccess: () => toast.success('Remaining quantity updated'),
                onError: () => {
                    toast.error('Failed to update remaining quantity');
                    setValue(original);
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const isNegative = parseInt(value, 10) < 0;

    if (!canEdit) {
        return (
            <div className="flex h-10 items-center justify-center">
                <p
                    className={`text-[12px] font-bold ${isNegative ? 'text-red-500' : 'text-emerald-600'}`}
                >
                    {transaction.remaining_qty ?? 0}
                </p>
            </div>
        );
    }

    return (
        <div className="flex h-10 items-center justify-center">
            <input
                type="number"
                value={value}
                disabled={saving}
                onChange={(e) => setValue(e.target.value)}
                onBlur={save}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        (e.target as HTMLInputElement).blur();
                    }
                }}
                className={`h-7 w-20 rounded-md border border-black/8 bg-stone-50 px-2 text-center font-mono! text-[12px]! font-bold outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 ${isNegative ? 'text-red-500' : 'text-emerald-600'}`}
            />
        </div>
    );
}

export default function Index({
    inventory,
    workspace,
    items = [],
    query,
}: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [searchQuery, setSearchQuery] = useState(query?.filter?.search ?? '');
    const [dateRange, setDateRange] = useState<string[]>(() => [
        query?.filter?.start_date ?? '',
        query?.filter?.end_date ?? '',
    ]);
    const [openFormModal, setOpenFormModal] = useState(false);
    const [selectedInventory, setSelectedInventory] = useState<
        InventoryTransaction | undefined
    >(undefined);
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    // Summary view is computed in the backend; this reflects the server's state.
    const summarize = !!query?.summarize;
    const canCreateTransactionLogs = usePermission(
        PERMISSIONS.CreateTransactionLogs,
    );
    const canEditTransactionLogs = usePermission(
        PERMISSIONS.EditTransactionLogs,
    );
    const canDeleteTransactionLogs = usePermission(
        PERMISSIONS.DeleteTransactionLogs,
    );
    const canManageTransactionLogs =
        canEditTransactionLogs || canDeleteTransactionLogs;

    const data = useMemo(() => inventory.data || [], [inventory.data]);

    const buildFilter = (search: string, range: string[]) => ({
        search: search || undefined,
        start_date: range[0] || undefined,
        end_date: range[1] || undefined,
    });

    useEffect(() => {
        const filterChanged =
            searchQuery !== (query?.filter?.search ?? '') ||
            (dateRange[0] || undefined) !==
                (query?.filter?.start_date ?? undefined) ||
            (dateRange[1] || undefined) !==
                (query?.filter?.end_date ?? undefined);
        if (!filterChanged) return;
        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/inventory/transactions`,
                {
                    filter: buildFilter(searchQuery, dateRange),
                    page: 1,
                    sort: query?.sort,
                    per_page: query?.perPage ?? inventory.per_page,
                    summarize: summarize ? 1 : undefined,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['inventory', 'query'],
                },
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [searchQuery, dateRange]);

    const toggleSummarize = (value: boolean) => {
        router.get(
            `/workspaces/${workspace.slug}/inventory/transactions`,
            {
                summarize: value ? 1 : undefined,
                filter: buildFilter(searchQuery, dateRange),
                page: 1,
                per_page: query?.perPage ?? inventory.per_page,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['inventory', 'query'],
            },
        );
    };

    const handleEdit = (item: InventoryTransaction) => {
        setSelectedInventory(item);
        setOpenFormModal(true);
    };

    const confirmDelete = (item: InventoryTransaction) => {
        setSelectedInventory(item);
        setDeleteModalOpen(true);
    };

    const handleDeleteAction = () => {
        if (!selectedInventory) return;

        router.delete(
            `/workspaces/${workspace.slug}/inventory/transactions/${selectedInventory.id}`,
            {
                onSuccess: () => {
                    setDeleteModalOpen(false);
                    setSelectedInventory(undefined);
                    toast.success('Record deleted successfully');
                },
            },
        );
    };

    const columns = useMemo<ColumnDef<TableRow>[]>(
        () =>
            [
                {
                    id: 'inventory_item',
                    accessorFn: (row) => row.inventory_item?.sku,
                    enableSorting: false,
                    header: ({ column }) => (
                        <SortableHeader
                            column={column}
                            title="Inventory Item"
                        />
                    ),
                    cell: ({ row }) => {
                        const item = row.original.inventory_item;
                        return (
                            <div className="flex h-10 items-center">
                                {item ? (
                                    <div className="flex flex-col gap-0.5">
                                        <span className="font-mono text-[11px] font-medium text-gray-600 dark:text-gray-400">
                                            {item.sku}
                                        </span>
                                        {item.product && (
                                            <span className="text-[10px] text-gray-400 dark:text-gray-500">
                                                {item.product.name}
                                            </span>
                                        )}
                                    </div>
                                ) : (
                                    <span className="text-[12px] text-gray-400">
                                        —
                                    </span>
                                )}
                            </div>
                        );
                    },
                },
                {
                    accessorKey: 'date',
                    enableSorting: true,
                    header: ({ column }) => (
                        <SortableHeader column={column} title="Date" />
                    ),
                    cell: ({ row }) => (
                        <div className="flex h-10 items-center justify-center">
                            <span className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                                {moment(row.original.date).format(
                                    'DD MMM YYYY',
                                )}
                            </span>
                        </div>
                    ),
                },
                {
                    accessorKey: 'ref_no',
                    enableSorting: true,
                    header: ({ column }) => (
                        <SortableHeader column={column} title="Reference No." />
                    ),
                    cell: ({ row }) => (
                        <div className="flex h-10 items-center justify-center">
                            <p className="text-[12px] text-gray-500 dark:text-gray-400">
                                {row.original.ref_no || (
                                    <span className="italic opacity-50">
                                        No Reference
                                    </span>
                                )}
                            </p>
                        </div>
                    ),
                },
                {
                    accessorKey: 'po_qty_in',
                    enableSorting: true,
                    header: ({ column }) => (
                        <SortableHeader
                            column={column}
                            title="PO Quantity In"
                        />
                    ),
                    cell: ({ row }) => (
                        <div className="flex h-10 items-center justify-center">
                            <p className="text-[12px] text-gray-500 dark:text-gray-400">
                                {row.original.po_qty_in || 0}
                            </p>
                        </div>
                    ),
                },
                {
                    accessorKey: 'po_qty_out',
                    enableSorting: true,
                    header: ({ column }) => (
                        <SortableHeader
                            column={column}
                            title="PO Quantity Out"
                        />
                    ),
                    cell: ({ row }) => (
                        <div className="flex h-10 items-center justify-center">
                            <p className="text-[12px] text-gray-500 dark:text-gray-400">
                                {row.original.po_qty_out || 0}
                            </p>
                        </div>
                    ),
                },
                {
                    accessorKey: 'rts_goods_in',
                    enableSorting: true,
                    header: ({ column }) => (
                        <SortableHeader column={column} title="RTS Goods In" />
                    ),
                    cell: ({ row }) => (
                        <div className="flex h-10 items-center justify-center">
                            <p className="text-[12px] text-gray-500 dark:text-gray-400">
                                {row.original.rts_goods_in || 0}
                            </p>
                        </div>
                    ),
                },
                {
                    accessorKey: 'rts_goods_out',
                    enableSorting: true,
                    header: ({ column }) => (
                        <SortableHeader column={column} title="RTS Goods Out" />
                    ),
                    cell: ({ row }) => (
                        <div className="flex h-10 items-center justify-center">
                            <p className="text-[12px] text-gray-500 dark:text-gray-400">
                                {row.original.rts_goods_out || 0}
                            </p>
                        </div>
                    ),
                },
                {
                    accessorKey: 'rts_bad',
                    enableSorting: true,
                    header: ({ column }) => (
                        <SortableHeader column={column} title="RTS Bad" />
                    ),
                    cell: ({ row }) => (
                        <div className="flex h-10 items-center justify-center">
                            <p className="text-[12px] text-gray-500 dark:text-gray-400">
                                {row.original.rts_bad || 0}
                            </p>
                        </div>
                    ),
                },
                {
                    accessorKey: 'lost',
                    enableSorting: true,
                    header: ({ column }) => (
                        <SortableHeader column={column} title="Lost" />
                    ),
                    cell: ({ row }) => (
                        <div className="flex h-10 items-center justify-center">
                            <p className="text-[12px] text-orange-500 dark:text-orange-400">
                                {row.original.lost || 0}
                            </p>
                        </div>
                    ),
                },
                {
                    accessorKey: 'remaining_qty',
                    enableSorting: true,
                    header: ({ column }) => (
                        <SortableHeader
                            column={column}
                            title="Remaining Qty (Manual)"
                        />
                    ),
                    cell: ({ row }) => (
                        <RemainingQtyCell
                            transaction={row.original}
                            workspace={workspace}
                            canEdit={canEditTransactionLogs}
                        />
                    ),
                },
                {
                    accessorKey: 'inventory_remaining_stock',
                    enableSorting: true,
                    header: ({ column }) => (
                        <SortableHeader
                            column={column}
                            title="Inventory Stock (ERP)"
                        />
                    ),
                    cell: ({ row }) => (
                        <div className="flex h-10 items-center justify-center">
                            <p className="text-[12px] font-medium text-gray-600 dark:text-gray-300">
                                {row.original.inventory_remaining_stock ?? '—'}
                            </p>
                        </div>
                    ),
                },
                {
                    id: 'actions',
                    header: () => (
                        <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                            Actions
                        </div>
                    ),
                    cell: ({ row }) => (
                        <div className="flex h-10 items-center justify-center">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                        <MoreHorizontal className="h-3.5 w-3.5" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="center"
                                    className="w-36"
                                >
                                    {canEditTransactionLogs && (
                                        <DropdownMenuItem
                                            onClick={() =>
                                                handleEdit(row.original)
                                            }
                                        >
                                            <Edit className="mr-2 h-3.5 w-3.5" />
                                            Edit
                                        </DropdownMenuItem>
                                    )}
                                    {canEditTransactionLogs &&
                                        canDeleteTransactionLogs && (
                                            <DropdownMenuSeparator />
                                        )}
                                    {canDeleteTransactionLogs && (
                                        <DropdownMenuItem
                                            variant="destructive"
                                            onClick={() =>
                                                confirmDelete(row.original)
                                            }
                                        >
                                            <Trash2 className="mr-2 h-3.5 w-3.5" />
                                            Delete
                                        </DropdownMenuItem>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    ),
                },
            ].filter(
                (column) => canManageTransactionLogs || column.id !== 'actions',
            ),
        [
            canDeleteTransactionLogs,
            canEditTransactionLogs,
            canManageTransactionLogs,
            workspace,
        ],
    );

    // Columns for the summarized (one row per item per date) view.
    const summaryColumns = useMemo<ColumnDef<TableRow>[]>(
        () => [
            {
                id: 'inventory_item',
                enableSorting: false,
                header: () => (
                    <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                        Inventory Item
                    </div>
                ),
                cell: ({ row }) => {
                    const item = row.original.inventory_item;
                    return (
                        <div className="flex h-10 items-center">
                            {item ? (
                                <div className="flex flex-col gap-0.5">
                                    <span className="font-mono text-[11px] font-medium text-gray-600 dark:text-gray-400">
                                        {item.sku}
                                    </span>
                                    {item.product && (
                                        <span className="text-[10px] text-gray-400 dark:text-gray-500">
                                            {item.product.name}
                                        </span>
                                    )}
                                    <span className="text-[9px] text-gray-300 dark:text-gray-600">
                                        {row.original.transaction_count} txns
                                    </span>
                                </div>
                            ) : (
                                <span className="text-[12px] text-gray-400">
                                    —
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'date',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Date" />
                ),
                cell: ({ row }) => (
                    <div className="flex h-10 items-center justify-center">
                        <span className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                            {row.original.date
                                ? moment(row.original.date).format(
                                      'DD MMM YYYY',
                                  )
                                : '—'}
                        </span>
                    </div>
                ),
            },
            {
                id: 'po_qty_in',
                enableSorting: false,
                header: () => (
                    <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                        PO Quantity In
                    </div>
                ),
                cell: ({ row }) => sumCell(row.original.po_qty_in),
            },
            {
                id: 'po_qty_out',
                enableSorting: false,
                header: () => (
                    <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                        PO Quantity Out
                    </div>
                ),
                cell: ({ row }) => sumCell(row.original.po_qty_out),
            },
            {
                id: 'rts_goods_in',
                enableSorting: false,
                header: () => (
                    <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                        RTS Goods In
                    </div>
                ),
                cell: ({ row }) => sumCell(row.original.rts_goods_in),
            },
            {
                id: 'rts_goods_out',
                enableSorting: false,
                header: () => (
                    <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                        RTS Goods Out
                    </div>
                ),
                cell: ({ row }) => sumCell(row.original.rts_goods_out),
            },
            {
                id: 'rts_bad',
                enableSorting: false,
                header: () => (
                    <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                        RTS Bad
                    </div>
                ),
                cell: ({ row }) => sumCell(row.original.rts_bad),
            },
            {
                id: 'lost',
                enableSorting: false,
                header: () => (
                    <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                        Lost
                    </div>
                ),
                cell: ({ row }) => (
                    <div className="flex h-10 items-center justify-center">
                        <p className="text-[12px] text-orange-500 dark:text-orange-400">
                            {row.original.lost || 0}
                        </p>
                    </div>
                ),
            },
            {
                id: 'inventory_remaining_stock',
                enableSorting: false,
                header: () => (
                    <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                        Inventory Stock (ERP)
                    </div>
                ),
                cell: ({ row }) => (
                    <div className="flex h-10 items-center justify-center">
                        <p className="text-[12px] font-medium text-gray-600 dark:text-gray-300">
                            {row.original.inventory_remaining_stock ?? '—'}
                        </p>
                    </div>
                ),
            },
        ],
        [],
    );

    const activeColumns = summarize ? summaryColumns : columns;
    // The backend returns per-transaction rows normally, or summed-per-item rows when
    // summarize is on — so the table data is whatever the server sent either way.
    const activeData = data as TableRow[];

    return (
        <AppLayout>
            <Head title="Transaction Logs" />
            <Toaster position="top-right" richColors />

            <Dialog
                open={deleteModalOpen}
                onOpenChange={(open) => {
                    setDeleteModalOpen(open);
                    if (!open) setSelectedInventory(undefined);
                }}
            >
                <DialogContent className="max-w-[400px] overflow-hidden rounded-2xl border-none bg-white p-0 shadow-2xl dark:bg-zinc-900">
                    <div className="p-6">
                        <DialogHeader>
                            <DialogTitle className="text-lg font-semibold text-gray-900 dark:text-white">
                                Delete Transaction
                            </DialogTitle>
                            <DialogDescription className="mt-2 text-[13px] text-gray-500 dark:text-gray-400">
                                Are you sure you want to delete{' '}
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {selectedInventory?.ref_no ||
                                        'this transaction'}
                                </span>
                                ? This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                onClick={() => setDeleteModalOpen(false)}
                                className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleDeleteAction}
                                className="flex h-9 items-center rounded-lg bg-red-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-red-700"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <InventoryFormDialog
                workspace={workspace}
                open={openFormModal}
                onOpenChange={(open) => {
                    setOpenFormModal(open);
                    if (!open) setSelectedInventory(undefined);
                }}
                inventory={selectedInventory}
                items={items}
            />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Transaction Logs"
                    description="Manage your inventory transactions"
                >
                    {canCreateTransactionLogs && (
                        <button
                            onClick={() => {
                                setSelectedInventory(undefined);
                                setOpenFormModal(true);
                            }}
                            className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            Record new Transaction
                        </button>
                    )}
                </PageHeader>

                <div className="mb-3 flex flex-col items-stretch gap-2 md:flex-row md:items-center">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search by reference, SKU or product..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 font-mono! text-[12px]! text-gray-800 outline-none focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                        />
                    </div>
                    <DatePicker
                        id="inventory-transactions-date-range"
                        mode="range"
                        placeholder="Filter by date"
                        defaultDate={
                            (dateRange[0] && dateRange[1]
                                ? dateRange
                                : undefined) as never as DateOption
                        }
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setDateRange([
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                ]);
                            } else if (dates.length === 0) {
                                setDateRange(['', '']);
                            }
                        }}
                    />
                    <label className="flex h-9 cursor-pointer items-center gap-2 rounded-[10px] border border-black/8 bg-white px-3.5 md:ml-auto dark:border-white/8 dark:bg-zinc-800">
                        <span className="font-mono! text-[12px]! font-medium text-gray-600 dark:text-gray-300">
                            Summarize
                        </span>
                        <Switch
                            checked={summarize}
                            onCheckedChange={toggleSummarize}
                        />
                    </label>
                </div>

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={activeColumns}
                        data={activeData}
                        enableInternalPagination={false}
                        initialSorting={initialSorting}
                        meta={{ ...omit(inventory, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                `/workspaces/${workspace.slug}/inventory/transactions`,
                                {
                                    sort: params?.sort,
                                    filter: buildFilter(searchQuery, dateRange),
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query?.perPage ??
                                        inventory.per_page,
                                    summarize: summarize ? 1 : undefined,
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
            </div>
        </AppLayout>
    );
}
