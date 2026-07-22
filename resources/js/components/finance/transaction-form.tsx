import {
    Field,
    Footer,
    inputCls,
} from '@/components/finance/account-form-dialog';
import { SearchableSelect } from '@/components/finance/searchable-select';
import { SubCategory } from '@/components/finance/sub-category';
import {
    buildTransactionTypeOptions,
    TransactionType,
    TransactionTypeItem,
} from '@/components/finance/transaction-type';
import { useForm } from '@inertiajs/react';
import { Package } from 'lucide-react';
import React, { useEffect } from 'react';

export type TransactionStatus = 'pending' | 'approved' | 'posted';

export interface FinanceTransaction {
    id: number;
    account_id: number;
    date: string;
    description: string;
    requested_by?: number | null;
    approved_by?: number | null;
    department?: string | null;
    charge_to?: number | null;
    requester?: { id: number; name: string } | null;
    approver?: { id: number; name: string } | null;
    charge_to_user?: { id: number; name: string } | null;
    type: 'in' | 'out';
    transaction_type: TransactionType | null;
    transaction_type_id?: number | null;
    amount: number | string;
    running_balance?: number | string | null;
    reference_no?: string | null;
    status?: TransactionStatus | null;
    position?: number | null;
    sub_category: SubCategory | null;
    product?: string | null;
    notes: string | null;
}

export interface AccountOpt {
    id: number;
    name: string;
    currency: string;
}

export interface UserOpt {
    id: number;
    name: string;
}

interface Props {
    transaction?: FinanceTransaction | null;
    accounts: AccountOpt[];
    transactionTypes: TransactionTypeItem[];
    users: UserOpt[];
    products?: string[];
    departments?: string[];
    defaults?: Partial<FinanceTransaction>;
    workspaceSlug: string;
    /** When the form becomes active (e.g. a dialog opens), (re)populate it. */
    active?: boolean;
    /** Constrain height and scroll the fields — for the modal. Pages scroll naturally. */
    scroll?: boolean;
    /** Where the server should send the user back to after saving. */
    returnTo?: string;
    onSuccess?: () => void;
    onCancel: () => void;
}

const today = () => new Date().toISOString().slice(0, 10);

export function TransactionForm({
    transaction,
    accounts,
    transactionTypes,
    users,
    products = [],
    departments = [],
    defaults,
    workspaceSlug,
    active = true,
    scroll = false,
    returnTo,
    onSuccess,
    onCancel,
}: Props) {
    const isEditing = !!transaction;
    const typeOptions = buildTransactionTypeOptions(transactionTypes);
    const defaultTypeId = typeOptions[0]?.value ?? '';

    const {
        data,
        setData,
        post,
        put,
        transform,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm({
        account_id: '',
        date: today(),
        description: '',
        requested_by: '' as number | '',
        approved_by: '' as number | '',
        department: '',
        charge_to: '' as number | '',
        product: '',
        type: 'in' as 'in' | 'out',
        transaction_type_id: defaultTypeId,
        amount: '',
        running_balance: '',
        reference_no: '',
        status: 'posted' as TransactionStatus,
        position: '',
        notes: '',
    });

    // Departments come from the workspace's saved list; keep a stored value that
    // is no longer in that list (e.g. renamed/inactive) selectable so edits don't
    // silently drop it.
    const departmentOptions =
        data.department && !departments.includes(data.department)
            ? [data.department, ...departments]
            : departments;

    useEffect(() => {
        if (!active) return;
        if (transaction) {
            setData({
                account_id: String(transaction.account_id),
                date: String(transaction.date).slice(0, 10),
                description: transaction.description ?? '',
                requested_by: transaction.requested_by ?? '',
                approved_by: transaction.approved_by ?? '',
                department: transaction.department ?? '',
                charge_to: transaction.charge_to ?? '',
                product: transaction.product ?? '',
                // (numeric user ids; see FinanceTransaction)
                type: transaction.type,
                transaction_type_id:
                    transaction.transaction_type_id != null
                        ? String(transaction.transaction_type_id)
                        : defaultTypeId,
                amount: String(transaction.amount ?? ''),
                running_balance: String(transaction.running_balance ?? ''),
                reference_no: transaction.reference_no ?? '',
                status: transaction.status ?? 'posted',
                position: String(transaction.position ?? ''),
                notes: transaction.notes ?? '',
            });
        } else {
            reset();
            clearErrors();
            if (defaults?.account_id)
                setData('account_id', String(defaults.account_id));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [active, transaction]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // `return_to` steers the post-save redirect; it isn't a model field, so the
        // controller reads it separately and validated() drops it.
        transform((d) => (returnTo ? { ...d, return_to: returnTo } : d));
        const options = {
            preserveScroll: true,
            onSuccess: () => onSuccess?.(),
        };
        const base = `/workspaces/${workspaceSlug}/finance/transactions`;
        if (isEditing) {
            put(`${base}/${transaction!.id}`, options);
        } else {
            post(base, options);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div
                className={`space-y-5 px-5 py-4 ${
                    scroll ? 'max-h-[70vh] overflow-y-auto' : ''
                }`}
            >
                <Field label="Account" required error={errors.account_id}>
                    <select
                        value={data.account_id}
                        onChange={(e) => setData('account_id', e.target.value)}
                        className={inputCls}
                    >
                        <option value="">Select account...</option>
                        {accounts.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name} ({a.currency})
                            </option>
                        ))}
                    </select>
                </Field>

                <div className="grid grid-cols-2 gap-3">
                    <Field label="Posted Date" required error={errors.date}>
                        <input
                            type="date"
                            value={data.date}
                            onChange={(e) => setData('date', e.target.value)}
                            className={inputCls}
                        />
                    </Field>
                    <Field label="Type" required error={errors.type}>
                        <select
                            value={data.type}
                            onChange={(e) =>
                                setData('type', e.target.value as 'in' | 'out')
                            }
                            className={inputCls}
                        >
                            <option value="in">IN (deposit)</option>
                            <option value="out">OUT (withdrawal)</option>
                        </select>
                    </Field>
                </div>

                <Field
                    label="Type of Expense"
                    required
                    error={errors.transaction_type_id}
                >
                    <select
                        value={data.transaction_type_id}
                        onChange={(e) =>
                            setData('transaction_type_id', e.target.value)
                        }
                        className={inputCls}
                    >
                        {typeOptions.length === 0 && (
                            <option value="">No types — add one first</option>
                        )}
                        {typeOptions.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field label="Transaction" required error={errors.description}>
                    <input
                        type="text"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        className={inputCls}
                    />
                </Field>

                <div className="grid grid-cols-2 gap-3">
                    <Field label="Requested By" error={errors.requested_by}>
                        <select
                            value={data.requested_by}
                            onChange={(e) =>
                                setData(
                                    'requested_by',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                            className={inputCls}
                        >
                            <option value="">Select…</option>
                            {users.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Approved By" error={errors.approved_by}>
                        <select
                            value={data.approved_by}
                            onChange={(e) =>
                                setData(
                                    'approved_by',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                            className={inputCls}
                        >
                            <option value="">Select…</option>
                            {users.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <Field label="Department" error={errors.department}>
                        <select
                            value={data.department}
                            onChange={(e) =>
                                setData('department', e.target.value)
                            }
                            className={inputCls}
                        >
                            <option value="">Select…</option>
                            {departmentOptions.map((d) => (
                                <option key={d} value={d}>
                                    {d}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Charge To" error={errors.charge_to}>
                        <select
                            value={data.charge_to}
                            onChange={(e) =>
                                setData(
                                    'charge_to',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                            className={inputCls}
                        >
                            <option value="">Select…</option>
                            {users.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <Field label="Reference No." error={errors.reference_no}>
                        <input
                            type="text"
                            value={data.reference_no}
                            onChange={(e) =>
                                setData('reference_no', e.target.value)
                            }
                            className={inputCls}
                        />
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <select
                            value={data.status}
                            onChange={(e) =>
                                setData(
                                    'status',
                                    e.target.value as TransactionStatus,
                                )
                            }
                            className={inputCls}
                        >
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="posted">Posted</option>
                        </select>
                    </Field>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <Field label="Amount" required error={errors.amount}>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            className={inputCls}
                        />
                    </Field>
                    <Field
                        label="Running Balance"
                        error={errors.running_balance}
                    >
                        <input
                            type="number"
                            step="0.01"
                            value={data.running_balance}
                            onChange={(e) =>
                                setData('running_balance', e.target.value)
                            }
                            placeholder="Optional"
                            className={inputCls}
                        />
                    </Field>
                </div>

                <Field label="Position" error={errors.position}>
                    <input
                        type="number"
                        min="1"
                        step="1"
                        value={data.position}
                        onChange={(e) => setData('position', e.target.value)}
                        placeholder="Auto"
                        className={inputCls}
                    />
                </Field>

                <Field label="Product" error={errors.product}>
                    <SearchableSelect
                        options={products}
                        value={data.product}
                        onChange={(v) => setData('product', v)}
                        placeholder="No product"
                        searchPlaceholder="Search products..."
                        emptyText="No products found."
                        icon={Package}
                    />
                    <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                        Attribute this entry to a product for the per-product
                        income statement.
                    </p>
                </Field>

                <Field label="Remarks" error={errors.notes}>
                    <textarea
                        value={data.notes ?? ''}
                        onChange={(e) => setData('notes', e.target.value)}
                        className={`${inputCls} min-h-[80px] resize-none py-2`}
                    />
                </Field>
            </div>

            <Footer
                processing={processing}
                isEditing={isEditing}
                onCancel={onCancel}
            />
        </form>
    );
}
