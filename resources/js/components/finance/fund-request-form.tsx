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
import { Link, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Circle,
    Download,
    FileText,
    Paperclip,
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
    amount_requested: number | string;
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
    onCancel: () => void;
}

const ACCEPT =
    '.jpg,.jpeg,.png,.webp,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv';

const money = (n: number) =>
    n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

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
        amount_requested: requestFund
            ? String(requestFund.amount_requested ?? '')
            : '',
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

    const total = parseFloat(data.amount_requested) || 0;

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

    const checklists = type?.checklists ?? [];
    const managementUrl = `/workspaces/${workspaceSlug}/finance/management`;

    return (
        <form onSubmit={handleSubmit}>
            <div className="divide-y divide-black/6 dark:divide-white/6">
                <Section
                    title="Request"
                    hint="What the funds are for, which department asks, and how much."
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
                            placeholder="0.00"
                            className={inputCls}
                        />
                    </Field>

                    {isEditing && (
                        <Field label="Status">
                            <div className="flex h-10 items-center font-mono text-[12px] text-gray-500 capitalize dark:text-gray-400">
                                {requestFund?.status}
                                {requestFund?.approver &&
                                    ` · approved by ${requestFund.approver.name}`}
                            </div>
                        </Field>
                    )}
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

                <Section
                    title="Checklist"
                    hint="Tick off each item this type of request is checked against."
                >
                    <Wide>
                        {checklists.length === 0 ? (
                            <EmptyRequirement
                                what="checklist items"
                                href={managementUrl}
                            />
                        ) : (
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
                        )}
                    </Wide>
                </Section>

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
