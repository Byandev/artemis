import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, Footer, inputCls } from '@/components/finance/account-form-dialog';
import { useForm } from '@inertiajs/react';
import React, { useEffect } from 'react';

export interface FinanceRemittance {
    id: number;
    courier: string;
    date: string;
    reference_no: string | null;
    gross_amount: number | string;
    deductions: number | string;
    net_amount: number | string;
    notes: string | null;
    status: 'pending' | 'remitted';
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    remittance?: FinanceRemittance | null;
    workspaceSlug: string;
}

const today = () => new Date().toISOString().slice(0, 10);

export function RemittanceFormDialog({ open, onOpenChange, remittance, workspaceSlug }: Props) {
    const isEditing = !!remittance;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        courier: '',
        date: today(),
        reference_no: '',
        gross_amount: '0',
        deductions: '0',
        notes: '',
        status: 'pending' as 'pending' | 'remitted',
    });

    useEffect(() => {
        if (open) {
            if (remittance) {
                setData({
                    courier: remittance.courier ?? '',
                    date: String(remittance.date).slice(0, 10),
                    reference_no: remittance.reference_no ?? '',
                    gross_amount: String(remittance.gross_amount ?? 0),
                    deductions: String(remittance.deductions ?? 0),
                    notes: remittance.notes ?? '',
                    status: remittance.status ?? 'pending',
                });
            } else {
                reset();
                clearErrors();
            }
        }
    }, [open, remittance]);

    const net = (Number(data.gross_amount || 0) - Number(data.deductions || 0)).toFixed(2);

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
            <DialogContent className="sm:max-w-md p-0 gap-0 overflow-hidden border-none shadow-2xl dark:bg-zinc-900">
                <div className="px-5 pt-5 pb-4 border-b border-black/6 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing ? 'Edit Remittance' : 'Add Remittance'}
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                            Net amount is computed as gross - deductions.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4 max-h-[70vh] overflow-y-auto">
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Courier" required error={errors.courier}>
                                <input type="text" value={data.courier} onChange={(e) => setData('courier', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="Date" required error={errors.date}>
                                <input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} className={inputCls} />
                            </Field>
                        </div>

                        <Field label="Reference No" error={errors.reference_no}>
                            <input type="text" value={data.reference_no ?? ''} onChange={(e) => setData('reference_no', e.target.value)} className={inputCls} />
                        </Field>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Gross Amount" required error={errors.gross_amount}>
                                <input type="number" step="0.01" min="0" value={data.gross_amount} onChange={(e) => setData('gross_amount', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="Deductions" required error={errors.deductions}>
                                <input type="number" step="0.01" min="0" value={data.deductions} onChange={(e) => setData('deductions', e.target.value)} className={inputCls} />
                            </Field>
                        </div>

                        <div className="rounded-[10px] border border-dashed border-black/10 bg-stone-50/60 px-3 py-2 text-[12px] text-gray-500 dark:border-white/10 dark:bg-white/2 dark:text-gray-400">
                            Net amount: <span className="font-mono font-medium text-gray-800 dark:text-gray-100">{net}</span>
                        </div>

                        <Field label="Status" required error={errors.status}>
                            <select value={data.status} onChange={(e) => setData('status', e.target.value as 'pending' | 'remitted')} className={inputCls}>
                                <option value="pending">pending</option>
                                <option value="remitted">remitted</option>
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
