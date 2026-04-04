import AppLayout from '@/layouts/app-layout';
import { DataTable } from '@/components/ui/data-table';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Search,
    MoreHorizontal,
    Edit,
    Trash2,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { toast, Toaster } from 'sonner';
import { Workspace } from '@/types/models/Workspace';
import { InventoryTransaction } from '@/types/models/InventoryTransaction';
import InventoryFormDialog from '@/components/inventory/inventory-form-dialog';
import PageHeader from '@/components/common/PageHeader';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import DatePicker from '@/components/ui/date-picker';
import moment from 'moment';
import { omit } from 'lodash';
import { Button } from '@/components/ui/button';

interface Props {
    inventory: {
        data: InventoryTransaction[];
        total: number;
        from: number;
        to: number;
        links: any[];
        last_page: number;
        current_page: number;
        per_page: number;
    }
    workspace: Workspace;
    query?: {
        sort?: string | null;
        page?: number | string;
        search?: string;
    };
}

export default function Index({ inventory, workspace, query }: Props) {
    const [searchQuery, setSearchQuery] = useState(query?.search ?? '');
    const [openFormModal, setOpenFormModal] = useState(false);
    const [selectedInventory, setSelectedInventory] = useState<InventoryTransaction | undefined>(undefined);
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [dateRange, setDateRange] = useState<string[]>([]);

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/inventory/transactions`,
                {
                    filter: {
                        search: searchQuery || undefined,
                        start_date: dateRange?.[0] || undefined,
                        end_date: dateRange?.[1] || undefined,
                    },
                    page: searchQuery ? 1 : query?.page ?? 1,
                    sort: query?.sort
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['inventory', 'query']
                }
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [searchQuery, dateRange]);

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

        router.delete(`/workspaces/${workspace.slug}/inventory/transactions/${selectedInventory.id}`, {
            onSuccess: () => {
                setDeleteModalOpen(false);
                setSelectedInventory(undefined);
                toast.success('Record deleted successfully');
            }
        });
    };

    const columns: ColumnDef<InventoryTransaction>[] = [
        {
            accessorKey: 'date',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Date</div>,
            cell: ({ row }) => (
                <div className="flex h-10 items-center justify-center">
                    <span className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                        {moment(row.original.date).format('DD MMM YYYY')}</span>

                </div>
            ),
        },
        {
            accessorKey: 'ref_no',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Reference No.</div>,
            cell: ({ row }) => (
                <div className="flex h-10 items-center justify-center">
                    <p className="text-[12px] text-gray-500 dark:text-gray-400">
                        {row.original.ref_no || <span className="italic opacity-50">No Reference</span>}
                    </p>
                </div>
            ),
        },
        {
            accessorKey: 'po_qty_in',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">PO Quantity In</div>,
            cell: ({ row }) => (
                <div className="flex h-10 items-center justify-center">
                    <p className="text-[12px] text-gray-500 dark:text-gray-400">{row.original.po_qty_in || 0}</p>
                </div>
            ),
        },
        {
            accessorKey: 'po_qty_out',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">PO Quantity Out</div>,
            cell: ({ row }) => (
                <div className="flex h-10 items-center justify-center">
                    <p className="text-[12px] text-gray-500 dark:text-gray-400">{row.original.po_qty_out || 0}</p>
                </div>
            ),
        },
        {
            accessorKey: 'rts_goods_in',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">RTS Goods In</div>,
            cell: ({ row }) => (
                <div className="flex h-10 items-center justify-center">
                    <p className="text-[12px] text-gray-500 dark:text-gray-400">{row.original.rts_goods_in || 0}</p>
                </div>
            ),
        },
        {
            accessorKey: 'rts_goods_out',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">RTS Goods Out</div>,
            cell: ({ row }) => (
                <div className="flex h-10 items-center justify-center">
                    <p className="text-[12px] text-gray-500 dark:text-gray-400">{row.original.rts_goods_out || 0}</p>
                </div>
            ),
        },
        {
            accessorKey: 'rts_bad',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">RTS Bad</div>,
            cell: ({ row }) => (
                <div className="flex h-10 items-center justify-center">
                    <p className="text-[12px] text-gray-500 dark:text-gray-400">{row.original.rts_bad || 0}</p>
                </div>
            ),
        },
        {
            accessorKey: 'remaining_qty',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Remaining Quantity</div>,
            cell: ({ row }) => (
                <div className="flex h-10 items-center justify-center">
                    <p className={`text-[12px] font-bold ${row.original.remaining_qty < 0 ? 'text-red-500' : 'text-emerald-600'}`}>
                        {row.original.remaining_qty ?? 0}
                    </p>
                </div>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Actions</div>,
            cell: ({ row }) => (
                <div className="flex h-10 items-center justify-center">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                <MoreHorizontal className="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="center" className="w-36">
                            <DropdownMenuItem onClick={() => handleEdit(row.original)}>
                                <Edit className="mr-2 h-3.5 w-3.5" />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem variant="destructive" onClick={() => confirmDelete(row.original)}>
                                <Trash2 className="mr-2 h-3.5 w-3.5" />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ),
        },
    ];

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
            />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Transaction Logs"
                    description="Manage your inventory transactions"
                >
                    <DatePicker
                        id={'inventory-date-range'}
                        mode={'range'}
                        placeholder="Filter by date range..."
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setDateRange([
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                ]);
                            } else if (dates.length === 0) {
                                setDateRange([]);
                            }
                        }}
                        defaultDate={
                            dateRange.length > 0
                                ? (dateRange as any)
                                : undefined
                        }
                    />
                    <button
                        onClick={() => {
                            setSelectedInventory(undefined);
                            setOpenFormModal(true);
                        }}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        Record new Transaction
                    </button>
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search Inventory Reference No...."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 font-mono! text-[12px]! text-gray-800 outline-none focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                        />
                    </div>
                </div>

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={inventory.data || []}
                        enableInternalPagination={false}
                        meta={{ ...omit(inventory, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                `/workspaces/${workspace.slug}/inventory/transactions`,
                                {
                                    sort: params?.sort,
                                    search: searchQuery || undefined,
                                    start_date: dateRange[0],
                                    end_date: dateRange[1],
                                    page: params?.page ?? 1,
                                },
                                {
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                    only: ['inventory'],
                                },
                            );
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
