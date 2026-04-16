import PageHeader from '@/components/common/PageHeader';
import { FinanceDeleteDialog } from '@/components/finance/delete-dialog';
import { ImportTransactionsDialog } from '@/components/finance/import-transactions-dialog';
import { FinanceTransaction, TransactionFormDialog } from '@/components/finance/transaction-form-dialog';
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
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import { MoreHorizontal, Pencil, Search, Trash2, Upload } from 'lucide-react';
import { SUB_CATEGORY_LABEL, SubCategory } from '@/components/finance/sub-category';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface Row extends FinanceTransaction {
    account: { id: number; name: string; currency: string } | null;
    remittance: { id: number; courier: string; soa_number: string } | null;
}

interface AccountOpt { id: number; name: string; currency: string }

interface Props {
    workspace: Workspace;
    transactions: PaginatedData<Row>;
    accounts: AccountOpt[];
    query?: { sort?: string | null; filter?: { search?: string } };
}

const fmt = (v: number | string) => Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const TXN_TYPE_STYLE: Record<string, { label: string; cls: string }> = {
    funds: { label: 'funds', cls: 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400' },
    profit_share: { label: 'profit share', cls: 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400' },
    expenses: { label: 'expenses', cls: 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400' },
    transfer: { label: 'transfer', cls: 'bg-slate-100 text-slate-600 dark:bg-slate-500/10 dark:text-slate-300' },
    remittance: { label: 'remittance', cls: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' },
};

export default function TransactionsIndex({ workspace, transactions, accounts, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);
    const [createOpen, setCreateOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [editing, setEditing] = useState<FinanceTransaction | null>(null);
    const [toDelete, setToDelete] = useState<Row | null>(null);
    const [search, setSearch] = useState(query?.filter?.search ?? '');

    const baseUrl = `/workspaces/${workspace.slug}/finance/transactions`;

    const performQuery = useCallback(
        debounce((s: string) => {
            router.get(baseUrl, { sort: query?.sort, 'filter[search]': s || undefined, page: 1 },
                { preserveState: true, replace: true, preserveScroll: true, only: ['transactions'] });
        }, 400),
        [baseUrl, query?.sort]
    );

    useEffect(() => { performQuery(search); return () => performQuery.cancel(); }, [search, performQuery]);

    const columns: ColumnDef<Row>[] = [
        {
            accessorKey: 'date', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Date" />,
            cell: ({ row }) => <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">{String(row.original.date).slice(0, 10)}</span>,
        },
        {
            id: 'account', header: () => <div className="font-mono text-[10px] uppercase tracking-wider text-gray-300">Account</div>,
            cell: ({ row }) => <span className="text-[12px] text-gray-700 dark:text-gray-200">{row.original.account?.name ?? '—'}</span>,
        },
        {
            accessorKey: 'description', enableSorting: false,
            header: () => <div className="font-mono text-[10px] uppercase tracking-wider text-gray-300">Description</div>,
            cell: ({ row }) => (
                <div className="flex max-w-[320px] flex-col gap-0.5">
                    <span className="truncate text-[12px] text-gray-800 dark:text-gray-100" title={row.original.description}>{row.original.description}</span>
                    {row.original.remittance && (
                        <span className="truncate text-[10px] text-gray-400">SOA {row.original.remittance.soa_number} · {row.original.remittance.courier}</span>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'sub_category', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Sub Category" className="justify-center" />,
            cell: ({ row }) => (
                <div className="text-center">
                    {row.original.sub_category
                        ? <span className="inline-flex items-center rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] uppercase text-gray-500 dark:bg-zinc-800 dark:text-gray-400">{SUB_CATEGORY_LABEL[row.original.sub_category]}</span>
                        : <span className="font-mono text-[10px] text-gray-300">—</span>}
                </div>
            ),
        },
        {
            accessorKey: 'transaction_type', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Txn Type" className="justify-center" />,
            cell: ({ row }) => {
                if (!row.original.transaction_type) return <div className="text-center font-mono text-[10px] text-gray-300">—</div>;
                const s = TXN_TYPE_STYLE[row.original.transaction_type] ?? TXN_TYPE_STYLE.funds;
                return (
                    <div className="text-center">
                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[10px] uppercase ${s.cls}`}>{s.label}</span>
                    </div>
                );
            },
        },
        {
            id: 'credit',
            header: () => <div className="text-right font-mono text-[10px] uppercase tracking-wider text-gray-300">Credit</div>,
            cell: ({ row }) => (
                <div className="text-right font-mono text-[12px] text-emerald-600 dark:text-emerald-400">
                    {row.original.type === 'in' ? fmt(row.original.amount) : ''}
                </div>
            ),
        },
        {
            id: 'debit',
            header: () => <div className="text-right font-mono text-[10px] uppercase tracking-wider text-gray-300">Debit</div>,
            cell: ({ row }) => (
                <div className="text-right font-mono text-[12px] text-red-500 dark:text-red-400">
                    {row.original.type === 'out' ? fmt(row.original.amount) : ''}
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
                            <DropdownMenuItem onClick={() => setEditing({
                                id: row.original.id, account_id: row.original.account_id,
                                date: String(row.original.date).slice(0, 10),
                                description: row.original.description, type: row.original.type,
                                transaction_type: row.original.transaction_type,
                                amount: row.original.amount, sub_category: row.original.sub_category,
                                notes: row.original.notes,
                            })}>
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

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Finance Transactions`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Transactions" description="Ledger entries across all accounts.">
                    <button
                        onClick={() => setImportOpen(true)}
                        className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                    >
                        <Upload className="h-3.5 w-3.5" /> Import CSV
                    </button>
                    <button
                        onClick={() => setCreateOpen(true)}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white hover:bg-emerald-700"
                    >
                        Add Transaction
                    </button>
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100"
                            placeholder="Search by description..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={transactions.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(transactions, ['data']) }}
                        onFetch={(params) => {
                            router.get(baseUrl,
                                { sort: params?.sort, 'filter[search]': search || undefined, page: params?.page ?? 1 },
                                { preserveState: true, replace: true, preserveScroll: true });
                        }}
                    />
                </div>

                <TransactionFormDialog
                    open={createOpen || editing !== null}
                    onOpenChange={(o) => { if (!o) { setCreateOpen(false); setEditing(null); } }}
                    transaction={editing}
                    accounts={accounts}
                    workspaceSlug={workspace.slug}
                />
                <ImportTransactionsDialog
                    open={importOpen}
                    onOpenChange={setImportOpen}
                    workspaceSlug={workspace.slug}
                    accounts={accounts}
                />
                <FinanceDeleteDialog
                    open={!!toDelete}
                    onClose={() => setToDelete(null)}
                    title="Delete Transaction?"
                    description="Remove this ledger entry?"
                    url={toDelete ? `${baseUrl}/${toDelete.id}` : ''}
                    successMessage="Transaction deleted"
                />
            </div>
        </AppLayout>
    );
}
