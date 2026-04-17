import PageHeader from '@/components/common/PageHeader';
import { FinanceDeleteDialog } from '@/components/finance/delete-dialog';
import { FinanceRemittance, RemittanceFormDialog } from '@/components/finance/remittance-form-dialog';
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
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import { AlertTriangle, ExternalLink, MoreHorizontal, Pencil, Search, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface TxnOpt {
    id: number;
    account_id: number;
    date: string;
    description: string;
    amount: number | string;
    type: 'in' | 'out';
    account?: { id: number; name: string } | null;
}

interface Row extends FinanceRemittance {
    is_reconciled: boolean;
    transaction?: { id: number; account?: { id: number; name: string } | null } | null;
}

interface Props {
    workspace: Workspace;
    remittances: PaginatedData<Row>;
    unreconciledCount: number;
    transactions: TxnOpt[];
    query?: { sort?: string | null; filter?: { search?: string; status?: string; unreconciled?: string } };
}

const fmt = (v: number | string) => Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function RemittancesIndex({ workspace, remittances, unreconciledCount, transactions, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<FinanceRemittance | null>(null);
    const [toDelete, setToDelete] = useState<Row | null>(null);
    const [search, setSearch] = useState(query?.filter?.search ?? '');
    const unreconciled = query?.filter?.unreconciled === '1' || query?.filter?.unreconciled === 'true';

    const baseUrl = `/workspaces/${workspace.slug}/finance/remittances`;

    const performQuery = useCallback(
        debounce((s: string) => {
            router.get(baseUrl,
                { sort: query?.sort, 'filter[search]': s || undefined, 'filter[unreconciled]': unreconciled ? 1 : undefined, page: 1 },
                { preserveState: true, replace: true, preserveScroll: true, only: ['remittances', 'unreconciledCount'] });
        }, 400),
        [baseUrl, query?.sort, unreconciled]
    );

    useEffect(() => { performQuery(search); return () => performQuery.cancel(); }, [search, performQuery]);

    const columns: ColumnDef<Row>[] = [
        {
            accessorKey: 'soa_number', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="SOA No." />,
            cell: ({ row }) => (
                <Link href={`${baseUrl}/${row.original.id}`} className="font-mono text-[12px] font-medium text-gray-800 hover:text-emerald-600 dark:text-gray-100">
                    {row.original.soa_number}
                </Link>
            ),
        },
        {
            id: 'billing_date',
            header: () => <div className="font-mono text-[10px] uppercase tracking-wider text-gray-300">Billing Date</div>,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {String(row.original.billing_date_from).slice(0, 10)} → {String(row.original.billing_date_to).slice(0, 10)}
                </span>
            ),
        },
        {
            accessorKey: 'gross_cod', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Gross COD" className="justify-end" />,
            cell: ({ row }) => <div className="text-right font-mono text-[12px] text-gray-700 dark:text-gray-200">{fmt(row.original.gross_cod)}</div>,
        },
        {
            id: 'cod_fee',
            header: () => <div className="text-right font-mono text-[10px] uppercase tracking-wider text-gray-300">COD Fee</div>,
            cell: ({ row }) => <div className="text-right font-mono text-[12px] text-gray-500">{fmt(row.original.cod_fee)}</div>,
        },
        {
            id: 'shipping_fee',
            header: () => <div className="text-right font-mono text-[10px] uppercase tracking-wider text-gray-300">Shipping</div>,
            cell: ({ row }) => <div className="text-right font-mono text-[12px] text-gray-500">{fmt(row.original.shipping_fee)}</div>,
        },
        {
            id: 'return_shipping',
            header: () => <div className="text-right font-mono text-[10px] uppercase tracking-wider text-gray-300">Return Ship</div>,
            cell: ({ row }) => <div className="text-right font-mono text-[12px] text-gray-500">{fmt(row.original.return_shipping)}</div>,
        },
        {
            accessorKey: 'net_amount', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Net Amount" className="justify-end" />,
            cell: ({ row }) => <div className="text-right font-mono text-[12px] font-medium text-gray-800 dark:text-gray-100">{fmt(row.original.net_amount)}</div>,
        },
        {
            accessorKey: 'status', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Status" className="justify-center" />,
            cell: ({ row }) => (
                <div className="text-center">
                    <span className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[10px] uppercase ${row.original.status === 'remitted' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400'}`}>
                        {row.original.status}
                    </span>
                </div>
            ),
        },
        {
            id: 'transaction',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-300">Transaction</div>,
            cell: ({ row }) => (
                <div className="text-center">
                    {row.original.is_reconciled ? (
                        <span className="font-mono text-[11px] text-emerald-600 dark:text-emerald-400">#{row.original.transaction_id}</span>
                    ) : (
                        <span className="font-mono text-[11px] text-red-500 dark:text-red-400">unlinked</span>
                    )}
                </div>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-300">Actions</div>,
            cell: ({ row }) => (
                <div className="flex justify-center">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800">
                                <MoreHorizontal className="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-36">
                            <DropdownMenuItem asChild>
                                <Link href={`${baseUrl}/${row.original.id}`}>
                                    <ExternalLink className="mr-2 h-3.5 w-3.5" /> View
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => setEditing(row.original)}>
                                <Pencil className="mr-2 h-3.5 w-3.5" /> Edit
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="text-red-600 focus:text-red-600" onClick={() => setToDelete(row.original)}>
                                <Trash2 className="mr-2 h-3.5 w-3.5" /> Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ),
        },
    ];

    const toggleUnreconciled = () => {
        router.get(baseUrl,
            { 'filter[search]': search || undefined, 'filter[unreconciled]': unreconciled ? undefined : 1, page: 1 },
            { preserveState: true, replace: true, preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Finance Remittances`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Remittances" description="Courier SOA (Statement of Account) records.">
                    <button
                        onClick={() => setCreateOpen(true)}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white hover:bg-emerald-700"
                    >
                        Add Remittance
                    </button>
                </PageHeader>

                {unreconciledCount > 0 && (
                    <div className="mb-4 flex items-center gap-2 rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-2.5 text-[12px] text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/5 dark:text-amber-300">
                        <AlertTriangle className="h-3.5 w-3.5" />
                        {unreconciledCount} remittance(s) not yet linked to a transaction.
                    </div>
                )}

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100"
                            placeholder="Search SOA No or courier..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <button
                        onClick={toggleUnreconciled}
                        className={`h-9 rounded-[10px] border px-3 font-mono! text-[12px]! font-medium transition-all ${unreconciled ? 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300' : 'border-black/6 bg-white text-gray-600 hover:bg-stone-50 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300'}`}
                    >
                        {unreconciled ? 'Showing Unlinked' : 'Show Unlinked'}
                    </button>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={remittances.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(remittances, ['data']) }}
                        onFetch={(params) => {
                            router.get(baseUrl,
                                { sort: params?.sort, 'filter[search]': search || undefined, 'filter[unreconciled]': unreconciled ? 1 : undefined, page: params?.page ?? 1 },
                                { preserveState: true, replace: true, preserveScroll: true });
                        }}
                    />
                </div>

                <RemittanceFormDialog
                    open={createOpen || editing !== null}
                    onOpenChange={(o) => { if (!o) { setCreateOpen(false); setEditing(null); } }}
                    remittance={editing}
                    workspaceSlug={workspace.slug}
                    transactions={transactions}
                />
                <FinanceDeleteDialog
                    open={!!toDelete}
                    onClose={() => setToDelete(null)}
                    title="Delete Remittance?"
                    description={`Delete SOA ${toDelete?.soa_number ?? ''}?`}
                    url={toDelete ? `${baseUrl}/${toDelete.id}` : ''}
                    successMessage="Remittance deleted"
                />
            </div>
        </AppLayout>
    );
}
