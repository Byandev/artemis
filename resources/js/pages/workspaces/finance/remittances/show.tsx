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
        description?: string;
        account?: { id: number; name: string } | null;
    } | null;
}

interface Props {
    workspace: Workspace;
    remittance: Remittance;
}

const peso = (v: number | string) => `₱${Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function RemittanceShow({ workspace, remittance }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const base = `/workspaces/${workspace.slug}/finance`;

    return (
        <AppLayout>
            <Head title={`${workspace.name} - SOA ${remittance.soa_number}`} />
            <div className="mx-auto w-full max-w-2xl p-4 md:p-6">
                <Link href={`${base}/remittances`} className="mb-3 inline-flex items-center gap-1 text-[12px] text-gray-500 hover:text-gray-800 dark:text-gray-400">
                    <ArrowLeft className="h-3.5 w-3.5" /> Back to Remittances
                </Link>

                <PageHeader
                    title={`SOA ${remittance.soa_number}`}
                    description={`${remittance.courier} · ${String(remittance.billing_date_from).slice(0, 10)} → ${String(remittance.billing_date_to).slice(0, 10)}`}
                >
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
                        This remittance is not yet linked to a transaction.
                    </div>
                )}

                <div className="rounded-[14px] border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900">
                    <h3 className="mb-4 font-mono text-[10px] uppercase tracking-wider text-gray-400">Breakdown</h3>
                    <dl className="space-y-2 font-mono text-[13px]">
                        <LineItem label="Gross COD" value={peso(remittance.gross_cod)} />
                        <LineItem label="Less COD Fee" value={`-${peso(remittance.cod_fee)}`} negative />
                        <LineItem label="Less COD Fee VAT" value={`-${peso(remittance.cod_fee_vat)}`} negative />
                        <LineItem label="Less Shipping Fee" value={`-${peso(remittance.shipping_fee)}`} negative />
                        <LineItem label="Less Return Shipping" value={`-${peso(remittance.return_shipping)}`} negative />
                        <div className="my-2 border-t border-dashed border-black/10 dark:border-white/10" />
                        <LineItem label="Net Remittance" value={peso(remittance.net_amount)} bold />
                    </dl>

                    <div className="mt-6 grid grid-cols-2 gap-4 border-t border-black/6 pt-4 text-[13px] dark:border-white/6">
                        <Meta label="Status">
                            <span className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[10px] uppercase ${remittance.status === 'remitted' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400'}`}>
                                {remittance.status}
                            </span>
                        </Meta>
                        <Meta label="Linked Transaction">
                            {remittance.transaction ? (
                                <Link href={`${base}/accounts/${remittance.transaction.account?.id}`} className="text-emerald-600 hover:underline">
                                    #{remittance.transaction.id} · {remittance.transaction.account?.name ?? '—'} · {peso(remittance.transaction.amount)}
                                </Link>
                            ) : (
                                <span className="text-gray-400">Not yet linked</span>
                            )}
                        </Meta>
                        {remittance.notes && (
                            <Meta label="Notes" fullWidth>
                                <p className="text-gray-700 dark:text-gray-200">{remittance.notes}</p>
                            </Meta>
                        )}
                    </div>
                </div>

                <RemittanceFormDialog
                    open={editOpen}
                    onOpenChange={setEditOpen}
                    remittance={remittance}
                    workspaceSlug={workspace.slug}
                    transactions={remittance.transaction ? [{
                        id: remittance.transaction.id,
                        account_id: remittance.transaction.account?.id ?? 0,
                        date: remittance.transaction.date,
                        description: remittance.transaction.description ?? '',
                        amount: remittance.transaction.amount,
                        type: 'in',
                        account: remittance.transaction.account,
                    }] : []}
                />
            </div>
        </AppLayout>
    );
}

function LineItem({ label, value, bold, negative }: { label: string; value: string; bold?: boolean; negative?: boolean }) {
    return (
        <div className="flex items-center justify-between">
            <dt className={bold ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'}>{label}</dt>
            <dd className={`${bold ? 'text-[16px] font-semibold text-gray-900 dark:text-gray-100' : negative ? 'text-rose-600 dark:text-rose-400' : 'text-gray-700 dark:text-gray-200'}`}>{value}</dd>
        </div>
    );
}

function Meta({ label, children, fullWidth }: { label: string; children: React.ReactNode; fullWidth?: boolean }) {
    return (
        <div className={fullWidth ? 'col-span-2' : ''}>
            <dt className="font-mono text-[10px] uppercase tracking-wider text-gray-400">{label}</dt>
            <dd className="mt-0.5">{children}</dd>
        </div>
    );
}
