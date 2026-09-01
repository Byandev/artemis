import {
    Field,
    Footer,
    inputCls,
} from '@/components/finance/account-form-dialog';
import {
    PurchasedOrderOption,
    PurchasedOrderPicker,
} from '@/components/finance/purchased-order-picker';
import {
    allocatedTotal,
    proportionalShares,
    Share,
    ShareAllocator,
    sharesBalanced,
} from '@/components/finance/share-allocator';
import { SubCategory } from '@/components/finance/sub-category';
import {
    buildTransactionTypeOptions,
    TransactionType,
    TransactionTypeItem,
} from '@/components/finance/transaction-type';
import { useForm } from '@inertiajs/react';
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

/** A product the transaction is charged to, with its share of the amount. */
export interface TxnProductShare {
    product: string;
    amount: number | string;
}

/** One product row as the form submits it. A blank amount is split evenly. */
export interface ProductShare {
    product: string;
    amount: string;
}

/** An approved fund request a new entry can be filled in from. */
export interface FundRequestOption {
    id: number;
    reference_no: string;
    request_date: string | null;
    amount_requested: number;
    status: string;
    transaction_type_id: number | null;
    department: string | null;
    charge_to: { user_id: number; name: string; amount: number }[];
    products: { product_label: string; amount: number }[];
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
    product_shares?: TxnProductShare[];
    fund_request_id?: number | null;
    fund_request?: { id: number; reference_no: string } | null;
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
    /**
     * Ids of the types whose entries are a purchase order's freight bill —
     * these get the PO picker (see TransactionType::isCogsDelivery()).
     */
    cogsDeliveryTypeIds?: number[];
    users: UserOpt[];
    products?: string[];
    fundRequests?: FundRequestOption[];
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
function Wide({
    children,
    ref,
}: {
    children: React.ReactNode;
    ref?: React.Ref<HTMLDivElement>;
}) {
    return (
        <div ref={ref} className="sm:col-span-2">
            {children}
        </div>
    );
}

export function TransactionForm({
    transaction,
    accounts,
    transactionTypes,
    cogsDeliveryTypeIds = [],
    users,
    products = [],
    fundRequests = [],
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

    // The in/out direction follows the chosen transaction type's nature
    // ('credit' = money in, 'debit' = money out) instead of a manual field.
    const natureById = React.useMemo(
        () =>
            Object.fromEntries(
                transactionTypes.map((t) => [String(t.id), t.nature]),
            ) as Record<string, 'debit' | 'credit' | undefined>,
        [transactionTypes],
    );
    const directionFor = (id: string | number): 'in' | 'out' =>
        natureById[String(id)] === 'credit' ? 'in' : 'out';

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
        products: [] as ProductShare[],
        type: directionFor(defaultTypeId) as 'in' | 'out',
        transaction_type_id: defaultTypeId,
        amount: '',
        running_balance: '',
        reference_no: '',
        fund_request_id: '' as number | '',
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
    const [autoSplitProducts, setAutoSplitProducts] = React.useState(true);

    // The purchase order a delivery fee belongs to. Held only for as long as the
    // split follows it: the moment a share is touched by hand the allocation is
    // the user's, and the picker steps back out of the way.
    const [purchasedOrder, setPurchasedOrder] =
        React.useState<PurchasedOrderOption | null>(null);

    // Whether the chosen type is a purchase order's freight bill, which is what
    // makes the PO picker (and the quantity-weighted split) worth offering.
    const isCogsDelivery = cogsDeliveryTypeIds.includes(
        Number(data.transaction_type_id),
    );

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

    // Keep the in/out direction pinned to the selected transaction type's nature.
    useEffect(() => {
        const derived = directionFor(data.transaction_type_id);
        if (derived !== data.type) setData('type', derived);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.transaction_type_id]);

    // Switching to a type that isn't a delivery fee retires the order: the
    // shares it produced stay put and stop tracking the amount, rather than a
    // hidden picker going on quietly rewriting them.
    useEffect(() => {
        if (!isCogsDelivery && purchasedOrder) setPurchasedOrder(null);
    }, [isCogsDelivery, purchasedOrder]);

    const totalAmount = parseFloat(data.amount) || 0;

    // The allocators work in `{key, amount}` rows; the payload keys each row by
    // user id / product name, so the two shapes are mapped at the boundary.
    const chargeToRows: Share[] = data.charge_to.map((r) => ({
        key: String(r.user_id),
        amount: r.amount,
    }));

    const productRows: Share[] = data.products.map((r) => ({
        key: r.product,
        amount: r.amount,
    }));

    // A product pulled from a fund request is a catalog name, which the Gencys
    // suggestion list need not contain — offer whatever is picked either way, or
    // the allocator would have nothing to render the selection with.
    const productOptions = React.useMemo(() => {
        const names = [...products, ...data.products.map((r) => r.product)];

        return [...new Set(names)].map((p) => ({ value: p, label: p }));
    }, [products, data.products]);

    /**
     * Re-cut the product shares by the selected order's quantities. Kept in an
     * effect rather than done once on pick so the split follows the amount:
     * typing the fee after choosing the order still lands correctly, as does
     * correcting it afterwards.
     */
    useEffect(() => {
        if (!purchasedOrder || purchasedOrder.products.length === 0) return;

        const shares = proportionalShares(
            totalAmount,
            purchasedOrder.products.map((p) => p.qty),
        );
        const next = purchasedOrder.products.map((p, i) => ({
            product: p.product,
            amount: shares[i],
        }));

        const unchanged =
            next.length === data.products.length &&
            next.every(
                (row, i) =>
                    row.product === data.products[i]?.product &&
                    row.amount === data.products[i]?.amount,
            );

        if (!unchanged) setData('products', next);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [purchasedOrder, totalAmount]);

    /**
     * Take an order's quantities as the split. The shares themselves are left to
     * the effect above, which is also what keeps them in step with the amount.
     */
    const pickPurchasedOrder = (order: PurchasedOrderOption | null) => {
        setPurchasedOrder(order);

        // Even-splitting would fight the quantity weighting; an order with no
        // quantities to weigh by has nothing to say, so the form carries on as
        // it did before.
        if (order && order.products.length > 0) setAutoSplitProducts(false);
    };

    /**
     * Product rows straight from the allocator. Adding or removing a product by
     * hand means the order's own list is no longer what is on screen, so the
     * split stops tracking it — the shares stay, they just stop being rewritten.
     */
    const setProductRows = (rows: Share[]) => {
        setData(
            'products',
            rows.map((r) => ({ product: r.key, amount: r.amount })),
        );

        if (!purchasedOrder) return;

        const picked = rows.map((r) => r.key).sort();
        const fromOrder = purchasedOrder.products.map((p) => p.product).sort();

        if (picked.join('\u0000') !== fromOrder.join('\u0000')) {
            setPurchasedOrder(null);
        }
    };

    /**
     * Fill the form in from an approved fund request. Everything stays editable
     * afterwards — this saves retyping the request into the ledger, it does not
     * bind the entry to it. The type/department fall back to whatever is already
     * set when the request carries none.
     */
    const pullFromFundRequest = (id: string) => {
        setData('fund_request_id', id ? Number(id) : '');

        const request = fundRequests.find((r) => String(r.id) === id);

        if (!request) return;

        // The request brings its own product shares — they replace whatever an
        // order had produced.
        setPurchasedOrder(null);
        setAutoSplit(false);
        setAutoSplitProducts(false);

        setData((current) => ({
            ...current,
            fund_request_id: request.id,
            amount: String(request.amount_requested ?? ''),
            reference_no: request.reference_no ?? '',
            transaction_type_id:
                request.transaction_type_id != null
                    ? String(request.transaction_type_id)
                    : current.transaction_type_id,
            department: request.department ?? current.department,
            charge_to: request.charge_to.map((row) => ({
                user_id: row.user_id,
                amount: Number(row.amount ?? 0).toFixed(2),
            })),
            products: request.products.map((row) => ({
                product: row.product_label,
                amount: Number(row.amount ?? 0).toFixed(2),
            })),
        }));
    };

    // The sum error lands on `charge_to`, per-row ones on `charge_to.0.user_id`.
    const fieldErrors = errors as Record<string, string | undefined>;
    const chargeToError =
        fieldErrors.charge_to ??
        Object.entries(fieldErrors).find(([key]) =>
            key.startsWith('charge_to.'),
        )?.[1];

    // Likewise the product sum lands on `products`, per-row on `products.0.product`.
    const productsError =
        fieldErrors.products ??
        Object.entries(fieldErrors).find(([key]) =>
            key.startsWith('products.'),
        )?.[1];

    // The shares have to account for the whole amount before this is worth
    // sending — the server rejects an entry whose shares don't (see
    // TransactionRequest::after()), and the rest of the amount would otherwise
    // belong to nobody. Raised on a submit attempt and cleared once it adds up.
    const [showShareErrors, setShowShareErrors] = React.useState(false);
    const chargeToRef = React.useRef<HTMLDivElement>(null);
    const productsRef = React.useRef<HTMLDivElement>(null);

    const chargeToBalanced = sharesBalanced(chargeToRows, totalAmount);
    const productsBalanced = sharesBalanced(productRows, totalAmount);

    const unbalancedMessage = (label: string, rows: Share[]) =>
        `The ${label} shares add up to ${money(
            allocatedTotal(rows),
        )}, but the amount is ${money(totalAmount)}. Allocate the remaining ${money(
            totalAmount - allocatedTotal(rows),
        )} before saving.`;

    useEffect(() => {
        if (!active) return;
        setShowShareErrors(false);
        // A saved entry's shares are whatever was saved; nothing links it back
        // to an order, so the picker starts empty either way.
        setPurchasedOrder(null);
        if (transaction) {
            // A saved balance and saved shares are left exactly as they were.
            setAutoBalance(
                transaction.running_balance == null ||
                    transaction.running_balance === '',
            );
            setAutoSplit((transaction.charge_to_users ?? []).length === 0);
            setAutoSplitProducts(
                (transaction.product_shares ?? []).length === 0,
            );
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
                products: (transaction.product_shares ?? []).map((p) => ({
                    product: p.product,
                    amount: Number(p.amount ?? 0).toFixed(2),
                })),
                // (numeric user ids; see FinanceTransaction)
                type: transaction.type,
                transaction_type_id:
                    transaction.transaction_type_id != null
                        ? String(transaction.transaction_type_id)
                        : defaultTypeId,
                amount: String(transaction.amount ?? ''),
                running_balance: String(transaction.running_balance ?? ''),
                reference_no: transaction.reference_no ?? '',
                fund_request_id: transaction.fund_request_id ?? '',
                status: transaction.status ?? 'posted',
                position: String(transaction.position ?? ''),
                notes: transaction.notes ?? '',
            });
        } else {
            reset();
            clearErrors();
            setAutoBalance(true);
            setAutoSplit(true);
            setAutoSplitProducts(true);
            if (defaults?.account_id)
                setData('account_id', String(defaults.account_id));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [active, transaction]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!chargeToBalanced || !productsBalanced) {
            setShowShareErrors(true);
            (chargeToBalanced
                ? productsRef
                : chargeToRef
            ).current?.scrollIntoView({ behavior: 'smooth', block: 'center' });

            return;
        }

        setShowShareErrors(false);
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
                        {data.transaction_type_id && (
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                Recorded as{' '}
                                {data.type === 'in'
                                    ? 'Credit (money in)'
                                    : 'Debit (money out)'}
                                , from this type&apos;s nature.
                            </p>
                        )}
                    </Field>
                    <Wide>
                        <Field
                            label="Fund Request"
                            error={errors.fund_request_id}
                        >
                            <select
                                value={String(data.fund_request_id)}
                                onChange={(e) =>
                                    pullFromFundRequest(e.target.value)
                                }
                                className={inputCls}
                            >
                                <option value="">
                                    {fundRequests.length
                                        ? 'Not from a fund request'
                                        : 'No approved fund requests'}
                                </option>
                                {fundRequests.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.reference_no} (
                                        {money(r.amount_requested)})
                                    </option>
                                ))}
                            </select>
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                Picking one fills in the type, department,
                                amount, reference, charged people and products
                                from the request. Everything stays editable
                                afterwards.
                            </p>
                        </Field>
                    </Wide>

                    <Wide>
                        <Field
                            label="Transaction Description"
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

                    <Wide ref={chargeToRef}>
                        <ShareAllocator
                            label="Charge To"
                            options={users.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            }))}
                            rows={chargeToRows}
                            total={totalAmount}
                            onChange={(rows) =>
                                setData(
                                    'charge_to',
                                    rows.map((r) => ({
                                        user_id: Number(r.key),
                                        amount: r.amount,
                                    })),
                                )
                            }
                            placeholder="No one charged"
                            error={
                                chargeToError ??
                                (showShareErrors && !chargeToBalanced
                                    ? unbalancedMessage(
                                          'charge-to',
                                          chargeToRows,
                                      )
                                    : undefined)
                            }
                            autoSplit={autoSplit}
                            onAutoSplitChange={setAutoSplit}
                            hint="Charged to several people? The amount is split between them — edit a share to divide it your way. Every share must be filled in and they have to add up to the amount, or this can’t be saved; “Split equally” divides it back evenly."
                        />
                    </Wide>

                    {isCogsDelivery && (
                        <Wide>
                            <PurchasedOrderPicker
                                workspaceSlug={workspaceSlug}
                                selected={purchasedOrder}
                                onSelect={pickPurchasedOrder}
                            />
                        </Wide>
                    )}

                    <Wide ref={productsRef}>
                        <ShareAllocator
                            label="Product"
                            options={productOptions}
                            rows={productRows}
                            total={totalAmount}
                            onChange={setProductRows}
                            placeholder="No product"
                            error={
                                productsError ??
                                (showShareErrors && !productsBalanced
                                    ? unbalancedMessage('product', productRows)
                                    : undefined)
                            }
                            autoSplit={autoSplitProducts}
                            onAutoSplitChange={(auto) => {
                                // Reached only from editing a share or hitting
                                // "Split equally" — both are the user taking the
                                // allocation over from the order.
                                setAutoSplitProducts(auto);
                                setPurchasedOrder(null);
                            }}
                            hint="Attribute this entry to one or more products for the per-product income statement. Split across several? Edit a share to divide it your way — every share must be filled in and they have to add up to the amount, or this can’t be saved."
                        />
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
