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
import { MultiSelect } from '@/components/ui/multi-select';
import { useForm } from '@inertiajs/react';
import { Package } from 'lucide-react';
import React, { useEffect } from 'react';

export type TransactionStatus = 'pending' | 'approved' | 'posted';

/** A user the transaction is charged to, with their share of the amount. */
export interface ChargedUser {
    id: number;
    name: string;
    pivot: { amount: number | string };
}

/** One charge-to row as the form submits it. A blank amount is split evenly. */
export interface ChargeToShare {
    user_id: number;
    amount: string;
}

export interface FinanceTransaction {
    id: number;
    account_id: number;
    date: string;
    description: string;
    requested_by?: number | null;
    approved_by?: number | null;
    department?: string | null;
    requester?: { id: number; name: string } | null;
    approver?: { id: number; name: string } | null;
    charge_to_users?: ChargedUser[];
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
    /** Running balance of the account's newest entry, else its opening balance. */
    current_balance?: number;
    has_transactions?: boolean;
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
    /** When the form becomes active, (re)populate it from `transaction`. */
    active?: boolean;
    /** Where the server should send the user back to after saving. */
    returnTo?: string;
    onSuccess?: () => void;
    onCancel: () => void;
}

const today = () => new Date().toISOString().slice(0, 10);

const money = (n: number) =>
    n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

/**
 * `total` cut into `count` even shares, in cents so nothing is lost to rounding
 * — 100 across 3 gives 33.34 / 33.33 / 33.33 rather than three times 33.33.
 */
function evenShares(total: number, count: number): string[] {
    if (count <= 0) return [];

    const cents = Math.round((Number.isFinite(total) ? total : 0) * 100);
    const each = Math.floor(cents / count);
    const odd = cents - each * count;

    return Array.from({ length: count }, (_, i) =>
        ((each + (i < odd ? 1 : 0)) / 100).toFixed(2),
    );
}

/**
 * A titled group of fields — label/description on the left, a two-column field
 * grid on the right. Defined at module scope so the inputs it wraps keep focus
 * across re-renders.
 */
function Section({
    title,
    hint,
    children,
}: {
    title: string;
    hint: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-x-8 gap-y-5 px-6 py-6 lg:grid-cols-3">
            <div className="lg:pr-4">
                <h3 className="font-mono text-[12px] font-semibold tracking-wide text-gray-800 uppercase dark:text-gray-100">
                    {title}
                </h3>
                <p className="mt-1 font-mono text-[11px] leading-relaxed text-gray-400 dark:text-gray-500">
                    {hint}
                </p>
            </div>
            <div className="grid grid-cols-1 gap-x-5 gap-y-5 sm:grid-cols-2 lg:col-span-2">
                {children}
            </div>
        </div>
    );
}

/** Spans both columns of a Section's field grid. */
function Wide({ children }: { children: React.ReactNode }) {
    return <div className="sm:col-span-2">{children}</div>;
}

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
        charge_to: [] as ChargeToShare[],
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

    // The running balance carries on from the account's last entry until someone
    // types their own figure. Same idea for the charge-to shares below.
    const [autoBalance, setAutoBalance] = React.useState(true);
    const [autoSplit, setAutoSplit] = React.useState(true);

    const selectedAccount = accounts.find(
        (a) => String(a.id) === data.account_id,
    );
    const baseBalance = selectedAccount?.current_balance;

    useEffect(() => {
        if (!autoBalance) return;

        // Nothing to carry from until an account and an amount are in.
        if (baseBalance == null || data.amount === '') {
            if (data.running_balance !== '') setData('running_balance', '');

            return;
        }

        const amount = parseFloat(data.amount) || 0;
        const next = (
            data.type === 'in' ? baseBalance + amount : baseBalance - amount
        ).toFixed(2);

        if (next !== data.running_balance) setData('running_balance', next);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        autoBalance,
        baseBalance,
        data.amount,
        data.type,
        data.running_balance,
    ]);

    const userNames = React.useMemo(
        () => new Map(users.map((u) => [u.id, u.name])),
        [users],
    );

    useEffect(() => {
        if (!autoSplit) return;

        const shares = evenShares(
            parseFloat(data.amount) || 0,
            data.charge_to.length,
        );

        if (shares.some((s, i) => s !== data.charge_to[i]?.amount)) {
            setData(
                'charge_to',
                data.charge_to.map((row, i) => ({ ...row, amount: shares[i] })),
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [autoSplit, data.amount, data.charge_to]);

    /** Keep the shares already entered, and start any newly picked user blank. */
    const setChargedUsers = (ids: string[]) => {
        setData(
            'charge_to',
            ids.map(
                (id) =>
                    data.charge_to.find((r) => String(r.user_id) === id) ?? {
                        user_id: Number(id),
                        amount: '',
                    },
            ),
        );
    };

    const setShare = (userId: number, amount: string) => {
        setAutoSplit(false);
        setData(
            'charge_to',
            data.charge_to.map((r) =>
                r.user_id === userId ? { ...r, amount } : r,
            ),
        );
    };

    const allocated = data.charge_to.reduce(
        (sum, r) => sum + (parseFloat(r.amount) || 0),
        0,
    );
    const totalAmount = parseFloat(data.amount) || 0;
    const balanced = Math.abs(allocated - totalAmount) < 0.01;

    // The sum error lands on `charge_to`, per-row ones on `charge_to.0.user_id`.
    const fieldErrors = errors as Record<string, string | undefined>;
    const chargeToError =
        fieldErrors.charge_to ??
        Object.entries(fieldErrors).find(([key]) =>
            key.startsWith('charge_to.'),
        )?.[1];

    useEffect(() => {
        if (!active) return;
        if (transaction) {
            // A saved balance and saved shares are left exactly as they were.
            setAutoBalance(
                transaction.running_balance == null ||
                    transaction.running_balance === '',
            );
            setAutoSplit((transaction.charge_to_users ?? []).length === 0);
            setData({
                account_id: String(transaction.account_id),
                date: String(transaction.date).slice(0, 10),
                description: transaction.description ?? '',
                requested_by: transaction.requested_by ?? '',
                approved_by: transaction.approved_by ?? '',
                department: transaction.department ?? '',
                charge_to: (transaction.charge_to_users ?? []).map((u) => ({
                    user_id: u.id,
                    amount: Number(u.pivot?.amount ?? 0).toFixed(2),
                })),
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
            setAutoBalance(true);
            setAutoSplit(true);
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
            <div className="divide-y divide-black/6 dark:divide-white/6">
                <Section
                    title="Entry"
                    hint="Where it posts, what it is, and the amount."
                >
                    <Wide>
                        <Field
                            label="Account"
                            required
                            error={errors.account_id}
                        >
                            <select
                                value={data.account_id}
                                onChange={(e) =>
                                    setData('account_id', e.target.value)
                                }
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
                    </Wide>

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

                    <Wide>
                        <Field
                            label="Type of Expense"
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
                                {typeOptions.length === 0 && (
                                    <option value="">
                                        No types — add one first
                                    </option>
                                )}
                                {typeOptions.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </Wide>

                    <Wide>
                        <Field
                            label="Transaction"
                            required
                            error={errors.description}
                        >
                            <input
                                type="text"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                className={inputCls}
                            />
                        </Field>
                    </Wide>

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
                            onChange={(e) => {
                                setAutoBalance(false);
                                setData('running_balance', e.target.value);
                            }}
                            placeholder="Optional"
                            className={inputCls}
                        />
                        {baseBalance == null ? (
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                Pick an account to carry its balance forward.
                            </p>
                        ) : autoBalance ? (
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                Carried from {money(baseBalance)} —{' '}
                                {selectedAccount?.has_transactions
                                    ? 'this account’s last entry'
                                    : 'the account’s opening balance'}
                                .
                            </p>
                        ) : (
                            <button
                                type="button"
                                onClick={() => setAutoBalance(true)}
                                className="font-mono text-[10px] text-emerald-600 hover:underline dark:text-emerald-500"
                            >
                                Carry forward from {money(baseBalance)}
                            </button>
                        )}
                    </Field>

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
                </Section>

                <Section
                    title="Attribution"
                    hint="Who handled it, its ordering, and the product or department it belongs to."
                >
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
                    <Field label="Position" error={errors.position}>
                        <input
                            type="number"
                            min="1"
                            step="1"
                            value={data.position}
                            onChange={(e) =>
                                setData('position', e.target.value)
                            }
                            placeholder="Auto"
                            className={inputCls}
                        />
                    </Field>

                    <Wide>
                        <Field label="Charge To" error={chargeToError}>
                            <MultiSelect
                                options={users.map((u) => ({
                                    value: String(u.id),
                                    label: u.name,
                                }))}
                                selected={data.charge_to.map((r) =>
                                    String(r.user_id),
                                )}
                                onChange={setChargedUsers}
                                placeholder="No one charged"
                            />

                            {data.charge_to.length > 1 && (
                                <div className="mt-2 space-y-1.5 rounded-[10px] border border-black/8 bg-stone-50 p-2.5 dark:border-white/8 dark:bg-zinc-800/60">
                                    {data.charge_to.map((row) => (
                                        <div
                                            key={row.user_id}
                                            className="flex items-center gap-2"
                                        >
                                            <span className="min-w-0 flex-1 truncate font-mono text-[11px] text-gray-600 dark:text-gray-300">
                                                {userNames.get(row.user_id) ??
                                                    `User #${row.user_id}`}
                                            </span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={row.amount}
                                                onChange={(e) =>
                                                    setShare(
                                                        row.user_id,
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="0.00"
                                                aria-label={`Share for ${userNames.get(row.user_id) ?? row.user_id}`}
                                                className="h-8 w-28 rounded-md border border-black/8 bg-white px-2 text-right font-mono! text-[12px]! text-gray-800 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-100"
                                            />
                                        </div>
                                    ))}

                                    <div className="flex items-center justify-between gap-2 border-t border-black/6 pt-2 dark:border-white/6">
                                        <span
                                            className={`font-mono text-[10px] ${
                                                balanced
                                                    ? 'text-gray-400 dark:text-gray-500'
                                                    : 'text-amber-600 dark:text-amber-500'
                                            }`}
                                        >
                                            {money(allocated)} of{' '}
                                            {money(totalAmount)} allocated
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => setAutoSplit(true)}
                                            className="font-mono text-[10px] text-emerald-600 hover:underline dark:text-emerald-500"
                                        >
                                            Split equally
                                        </button>
                                    </div>
                                </div>
                            )}

                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                Charged to several people? The amount is split
                                between them — edit a share to divide it your
                                way. Shares must add up to the amount; leave one
                                blank to give it the remainder.
                            </p>
                        </Field>
                    </Wide>

                    <Wide>
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
                                Attribute this entry to a product for the
                                per-product income statement.
                            </p>
                        </Field>
                    </Wide>
                </Section>

                <Section
                    title="Notes"
                    hint="Any additional remarks for this entry."
                >
                    <Wide>
                        <Field label="Remarks" error={errors.notes}>
                            <textarea
                                value={data.notes ?? ''}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                className={`${inputCls} min-h-[80px] resize-none py-2`}
                            />
                        </Field>
                    </Wide>
                </Section>
            </div>

            <Footer
                processing={processing}
                isEditing={isEditing}
                onCancel={onCancel}
            />
        </form>
    );
}
