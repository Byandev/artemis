import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, Footer, inputCls } from '@/components/finance/account-form-dialog';
import { SUB_CATEGORIES, SubCategory } from '@/components/finance/sub-category';
import { TRANSACTION_TYPES, TransactionType } from '@/components/finance/transaction-type';
import { useForm } from '@inertiajs/react';
import React, { useEffect } from 'react';

export interface FinanceTransaction {
    id: number;
    account_id: number;
    date: string;
    description: string;
    type: 'in' | 'out';
    transaction_type: TransactionType | null;
    amount: number | string;
    running_balance?: number | string | null;
    position?: number | null;
    sub_category: SubCategory | null;
    notes: string | null;
}

interface AccountOpt { id: number; name: string; currency: string }

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transaction?: FinanceTransaction | null;
    accounts: AccountOpt[];
    defaults?: Partial<FinanceTransaction>;
    workspaceSlug: string;
}

const today = () => new Date().toISOString().slice(0, 10);

export function TransactionFormDialog({ open, onOpenChange, transaction, accounts, defaults, workspaceSlug }: Props) {
    const isEditing = !!transaction;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        account_id: '',
        date: today(),
        description: '',
        type: 'in' as 'in' | 'out',
        transaction_type: 'funds' as TransactionType,
        amount: '',
        running_balance: '',
        position: '',
        sub_category: '' as SubCategory | '',
        notes: '',
    });

    useEffect(() => {
        if (open) {
            if (transaction) {
                setData({
                    account_id: String(transaction.account_id),
                    date: transaction.date,
                    description: transaction.description ?? '',
                    type: transaction.type,
                    transaction_type: transaction.transaction_type ?? 'funds',
                    amount: String(transaction.amount ?? ''),
                    running_balance: String(transaction.running_balance ?? ''),
                    position: String(transaction.position ?? ''),
                    sub_category: transaction.sub_category ?? '',
                    notes: transaction.notes ?? '',
                });
            } else {
                reset();
                clearErrors();
                if (defaults?.account_id) setData('account_id', String(defaults.account_id));
                if (defaults?.sub_category) setData('sub_category', defaults.sub_category);
            }
        }
    }, [open, transaction]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => { reset(); onOpenChange(false); },
        };
        const base = `/workspaces/${workspaceSlug}/finance/transactions`;
        if (isEditing) {
            put(`${base}/${transaction!.id}`, options);
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
                            {isEditing ? 'Edit Transaction' : 'Add Transaction'}
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                            {isEditing ? 'Update this ledger entry.' : 'Record a new ledger entry.'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4 max-h-[70vh] overflow-y-auto">
                        <Field label="Account" required error={errors.account_id}>
                            <select value={data.account_id} onChange={(e) => setData('account_id', e.target.value)} className={inputCls}>
                                <option value="">Select account...</option>
                                {accounts.map((a) => (
                                    <option key={a.id} value={a.id}>{a.name} ({a.currency})</option>
                                ))}
                            </select>
                        </Field>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Date" required error={errors.date}>
                                <input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="Type" required error={errors.type}>
                                <select value={data.type} onChange={(e) => setData('type', e.target.value as 'in' | 'out')} className={inputCls}>
                                    <option value="in">IN (deposit)</option>
                                    <option value="out">OUT (withdrawal)</option>
                                </select>
                            </Field>
                        </div>

                        <Field label="Transaction Type" required error={errors.transaction_type}>
                            <select
                                value={data.transaction_type}
                                onChange={(e) => setData('transaction_type', e.target.value as TransactionType)}
                                className={inputCls}
                            >
                                {TRANSACTION_TYPES.map((t) => (
                                    <option key={t.value} value={t.value}>{t.label}</option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Description" required error={errors.description}>
                            <input type="text" value={data.description} onChange={(e) => setData('description', e.target.value)} className={inputCls} />
                        </Field>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Amount" required error={errors.amount}>
                                <input type="number" step="0.01" min="0" value={data.amount} onChange={(e) => setData('amount', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="Running Balance" error={errors.running_balance}>
                                <input type="number" step="0.01" value={data.running_balance} onChange={(e) => setData('running_balance', e.target.value)} placeholder="Optional" className={inputCls} />
                            </Field>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Position" error={errors.position}>
                                <input type="number" min="1" step="1" value={data.position} onChange={(e) => setData('position', e.target.value)} placeholder="Auto" className={inputCls} />
                            </Field>
                            <Field label="Sub Category" error={errors.sub_category}>
                                <select
                                    value={data.sub_category}
                                    onChange={(e) => setData('sub_category', e.target.value as SubCategory | '')}
                                    className={inputCls}
                                >
                                    <option value="">— none —</option>
                                    {SUB_CATEGORIES.map(s => (
                                        <option key={s.value} value={s.value}>{s.label}</option>
                                    ))}
                                </select>
                            </Field>
                        </div>

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
