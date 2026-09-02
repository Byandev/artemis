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
import { EntryType, LiquidationEntry, TransactionTypeOption } from './types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Present when editing an existing line; absent when adding one. */
    entry?: LiquidationEntry | null;
    endpoint: string;
    /** The workspace's active departments, for the Department select. */
    departments: string[];
    /** The workspace's transaction types, for the Transaction select. */
    transactionTypes: TransactionTypeOption[];
}

const DIRECTIONS: { value: EntryType; label: string }[] = [
    { value: 'in', label: 'Credit (Income)' },
    { value: 'out', label: 'Debit (Expense)' },
];

const today = () => new Date().toISOString().slice(0, 10);

/** Add or amend one line of a wallet's liquidation ledger. */
export function EntryFormDialog({
    open,
    onOpenChange,
    entry,
    endpoint,
    departments,
    transactionTypes,
}: Props) {
    const isEditing = !!entry;

    // Keep a department that is no longer on the list (renamed or deactivated)
    // selectable, so editing an old entry does not silently drop it.
    const departmentOptions =
        entry?.department && !departments.includes(entry.department)
            ? [entry.department, ...departments]
            : departments;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<{
            date: string;
            transaction_type_id: string;
            description: string;
            department: string;
            type: EntryType;
            amount: string;
            notes: string;
        }>({
            date: today(),
            transaction_type_id: '',
            description: '',
            department: '',
            type: 'out',
            amount: '',
            notes: '',
        });

    useEffect(() => {
        if (!open) return;

        clearErrors();

        if (entry) {
            setData({
                date: entry.date,
                transaction_type_id: String(entry.transaction_type_id),
                description: entry.description,
                department: entry.department ?? '',
                type: entry.type,
                amount: String(entry.amount),
                notes: entry.notes ?? '',
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, entry]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };

        if (isEditing) {
            put(`${endpoint}/${entry!.id}`, options);
        } else {
            post(endpoint, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-lg dark:bg-zinc-900">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing ? 'Edit Entry' : 'Add Entry'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {isEditing
                                ? 'Update this liquidation line.'
                                : 'Record a funding or an expense against this wallet.'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="max-h-[65vh] space-y-5 overflow-y-auto px-5 py-4">
                        <Field label="Direction" required error={errors.type}>
                            <div className="flex gap-2">
                                {DIRECTIONS.map((d) => (
                                    <button
                                        key={d.value}
                                        type="button"
                                        onClick={() => setData('type', d.value)}
                                        className={`h-10 flex-1 rounded-[10px] border font-mono! text-[12px]! font-medium transition-all ${
                                            data.type === d.value
                                                ? d.value === 'in'
                                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                                                    : 'border-red-400 bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400'
                                                : 'border-black/8 bg-stone-50 text-gray-500 hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700'
                                        }`}
                                    >
                                        {d.label}
                                    </button>
                                ))}
                            </div>
                        </Field>

                        <div className="grid grid-cols-2 gap-4">
                            <Field
                                label="Posted Date"
                                required
                                error={errors.date}
                            >
                                <input
                                    type="date"
                                    value={data.date}
                                    onChange={(e) =>
                                        setData('date', e.target.value)
                                    }
                                    className={inputCls}
                                />
                            </Field>

                            <Field
                                label="Amount"
                                required
                                error={errors.amount}
                            >
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.amount}
                                    onChange={(e) =>
                                        setData('amount', e.target.value)
                                    }
                                    className={inputCls}
                                />
                            </Field>
                        </div>

                        <Field
                            label="Transaction"
                            required
                            error={errors.transaction_type_id}
                        >
                            <select
                                value={data.transaction_type_id}
                                onChange={(e) =>
                                    setData(
                                        'transaction_type_id',
                                        e.target.value,
                                    )
                                }
                                className={inputCls}
                            >
                                <option value="">Select a type…</option>
                                {transactionTypes.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <div className="grid grid-cols-2 gap-4">
                            <Field
                                label="Type Expenses"
                                required
                                error={errors.description}
                            >
                                <input
                                    type="text"
                                    placeholder="FB ADS"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    className={inputCls}
                                />
                            </Field>

                            <Field label="Department" error={errors.department}>
                                <select
                                    value={data.department}
                                    onChange={(e) =>
                                        setData('department', e.target.value)
                                    }
                                    className={inputCls}
                                >
                                    <option value="">—</option>
                                    {departmentOptions.map((name) => (
                                        <option key={name} value={name}>
                                            {name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </div>

                        <Field label="Remarks" error={errors.notes}>
                            <textarea
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                className={`${inputCls} min-h-[70px] resize-none py-2`}
                            />
                        </Field>
                    </div>

                    <Footer
                        processing={processing}
                        isEditing={isEditing}
                        onCancel={() => onOpenChange(false)}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}
