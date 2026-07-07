import PageHeader from '@/components/common/PageHeader';
import { FinanceDeleteDialog } from '@/components/finance/delete-dialog';
import {
    SUB_CATEGORY_LABEL,
    SubCategory,
} from '@/components/finance/sub-category';
import {
    FinanceTransaction,
    TransactionFormDialog,
} from '@/components/finance/transaction-form-dialog';
import {
    TransactionType,
    TransactionTypeItem,
    transactionTypeLabel,
    transactionTypeStyle,
} from '@/components/finance/transaction-type';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Account {
    id: number;
    name: string;
    currency: string;
    opening_balance: number | string;
    notes: string | null;
    is_active: boolean;
}

interface Txn {
    id: number;
    account_id: number;
    date: string;
    description: string;
    requested_by?: string | null;
    approved_by?: string | null;
    department?: string | null;
    charge_to?: string | null;
    type: 'in' | 'out';
    transaction_type: TransactionType | null;
    transaction_type_id?: number | null;
    amount: number | string;
    running_balance: number | string | null;
    reference_no?: string | null;
    status?: 'pending' | 'approved' | 'posted' | null;
    sub_category: SubCategory | null;
    notes: string | null;
    remittance?: { id: number; courier: string; soa_number: string } | null;
}

interface Props {
    workspace: Workspace;
    account: Account;
    transactions: Txn[];
    transactionTypes: TransactionTypeItem[];
}

const fmt = (v: number | string) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function AccountShow({
    workspace,
    account,
    transactions,
    transactionTypes,
}: Props) {
    const typeNameById = useMemo(
        () => new Map(transactionTypes.map((t) => [t.id, t.name])),
        [transactionTypes],
    );
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<FinanceTransaction | null>(null);
    const [toDelete, setToDelete] = useState<Txn | null>(null);

    const base = `/workspaces/${workspace.slug}/finance`;
    const canCreateTransactions = usePermission(
        PERMISSIONS.CreateFinanceTransactions,
    );
    const canEditTransactions = usePermission(
        PERMISSIONS.EditFinanceTransactions,
    );
    const canDeleteTransactions = usePermission(
        PERMISSIONS.DeleteFinanceTransactions,
    );
    const showActions = canEditTransactions || canDeleteTransactions;
    const backHref =
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('from') ===
            'live-cashflow'
            ? `${base}/dashboard`
            : `${base}/accounts`;
    const backLabel = backHref.endsWith('/dashboard')
        ? 'Back to Live Cashflow'
        : 'Back to Accounts';

    // Server returns transactions sorted by date desc. For rows that don't have a
    // stored running_balance (manual entries), compute one chronologically.
    const rows = useMemo(() => {
        const asc = [...transactions].reverse();
        let running = Number(account.opening_balance);
        const computed = new Map<number, number>();
        for (const t of asc) {
            running += t.type === 'in' ? Number(t.amount) : -Number(t.amount);
            computed.set(t.id, running);
        }
        return transactions.map((t) => ({
            ...t,
            display_balance:
                t.running_balance != null
                    ? Number(t.running_balance)
                    : (computed.get(t.id) ?? running),
        }));
    }, [transactions, account.opening_balance]);

    const currentBalance = rows.length
        ? rows[0].display_balance
        : Number(account.opening_balance);

    return (
        <AppLayout>
            <Head title={`${workspace.name} - ${account.name}`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <Link
                    href={backHref}
                    className="mb-3 inline-flex items-center gap-1 text-[12px] text-gray-500 hover:text-gray-800 dark:text-gray-400"
                >
                    <ArrowLeft className="h-3.5 w-3.5" /> {backLabel}
                </Link>

                <PageHeader
                    title={`${account.name} (${account.currency})`}
                    description={`Opening: ${fmt(account.opening_balance)} · Current: ${fmt(currentBalance)}`}
                >
                    {canCreateTransactions && (
                        <button
                            onClick={() => setCreateOpen(true)}
                            className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            Add Transaction
                        </button>
                    )}
                </PageHeader>

                <div className="overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <table className="w-full text-[12px]">
                        <thead>
                            <tr className="border-b border-black/6 dark:border-white/6">
                                {[
                                    'Date',
                                    'Description',
                                    'Sub Category',
                                    'Credit',
                                    'Debit',
                                    'Balance',
                                    ...(showActions ? [''] : []),
                                ].map((h, i) => (
                                    <th
                                        key={i}
                                        className={`px-4 py-2.5 font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600 ${i >= 3 && i <= 5 ? 'text-right' : 'text-left'}`}
                                    >
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={showActions ? 7 : 6}
                                        className="px-4 py-10 text-center text-gray-400"
                                    >
                                        No transactions yet.
                                    </td>
                                </tr>
                            )}
                            {rows.map((r) => {
                                // Prefer the dynamic type (via FK); fall back to
                                // the legacy enum value for un-linked rows.
                                const typeName =
                                    r.transaction_type_id != null
                                        ? (typeNameById.get(
                                              r.transaction_type_id,
                                          ) ?? null)
                                        : r.transaction_type;
                                const s = transactionTypeStyle(typeName);
                                const label = transactionTypeLabel(typeName);
                                return (
                                    <tr
                                        key={r.id}
                                        className="border-b border-black/6 last:border-0 hover:bg-stone-50 dark:border-white/6 dark:hover:bg-white/2"
                                    >
                                        <td className="px-4 py-2.5 font-mono text-[11px] text-gray-600 dark:text-gray-400">
                                            {String(r.date).slice(0, 10)}
                                        </td>
                                        <td className="px-4 py-2.5 text-gray-800 dark:text-gray-200">
                                            <div
                                                className="max-w-[320px] truncate"
                                                title={r.description}
                                            >
                                                {r.description}
                                            </div>
                                            {r.remittance && (
                                                <Link
                                                    href={`${base}/remittances/${r.remittance.id}`}
                                                    className="block max-w-[320px] truncate text-[10px] text-gray-400 hover:text-emerald-600"
                                                >
                                                    SOA{' '}
                                                    {r.remittance.soa_number} ·{' '}
                                                    {r.remittance.courier}
                                                </Link>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <div className="flex flex-col gap-1">
                                                {r.sub_category && (
                                                    <span className="inline-flex w-fit items-center rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] text-gray-500 uppercase dark:bg-zinc-800 dark:text-gray-400">
                                                        {
                                                            SUB_CATEGORY_LABEL[
                                                                r.sub_category
                                                            ]
                                                        }
                                                    </span>
                                                )}
                                                {typeName && (
                                                    <span
                                                        className={`inline-flex w-fit items-center rounded-full px-2 py-0.5 font-mono text-[10px] uppercase ${s.cls}`}
                                                    >
                                                        {label}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-mono text-[12px] text-emerald-600 dark:text-emerald-400">
                                            {r.type === 'in'
                                                ? fmt(r.amount)
                                                : ''}
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-mono text-[12px] text-red-500 dark:text-red-400">
                                            {r.type === 'out'
                                                ? fmt(r.amount)
                                                : ''}
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">
                                            {fmt(r.display_balance)}
                                        </td>
                                        {showActions && (
                                            <td className="px-4 py-2.5">
                                                <div className="flex justify-center">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800">
                                                                <MoreHorizontal className="h-3.5 w-3.5" />
                                                            </button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent
                                                            align="end"
                                                            className="w-36"
                                                        >
                                                            {canEditTransactions && (
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        setEditing(
                                                                            {
                                                                                id: r.id,
                                                                                account_id:
                                                                                    account.id,
                                                                                date: String(
                                                                                    r.date,
                                                                                ).slice(
                                                                                    0,
                                                                                    10,
                                                                                ),
                                                                                description:
                                                                                    r.description,
                                                                                requested_by:
                                                                                    r.requested_by,
                                                                                approved_by:
                                                                                    r.approved_by,
                                                                                department:
                                                                                    r.department,
                                                                                charge_to:
                                                                                    r.charge_to,
                                                                                type: r.type,
                                                                                transaction_type:
                                                                                    r.transaction_type,
                                                                                transaction_type_id:
                                                                                    r.transaction_type_id,
                                                                                amount: r.amount,
                                                                                running_balance:
                                                                                    r.running_balance,
                                                                                reference_no:
                                                                                    r.reference_no,
                                                                                status: r.status,
                                                                                sub_category:
                                                                                    r.sub_category,
                                                                                notes: r.notes,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <Pencil className="mr-2 h-3.5 w-3.5" />{' '}
                                                                    Edit
                                                                </DropdownMenuItem>
                                                            )}
                                                            {canEditTransactions &&
                                                                canDeleteTransactions && (
                                                                    <DropdownMenuSeparator />
                                                                )}
                                                            {canDeleteTransactions && (
                                                                <DropdownMenuItem
                                                                    className="text-red-600 focus:text-red-600"
                                                                    onClick={() =>
                                                                        setToDelete(
                                                                            r,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="mr-2 h-3.5 w-3.5" />{' '}
                                                                    Delete
                                                                </DropdownMenuItem>
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {(canCreateTransactions || canEditTransactions) && (
                    <TransactionFormDialog
                        open={createOpen || editing !== null}
                        onOpenChange={(o) => {
                            if (!o) {
                                setCreateOpen(false);
                                setEditing(null);
                            }
                        }}
                        transaction={editing}
                        accounts={[
                            {
                                id: account.id,
                                name: account.name,
                                currency: account.currency,
                            },
                        ]}
                        transactionTypes={transactionTypes}
                        defaults={{ account_id: account.id }}
                        workspaceSlug={workspace.slug}
                    />
                )}
                {canDeleteTransactions && (
                    <FinanceDeleteDialog
                        open={!!toDelete}
                        onClose={() => setToDelete(null)}
                        title="Delete Transaction?"
                        description="Remove this ledger entry? The account balance will update automatically."
                        url={
                            toDelete
                                ? `${base}/transactions/${toDelete.id}`
                                : ''
                        }
                        successMessage="Transaction deleted"
                    />
                )}
            </div>
        </AppLayout>
    );
}
