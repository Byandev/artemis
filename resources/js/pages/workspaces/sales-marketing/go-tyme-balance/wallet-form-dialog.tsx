import {
    Field,
    Footer,
    inputCls,
} from '@/components/finance/account-form-dialog';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import React, { useEffect } from 'react';
import { WALLET_TYPES, WalletRow, WalletType } from './types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspaceSlug: string;
    /** The wallet being edited, or null to create one. */
    wallet?: WalletRow | null;
}

/**
 * Create/edit form for a Go Tyme wallet. The saved record is an ordinary
 * Finance account — this posts to the S&M endpoint, which is what stamps
 * `is_user_wallet` — plus the wallet-only Main/Backup slot, which has no
 * meaning for the company accounts the Finance dialog creates.
 */
export function WalletFormDialog({
    open,
    onOpenChange,
    workspaceSlug,
    wallet = null,
}: Props) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<{
            name: string;
            wallet_type: WalletType;
            opening_balance: string;
            currency: string;
            notes: string;
            is_active: boolean;
        }>({
            name: '',
            wallet_type: 'main',
            opening_balance: '0',
            currency: 'PHP',
            notes: '',
            is_active: true,
        });

    // Filled from the row on open, so editing starts on what is on screen and
    // creating starts clean.
    useEffect(() => {
        if (!open) return;

        clearErrors();

        if (wallet) {
            setData({
                name: wallet.name,
                wallet_type: wallet.wallet_type ?? 'main',
                opening_balance: String(wallet.opening_balance),
                currency: wallet.currency,
                notes: wallet.notes ?? '',
                is_active: wallet.is_active,
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, wallet]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const baseUrl = `/workspaces/${workspaceSlug}/sales-marketing/go-tyme-balance`;
        const done = () => {
            reset();
            onOpenChange(false);
        };

        if (wallet) {
            put(`${baseUrl}/${wallet.id}`, {
                preserveScroll: true,
                onSuccess: done,
            });

            return;
        }

        post(baseUrl, { preserveScroll: true, onSuccess: done });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-md dark:bg-zinc-900">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {wallet ? 'Edit Account' : 'Create Account'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {wallet
                                ? 'Moving the opening balance re-flows every figure in this wallet’s ledger.'
                                : 'Creates a Go Tyme wallet. It is kept out of Finance → Accounts.'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        <Field label="Name" required error={errors.name}>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                className={inputCls}
                            />
                        </Field>

                        <Field
                            label="Account Type"
                            required
                            error={errors.wallet_type}
                        >
                            <div className="flex gap-2">
                                {WALLET_TYPES.map((type) => (
                                    <button
                                        key={type.value}
                                        type="button"
                                        onClick={() =>
                                            setData('wallet_type', type.value)
                                        }
                                        className={`h-10 flex-1 rounded-[10px] border font-mono! text-[12px]! font-medium transition-all ${
                                            data.wallet_type === type.value
                                                ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                                                : 'border-black/8 bg-stone-50 text-gray-500 hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700'
                                        }`}
                                    >
                                        {type.label}
                                    </button>
                                ))}
                            </div>
                        </Field>

                        <Field
                            label="Opening Balance"
                            required
                            error={errors.opening_balance}
                        >
                            <input
                                type="number"
                                step="0.01"
                                value={data.opening_balance}
                                onChange={(e) =>
                                    setData('opening_balance', e.target.value)
                                }
                                className={inputCls}
                            />
                        </Field>

                        <Field
                            label="Currency"
                            required
                            error={errors.currency}
                        >
                            <input
                                type="text"
                                maxLength={3}
                                value={data.currency}
                                onChange={(e) =>
                                    setData(
                                        'currency',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                className={inputCls}
                            />
                        </Field>

                        <Field label="Notes" error={errors.notes}>
                            <textarea
                                value={data.notes ?? ''}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                className={`${inputCls} min-h-[80px] resize-none py-2`}
                            />
                        </Field>

                        <label className="flex items-center gap-2 text-[12px] text-gray-600 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) =>
                                    setData('is_active', e.target.checked)
                                }
                            />
                            Active
                        </label>
                    </div>

                    <Footer
                        processing={processing}
                        isEditing={wallet !== null}
                        onCancel={() => onOpenChange(false)}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}
