import PageHeader from '@/components/common/PageHeader';
import { FinanceDeleteDialog } from '@/components/finance/delete-dialog';
import { ImportTransactionsDialog } from '@/components/finance/import-transactions-dialog';
import { FinanceTransaction, TransactionFormDialog } from '@/components/finance/transaction-form-dialog';
import { Checkbox } from '@/components/ui/checkbox';
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
import { ColumnDef, RowSelectionState } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import { MoreHorizontal, Pencil, Search, Trash2, Upload, X } from 'lucide-react';
import { SUB_CATEGORIES, SUB_CATEGORY_LABEL, SubCategory } from '@/components/finance/sub-category';
import { TRANSACTION_TYPES, TRANSACTION_TYPE_LABEL, TRANSACTION_TYPE_STYLE, TransactionType } from '@/components/finance/transaction-type';
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
    query?: {
        sort?: string | null;
        filter?: {
            search?: string;
            type?: 'in' | 'out';
            account_id?: string | number;
            transaction_type?: string;
            sub_category?: string;
            missing_type?: string | boolean;
            expenses_missing_sub?: string | boolean;
        };
    };
}

const fmt = (v: number | string) => Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });


export default function TransactionsIndex({ workspace, transactions, accounts, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);
    const [createOpen, setCreateOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [editing, setEditing] = useState<FinanceTransaction | null>(null);
    const [toDelete, setToDelete] = useState<Row | null>(null);
    const [search, setSearch] = useState(query?.filter?.search ?? '');
    const [typeFilter, setTypeFilter] = useState<'' | 'in' | 'out'>(query?.filter?.type ?? '');
    const [accountFilter, setAccountFilter] = useState<string>(query?.filter?.account_id != null ? String(query.filter.account_id) : '');
    const [txnTypeFilter, setTxnTypeFilter] = useState<string>(query?.filter?.transaction_type ?? '');
    const [subCategoryFilter, setSubCategoryFilter] = useState<string>(query?.filter?.sub_category ?? '');
    const boolish = (v: string | boolean | undefined) => v === true || v === '1' || v === 'true';
    const [missingType, setMissingType] = useState<boolean>(boolish(query?.filter?.missing_type));
    const [expensesMissingSub, setExpensesMissingSub] = useState<boolean>(boolish(query?.filter?.expenses_missing_sub));
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [bulkType, setBulkType] = useState<TransactionType | ''>('');
    const [bulkSubCategory, setBulkSubCategory] = useState<SubCategory | ''>('');
    const [bulkProcessing, setBulkProcessing] = useState(false);

    const baseUrl = `/workspaces/${workspace.slug}/finance/transactions`;

    const selectedIds = useMemo(() => Object.keys(rowSelection).filter((id) => rowSelection[id]), [rowSelection]);
    const selectedCount = selectedIds.length;

    useEffect(() => { setRowSelection({}); }, [transactions.data]);

    const applyBulkType = () => {
        if (!selectedCount) return;
        setBulkProcessing(true);
        router.put(
            `${baseUrl}/bulk-update-type`,
            { ids: selectedIds.map(Number), transaction_type: bulkType === '' ? null : bulkType },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setBulkProcessing(false),
                onSuccess: () => { setRowSelection({}); setBulkType(''); },
            },
        );
    };

    const applyBulkSubCategory = () => {
        if (!selectedCount) return;
        setBulkProcessing(true);
        router.put(
            `${baseUrl}/bulk-update-sub-category`,
            { ids: selectedIds.map(Number), sub_category: bulkSubCategory === '' ? null : bulkSubCategory },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setBulkProcessing(false),
                onSuccess: () => { setRowSelection({}); setBulkSubCategory(''); },
            },
        );
    };

    const performQuery = useCallback(
        debounce((s: string, t: '' | 'in' | 'out', a: string, tt: string, sc: string, mt: boolean, ems: boolean) => {
            router.get(baseUrl, {
                sort: query?.sort,
                'filter[search]': s || undefined,
                'filter[type]': t || undefined,
                'filter[account_id]': a || undefined,
                'filter[transaction_type]': tt || undefined,
                'filter[sub_category]': sc || undefined,
                'filter[missing_type]': mt ? 1 : undefined,
                'filter[expenses_missing_sub]': ems ? 1 : undefined,
                page: 1,
            }, { preserveState: true, replace: true, preserveScroll: true, only: ['transactions'] });
        }, 400),
        [baseUrl, query?.sort]
    );

    useEffect(() => { performQuery(search, typeFilter, accountFilter, txnTypeFilter, subCategoryFilter, missingType, expensesMissingSub); return () => performQuery.cancel(); }, [search, typeFilter, accountFilter, txnTypeFilter, subCategoryFilter, missingType, expensesMissingSub, performQuery]);

    const columns: ColumnDef<Row>[] = [
        {
            id: 'select',
            enableSorting: false,
            header: ({ table }) => (
                <div className="flex h-5 items-center justify-center">
                    <Checkbox
                        checked={
                            table.getRowModel().rows.length > 0 &&
                            table.getRowModel().rows.every((r) => r.getIsSelected())
                        }
                        onCheckedChange={(value) => {
                            const next: RowSelectionState = { ...rowSelection };
                            table.getRowModel().rows.forEach((r) => { next[r.id] = !!value; });
                            setRowSelection(next);
                        }}
                        aria-label="Select all"
                    />
                </div>
            ),
            cell: ({ row }) => (
                <div className="flex h-5 items-center justify-center">
                    <Checkbox
                        checked={row.getIsSelected()}
                        onCheckedChange={(value) => row.toggleSelected(!!value)}
                        aria-label="Select row"
                    />
                </div>
            ),
        },
        {
            accessorKey: 'date', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Date" />,
            cell: ({ row }) => <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">{row.original.date}</span>,
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
            accessorKey: 'transaction_type', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Txn Type" className="justify-center" />,
            cell: ({ row }) => {
                if (!row.original.transaction_type) return <div className="text-center font-mono text-[10px] text-gray-300">—</div>;
                const key = row.original.transaction_type as TransactionType;
                const s = TRANSACTION_TYPE_STYLE[key] ?? TRANSACTION_TYPE_STYLE.funds;
                const label = TRANSACTION_TYPE_LABEL[key] ?? key;
                return (
                    <div className="text-center">
                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[10px] uppercase ${s.cls}`}>{label}</span>
                    </div>
                );
            },
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
            id: 'running_balance',
            header: () => <div className="text-right font-mono text-[10px] uppercase tracking-wider text-gray-300">Balance</div>,
            cell: ({ row }) => {
                const bal = Number(row.original.running_balance ?? 0);
                return (
                    <div className={`text-right font-mono text-[12px] font-semibold ${bal >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-500'}`}>
                        {fmt(bal)}
                    </div>
                );
            },
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

                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100"
                            placeholder="Search by description..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <select
                        value={accountFilter}
                        onChange={(e) => setAccountFilter(e.target.value)}
                        className="h-9 rounded-[10px] border border-black/6 bg-stone-100 px-2.5 font-mono! text-[11px]! text-gray-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                    >
                        <option value="">All accounts</option>
                        {accounts.map((a) => (
                            <option key={a.id} value={a.id}>{a.name} ({a.currency})</option>
                        ))}
                    </select>
                    <select
                        value={txnTypeFilter}
                        onChange={(e) => setTxnTypeFilter(e.target.value)}
                        className="h-9 rounded-[10px] border border-black/6 bg-stone-100 px-2.5 font-mono! text-[11px]! text-gray-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                    >
                        <option value="">All txn types</option>
                        {TRANSACTION_TYPES.map((t) => (
                            <option key={t.value} value={t.value}>{t.label}</option>
                        ))}
                    </select>
                    <select
                        value={subCategoryFilter}
                        onChange={(e) => setSubCategoryFilter(e.target.value)}
                        className="h-9 rounded-[10px] border border-black/6 bg-stone-100 px-2.5 font-mono! text-[11px]! text-gray-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                    >
                        <option value="">All sub categories</option>
                        {SUB_CATEGORIES.map((s) => (
                            <option key={s.value} value={s.value}>{s.label}</option>
                        ))}
                    </select>
                    <div className="inline-flex h-9 overflow-hidden rounded-[10px] border border-black/6 bg-stone-100 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800">
                        {([
                            { value: '', label: 'All' },
                            { value: 'in', label: 'Credit' },
                            { value: 'out', label: 'Debit' },
                        ] as const).map((opt) => (
                            <button
                                key={opt.value || 'all'}
                                onClick={() => setTypeFilter(opt.value)}
                                className={`px-3 transition-colors ${
                                    typeFilter === opt.value
                                        ? (opt.value === 'in'
                                            ? 'bg-emerald-600 text-white'
                                            : opt.value === 'out'
                                                ? 'bg-red-500 text-white'
                                                : 'bg-gray-700 text-white dark:bg-gray-200 dark:text-gray-900')
                                        : 'text-gray-600 hover:bg-stone-200 dark:text-gray-300 dark:hover:bg-zinc-700'
                                }`}
                            >
                                {opt.label}
                            </button>
                        ))}
                    </div>
                    <button
                        onClick={() => setMissingType((v) => !v)}
                        className={`h-9 rounded-[10px] border px-3 font-mono! text-[11px]! transition-colors ${
                            missingType
                                ? 'border-amber-500 bg-amber-500 text-white'
                                : 'border-black/6 bg-stone-100 text-gray-600 hover:bg-stone-200 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700'
                        }`}
                    >
                        No Txn Type
                    </button>
                    <button
                        onClick={() => setExpensesMissingSub((v) => !v)}
                        className={`h-9 rounded-[10px] border px-3 font-mono! text-[11px]! transition-colors ${
                            expensesMissingSub
                                ? 'border-amber-500 bg-amber-500 text-white'
                                : 'border-black/6 bg-stone-100 text-gray-600 hover:bg-stone-200 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700'
                        }`}
                    >
                        Expenses w/o Sub Cat
                    </button>
                </div>

                {selectedCount > 0 && (
                    <div className="mb-3 flex flex-wrap items-center gap-2 rounded-[10px] border border-emerald-200 bg-emerald-50/60 px-3 py-2 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                        <span className="font-mono text-[11px] text-emerald-700 dark:text-emerald-300">
                            {selectedCount} selected
                        </span>
                        <span className="h-4 w-px bg-emerald-200 dark:bg-emerald-500/30" />
                        <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">Txn type:</span>
                        <select
                            value={bulkType}
                            onChange={(e) => setBulkType(e.target.value as typeof bulkType)}
                            className="h-8 rounded-lg border border-black/8 bg-white px-2 font-mono! text-[11px]! text-gray-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                        >
                            <option value="">— clear —</option>
                            {TRANSACTION_TYPES.map((t) => (
                                <option key={t.value} value={t.value}>{t.label}</option>
                            ))}
                        </select>
                        <button
                            onClick={applyBulkType}
                            disabled={bulkProcessing}
                            className="flex h-8 items-center rounded-lg bg-emerald-600 px-3 font-mono! text-[11px]! font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {bulkProcessing ? 'Applying…' : 'Apply'}
                        </button>
                        <span className="h-4 w-px bg-emerald-200 dark:bg-emerald-500/30" />
                        <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">Sub category:</span>
                        <select
                            value={bulkSubCategory}
                            onChange={(e) => setBulkSubCategory(e.target.value as SubCategory | '')}
                            className="h-8 rounded-lg border border-black/8 bg-white px-2 font-mono! text-[11px]! text-gray-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                        >
                            <option value="">— clear —</option>
                            {SUB_CATEGORIES.map((s) => (
                                <option key={s.value} value={s.value}>{s.label}</option>
                            ))}
                        </select>
                        <button
                            onClick={applyBulkSubCategory}
                            disabled={bulkProcessing}
                            className="flex h-8 items-center rounded-lg bg-emerald-600 px-3 font-mono! text-[11px]! font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {bulkProcessing ? 'Applying…' : 'Apply'}
                        </button>
                        <button
                            onClick={() => setRowSelection({})}
                            className="ml-auto flex h-8 items-center gap-1 rounded-lg border border-black/8 bg-white px-2.5 font-mono! text-[11px]! text-gray-600 hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            <X className="h-3 w-3" /> Clear
                        </button>
                    </div>
                )}

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={transactions.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(transactions, ['data']) }}
                        rowSelection={rowSelection}
                        onRowSelectionChange={setRowSelection}
                        getRowId={(row) => String(row.id)}
                        onFetch={(params) => {
                            router.get(baseUrl,
                                {
                                    sort: params?.sort,
                                    'filter[search]': search || undefined,
                                    'filter[type]': typeFilter || undefined,
                                    'filter[account_id]': accountFilter || undefined,
                                    'filter[transaction_type]': txnTypeFilter || undefined,
                                    'filter[sub_category]': subCategoryFilter || undefined,
                                    'filter[missing_type]': missingType ? 1 : undefined,
                                    'filter[expenses_missing_sub]': expensesMissingSub ? 1 : undefined,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page ?? undefined,
                                },
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
