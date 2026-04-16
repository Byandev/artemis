import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Workspace } from '@/types/models/Workspace';
import { omit } from 'lodash';
import { DeleteOrderDialog } from '@/components/inventory/delete-order-dialog';

interface PurchasedOrderItem {
    id: number;
    count: number;
    amount: string;
    total_amount: string;
    inventory_item?: {
        sku: string;
        product?: { name: string };
    };
}

const STATUSES: Record<number, { label: string; color: string }> = {
    1: { label: 'For Approval',        color: 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400' },
    2: { label: 'Approved',            color: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400' },
    3: { label: 'To Pay',              color: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400' },
    4: { label: 'Paid',                color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400' },
    5: { label: 'For Purchase',        color: 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-400' },
    6: { label: 'Waiting For Delivery',color: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-400' },
    7: { label: 'Delivered',           color: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400' },
    8: { label: 'Cancelled',           color: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400' },
};

interface PurchasedOrder {
    id: number;
    issue_date: string;
    delivery_no: string | null;
    cust_po_no: string | null;
    control_no: string | null;
    delivery_fee: string;
    total_amount: string;
    status: number;
    items: PurchasedOrderItem[];
}

interface Props {
    workspace: Workspace;
    orders: {
        data: PurchasedOrder[];
        total: number;
        from: number;
        to: number;
        links: any[];
        last_page: number;
        current_page: number;
        per_page: number;
    };
}

export default function PurchasedOrderIndex({ workspace, orders }: Props) {
   const [deletingOrder, setDeletingOrder] = useState<PurchasedOrder | null>(null);

   const baseUrl = `/workspaces/${workspace.slug}/inventory/purchased-orders`;

    const columns: ColumnDef<PurchasedOrder>[] = [
        {
            accessorKey: 'issue_date',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Issue Date" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.issue_date ? row.original.issue_date.slice(0, 10) : '—'}
                </span>
            ),
        },
        {
            accessorKey: 'delivery_no',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Delivery No." />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.delivery_no || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'cust_po_no',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Cust PO No." />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.cust_po_no || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'control_no',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Control No." />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.control_no || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'delivery_fee',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Delivery Fee" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    ₱{Number(row.original.delivery_fee).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                </span>
            ),
        },
        {
            accessorKey: 'total_amount',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Total Amount" />,
            cell: ({ row }) => (
                <span className="font-mono text-[12px] font-semibold text-gray-800 dark:text-gray-200">
                    ₱{Number(row.original.total_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            enableSorting: false,
            header: ({ column }) => <SortableHeader column={column} title="Status" />,
            cell: ({ row }) => {
                const s = STATUSES[row.original.status] ?? STATUSES[1];
                return (
                    <span className={`inline-flex items-center rounded-full px-2.5 py-1 font-mono text-[11px] font-medium ${s.color}`}>
                        {s.label}
                    </span>
                );
            },
        },
        {
            accessorKey: 'items',
            enableSorting: false,
            header: () => <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400">Items</span>,
            cell: ({ row }) => (
                <span className="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                    {row.original.items.length}
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Actions</div>,
            cell: ({ row }) => {
                const order = row.original;
                return (
                    <div className="flex justify-center">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                    <MoreHorizontal className="h-3.5 w-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-36">
                                <DropdownMenuItem onClick={() => router.get(`${baseUrl}/${order.id}/edit`)}>
                                    <Pencil className="mr-2 h-3.5 w-3.5" />
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    className="text-red-600 focus:text-red-600 dark:text-red-400"
                                   onClick={() => setDeletingOrder(order)}
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
            <Head title={`${workspace.name} - Purchased Orders`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Purchased Orders"
                    description="Manage your inventory purchased orders."
                >
                    <button
                        onClick={() => router.get(`${baseUrl}/create`)}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        Add Order
                    </button>
                </PageHeader>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={orders.data || []}
                        meta={{ ...omit(orders, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                { page: params?.page ?? 1 },
                                { preserveState: true, replace: true, preserveScroll: true }
                            );
                        }}
                    />
                </div>
                <DeleteOrderDialog 
                    order={deletingOrder} 
                    workspace={workspace} 
                    onClose={() => setDeletingOrder(null)} 
                />
            </div>
        </AppLayout>
    );
}
