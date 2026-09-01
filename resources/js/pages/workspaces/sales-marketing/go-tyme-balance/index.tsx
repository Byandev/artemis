import PageHeader from '@/components/common/PageHeader';
import { AccountFormDialog } from '@/components/finance/account-form-dialog';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useState } from 'react';

interface WalletAccount {
    id: number;
    name: string;
    opening_balance: number;
    current_balance: number;
    currency: string;
    notes: string | null;
    is_active: boolean;
}

interface Props {
    workspace: Workspace;
    accounts: WalletAccount[];
    canManage: boolean;
}

const fmt = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

/**
 * Go Tyme Balance — the per-user wallets. These are ordinary Finance accounts
 * under the hood, flagged `is_user_wallet` when created here so they stay out
 * of Finance → Accounts.
 */
export default function GoTymeBalanceIndex({
    workspace,
    accounts,
    canManage,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);

    const columns: ColumnDef<WalletAccount>[] = [
        {
            accessorKey: 'name',
            header: () => (
                <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Name
                </div>
            ),
            cell: ({ row }) => (
                <span className="text-[13px] font-medium text-gray-800 dark:text-gray-100">
                    {row.original.name}
                </span>
            ),
        },
        {
            accessorKey: 'currency',
            header: () => (
                <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Currency
                </div>
            ),
            cell: ({ row }) => (
                <div className="text-center font-mono text-[11px] text-gray-500 uppercase">
                    {row.original.currency}
                </div>
            ),
        },
        {
            id: 'current_balance',
            header: () => (
                <div className="text-right font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Current Balance
                </div>
            ),
            cell: ({ row }) => {
                const bal = Number(row.original.current_balance ?? 0);
                return (
                    <div
                        className={`text-right font-mono text-[12px] font-medium ${bal >= 0 ? 'text-gray-700 dark:text-gray-200' : 'text-red-500'}`}
                    >
                        {fmt(bal)}
                    </div>
                );
            },
        },
        {
            accessorKey: 'is_active',
            header: () => (
                <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Status
                </div>
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    <span
                        className={`inline-flex items-center rounded-full px-2.5 py-1 font-mono text-[11px] font-medium ${row.original.is_active ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400'}`}
                    >
                        {row.original.is_active ? 'active' : 'inactive'}
                    </span>
                </div>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Go Tyme Balance`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Go Tyme Balance">
                    {canManage && (
                        <button
                            onClick={() => setCreateOpen(true)}
                            className="flex h-8 items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            Create Account
                        </button>
                    )}
                </PageHeader>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable columns={columns} data={accounts} />
                </div>
            </div>

            <AccountFormDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                workspaceSlug={workspace.slug}
                endpoint={`/workspaces/${workspace.slug}/sales-marketing/go-tyme-balance`}
                title="Create Account"
                description="Creates a Go Tyme wallet. It is kept out of Finance → Accounts."
            />
        </AppLayout>
    );
}
