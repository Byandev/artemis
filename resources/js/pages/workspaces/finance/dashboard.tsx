import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowDownRight, ArrowUpRight, Wallet } from 'lucide-react';
import { useMemo } from 'react';

interface AccountRow {
    id: number;
    name: string;
    currency: string;
    opening_balance: number | string;
    is_active: boolean;
    transactions: { id: number; account_id: number; type: 'in' | 'out'; amount: number | string; date: string }[];
}

interface TxnRow { id: number; type: 'in' | 'out'; amount: number | string; date: string }
interface RemittanceRow { id: number; transaction_id: number | null }

interface Props {
    workspace: Workspace;
    accounts: AccountRow[];
    transactions: TxnRow[];
    remittances: RemittanceRow[];
}

const fmt = (v: number) => Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function balanceOf(a: AccountRow): number {
    const inSum = a.transactions.filter(t => t.type === 'in').reduce((s, t) => s + Number(t.amount), 0);
    const outSum = a.transactions.filter(t => t.type === 'out').reduce((s, t) => s + Number(t.amount), 0);
    return Number(a.opening_balance) + inSum - outSum;
}

export default function FinanceDashboard({ workspace, accounts, transactions, remittances }: Props) {
    const base = `/workspaces/${workspace.slug}/finance`;

    const active = useMemo(() => accounts.filter(a => a.is_active), [accounts]);
    const totalBalance = useMemo(() => active.reduce((s, a) => s + balanceOf(a), 0), [active]);

    const { totalIn, totalOut } = useMemo(() => {
        let mi = 0, mo = 0;
        for (const t of transactions) {
            if (t.type === 'in') mi += Number(t.amount);
            else mo += Number(t.amount);
        }
        return { totalIn: mi, totalOut: mo };
    }, [transactions]);

    const unreconciledCount = useMemo(() => remittances.filter(r => r.transaction_id == null).length, [remittances]);

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Live Cashflow`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Live Cashflow" description="Overview of accounts, activity, and unreconciled remittances." />

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard icon={<Wallet className="h-4 w-4" />} label="Total Balance" value={fmt(totalBalance)} />
                    <StatCard icon={<ArrowUpRight className="h-4 w-4 text-emerald-500" />} label="Total IN" value={fmt(totalIn)} />
                    <StatCard icon={<ArrowDownRight className="h-4 w-4 text-red-500" />} label="Total OUT" value={fmt(totalOut)} />
                    <Link href={`${base}/remittances?filter[unreconciled]=1`} className="block">
                        <StatCard
                            icon={<AlertTriangle className="h-4 w-4 text-amber-500" />}
                            label="Unreconciled Remittances"
                            value={String(unreconciledCount)}
                        />
                    </Link>
                </div>

                <div className="mt-6 rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="border-b border-black/6 px-5 py-3 text-[11px] font-mono font-medium uppercase tracking-wider text-gray-400 dark:border-white/6 dark:text-gray-500">
                        Accounts
                    </div>
                    <div className="divide-y divide-black/6 dark:divide-white/6">
                        {active.length === 0 && (
                            <div className="px-5 py-6 text-center text-[12px] text-gray-400">No active accounts yet.</div>
                        )}
                        {active.map((a) => (
                            <Link key={a.id} href={`${base}/accounts/${a.id}`} className="flex items-center justify-between px-5 py-3 hover:bg-stone-50 dark:hover:bg-white/2">
                                <div className="flex flex-col gap-0.5">
                                    <span className="text-[13px] font-medium text-gray-800 dark:text-gray-100">{a.name}</span>
                                    <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400">{a.currency}</span>
                                </div>
                                <span className="font-mono text-[13px] font-medium text-gray-700 dark:text-gray-200">{fmt(balanceOf(a))}</span>
                            </Link>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function StatCard({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-center gap-2 text-[10px] font-mono uppercase tracking-wider text-gray-400 dark:text-gray-500">
                {icon}
                <span>{label}</span>
            </div>
            <div className="mt-2 font-mono text-[20px] font-semibold text-gray-800 dark:text-gray-100">{value}</div>
        </div>
    );
}
