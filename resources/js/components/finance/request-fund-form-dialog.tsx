import {
    Field,
    Footer,
    inputCls,
} from '@/components/finance/account-form-dialog';
import { Share, ShareAllocator } from '@/components/finance/share-allocator';
import {
    buildTransactionTypeOptions,
    TransactionTypeItem,
} from '@/components/finance/transaction-type';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import React, { useEffect } from 'react';

interface UserOption {
    id: number;
    name: string;
}

/** A product option for the product-share picker. */
export interface ProductOption {
    id: number;
    name: string;
}

/** A department option for the department select. */
export interface DepartmentOption {
    id: number;
    name: string;
}

/** A user the request is charged to, with their share of the amount. */
export interface ChargedUser {
    id: number;
    name: string;
    pivot: { amount: number | string };
}

/** A product the request covers, with its share of the amount. */
export interface RequestFundProduct {
    product_id: number | null;
    product_label: string;
    amount: number | string;
}

/** One allocation row as the form submits it. A blank amount is split evenly. */
export interface ChargeToShare {
    user_id: number;
    amount: string;
}

export interface ProductShare {
    product_id: number;
    amount: string;
}

export interface RequestFund {
    id: number;
    request_date: string;
    reference_no: string;
    requested_by: number;
    transaction_type_id: number | null;
    transactionType?: { id: number; name: string } | null;
    department_id: number | null;
    department?: { id: number; name: string } | null;
    charge_to_users?: ChargedUser[];
    product_shares?: RequestFundProduct[];
    amount_requested: number | string;
    approved_by: number | null;
    status: string;
    remarks: string | null;
    requester?: UserOption | null;
    approver?: UserOption | null;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    requestFund?: RequestFund | null;
    workspaceSlug: string;
    users: UserOption[];
    products: ProductOption[];
    transactionTypes: TransactionTypeItem[];
    departments: DepartmentOption[];
}

export function RequestFundFormDialog({
    open,
    onOpenChange,
    requestFund,
    workspaceSlug,
    users,
    products,
    transactionTypes,
    departments,
}: Props) {
    const isEditing = !!requestFund;
    const typeOptions = buildTransactionTypeOptions(transactionTypes);
    const defaultTypeId = typeOptions[0]?.value ?? '';

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm({
            transaction_type_id: defaultTypeId,
            department_id: '' as number | '',
            charge_to: [] as ChargeToShare[],
            products: [] as ProductShare[],
            amount_requested: '',
            remarks: '',
        });

    // The charge-to and product shares must add up to the amount requested.
    const total = parseFloat(data.amount_requested) || 0;

    // The shares carry on splitting evenly until someone types their own figure.
    // A saved set is left exactly as it was.
    const [autoSplit, setAutoSplit] = React.useState(true);
    const [autoSplitProducts, setAutoSplitProducts] = React.useState(true);

    const chargeToRows: Share[] = data.charge_to.map((r) => ({
        key: String(r.user_id),
        amount: r.amount,
    }));

    const productRows: Share[] = data.products.map((r) => ({
        key: String(r.product_id),
        amount: r.amount,
    }));

    // The sum error lands on `charge_to`, per-row ones on `charge_to.0.user_id`.
    const fieldErrors = errors as Record<string, string | undefined>;

    const errorFor = (field: string) =>
        fieldErrors[field] ??
        Object.entries(fieldErrors).find(([key]) =>
            key.startsWith(`${field}.`),
        )?.[1];

    useEffect(() => {
        if (!open) return;
        if (requestFund) {
            setAutoSplit((requestFund.charge_to_users ?? []).length === 0);
            setAutoSplitProducts(
                (requestFund.product_shares ?? []).length === 0,
            );
            setData({
                transaction_type_id:
                    requestFund.transaction_type_id != null
                        ? String(requestFund.transaction_type_id)
                        : defaultTypeId,
                department_id: requestFund.department_id ?? '',
                charge_to: (requestFund.charge_to_users ?? []).map((u) => ({
                    user_id: u.id,
                    amount: Number(u.pivot?.amount ?? 0).toFixed(2),
                })),
                products: (requestFund.product_shares ?? [])
                    .filter((p) => p.product_id !== null)
                    .map((p) => ({
                        product_id: p.product_id as number,
                        amount: Number(p.amount ?? 0).toFixed(2),
                    })),
                amount_requested: String(requestFund.amount_requested ?? ''),
                remarks: requestFund.remarks ?? '',
            });
        } else {
            reset();
            clearErrors();
            setAutoSplit(true);
            setAutoSplitProducts(true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, requestFund]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };
        const base = `/workspaces/${workspaceSlug}/finance/request-funds`;
        if (isEditing) {
            put(`${base}/${requestFund!.id}`, options);
        } else {
            post(base, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-3xl dark:bg-zinc-900">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing
                                ? 'Edit Fund Request'
                                : 'New Fund Request'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {isEditing ? (
                                <>
                                    <span className="font-mono text-gray-500 dark:text-gray-400">
                                        {requestFund?.reference_no}
                                    </span>{' '}
                                    · Update this fund request’s details.
                                </>
                            ) : (
                                'Create a new request for funds. A reference number, the date and the requester are assigned automatically.'
                            )}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="max-h-[65vh] space-y-5 overflow-y-auto px-5 py-4">
                        <Field label="Type" error={errors.transaction_type_id}>
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

                        <Field label="Department" error={errors.department_id}>
                            <select
                                value={data.department_id}
                                onChange={(e) =>
                                    setData(
                                        'department_id',
                                        e.target.value
                                            ? Number(e.target.value)
                                            : '',
                                    )
                                }
                                className={inputCls}
                            >
                                <option value="">Select…</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            label="Amount Requested"
                            required
                            error={errors.amount_requested}
                        >
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.amount_requested}
                                onChange={(e) =>
                                    setData('amount_requested', e.target.value)
                                }
                                className={inputCls}
                            />
                        </Field>

                        <ShareAllocator
                            label="Charge To"
                            required
                            options={users.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            }))}
                            rows={chargeToRows}
                            total={total}
                            onChange={(rows) =>
                                setData(
                                    'charge_to',
                                    rows.map((r) => ({
                                        user_id: Number(r.key),
                                        amount: r.amount,
                                    })),
                                )
                            }
                            placeholder="Select…"
                            error={errorFor('charge_to')}
                            autoSplit={autoSplit}
                            onAutoSplitChange={setAutoSplit}
                            hint="Who the request applies to. Charged to several people? The amount is split between them — edit a share to divide it your way, or leave one blank to give it the remainder."
                        />

                        <ShareAllocator
                            label="Products Involved"
                            options={products.map((p) => ({
                                value: String(p.id),
                                label: p.name,
                            }))}
                            rows={productRows}
                            total={total}
                            onChange={(rows) =>
                                setData(
                                    'products',
                                    rows.map((r) => ({
                                        product_id: Number(r.key),
                                        amount: r.amount,
                                    })),
                                )
                            }
                            placeholder="No product"
                            error={errorFor('products')}
                            autoSplit={autoSplitProducts}
                            onAutoSplitChange={setAutoSplitProducts}
                            hint="The products this request covers. Optional — leave empty if it isn’t for any product in particular."
                        />

                        {isEditing && requestFund?.approver && (
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                {requestFund.status} · approved by{' '}
                                {requestFund.approver.name}
                            </p>
                        )}

                        <Field label="Remarks" error={errors.remarks}>
                            <textarea
                                value={data.remarks ?? ''}
                                onChange={(e) =>
                                    setData('remarks', e.target.value)
                                }
                                className={`${inputCls} min-h-[60px] resize-none py-2`}
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
