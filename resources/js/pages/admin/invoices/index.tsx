import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import {
    Download,
    FileText,
    Paperclip,
    Plus,
    Receipt,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface Proof {
    file_name: string;
    mime_type: string;
    size: number;
    uploaded_at: string | null;
}

interface Invoice {
    id: number;
    number: string;
    bill_to_name: string;
    total: string;
    currency: string;
    status: 'draft' | 'sent' | 'paid';
    issue_date: string;
    workspace: { id: number; name: string; slug: string } | null;
    proof: Proof | null;
}

const MAX_PROOF_MB = 10;

function fileSize(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

interface Props {
    invoices: PaginatedData<Invoice>;
    filters: { search?: string; status?: string };
}

const STATUS_STYLES: Record<Invoice['status'], string> = {
    paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400',
    sent: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400',
    draft: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
};

function peso(amount: string, currency: string) {
    const n = parseFloat(amount);
    return `${currency === 'PHP' ? '₱' : ''}${n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export default function Index({ invoices, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const status = filters.status || '';

    const initialSorting = useMemo(() => [], []);

    const query = useCallback(
        (params: Record<string, string | number | undefined>) => {
            router.get(
                '/admin/invoices',
                {
                    search: search || undefined,
                    status: status || undefined,
                    page: 1,
                    ...params,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [search, status],
    );

    const performSearch = useCallback(
        debounce((s: string) => query({ search: s || undefined }), 400),
        [status],
    );

    useEffect(() => {
        if (search !== (filters.search || '')) performSearch(search);
        return () => performSearch.cancel();
    }, [search]);

    // Marking paid opens the receipt dialog; the other transitions are a
    // one-click change and stay that way.
    const [markingPaid, setMarkingPaid] = useState<Invoice | null>(null);
    const [uploadingFor, setUploadingFor] = useState<Invoice | null>(null);

    function changeStatus(invoice: Invoice, next: Invoice['status']) {
        if (next === 'paid') {
            setMarkingPaid(invoice);
            return;
        }

        router.patch(
            `/admin/invoices/${invoice.id}/status`,
            { status: next },
            { preserveScroll: true },
        );
    }

    function removeProof(invoice: Invoice) {
        if (
            confirm(
                `Remove the proof of payment for ${invoice.number}? The file is deleted from storage.`,
            )
        ) {
            router.delete(`/admin/invoices/${invoice.id}/proof`, {
                preserveScroll: true,
            });
        }
    }

    function handleDelete(invoice: Invoice) {
        if (
            confirm(`Delete invoice ${invoice.number}? This cannot be undone.`)
        ) {
            router.delete(`/admin/invoices/${invoice.id}`, {
                preserveScroll: true,
            });
        }
    }

    const columns: ColumnDef<Invoice>[] = [
        {
            accessorKey: 'number',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader column={column} title="Invoice" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-3">
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                        <FileText className="h-4 w-4" />
                    </div>
                    <div>
                        <div className="font-mono text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            {row.original.number}
                        </div>
                        <div className="text-xs text-zinc-500">
                            {row.original.workspace?.name ?? '—'}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'bill_to_name',
            enableSorting: false,
            header: () => <span>Bill to</span>,
            cell: ({ row }) => (
                <span className="text-sm text-zinc-700 dark:text-zinc-300">
                    {row.original.bill_to_name}
                </span>
            ),
        },
        {
            accessorKey: 'total',
            enableSorting: false,
            header: () => <span>Total</span>,
            cell: ({ row }) => (
                <span className="font-medium text-zinc-900 dark:text-zinc-100">
                    {peso(row.original.total, row.original.currency)}
                </span>
            ),
        },
        {
            accessorKey: 'issue_date',
            enableSorting: false,
            header: () => <span>Issued</span>,
            cell: ({ row }) => (
                <span className="text-sm text-zinc-600 dark:text-zinc-400">
                    {new Date(row.original.issue_date).toLocaleDateString(
                        undefined,
                        {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                        },
                    )}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            enableSorting: false,
            header: () => <span>Status</span>,
            cell: ({ row }) => (
                <select
                    value={row.original.status}
                    onChange={(e) =>
                        changeStatus(
                            row.original,
                            e.target.value as Invoice['status'],
                        )
                    }
                    className={`cursor-pointer rounded-full border-0 px-2.5 py-1 text-xs font-semibold capitalize outline-none focus:ring-2 focus:ring-brand-500/30 ${STATUS_STYLES[row.original.status]}`}
                >
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="paid">Paid</option>
                </select>
            ),
        },
        {
            id: 'proof',
            enableSorting: false,
            header: () => <span>Proof of payment</span>,
            cell: ({ row }) => {
                const invoice = row.original;

                if (!invoice.proof) {
                    return (
                        <button
                            type="button"
                            onClick={() => setUploadingFor(invoice)}
                            className="inline-flex items-center gap-1.5 rounded-md border border-dashed border-zinc-300 px-2.5 py-1.5 text-xs font-medium text-zinc-500 transition-colors hover:border-brand-400 hover:text-brand-600 dark:border-zinc-700 dark:hover:border-brand-500"
                        >
                            <Paperclip className="h-3.5 w-3.5" />
                            Attach
                        </button>
                    );
                }

                return (
                    <div className="flex items-center gap-1">
                        <a
                            href={`/admin/invoices/${invoice.id}/proof`}
                            target="_blank"
                            rel="noopener noreferrer"
                            title={`${invoice.proof.file_name} · ${fileSize(invoice.proof.size)}`}
                            className="inline-flex max-w-[10rem] items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-400"
                        >
                            <Receipt className="h-3.5 w-3.5 shrink-0" />
                            <span className="truncate">
                                {invoice.proof.file_name}
                            </span>
                        </a>
                        <button
                            type="button"
                            onClick={() => setUploadingFor(invoice)}
                            className="rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800"
                            title="Replace"
                        >
                            <Paperclip className="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            onClick={() => removeProof(invoice)}
                            className="rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                            title="Remove"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    </div>
                );
            },
        },
        {
            id: 'actions',
            enableSorting: false,
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => (
                <div className="flex items-center justify-end gap-1">
                    <a
                        href={`/admin/invoices/${row.original.id}/download`}
                        className="inline-flex items-center gap-1.5 rounded-md border border-zinc-200 px-2.5 py-1.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    >
                        <Download className="h-3.5 w-3.5" />
                        PDF
                    </a>
                    <button
                        type="button"
                        onClick={() => handleDelete(row.original)}
                        className="inline-flex items-center justify-center rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                        aria-label="Delete invoice"
                    >
                        <Trash2 className="h-4 w-4" />
                    </button>
                </div>
            ),
        },
    ];

    return (
        <AdminSidebarLayout>
            <Head title="Admin | Invoices" />

            <div className="p-4 md:p-6">
                <PageHeader
                    title="Invoices"
                    description="Generate and download invoices for paying workspaces."
                >
                    <div className="relative w-full sm:w-64">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            type="text"
                            placeholder="Search invoices..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-md border border-zinc-200 bg-white py-2 pr-4 pl-10 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950"
                        />
                    </div>
                    <select
                        value={status}
                        onChange={(e) =>
                            query({ status: e.target.value || undefined })
                        }
                        className="rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950"
                    >
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="paid">Paid</option>
                    </select>
                    <Link
                        href="/admin/invoices/create"
                        className="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700"
                    >
                        <Plus className="h-4 w-4" />
                        New Invoice
                    </Link>
                </PageHeader>

                <div className="mt-6 rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={invoices.data || []}
                        enableInternalPagination={false}
                        initialSorting={initialSorting}
                        meta={{ ...omit(invoices, ['data']) }}
                        onFetch={(params) =>
                            query({
                                page: params?.page ?? 1,
                                per_page: params?.per_page ?? undefined,
                            })
                        }
                    />
                </div>
            </div>

            {markingPaid && (
                <ProofDialog
                    invoice={markingPaid}
                    mode="mark-paid"
                    onClose={() => setMarkingPaid(null)}
                />
            )}

            {uploadingFor && (
                <ProofDialog
                    invoice={uploadingFor}
                    mode="attach"
                    onClose={() => setUploadingFor(null)}
                />
            )}
        </AdminSidebarLayout>
    );
}

/**
 * Files the receipt for an invoice — either on its own, or as part of marking
 * the invoice paid, which is when the receipt is usually in hand.
 */
function ProofDialog({
    invoice,
    mode,
    onClose,
}: {
    invoice: Invoice;
    mode: 'mark-paid' | 'attach';
    onClose: () => void;
}) {
    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const markingPaid = mode === 'mark-paid';

    function pick(selected: File | null) {
        setError(null);

        if (selected && selected.size > MAX_PROOF_MB * 1024 * 1024) {
            setError(
                `That file is ${fileSize(selected.size)}. The limit is ${MAX_PROOF_MB} MB.`,
            );
            setFile(null);
            return;
        }

        setFile(selected);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        // Attaching without a file is the one combination that means nothing;
        // marking paid without one is legitimate.
        if (!markingPaid && !file) {
            setError('Choose a receipt to attach.');
            return;
        }

        setProcessing(true);

        const done = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errors: Record<string, string>) =>
                setError(
                    errors.proof ??
                        errors.status ??
                        'Upload failed. Try again.',
                ),
            onFinish: () => setProcessing(false),
        };

        if (markingPaid) {
            // Inertia switches to multipart automatically once a File is in the
            // payload, so the status change and the receipt land together.
            router.post(
                `/admin/invoices/${invoice.id}/status`,
                { _method: 'patch', status: 'paid', proof: file },
                done,
            );
            return;
        }

        router.post(
            `/admin/invoices/${invoice.id}/proof`,
            { proof: file },
            done,
        );
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            onClick={onClose}
        >
            <div
                className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-900"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="mb-5 flex items-start justify-between">
                    <div>
                        <h3 className="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            {markingPaid
                                ? 'Mark as paid'
                                : invoice.proof
                                  ? 'Replace proof of payment'
                                  : 'Attach proof of payment'}
                        </h3>
                        <p className="font-mono text-sm text-zinc-500">
                            {invoice.number} ·{' '}
                            {peso(invoice.total, invoice.currency)}
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded-md p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Receipt{markingPaid && ' (optional)'}
                        </label>

                        <input
                            ref={inputRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/heic,application/pdf"
                            onChange={(e) => pick(e.target.files?.[0] ?? null)}
                            className="hidden"
                        />

                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            className="flex w-full items-center gap-2 rounded-md border border-dashed border-zinc-300 px-3 py-3 text-left text-sm text-zinc-500 transition-colors hover:border-brand-400 hover:text-brand-600 dark:border-zinc-700"
                        >
                            <Paperclip className="h-4 w-4 shrink-0" />
                            <span className="truncate">
                                {file
                                    ? `${file.name} · ${fileSize(file.size)}`
                                    : 'Choose a file…'}
                            </span>
                        </button>

                        <p className="mt-1 text-xs text-zinc-500">
                            JPG, PNG, WEBP, HEIC, or PDF up to {MAX_PROOF_MB}{' '}
                            MB.
                            {invoice.proof &&
                                ` Replaces ${invoice.proof.file_name}.`}
                        </p>

                        {error && (
                            <p className="mt-1 text-xs text-red-500">{error}</p>
                        )}
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-md border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Saving…'
                                : markingPaid
                                  ? 'Mark paid'
                                  : 'Upload'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
