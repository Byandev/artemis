import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import React, { useEffect } from 'react';

export interface FinanceAccount {
    id: number;
    name: string;
    opening_balance: number | string;
    currency: string;
    notes: string | null;
    is_active: boolean;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    account?: FinanceAccount | null;
    workspaceSlug: string;
}

export function AccountFormDialog({ open, onOpenChange, account, workspaceSlug }: Props) {
    const isEditing = !!account;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '',
        opening_balance: '0',
        currency: 'PHP',
        notes: '',
        is_active: true,
    });

    useEffect(() => {
        if (open) {
            if (account) {
                setData({
                    name: account.name ?? '',
                    opening_balance: String(account.opening_balance ?? 0),
                    currency: account.currency ?? 'PHP',
                    notes: account.notes ?? '',
                    is_active: !!account.is_active,
                });
            } else {
                reset();
                clearErrors();
            }
        }
    }, [open, account]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => { reset(); onOpenChange(false); },
        };
        const base = `/workspaces/${workspaceSlug}/finance/accounts`;
        if (isEditing) {
            put(`${base}/${account!.id}`, options);
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
                            {isEditing ? 'Edit Account' : 'Add Account'}
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                            {isEditing ? 'Update this account’s details.' : 'Create a new finance account.'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        <Field label="Name" required error={errors.name}>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className={inputCls}
                            />
                        </Field>

                        <Field label="Opening Balance" required error={errors.opening_balance}>
                            <input
                                type="number"
                                step="0.01"
                                value={data.opening_balance}
                                onChange={(e) => setData('opening_balance', e.target.value)}
                                className={inputCls}
                            />
                        </Field>

                        <Field label="Currency" required error={errors.currency}>
                            <input
                                type="text"
                                maxLength={3}
                                value={data.currency}
                                onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                                className={inputCls}
                            />
                        </Field>

                        <Field label="Notes" error={errors.notes}>
                            <textarea
                                value={data.notes ?? ''}
                                onChange={(e) => setData('notes', e.target.value)}
                                className={`${inputCls} min-h-[80px] py-2 resize-none`}
                            />
                        </Field>

                        <label className="flex items-center gap-2 text-[12px] text-gray-600 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                            />
                            Active
                        </label>
                    </div>

                    <Footer processing={processing} isEditing={isEditing} onCancel={() => onOpenChange(false)} />
                </form>
            </DialogContent>
        </Dialog>
    );
}

const inputCls =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100';

function Field({ label, required, error, children }: { label: string; required?: boolean; error?: string; children: React.ReactNode }) {
    return (
        <div className="space-y-1.5">
            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                {label} {required && <span className="text-red-400">*</span>}
            </label>
            {children}
            {error && <p className="font-mono text-[11px] text-red-500 mt-1">{error}</p>}
        </div>
    );
}

function Footer({ processing, isEditing, onCancel }: { processing: boolean; isEditing: boolean; onCancel: () => void }) {
    return (
        <div className="flex items-center justify-end gap-2 border-t border-black/6 dark:border-white/6 px-5 py-3 bg-stone-50/50 dark:bg-white/2">
            <button
                type="button"
                onClick={onCancel}
                className="flex h-9 items-center rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
            >
                Cancel
            </button>
            <button
                type="submit"
                disabled={processing}
                className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
            >
                {processing ? (isEditing ? 'Saving…' : 'Creating…') : (isEditing ? 'Save Changes' : 'Create')}
            </button>
        </div>
    );
}

export { Field, Footer, inputCls };
