import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, Footer, inputCls } from '@/components/finance/account-form-dialog';
import { useForm } from '@inertiajs/react';
import React, { useEffect, useMemo, useState } from 'react';

export interface FinanceRemittance {
    id: number;
    courier: string;
    soa_number: string;
    billing_date_from: string;
    billing_date_to: string;
    gross_cod: number | string;
    cod_fee: number | string;
    cod_fee_vat: number | string;
    shipping_fee: number | string;
    return_shipping: number | string;
    net_amount: number | string;
    notes: string | null;
    status: 'pending' | 'remitted';
    transaction_id: number | null;
}

interface TransactionOpt {
    id: number;
    date: string;
    description: string;
    amount: number | string;
    type: 'in' | 'out';
    account?: { id: number; name: string } | null;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    remittance?: FinanceRemittance | null;
    workspaceSlug: string;
    transactions: TransactionOpt[];
}

const today = () => new Date().toISOString().slice(0, 10);

const COD_FEE_RATE = 0.0275;
const COD_FEE_VAT_RATE = 0.12;

export function RemittanceFormDialog({ open, onOpenChange, remittance, workspaceSlug, transactions }: Props) {
    const isEditing = !!remittance;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        courier: 'J&T',
        soa_number: '',
        billing_date_from: today(),
        billing_date_to: today(),
        gross_cod: '0',
        cod_fee: '0',
        cod_fee_vat: '0',
        shipping_fee: '0',
        return_shipping: '0',
        net_amount: '0',
        status: 'pending' as 'pending' | 'remitted',
        transaction_id: '' as string,
        notes: '',
    });

    const [codFeeOverride, setCodFeeOverride] = useState(false);
    const [codFeeVatOverride, setCodFeeVatOverride] = useState(false);
    const [txnSearch, setTxnSearch] = useState('');

    useEffect(() => {
        if (open) {
            if (remittance) {
                setData({
                    courier: remittance.courier ?? 'J&T',
                    soa_number: remittance.soa_number ?? '',
                    billing_date_from: String(remittance.billing_date_from).slice(0, 10),
                    billing_date_to: String(remittance.billing_date_to).slice(0, 10),
                    gross_cod: String(remittance.gross_cod ?? 0),
                    cod_fee: String(remittance.cod_fee ?? 0),
                    cod_fee_vat: String(remittance.cod_fee_vat ?? 0),
                    shipping_fee: String(remittance.shipping_fee ?? 0),
                    return_shipping: String(remittance.return_shipping ?? 0),
                    net_amount: String(remittance.net_amount ?? 0),
                    status: remittance.status ?? 'pending',
                    transaction_id: remittance.transaction_id ? String(remittance.transaction_id) : '',
                    notes: remittance.notes ?? '',
                });
                setCodFeeOverride(true);
                setCodFeeVatOverride(true);
            } else {
                reset();
                clearErrors();
                setCodFeeOverride(false);
                setCodFeeVatOverride(false);
            }
            setTxnSearch('');
        }
    }, [open, remittance]);

    // Auto-compute cod_fee and cod_fee_vat when gross_cod changes
    useEffect(() => {
        if (!codFeeOverride) {
            const computed = (Number(data.gross_cod || 0) * COD_FEE_RATE).toFixed(2);
            if (computed !== data.cod_fee) setData('cod_fee', computed);
        }
    }, [data.gross_cod, codFeeOverride]);

    useEffect(() => {
        if (!codFeeVatOverride) {
            const computed = (Number(data.cod_fee || 0) * COD_FEE_VAT_RATE).toFixed(2);
            if (computed !== data.cod_fee_vat) setData('cod_fee_vat', computed);
        }
    }, [data.cod_fee, codFeeVatOverride]);

    const net = useMemo(() => (
        Number(data.gross_cod || 0)
        - Number(data.cod_fee || 0)
        - Number(data.cod_fee_vat || 0)
        - Number(data.shipping_fee || 0)
        - Number(data.return_shipping || 0)
    ).toFixed(2), [data.gross_cod, data.cod_fee, data.cod_fee_vat, data.shipping_fee, data.return_shipping]);

    useEffect(() => {
        if (net !== data.net_amount) setData('net_amount', net);
    }, [net]);

    const filteredTxns = useMemo(() => {
        const q = txnSearch.trim().toLowerCase();
        const list = q
            ? transactions.filter(t =>
                t.description.toLowerCase().includes(q) ||
                String(t.id).includes(q) ||
                String(t.amount).includes(q) ||
                (t.account?.name ?? '').toLowerCase().includes(q))
            : transactions;
        return list.slice(0, 50);
    }, [txnSearch, transactions]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => { reset(); onOpenChange(false); },
        };
        const base = `/workspaces/${workspaceSlug}/finance/remittances`;
        if (isEditing) {
            put(`${base}/${remittance!.id}`, options);
        } else {
            post(base, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg p-0 gap-0 overflow-hidden border-none shadow-2xl dark:bg-zinc-900">
                <div className="px-5 pt-5 pb-4 border-b border-black/6 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing ? 'Edit Remittance' : 'Add Remittance (SOA)'}
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                            COD Fee = Gross × 2.75%, VAT = COD Fee × 12%. Net = Gross − COD Fee − VAT − Shipping − Return Shipping.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4 max-h-[70vh] overflow-y-auto">
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Courier" required error={errors.courier}>
                                <input type="text" value={data.courier} onChange={(e) => setData('courier', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="SOA Number" required error={errors.soa_number}>
                                <input type="text" value={data.soa_number} onChange={(e) => setData('soa_number', e.target.value)} className={inputCls} />
                            </Field>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Billing From" required error={errors.billing_date_from}>
                                <input type="date" value={data.billing_date_from} onChange={(e) => setData('billing_date_from', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="Billing To" required error={errors.billing_date_to}>
                                <input type="date" value={data.billing_date_to} onChange={(e) => setData('billing_date_to', e.target.value)} className={inputCls} />
                            </Field>
                        </div>

                        <Field label="Gross COD" required error={errors.gross_cod}>
                            <input type="number" step="0.01" min="0" value={data.gross_cod} onChange={(e) => setData('gross_cod', e.target.value)} className={inputCls} />
                        </Field>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="COD Fee (2.75%)" required error={errors.cod_fee}>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="number" step="0.01" min="0"
                                        value={data.cod_fee}
                                        onChange={(e) => { setCodFeeOverride(true); setData('cod_fee', e.target.value); }}
                                        className={inputCls}
                                    />
                                    {codFeeOverride && (
                                        <button type="button" onClick={() => setCodFeeOverride(false)} className="shrink-0 font-mono text-[10px] text-gray-400 hover:text-emerald-600" title="Reset to auto">auto</button>
                                    )}
                                </div>
                            </Field>
                            <Field label="COD Fee VAT (12%)" required error={errors.cod_fee_vat}>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="number" step="0.01" min="0"
                                        value={data.cod_fee_vat}
                                        onChange={(e) => { setCodFeeVatOverride(true); setData('cod_fee_vat', e.target.value); }}
                                        className={inputCls}
                                    />
                                    {codFeeVatOverride && (
                                        <button type="button" onClick={() => setCodFeeVatOverride(false)} className="shrink-0 font-mono text-[10px] text-gray-400 hover:text-emerald-600" title="Reset to auto">auto</button>
                                    )}
                                </div>
                            </Field>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Shipping Fee" required error={errors.shipping_fee}>
                                <input type="number" step="0.01" min="0" value={data.shipping_fee} onChange={(e) => setData('shipping_fee', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="Return Shipping" required error={errors.return_shipping}>
                                <input type="number" step="0.01" min="0" value={data.return_shipping} onChange={(e) => setData('return_shipping', e.target.value)} className={inputCls} />
                            </Field>
                        </div>

                        <div className="rounded-[10px] border border-dashed border-emerald-200 bg-emerald-50/60 px-3 py-2 text-[12px] text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/5 dark:text-emerald-300">
                            Net Amount: <span className="font-mono font-semibold">{net}</span>
                        </div>

                        <Field label="Status" required error={errors.status}>
                            <select value={data.status} onChange={(e) => setData('status', e.target.value as 'pending' | 'remitted')} className={inputCls}>
                                <option value="pending">pending</option>
                                <option value="remitted">remitted</option>
                            </select>
                        </Field>

                        <Field label="Linked Transaction" error={errors.transaction_id}>
                            <input
                                type="text"
                                placeholder="Search by description, account, amount..."
                                value={txnSearch}
                                onChange={(e) => setTxnSearch(e.target.value)}
                                className={`${inputCls} mb-2`}
                            />
                            <select
                                value={data.transaction_id}
                                onChange={(e) => setData('transaction_id', e.target.value)}
                                className={inputCls}
                                size={Math.min(6, filteredTxns.length + 1) || 1}
                            >
                                <option value="">— none —</option>
                                {filteredTxns.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {String(t.date).slice(0, 10)} · {t.account?.name ?? '—'} · {t.type.toUpperCase()} {Number(t.amount).toLocaleString()} · {t.description}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Notes" error={errors.notes}>
                            <textarea value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} className={`${inputCls} min-h-[80px] py-2 resize-none`} />
                        </Field>
                    </div>

                    <Footer processing={processing} isEditing={isEditing} onCancel={() => onOpenChange(false)} />
                </form>
            </DialogContent>
        </Dialog>
    );
}
