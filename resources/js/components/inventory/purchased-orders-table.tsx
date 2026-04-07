import { ChevronDown, Pencil, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { DataTable } from '@/components/ui/data-table';
import { PurchasedOrder, StatusId, StatusOption } from '@/types/models/PurchasedOrder';
import { ColumnDef } from '@tanstack/react-table';

interface PurchasedOrdersTableProps {
    rows: PurchasedOrder[];
    monoFont: string;
    formatIssueDate: (value: string) => string;
    formatMoney: (amount: number) => string;
    statusBadgeClass: (status: StatusId | string) => string;
    statusLabel: (value: StatusId | number | string) => string;
    statusOptions: StatusOption[];
    statusOptionTextClass: (status: StatusId | string) => string;
    onUpdateStatus: (id: number, status: StatusId) => void;
    onOpenEdit: (row: PurchasedOrder) => void;
    onOpenDelete: (row: PurchasedOrder) => void;
    currentPage: number;
    lastPage: number;
    perPage: number;
    totalRows: number;
    onFetchPage: (page: number) => void;
}

export function PurchasedOrdersTable({
    rows,
    monoFont,
    formatIssueDate,
    formatMoney,
    statusBadgeClass,
    statusLabel,
    statusOptions,
    statusOptionTextClass,
    onUpdateStatus,
    onOpenEdit,
    onOpenDelete,
    currentPage,
    lastPage,
    perPage,
    totalRows,
    onFetchPage,
}: PurchasedOrdersTableProps) {
    const fromRow = totalRows === 0 ? 0 : Math.min((currentPage - 1) * perPage + 1, totalRows);
    const toRow = totalRows === 0 ? 0 : Math.min(currentPage * perPage, totalRows);

    const columns = useMemo<ColumnDef<PurchasedOrder>[]>(() => [
        {
            accessorKey: 'issue_date',
            header: 'Issue Date',
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-[12px] text-gray-700 dark:text-gray-200" style={{ fontFamily: monoFont }}>
                    {formatIssueDate(row.original.issue_date)}
                </span>
            ),
        },
        {
            accessorKey: 'delivery_no',
            header: 'Delivered No.',
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-[12px] text-gray-800 dark:text-gray-100" style={{ fontFamily: monoFont }}>
                    {row.original.delivery_no || '-'}
                </span>
            ),
        },
        {
            accessorKey: 'cust_po_no',
            header: 'Cust PO No.',
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-[12px] text-gray-500 dark:text-gray-400" style={{ fontFamily: monoFont }}>
                    {row.original.cust_po_no || '-'}
                </span>
            ),
        },
        {
            accessorKey: 'control_no',
            header: 'Control No.',
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-[12px] text-gray-500 dark:text-gray-400" style={{ fontFamily: monoFont }}>
                    {row.original.control_no || '-'}
                </span>
            ),
        },
        {
            accessorKey: 'item',
            header: 'Item',
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-[12px] font-semibold text-gray-800 dark:text-gray-100">
                    {row.original.item}
                </span>
            ),
        },
        {
            accessorKey: 'cog_amount',
            header: 'COG Amount',
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-[12px] text-gray-700 dark:text-gray-200" style={{ fontFamily: monoFont }}>
                    {formatMoney(row.original.cog_amount)}
                </span>
            ),
        },
        {
            accessorKey: 'delivery_fee',
            header: 'Delivery Fee',
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-[12px] text-gray-700 dark:text-gray-200" style={{ fontFamily: monoFont }}>
                    {formatMoney(row.original.delivery_fee)}
                </span>
            ),
        },
        {
            accessorKey: 'total_amount',
            header: 'Total Amount',
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-[12px] text-gray-800 dark:text-gray-100" style={{ fontFamily: monoFont }}>
                    {formatMoney(row.original.total_amount)}
                </span>
            ),
        },
        {
            id: 'status',
            header: 'Status',
            cell: ({ row }) => (
                <div className="inline-flex h-7 min-w-[170px] items-center rounded-2xl pl-1.5 pr-1">
                    <span className={`inline-flex w-full items-center gap-1 rounded-2xl px-2 py-1 text-[11px] font-medium ${statusBadgeClass(row.original.status)}`}>
                        <span className="h-1.5 w-1.5 rounded-full bg-current/60" />
                        <span>{statusLabel(row.original.status)}</span>
                    </span>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="ml-1 inline-flex h-6 w-5 items-center justify-center rounded-md text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                aria-label={`Open status dropdown for ${row.original.delivery_no || row.original.item}`}
                            >
                                <ChevronDown className="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-[150px] p-1.5">
                            {statusOptions.map((opt) => (
                                <DropdownMenuItem
                                    key={opt.value}
                                    className={statusOptionTextClass(opt.value)}
                                    onClick={() => onUpdateStatus(row.original.id, opt.value)}
                                >
                                    {opt.label}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            cell: ({ row }) => (
                <div className="inline-flex items-center text-[11px]">
                    <button
                        type="button"
                        className="inline-flex items-center px-1 text-gray-400 transition-colors hover:text-sky-600 dark:text-gray-500 dark:hover:text-sky-400"
                        onClick={() => onOpenEdit(row.original)}
                    >
                        <Pencil className="h-3.5 w-3.5" />
                    </button>
                    <span className="mx-1 h-3.5 w-px bg-black/10 dark:bg-white/10" />
                    <button
                        type="button"
                        className="inline-flex items-center px-1 text-gray-400 transition-colors hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                        onClick={() => onOpenDelete(row.original)}
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                    </button>
                </div>
            ),
        },
    ], [
        formatIssueDate,
        formatMoney,
        monoFont,
        onOpenDelete,
        onOpenEdit,
        onUpdateStatus,
        statusBadgeClass,
        statusLabel,
        statusOptionTextClass,
        statusOptions,
    ]);

    return (
        <div className="relative overflow-visible">
            <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white shadow-theme-xs dark:border-white/6 dark:bg-zinc-900">
                <div className="max-w-full overflow-x-auto custom-scrollbar">
                    <DataTable
                        columns={columns}
                        data={rows}
                        meta={{
                            current_page: currentPage,
                            last_page: lastPage,
                            per_page: perPage,
                            total: totalRows,
                            from: fromRow,
                            to: toRow,
                            links: lastPage > 1
                                ? [{ url: '#', label: String(currentPage), active: true }]
                                : [],
                        }}
                        onFetch={(params) => {
                            const nextPage = Number(params?.page ?? currentPage);
                            onFetchPage(Number.isFinite(nextPage) ? nextPage : currentPage);
                        }}
                    />
                </div>
            </div>
        </div>
    );
}
