import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import { Download, FileText, Plus, Search, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface Invoice {
    id: number;
    number: string;
    bill_to_name: string;
    total: string;
    currency: string;
    status: 'draft' | 'sent' | 'paid';
    issue_date: string;
    workspace: { id: number; name: string; slug: string } | null;
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

    function changeStatus(invoice: Invoice, next: Invoice['status']) {
        router.patch(
            `/admin/invoices/${invoice.id}/status`,
            { status: next },
            { preserveScroll: true },
        );
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
        </AdminSidebarLayout>
    );
}
