import PageHeader from '@/components/common/PageHeader';
import { FinanceRemittance, RemittanceFormDialog } from '@/components/finance/remittance-form-dialog';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Pencil } from 'lucide-react';
import { useState } from 'react';

interface Remittance extends FinanceRemittance {
    is_reconciled: boolean;
    transaction?: {
        id: number;
        date: string;
        amount: number | string;
        account?: { id: number; name: string } | null;
    } | null;
}

interface Props { workspace: Workspace; remittance: Remittance }

const fmt = (v: number | string) => Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function RemittanceShow({ workspace, remittance }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const base = `/workspaces/${workspace.slug}/finance`;

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Remittance #${remittance.id}`} />
            <div className="mx-auto w-full max-w-2xl p-4 md:p-6">
                <Link href={`${base}/remittances`} className="mb-3 inline-flex items-center gap-1 text-[12px] text-gray-500 hover:text-gray-800 dark:text-gray-400">
                    <ArrowLeft className="h-3.5 w-3.5" /> Back to Remittances
                </Link>

                <PageHeader title={`Remittance #${remittance.id}`} description={`${remittance.courier} · ${String(remittance.date).slice(0, 10)}`}>
                    <button
                        onClick={() => setEditOpen(true)}
                        className="flex h-8 items-center rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                    >
                        <Pencil className="mr-1.5 h-3.5 w-3.5" /> Edit
                    </button>
                </PageHeader>

                {!remittance.is_reconciled && (
                    <div className="mb-4 flex items-center gap-2 rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-2.5 text-[12px] text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/5 dark:text-amber-300">
                        <AlertTriangle className="h-3.5 w-3.5" />
                        This remittance is not linked to any transaction (unreconciled).
                    </div>
                )}

                <div className="rounded-[14px] border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
                    <dl className="grid grid-cols-2 gap-y-3 text-[13px]">
                        <Item label="Courier" value={remittance.courier} />
                        <Item label="Reference" value={remittance.reference_no ?? '—'} />
                        <Item label="Gross" value={fmt(remittance.gross_amount)} />
                        <Item label="Deductions" value={fmt(remittance.deductions)} />
                        <Item label="Net" value={fmt(remittance.net_amount)} bold />
                        <Item label="Status" value={remittance.status} />
                        <Item label="Notes" value={remittance.notes ?? '—'} fullWidth />
                        <Item
                            label="Linked Transaction"
                            fullWidth
                            value={remittance.transaction ? (
                                <>
                                    {String(remittance.transaction.date).slice(0, 10)} · {remittance.transaction.account?.name ?? '—'} · {fmt(remittance.transaction.amount)}
                                </>
                            ) : <em className="text-gray-400">None</em>}
                        />
                    </dl>
                </div>

                <RemittanceFormDialog open={editOpen} onOpenChange={setEditOpen} remittance={remittance} workspaceSlug={workspace.slug} />
            </div>
        </AppLayout>
    );
}

function Item({ label, value, bold, fullWidth }: { label: string; value: React.ReactNode; bold?: boolean; fullWidth?: boolean }) {
    return (
        <div className={fullWidth ? 'col-span-2' : ''}>
            <dt className="font-mono text-[10px] uppercase tracking-wider text-gray-400">{label}</dt>
            <dd className={`mt-0.5 ${bold ? 'font-mono text-[14px] font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-200'}`}>{value}</dd>
        </div>
    );
}
