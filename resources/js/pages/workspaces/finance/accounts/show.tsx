import PageHeader from '@/components/common/PageHeader';
import { FinanceDeleteDialog } from '@/components/finance/delete-dialog';
import { TransactionFormDialog, FinanceTransaction } from '@/components/finance/transaction-form-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Account {
    id: number;
    name: string;
    currency: string;
    opening_balance: number;
    current_balance: number;
    notes: string | null;
    is_active: boolean;
}

interface Row {
    id: number;
    date: string;
    description: string;
    type: 'in' | 'out';
    transaction_type: 'funds' | 'profit_share' | 'expenses' | 'transfer' | 'remittance';
    amount: number;
    category: 'remittance' | 'expense' | 'transfer' | 'other';
    remittance: { id: number; courier: string; reference_no: string | null } | null;
    notes: string | null;
    running_balance: number;
}

interface Props {
    workspace: Workspace;
    account: Account;
    rows: Row[];
}

const fmt = (v: number) => Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const TXN_TYPE_STYLE: Record<string, { label: string; cls: string }> = {
    funds: { label: 'funds', cls: 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400' },
    profit_share: { label: 'profit share', cls: 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400' },
    expenses: { label: 'expenses', cls: 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400' },
    transfer: { label: 'transfer', cls: 'bg-slate-100 text-slate-600 dark:bg-slate-500/10 dark:text-slate-300' },
    remittance: { label: 'remittance', cls: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' },
};

export default function AccountShow({ workspace, account, rows }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<FinanceTransaction | null>(null);
    const [toDelete, setToDelete] = useState<Row | null>(null);

    const base = `/workspaces/${workspace.slug}/finance`;

    return (
        <AppLayout>
            <Head title={`${workspace.name} - ${account.name}`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <Link href={`${base}/accounts`} className="mb-3 inline-flex items-center gap-1 text-[12px] text-gray-500 hover:text-gray-800 dark:text-gray-400">
                    <ArrowLeft className="h-3.5 w-3.5" /> Back to Accounts
                </Link>

                <PageHeader
                    title={`${account.name} (${account.currency})`}
                    description={`Opening: ${fmt(account.opening_balance)} · Current: ${fmt(account.current_balance)}`}
                >
                    <button
                        onClick={() => setCreateOpen(true)}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        Add Transaction
                    </button>
                </PageHeader>

                <div className="overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <table className="w-full text-[12px]">
                        <thead>
                            <tr className="border-b border-black/6 dark:border-white/6">
                                {['Date', 'Description', 'Category', 'IN', 'OUT', 'Balance', ''].map((h, i) => (
                                    <th key={i} className={`px-4 py-2.5 font-mono text-[10px] font-medium uppercase tracking-wider text-gray-300 dark:text-gray-600 ${i >= 3 && i <= 5 ? 'text-right' : 'text-left'}`}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr><td colSpan={7} className="px-4 py-10 text-center text-gray-400">No transactions yet.</td></tr>
                            )}
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-black/6 last:border-0 hover:bg-stone-50 dark:border-white/6 dark:hover:bg-white/2">
                                    <td className="px-4 py-2.5 font-mono text-[11px] text-gray-600 dark:text-gray-400">{r.date}</td>
                                    <td className="px-4 py-2.5 text-gray-800 dark:text-gray-200">
                                        <div>{r.description}</div>
                                        {r.remittance && (
                                            <Link href={`${base}/remittances/${r.remittance.id}`} className="text-[10px] text-gray-400 hover:text-emerald-600">
                                                Remittance: {r.remittance.courier} #{r.remittance.reference_no ?? '—'}
                                            </Link>
                                        )}
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <div className="flex flex-col gap-1">
                                            <span className="inline-flex w-fit items-center rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] uppercase text-gray-500 dark:bg-zinc-800 dark:text-gray-400">{r.category}</span>
                                            {(() => {
                                                const s = TXN_TYPE_STYLE[r.transaction_type] ?? TXN_TYPE_STYLE.funds;
                                                return (
                                                    <span className={`inline-flex w-fit items-center rounded-full px-2 py-0.5 font-mono text-[10px] uppercase ${s.cls}`}>{s.label}</span>
                                                );
                                            })()}
                                        </div>
                                    </td>
                                    <td className="px-4 py-2.5 text-right font-mono text-[12px] text-emerald-600 dark:text-emerald-400">{r.type === 'in' ? fmt(r.amount) : ''}</td>
                                    <td className="px-4 py-2.5 text-right font-mono text-[12px] text-red-500 dark:text-red-400">{r.type === 'out' ? fmt(r.amount) : ''}</td>
                                    <td className="px-4 py-2.5 text-right font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">{fmt(r.running_balance)}</td>
                                    <td className="px-4 py-2.5">
                                        <div className="flex justify-center">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800">
                                                        <MoreHorizontal className="h-3.5 w-3.5" />
                                                    </button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="w-36">
                                                    <DropdownMenuItem onClick={() => setEditing({
                                                        id: r.id, account_id: account.id, date: r.date, description: r.description,
                                                        type: r.type, transaction_type: r.transaction_type,
                                                        amount: r.amount, category: r.category,
                                                        remittance_id: r.remittance?.id ?? null, notes: r.notes,
                                                    })}>
                                                        <Pencil className="mr-2 h-3.5 w-3.5" /> Edit
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem className="text-red-600 focus:text-red-600" onClick={() => setToDelete(r)}>
                                                        <Trash2 className="mr-2 h-3.5 w-3.5" /> Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <TransactionFormDialog
                    open={createOpen || editing !== null}
                    onOpenChange={(o) => { if (!o) { setCreateOpen(false); setEditing(null); } }}
                    transaction={editing}
                    accounts={[{ id: account.id, name: account.name, currency: account.currency }]}
                    remittances={[]}
                    defaults={{ account_id: account.id }}
                    workspaceSlug={workspace.slug}
                />
                <FinanceDeleteDialog
                    open={!!toDelete}
                    onClose={() => setToDelete(null)}
                    title="Delete Transaction?"
                    description="Remove this ledger entry? The account balance will update automatically."
                    url={toDelete ? `${base}/transactions/${toDelete.id}` : ''}
                    successMessage="Transaction deleted"
                />
            </div>
        </AppLayout>
    );
}
