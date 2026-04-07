import { ChevronDown, Inbox, Pencil, Plus, Trash2 } from 'lucide-react';
import { ReactNode } from 'react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { PurchasedOrder, StatusId, StatusOption } from '@/types/models/PurchasedOrder';

interface PurchasedOrdersTableProps {
    loading: boolean;
    showEmptyState: boolean;
    emptyStateTitle: string;
    emptyStateDescription: string;
    emptyStateButtonLabel: string;
    onOpenAddItem: () => void;
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
    footerSlot?: ReactNode;
}

export function PurchasedOrdersTable({
    loading,
    showEmptyState,
    emptyStateTitle,
    emptyStateDescription,
    emptyStateButtonLabel,
    onOpenAddItem,
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
    footerSlot,
}: PurchasedOrdersTableProps) {
    return (
        <div className="relative overflow-visible">
            <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white shadow-theme-xs dark:border-white/6 dark:bg-zinc-900">
                {loading ? (
                    <div className="flex min-h-[520px] flex-col items-center justify-center px-4">
                        <div className="relative h-32 w-32">
                            <div className="absolute inset-0 rounded-full border-10 border-gray-300/80 dark:border-white/10" />
                            <div className="absolute inset-0 animate-spin rounded-full border-10 border-transparent border-r-[#16d5b2] border-b-[#16d5b2]" />
                            <div className="absolute inset-0 flex items-center justify-center">
                                <img
                                    src="/img/logo/artemis.png"
                                    alt="Artemis"
                                    className="h-[58px] w-[58px] object-contain"
                                />
                            </div>
                        </div>
                        <p className="mt-4 text-[18px] font-normal tracking-[0.01em] text-gray-800 dark:text-gray-200" style={{ fontFamily: monoFont }}>
                            Loading...
                        </p>
                    </div>
                ) : showEmptyState ? (
                    <div className="flex min-h-[420px] flex-col items-center justify-center px-4 text-center">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 dark:bg-emerald-500/10">
                            <Plus className="h-7 w-7" />
                        </div>
                        <h3 className="mt-4 text-[22px] font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{emptyStateTitle}</h3>
                        <p className="mt-1 text-[14px] text-gray-500 dark:text-gray-500">{emptyStateDescription}</p>
                        <Button
                            type="button"
                            onClick={onOpenAddItem}
                            className="mt-4 inline-flex h-10 items-center gap-1.5 rounded-lg px-4 text-[14px] font-medium"
                        >
                            <Plus className="h-4 w-4" />
                            {emptyStateButtonLabel}
                        </Button>
                    </div>
                ) : (
                    <div className="max-w-full overflow-x-auto custom-scrollbar">
                        <Table className="min-w-full">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="px-4 py-2.5 text-left text-[10px] font-mono font-medium uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500 border-b border-black/6 dark:border-white/6">Issue Date</TableHead>
                                    <TableHead className="px-4 py-2.5 text-left text-[10px] font-mono font-medium uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500 border-b border-black/6 dark:border-white/6">Delivered No.</TableHead>
                                    <TableHead className="px-4 py-2.5 text-left text-[10px] font-mono font-medium uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500 border-b border-black/6 dark:border-white/6">Cust PO No.</TableHead>
                                    <TableHead className="px-4 py-2.5 text-left text-[10px] font-mono font-medium uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500 border-b border-black/6 dark:border-white/6">Control No.</TableHead>
                                    <TableHead className="px-4 py-2.5 text-left text-[10px] font-mono font-medium uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500 border-b border-black/6 dark:border-white/6">Item</TableHead>
                                    <TableHead className="px-4 py-2.5 text-left text-[10px] font-mono font-medium uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500 border-b border-black/6 dark:border-white/6">COG Amount</TableHead>
                                    <TableHead className="px-4 py-2.5 text-left text-[10px] font-mono font-medium uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500 border-b border-black/6 dark:border-white/6">Delivery Fee</TableHead>
                                    <TableHead className="px-4 py-2.5 text-left text-[10px] font-mono font-medium uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500 border-b border-black/6 dark:border-white/6">Total Amount</TableHead>
                                    <TableHead className="px-4 py-2.5 text-left text-[10px] font-mono font-medium uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500 border-b border-black/6 dark:border-white/6">Status</TableHead>
                                    <TableHead className="px-4 py-2.5 text-left text-[10px] font-mono font-medium uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500 border-b border-black/6 dark:border-white/6">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="bg-white text-gray-600 dark:bg-zinc-900 dark:text-gray-300">
                                {rows.length === 0 ? (
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell colSpan={10} className="py-16 text-center">
                                            <div className="flex flex-col items-center gap-3">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-stone-100 dark:bg-zinc-800">
                                                    <Inbox className="h-5 w-5 text-gray-400 dark:text-gray-500" />
                                                </div>
                                                <div className="space-y-1">
                                                    <p className="font-mono text-[12px] font-medium text-gray-600 dark:text-gray-400">No results found</p>
                                                    <p className="font-mono text-[11px] text-gray-400 dark:text-gray-600">Try adjusting your search or filters</p>
                                                </div>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    rows.map((row) => (
                                        <TableRow key={row.id} className="border-b border-black/6 transition-colors hover:bg-emerald-500/[0.03] dark:border-white/6 dark:hover:bg-emerald-500/8">
                                            <TableCell className="whitespace-nowrap px-4 py-3 text-[12px] text-gray-700 dark:text-gray-200" style={{ fontFamily: monoFont }}>{formatIssueDate(row.issue_date)}</TableCell>
                                            <TableCell className="whitespace-nowrap px-4 py-3 text-[12px] text-gray-800 dark:text-gray-100" style={{ fontFamily: monoFont }}>{row.delivery_no || '-'}</TableCell>
                                            <TableCell className="whitespace-nowrap px-4 py-3 text-[12px] text-gray-500 dark:text-gray-400" style={{ fontFamily: monoFont }}>{row.cust_po_no || '-'}</TableCell>
                                            <TableCell className="whitespace-nowrap px-4 py-3 text-[12px] text-gray-500 dark:text-gray-400" style={{ fontFamily: monoFont }}>{row.control_no || '-'}</TableCell>
                                            <TableCell className="whitespace-nowrap px-4 py-3 text-[12px] font-semibold text-gray-800 dark:text-gray-100">{row.item}</TableCell>
                                            <TableCell className="whitespace-nowrap px-4 py-3 text-[12px] text-gray-700 dark:text-gray-200" style={{ fontFamily: monoFont }}>{formatMoney(row.cog_amount)}</TableCell>
                                            <TableCell className="whitespace-nowrap px-4 py-3 text-[12px] text-gray-700 dark:text-gray-200" style={{ fontFamily: monoFont }}>{formatMoney(row.delivery_fee)}</TableCell>
                                            <TableCell className="whitespace-nowrap px-4 py-3 text-[12px] text-gray-800 dark:text-gray-100" style={{ fontFamily: monoFont }}>{formatMoney(row.total_amount)}</TableCell>
                                            <TableCell className="whitespace-nowrap px-4 py-3">
                                                <div className="inline-flex h-7 min-w-[170px] items-center rounded-2xl pl-1.5 pr-1">
                                                    <span className={`inline-flex w-full items-center gap-1 rounded-2xl px-2 py-1 text-[11px] font-medium ${statusBadgeClass(row.status)}`}>
                                                        <span className="h-1.5 w-1.5 rounded-full bg-current/60" />
                                                        <span>{statusLabel(row.status)}</span>
                                                    </span>

                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <button
                                                                type="button"
                                                                className="ml-1 inline-flex h-6 w-5 items-center justify-center rounded-md text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                                                aria-label={`Open status dropdown for ${row.delivery_no || row.item}`}
                                                            >
                                                                <ChevronDown className="h-3.5 w-3.5" />
                                                            </button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end" className="w-[150px] p-1.5">
                                                            {statusOptions.map((opt) => (
                                                                <DropdownMenuItem
                                                                    key={opt.value}
                                                                    className={statusOptionTextClass(opt.value)}
                                                                    onClick={() => onUpdateStatus(row.id, opt.value)}
                                                                >
                                                                    {opt.label}
                                                                </DropdownMenuItem>
                                                            ))}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap px-4 py-3">
                                                <div className="inline-flex items-center text-[11px]">
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center px-1 text-gray-400 transition-colors hover:text-sky-600 dark:text-gray-500 dark:hover:text-sky-400"
                                                        onClick={() => onOpenEdit(row)}
                                                    >
                                                        <Pencil className="h-3.5 w-3.5" />
                                                    </button>
                                                    <span className="mx-1 h-3.5 w-px bg-black/10 dark:bg-white/10" />
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center px-1 text-gray-400 transition-colors hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                                                        onClick={() => onOpenDelete(row)}
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                        {footerSlot && (
                            <div className="border-t border-black/6 bg-white px-4 py-3 text-[12px] text-gray-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300">
                                {footerSlot}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
