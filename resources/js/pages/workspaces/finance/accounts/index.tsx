import PageHeader from '@/components/common/PageHeader';
import { AccountFormDialog, FinanceAccount } from '@/components/finance/account-form-dialog';
import { FinanceDeleteDialog } from '@/components/finance/delete-dialog';
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
import { ExternalLink, MoreHorizontal, Pencil, Search, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface Account extends FinanceAccount {
    transactions?: { id: number; account_id: number; type: 'in' | 'out'; amount: number | string }[];
}

const balanceOf = (a: Account) => {
    const ts = a.transactions ?? [];
    const inSum = ts.filter(t => t.type === 'in').reduce((s, t) => s + Number(t.amount), 0);
    const outSum = ts.filter(t => t.type === 'out').reduce((s, t) => s + Number(t.amount), 0);
    return Number(a.opening_balance) + inSum - outSum;
};

interface Props {
    workspace: Workspace;
    accounts: PaginatedData<Account>;
    query?: { sort?: string | null; filter?: { search?: string } };
}

const fmt = (v: number | string) =>
    Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function AccountsIndex({ workspace, accounts, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<Account | null>(null);
    const [toDelete, setToDelete] = useState<Account | null>(null);
    const [search, setSearch] = useState(query?.filter?.search ?? '');

    const baseUrl = `/workspaces/${workspace.slug}/finance/accounts`;

    const performQuery = useCallback(
        debounce((s: string) => {
            router.get(baseUrl, { sort: query?.sort, 'filter[search]': s || undefined, page: 1 },
                { preserveState: true, replace: true, preserveScroll: true, only: ['accounts'] });
        }, 400),
        [baseUrl, query?.sort]
    );

    useEffect(() => { performQuery(search); return () => performQuery.cancel(); }, [search, performQuery]);

    const columns: ColumnDef<Account>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Name" />,
            cell: ({ row }) => (
                <Link href={`${baseUrl}/${row.original.id}`} className="text-[13px] font-medium text-gray-800 hover:text-emerald-600 dark:text-gray-100">
                    {row.original.name}
                </Link>
            ),
        },
        {
            accessorKey: 'currency',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Currency" className="justify-center" />,
            cell: ({ row }) => <div className="text-center font-mono text-[11px] uppercase text-gray-500">{row.original.currency}</div>,
        },
        {
            id: 'current_balance',
            header: () => <div className="text-right font-mono text-[10px] uppercase tracking-wider text-gray-300">Current Balance</div>,
            cell: ({ row }) => (
                <div className="text-right font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">
                    {fmt(balanceOf(row.original))}
                </div>
            ),
        },
        {
            accessorKey: 'is_active',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Status" className="justify-center" />,
            cell: ({ row }) => (
                <div className="text-center">
                    <span className={`inline-flex items-center rounded-full px-2.5 py-1 font-mono text-[11px] font-medium ${row.original.is_active ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400'}`}>
                        {row.original.is_active ? 'active' : 'inactive'}
                    </span>
                </div>
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Actions</div>,
            cell: ({ row }) => (
                <div className="flex justify-center">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500">
                                <MoreHorizontal className="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-40">
                            <DropdownMenuItem asChild>
                                <Link href={`${baseUrl}/${row.original.id}`}>
                                    <ExternalLink className="mr-2 h-3.5 w-3.5" /> View Ledger
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => setEditing(row.original)}>
                                <Pencil className="mr-2 h-3.5 w-3.5" /> Edit
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="text-red-600 focus:text-red-600 dark:text-red-400" onClick={() => setToDelete(row.original)}>
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
            <Head title={`${workspace.name} - Finance Accounts`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Accounts" description="Manage finance accounts and view current balances.">
                    <button
                        onClick={() => setCreateOpen(true)}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        Add Account
                    </button>
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 outline-none transition-all placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100"
                            placeholder="Search accounts..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={accounts.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(accounts, ['data']) }}
                        onFetch={(params) => {
                            router.get(baseUrl,
                                { sort: params?.sort, 'filter[search]': search || undefined, page: params?.page ?? 1 },
                                { preserveState: true, replace: true, preserveScroll: true });
                        }}
                    />
                </div>

                <AccountFormDialog
                    open={createOpen || editing !== null}
                    onOpenChange={(o) => { if (!o) { setCreateOpen(false); setEditing(null); } }}
                    account={editing}
                    workspaceSlug={workspace.slug}
                />
                <FinanceDeleteDialog
                    open={!!toDelete}
                    onClose={() => setToDelete(null)}
                    title="Delete Account?"
                    description={`Delete "${toDelete?.name}" and all its transactions? This cannot be undone.`}
                    url={toDelete ? `${baseUrl}/${toDelete.id}` : ''}
                    successMessage="Account deleted"
                />
            </div>
        </AppLayout>
    );
}
