import {
    Field,
    Footer,
    inputCls,
} from '@/components/finance/account-form-dialog';
import {
    allocatedTotal,
    Share,
    ShareAllocator,
    sharesBalanced,
} from '@/components/finance/share-allocator';
import { Section, Wide } from '@/components/finance/transaction-form';
import {
    buildTransactionTypeOptions,
    TransactionTypeItem,
} from '@/components/finance/transaction-type';
import { Checkbox } from '@/components/ui/checkbox';
import { Switch } from '@/components/ui/switch';
import { Link, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Circle,
    Download,
    FileText,
    Paperclip,
    Plus,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';

export interface UserOption {
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

/** A stored fund request status as shown, e.g. `for_approval` → "For Approval". */
export const fundRequestStatusLabel = (status: string) =>
    status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

/** A way the funds can be released; `needs_account` ones go to an account. */
export interface PaymentMethodOption {
    value: string;
    label: string;
    needs_account: boolean;
}

/** A line item of a request; `amount` is quantity × unit price. */
export interface FundRequestParticular {
    id: number;
    name: string;
    quantity: number | string;
    unit_price: number | string;
    amount: number | string;
}

/** A particular as the form edits and submits it. */
interface ParticularRow {
    name: string;
    quantity: string;
    unit_price: string;
}

/** One allocation row as the form submits it. */
export interface ChargeToShare {
    user_id: number;
    amount: string;
}

export interface ProductShare {
    product_id: number;
    amount: string;
}

interface NamedItem {
    id: number;
    name: string;
}

/** A transaction type with the attachments and checklist it calls for. */
export interface FundRequestType extends TransactionTypeItem {
    attachments: NamedItem[];
    checklists: NamedItem[];
}

/** A file already uploaded against one of the request's attachments. */
export interface FundRequestFile {
    id: number;
    attachment_requirement_id: number;
    file_name: string;
    size: number;
    url: string;
}

export interface RequestFund {
    id: number;
    request_date: string;
    reference_no: string;
    requested_by: number;
    transaction_type_id: number | null;
    // Relations serialize snake_case.
    transaction_type?: { id: number; name: string } | null;
    department_id: number | null;
    department?: { id: number; name: string } | null;
    charge_to_users?: ChargedUser[];
    product_shares?: RequestFundProduct[];
    particulars?: FundRequestParticular[];
    amount_requested: number | string;
    liquidation_required: boolean;
    liquidation_deadline: string | null;
    payment_method: string | null;
    bank_name: string | null;
    account_name: string | null;
    account_number: string | null;
    approved_by: number | null;
    status: string;
    remarks: string | null;
    requester?: UserOption | null;
    approver?: UserOption | null;
    // Only on the edit page.
    checklist_ids?: number[];
    files?: FundRequestFile[];
}

interface Props {
    requestFund?: RequestFund | null;
    workspaceSlug: string;
    users: UserOption[];
    products: ProductOption[];
    transactionTypes: FundRequestType[];
    departments: DepartmentOption[];
    paymentMethods: PaymentMethodOption[];
    onCancel: () => void;
}

const ACCEPT =
    '.jpg,.jpeg,.png,.webp,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv';

const money = (n: number) =>
    n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const round2 = (n: number) => Math.round(n * 100) / 100;

/** A particular's amount, as the server works it out: quantity × unit price. */
const particularAmount = (row: ParticularRow) =>
    round2(
        round2(parseFloat(row.quantity) || 0) *
            round2(parseFloat(row.unit_price) || 0),
    );

const emptyParticular = (): ParticularRow => ({
    name: '',
    quantity: '1',
    unit_price: '',
});

/**
 * The particulars to start the form with: the saved ones, or — for a request
 * saved before particulars existed — one row carrying its amount, to be named.
 */
const initialParticulars = (
    requestFund?: RequestFund | null,
): ParticularRow[] => {
    if (requestFund?.particulars?.length) {
        return requestFund.particulars.map((p) => ({
            name: p.name,
            quantity: String(Number(p.quantity)),
            unit_price: Number(p.unit_price).toFixed(2),
        }));
    }
    if (requestFund && Number(requestFund.amount_requested) > 0) {
        return [
            {
                name: '',
                quantity: '1',
                unit_price: Number(requestFund.amount_requested).toFixed(2),
            },
        ];
    }
    return [emptyParticular()];
};

const fileSize = (bytes: number) =>
    bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;

export function FundRequestForm({
    requestFund,
    workspaceSlug,
    users,
    products,
    transactionTypes,
    departments,
    paymentMethods,
    onCancel,
}: Props) {
    const isEditing = !!requestFund;
    const typeOptions = buildTransactionTypeOptions(transactionTypes);
    const base = `/workspaces/${workspaceSlug}/finance/request-funds`;

    const { data, setData, post, transform, processing, errors } = useForm({
        transaction_type_id:
            requestFund?.transaction_type_id != null
                ? String(requestFund.transaction_type_id)
                : (typeOptions[0]?.value ?? ''),
        department_id: (requestFund?.department_id ?? '') as number | '',
        // The amount requested is their total, worked out by the server.
        particulars: initialParticulars(requestFund),
        liquidation_required: requestFund?.liquidation_required ?? false,
        liquidation_deadline: requestFund?.liquidation_deadline ?? '',
        payment_method: requestFund?.payment_method ?? '',
        bank_name: requestFund?.bank_name ?? '',
        account_name: requestFund?.account_name ?? '',
        account_number: requestFund?.account_number ?? '',
        charge_to: (requestFund?.charge_to_users ?? []).map((u) => ({
            user_id: u.id,
            amount: Number(u.pivot?.amount ?? 0).toFixed(2),
        })) as ChargeToShare[],
        products: (requestFund?.product_shares ?? [])
            .filter((p) => p.product_id !== null)
            .map((p) => ({
                product_id: p.product_id as number,
                amount: Number(p.amount ?? 0).toFixed(2),
            })) as ProductShare[],
        remarks: requestFund?.remarks ?? '',
        checklist_ids: (requestFund?.checklist_ids ?? []) as number[],
        // New files, keyed by the attachment they answer.
        attachments: {} as Record<string, File>,
        // Attachments whose saved file should be removed.
        remove_attachments: [] as number[],
    });

    const type = transactionTypes.find(
        (t) => String(t.id) === data.transaction_type_id,
    );
    const savedFiles = requestFund?.files ?? [];

    // The shares carry on splitting evenly until someone types their own
    // figure. A saved set is left exactly as it was.
    const [autoSplit, setAutoSplit] = useState(
        (requestFund?.charge_to_users ?? []).length === 0,
    );
    const [autoSplitProducts, setAutoSplitProducts] = useState(
        (requestFund?.product_shares ?? []).length === 0,
    );

    const total = round2(
        data.particulars.reduce((sum, row) => sum + particularAmount(row), 0),
    );

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

    // Checked before sending, as on the transaction form: the server rejects
    // shares that don't add up to the amount requested.
    const [showShareErrors, setShowShareErrors] = useState(false);
    const chargeToRef = useRef<HTMLDivElement>(null);
    const productsRef = useRef<HTMLDivElement>(null);
    const chargeToBalanced = sharesBalanced(chargeToRows, total);
    const productsBalanced = sharesBalanced(productRows, total);

    const unbalancedMessage = (label: string, rows: Share[]) =>
        `The ${label} shares add up to ${money(
            allocatedTotal(rows),
        )}, but the amount is ${money(total)}. Allocate the remaining ${money(
            total - allocatedTotal(rows),
        )} before saving.`;

    // A different type calls for different attachments and a different
    // checklist, so what was picked for the old one no longer applies. Saved
    // files of the old type are dropped by the server on save.
    const changeType = (id: string) => {
        setData((current) => ({
            ...current,
            transaction_type_id: id,
            checklist_ids: [],
            attachments: {},
            remove_attachments: [],
        }));
    };

    const toggleChecklist = (id: number, on: boolean) =>
        setData(
            'checklist_ids',
            on
                ? [...data.checklist_ids, id]
                : data.checklist_ids.filter((x) => x !== id),
        );

    const pickFile = (attachmentId: number, file: File | null) => {
        const next = { ...data.attachments };
        if (file) next[attachmentId] = file;
        else delete next[attachmentId];
        setData('attachments', next);
    };

    const removeSaved = (attachmentId: number, removed: boolean) =>
        setData(
            'remove_attachments',
            removed
                ? [...data.remove_attachments, attachmentId]
                : data.remove_attachments.filter((x) => x !== attachmentId),
        );

    useEffect(() => {
        if (chargeToBalanced && productsBalanced) setShowShareErrors(false);
    }, [chargeToBalanced, productsBalanced]);

    // Every attachment the type calls for needs a file — a new upload, or the
    // one already on file unless it is being removed. Enforced by the server
    // too (FundRequestRequest::after()); checked here so the gaps show at once.
    const attachments = type?.attachments ?? [];
    const hasFile = (attachmentId: number) =>
        !!data.attachments[attachmentId] ||
        savedFiles.some(
            (f) =>
                f.attachment_requirement_id === attachmentId &&
                !data.remove_attachments.includes(attachmentId),
        );
    const missingAttachments = attachments.filter((a) => !hasFile(a.id));
    const attachedCount = attachments.length - missingAttachments.length;
    const [showAttachmentErrors, setShowAttachmentErrors] = useState(false);
    const attachmentsRef = useRef<HTMLDivElement>(null);

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

        if (missingAttachments.length > 0) {
            setShowAttachmentErrors(true);
            attachmentsRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });

            return;
        }

        // Files only travel as multipart, which PUT can't carry — so an edit is
        // POSTed with Laravel's method spoofing instead.
        transform((d) => (isEditing ? { ...d, _method: 'put' } : d));
        post(isEditing ? `${base}/${requestFund!.id}` : base, {
            preserveScroll: true,
        });
    };

    const needsAccount = !!paymentMethods.find(
        (m) => m.value === data.payment_method,
    )?.needs_account;
    const isEWallet = data.payment_method === 'e_wallet';

    const checklists = type?.checklists ?? [];
    const managementUrl = `/workspaces/${workspaceSlug}/finance/management`;

    return (
        <form onSubmit={handleSubmit}>
            <div className="divide-y divide-black/6 dark:divide-white/6">
                <Section
                    title="Request"
                    hint="What kind of request this is and which department asks for it."
                >
                    <Field label="Type" error={errors.transaction_type_id}>
                        <select
                            value={data.transaction_type_id}
                            onChange={(e) => changeType(e.target.value)}
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

                    {isEditing && (
                        <Wide>
                            <p className="flex items-center gap-2 font-mono text-[11px] text-gray-400">
                                Status
                                <span className="rounded-full bg-stone-100 px-2.5 py-0.5 text-gray-600 dark:bg-zinc-800 dark:text-gray-300">
                                    {requestFund?.status &&
                                        fundRequestStatusLabel(
                                            requestFund.status,
                                        )}
                                </span>
                                {requestFund?.approver &&
                                    `approved by ${requestFund.approver.name}`}
                            </p>
                        </Wide>
                    )}
                </Section>

                <Section
                    title="Particulars"
                    hint="What the funds are for, line by line. Each amount is the quantity × unit price; together they make the amount requested."
                >
                    <Wide>
                        <ParticularsEditor
                            rows={data.particulars}
                            onChange={(rows) => setData('particulars', rows)}
                            errors={fieldErrors}
                            total={total}
                        />
                    </Wide>
                </Section>

                <Section
                    title="Payment"
                    hint="How the funds are to be released. Online banking and e-wallets need the account to send them to."
                >
                    <Wide>
                        <Field
                            label="Payment Method"
                            required
                            error={errors.payment_method}
                        >
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                {paymentMethods.map((m) => {
                                    const active =
                                        data.payment_method === m.value;
                                    return (
                                        <button
                                            key={m.value}
                                            type="button"
                                            onClick={() =>
                                                setData(
                                                    'payment_method',
                                                    m.value,
                                                )
                                            }
                                            className={`h-10 rounded-[10px] border font-mono text-[12px] transition-all ${
                                                active
                                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                    : 'border-black/6 bg-stone-50 text-gray-500 hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-400'
                                            }`}
                                        >
                                            {m.label}
                                        </button>
                                    );
                                })}
                            </div>
                        </Field>
                    </Wide>

                    {needsAccount && (
                        <Wide>
                            <div className="grid grid-cols-1 gap-4 rounded-[12px] border border-black/6 bg-stone-50/60 p-4 sm:grid-cols-2 dark:border-white/6 dark:bg-zinc-800/40">
                                <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase sm:col-span-2">
                                    Send to
                                </p>
                                <div className="sm:col-span-2">
                                    <Field
                                        label={isEWallet ? 'E-Wallet' : 'Bank'}
                                        required
                                        error={errors.bank_name}
                                    >
                                        <input
                                            type="text"
                                            value={data.bank_name}
                                            onChange={(e) =>
                                                setData(
                                                    'bank_name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={
                                                isEWallet
                                                    ? 'e.g. GCash'
                                                    : 'e.g. BDO'
                                            }
                                            className={inputCls}
                                        />
                                    </Field>
                                </div>
                                <Field
                                    label="Account Name"
                                    required
                                    error={errors.account_name}
                                >
                                    <input
                                        type="text"
                                        value={data.account_name}
                                        onChange={(e) =>
                                            setData(
                                                'account_name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. Juan dela Cruz"
                                        className={inputCls}
                                    />
                                </Field>
                                <Field
                                    label="Account Number"
                                    required
                                    error={errors.account_number}
                                >
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        value={data.account_number}
                                        onChange={(e) =>
                                            setData(
                                                'account_number',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={
                                            isEWallet
                                                ? 'e.g. 09171234567'
                                                : 'e.g. 001234567890'
                                        }
                                        className={inputCls}
                                    />
                                </Field>
                            </div>
                        </Wide>
                    )}
                </Section>

                <Section
                    title="Liquidation"
                    hint="Whether the requester has to account for how the funds were spent, and by when."
                >
                    <Wide>
                        <div className="rounded-[12px] border border-black/6 bg-stone-50/60 dark:border-white/6 dark:bg-zinc-800/40">
                            <label className="flex cursor-pointer items-center justify-between gap-3 p-4">
                                <span className="text-[12px] leading-snug text-gray-600 dark:text-gray-300">
                                    <span className="font-medium text-gray-800 dark:text-gray-100">
                                        Liquidation required
                                    </span>
                                    <br />
                                    Receipts or a report are due after the funds
                                    are released.
                                </span>
                                <Switch
                                    checked={data.liquidation_required}
                                    onCheckedChange={(v) =>
                                        setData('liquidation_required', v)
                                    }
                                />
                            </label>
                            {data.liquidation_required && (
                                <div className="border-t border-black/6 p-4 sm:max-w-xs dark:border-white/6">
                                    <Field
                                        label="Liquidation Deadline"
                                        required
                                        error={errors.liquidation_deadline}
                                    >
                                        <input
                                            type="date"
                                            value={data.liquidation_deadline}
                                            onChange={(e) =>
                                                setData(
                                                    'liquidation_deadline',
                                                    e.target.value,
                                                )
                                            }
                                            className={inputCls}
                                        />
                                    </Field>
                                </div>
                            )}
                        </div>
                    </Wide>
                </Section>

                <Section
                    title="Allocation"
                    hint="Who the request is charged to and the products it covers. Pick as many as apply — the amount is split between them."
                >
                    <Wide ref={chargeToRef}>
                        <ShareAllocator
                            label="Charge To"
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
                            placeholder="Select people…"
                            error={
                                errorFor('charge_to') ??
                                (showShareErrors && !chargeToBalanced
                                    ? unbalancedMessage(
                                          'charge-to',
                                          chargeToRows,
                                      )
                                    : undefined)
                            }
                            autoSplit={autoSplit}
                            onAutoSplitChange={setAutoSplit}
                            hint="Required. Charged to several people? Edit a share to divide it your way — the shares have to add up to the amount; “Split equally” divides it back evenly."
                        />
                    </Wide>

                    <Wide ref={productsRef}>
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
                            placeholder="Select products…"
                            error={
                                errorFor('products') ??
                                (showShareErrors && !productsBalanced
                                    ? unbalancedMessage('product', productRows)
                                    : undefined)
                            }
                            autoSplit={autoSplitProducts}
                            onAutoSplitChange={setAutoSplitProducts}
                            hint="Optional — leave empty if it isn’t for any product in particular."
                        />
                    </Wide>
                </Section>

                <Section
                    title="Attachments"
                    hint="Required — upload every document this type of request calls for before saving. Images, PDF, Word, Excel or CSV, up to 10 MB each."
                >
                    <Wide ref={attachmentsRef}>
                        {attachments.length === 0 ? (
                            <EmptyRequirement
                                what="attachments"
                                href={managementUrl}
                            />
                        ) : (
                            <div className="space-y-2">
                                <p
                                    className={`font-mono text-[10px] ${
                                        showAttachmentErrors &&
                                        missingAttachments.length > 0
                                            ? 'text-red-500'
                                            : 'text-gray-400'
                                    }`}
                                >
                                    {attachedCount} of {attachments.length}{' '}
                                    attached
                                    {showAttachmentErrors &&
                                        missingAttachments.length > 0 &&
                                        ` — ${missingAttachments.length} still missing`}
                                </p>
                                {attachments.map((a) => (
                                    <AttachmentSlot
                                        key={a.id}
                                        name={a.name}
                                        saved={savedFiles.find(
                                            (f) =>
                                                f.attachment_requirement_id ===
                                                a.id,
                                        )}
                                        removed={data.remove_attachments.includes(
                                            a.id,
                                        )}
                                        picked={data.attachments[a.id] ?? null}
                                        error={
                                            fieldErrors[
                                                `attachments.${a.id}`
                                            ] ??
                                            (showAttachmentErrors &&
                                            !hasFile(a.id)
                                                ? `Upload the ${a.name}.`
                                                : undefined)
                                        }
                                        onPick={(f) => pickFile(a.id, f)}
                                        onRemoveSaved={(r) =>
                                            removeSaved(a.id, r)
                                        }
                                    />
                                ))}
                            </div>
                        )}
                    </Wide>
                </Section>

                {/* A type with no checklist shows no Checklist section. */}
                {checklists.length > 0 && (
                    <Section
                        title="Checklist"
                        hint="Tick off each item this type of request is checked against."
                    >
                        <Wide>
                            <div className="space-y-1">
                                <p className="mb-2 font-mono text-[10px] text-gray-400">
                                    {
                                        checklists.filter((c) =>
                                            data.checklist_ids.includes(c.id),
                                        ).length
                                    }{' '}
                                    of {checklists.length} done
                                </p>
                                {checklists.map((c) => (
                                    <label
                                        key={c.id}
                                        className="flex cursor-pointer items-center gap-3 rounded-[10px] border border-black/6 bg-stone-50 px-3 py-2.5 transition-all hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800/60 dark:hover:bg-zinc-800"
                                    >
                                        <Checkbox
                                            checked={data.checklist_ids.includes(
                                                c.id,
                                            )}
                                            onCheckedChange={(v) =>
                                                toggleChecklist(
                                                    c.id,
                                                    v === true,
                                                )
                                            }
                                        />
                                        <span className="text-[13px] text-gray-700 dark:text-gray-200">
                                            {c.name}
                                        </span>
                                    </label>
                                ))}
                                {errorFor('checklist_ids') && (
                                    <p className="text-[11px] text-red-500">
                                        {errorFor('checklist_ids')}
                                    </p>
                                )}
                            </div>
                        </Wide>
                    </Section>
                )}

                <Section title="Notes" hint="Any additional remarks.">
                    <Wide>
                        <Field label="Remarks" error={errors.remarks}>
                            <textarea
                                value={data.remarks ?? ''}
                                onChange={(e) =>
                                    setData('remarks', e.target.value)
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

function EmptyRequirement({ what, href }: { what: string; href: string }) {
    return (
        <p className="rounded-[10px] border border-dashed border-black/10 px-3 py-4 text-center text-[12px] text-gray-400 dark:border-white/10">
            This type has no {what}.{' '}
            <Link href={href} className="text-emerald-600 hover:underline">
                Set them up in Management
            </Link>
            .
        </p>
    );
}

/**
 * One attachment the type calls for: shows the saved file (download / remove),
 * a newly picked one, or an upload button.
 */
function AttachmentSlot({
    name,
    saved,
    removed,
    picked,
    error,
    onPick,
    onRemoveSaved,
}: {
    name: string;
    saved?: FundRequestFile;
    removed: boolean;
    picked: File | null;
    error?: string;
    onPick: (file: File | null) => void;
    onRemoveSaved: (removed: boolean) => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    const current = saved && !removed ? saved : null;
    const done = !!picked || !!current;

    return (
        <div
            className={`rounded-[10px] border px-3 py-2.5 ${
                error
                    ? 'border-red-300 dark:border-red-500/40'
                    : 'border-black/6 dark:border-white/6'
            } bg-stone-50 dark:bg-zinc-800/60`}
        >
            <div className="flex items-center gap-3">
                {done ? (
                    <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-500" />
                ) : (
                    <Circle className="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" />
                )}
                <div className="min-w-0 flex-1">
                    <p className="text-[13px] font-medium text-gray-800 dark:text-gray-100">
                        {name}
                        <span className="ml-0.5 text-red-500">*</span>
                    </p>
                    {picked ? (
                        <p className="flex items-center gap-1 truncate font-mono text-[11px] text-emerald-600 dark:text-emerald-400">
                            <Paperclip className="h-3 w-3 shrink-0" />
                            {picked.name} · {fileSize(picked.size)} · new
                        </p>
                    ) : current ? (
                        <a
                            href={current.url}
                            target="_blank"
                            rel="noreferrer"
                            className="flex items-center gap-1 truncate font-mono text-[11px] text-gray-500 hover:text-emerald-600 dark:text-gray-400"
                        >
                            <FileText className="h-3 w-3 shrink-0" />
                            {current.file_name} · {fileSize(current.size)}
                            <Download className="h-3 w-3 shrink-0" />
                        </a>
                    ) : removed ? (
                        <p className="font-mono text-[11px] text-amber-600">
                            {saved?.file_name} will be removed ·{' '}
                            <button
                                type="button"
                                onClick={() => onRemoveSaved(false)}
                                className="underline"
                            >
                                undo
                            </button>
                        </p>
                    ) : (
                        <p className="font-mono text-[11px] text-gray-400">
                            No file yet
                        </p>
                    )}
                </div>

                <input
                    ref={input}
                    type="file"
                    accept={ACCEPT}
                    className="hidden"
                    onChange={(e) => {
                        onPick(e.target.files?.[0] ?? null);
                        e.target.value = '';
                    }}
                />
                {picked ? (
                    <button
                        type="button"
                        onClick={() => onPick(null)}
                        className="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-stone-200 hover:text-gray-600 dark:hover:bg-zinc-700"
                        aria-label={`Clear ${name}`}
                    >
                        <X className="h-4 w-4" />
                    </button>
                ) : (
                    current && (
                        <button
                            type="button"
                            onClick={() => onRemoveSaved(true)}
                            className="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40"
                            aria-label={`Remove ${name}`}
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )
                )}
                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    className="flex h-8 shrink-0 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3 font-mono! text-[11px]! text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-zinc-700"
                >
                    <Upload className="h-3.5 w-3.5" />
                    {done ? 'Replace' : 'Upload'}
                </button>
            </div>
            {error && (
                <p className="mt-1.5 text-[11px] text-red-500">{error}</p>
            )}
        </div>
    );
}

/**
 * The request's line items: name, quantity and unit price per row, with the
 * row's amount and the running total worked out as they are typed.
 */
function ParticularsEditor({
    rows,
    onChange,
    errors,
    total,
}: {
    rows: ParticularRow[];
    onChange: (rows: ParticularRow[]) => void;
    errors: Record<string, string | undefined>;
    total: number;
}) {
    const update = (index: number, field: keyof ParticularRow, value: string) =>
        onChange(
            rows.map((row, i) =>
                i === index ? { ...row, [field]: value } : row,
            ),
        );

    const rowError = (index: number) =>
        errors[`particulars.${index}.name`] ??
        errors[`particulars.${index}.quantity`] ??
        errors[`particulars.${index}.unit_price`];

    const cols =
        'sm:grid-cols-[minmax(0,1fr)_88px_128px_120px_36px] sm:items-center';

    return (
        <div className="overflow-hidden rounded-[12px] border border-black/6 dark:border-white/6">
            <div
                className={`hidden gap-2 border-b border-black/6 bg-stone-50 px-3 py-2 font-mono text-[10px] tracking-wider text-gray-400 uppercase sm:grid dark:border-white/6 dark:bg-zinc-800/60 ${cols}`}
            >
                <span>
                    Name<span className="ml-0.5 text-red-500">*</span>
                </span>
                <span>
                    Qty<span className="ml-0.5 text-red-500">*</span>
                </span>
                <span>
                    Unit Price<span className="ml-0.5 text-red-500">*</span>
                </span>
                <span className="text-right">Amount</span>
                <span />
            </div>

            <div className="divide-y divide-black/6 dark:divide-white/6">
                {rows.map((row, index) => (
                    <div key={index} className="px-3 py-2.5">
                        <div
                            className={`grid grid-cols-[1fr_1fr_36px] gap-2 ${cols}`}
                        >
                            <input
                                type="text"
                                value={row.name}
                                onChange={(e) =>
                                    update(index, 'name', e.target.value)
                                }
                                placeholder="e.g. Bond paper"
                                aria-label="Particular name"
                                className={`${inputCls} col-span-2 sm:col-span-1`}
                            />
                            <button
                                type="button"
                                onClick={() =>
                                    onChange(rows.filter((_, i) => i !== index))
                                }
                                disabled={rows.length === 1}
                                className="flex h-10 w-9 items-center justify-center rounded-lg text-gray-400 transition-all hover:bg-red-50 hover:text-red-600 disabled:pointer-events-none disabled:opacity-30 sm:order-last dark:hover:bg-red-950/40"
                                aria-label="Remove particular"
                            >
                                <Trash2 className="h-4 w-4" />
                            </button>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={row.quantity}
                                onChange={(e) =>
                                    update(index, 'quantity', e.target.value)
                                }
                                placeholder="Qty"
                                aria-label="Quantity"
                                className={inputCls}
                            />
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={row.unit_price}
                                onChange={(e) =>
                                    update(index, 'unit_price', e.target.value)
                                }
                                placeholder="Unit price"
                                aria-label="Unit price"
                                className={inputCls}
                            />
                            <div className="col-span-2 flex h-8 items-center justify-between font-mono text-[12px] text-gray-700 sm:col-span-1 sm:h-10 sm:justify-end dark:text-gray-200">
                                <span className="text-[10px] tracking-wider text-gray-400 uppercase sm:hidden">
                                    Amount
                                </span>
                                {money(particularAmount(row))}
                            </div>
                        </div>
                        {rowError(index) && (
                            <p className="mt-1 text-[11px] text-red-500">
                                {rowError(index)}
                            </p>
                        )}
                    </div>
                ))}
            </div>

            {errors.particulars && (
                <p className="border-t border-black/6 px-3 py-2 text-[11px] text-red-500 dark:border-white/6">
                    {errors.particulars}
                </p>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-black/6 bg-stone-50 px-3 py-3 dark:border-white/6 dark:bg-zinc-800/60">
                <button
                    type="button"
                    onClick={() => onChange([...rows, emptyParticular()])}
                    className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3 font-mono! text-[11px]! text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-zinc-700"
                >
                    <Plus className="h-3.5 w-3.5" />
                    Add particular
                </button>
                <p className="flex items-baseline gap-3 font-mono">
                    <span className="text-[10px] tracking-wider text-gray-400 uppercase">
                        Amount Requested
                    </span>
                    <span className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        {money(total)}
                    </span>
                </p>
            </div>
        </div>
    );
}
