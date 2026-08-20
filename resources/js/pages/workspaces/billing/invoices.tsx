import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { type BreadcrumbItem, PaginatedData } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit, omitBy } from 'lodash';
import { Download, FileText, Search } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface Invoice {
    id: number;
    number: string;
    bill_to_name: string;
    total: string;
    currency: string;
    status: 'draft' | 'sent' | 'paid';
    issue_date: string;
    due_date: string | null;
    paid_at: string | null;
}

interface Props {
    workspace: { id: number; name: string; slug: string };
    invoices: PaginatedData<Invoice>;
    filters: { search?: string; status?: string; sort?: string };
}

const STATUS_STYLES: Record<Invoice['status'], string> = {
    paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400',
    sent: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400',
    draft: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
};

function money(amount: string | number, symbol: string) {
    const n = typeof amount === 'string' ? parseFloat(amount) : amount;
    return `${symbol}${n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function shortDate(value: string | null) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/** True once the due date has passed and the invoice is still unpaid. */
function isOverdue(invoice: Invoice) {
    if (invoice.status !== 'sent' || !invoice.due_date) return false;
    const due = new Date(invoice.due_date);
    due.setHours(23, 59, 59, 999);
    return due.getTime() < Date.now();
}

export default function WorkspaceInvoices({
    workspace,
    invoices,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const status = filters.status || '';
    const baseUrl = `/workspaces/${workspace.slug}/billing/invoices`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Invoices', href: baseUrl },
    ];

    const sort = filters.sort || '';
    const initialSorting = useMemo(() => toFrontendSort(sort), [sort]);

    const query = useCallback(
        (params: Record<string, string | number | undefined | null>) => {
            router.get(
                baseUrl,
                // Carry the current sort/filters so paging or resizing doesn't
                // silently reset them; `params` overrides whatever it names.
                omitBy(
                    {
                        search: search || undefined,
                        status: status || undefined,
                        sort: sort || undefined,
                        page: 1,
                        ...params,
                    },
                    (v) => v === undefined || v === null || v === '',
                ),
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [search, status, sort, baseUrl],
    );

    const performSearch = useMemo(
        () => debounce((s: string) => query({ search: s || undefined }), 400),
        [query],
    );

    useEffect(() => {
        if (search !== (filters.search || '')) performSearch(search);
        return () => performSearch.cancel();
    }, [search, filters.search, performSearch]);

    const columns: ColumnDef<Invoice>[] = [
        {
            accessorKey: 'number',
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
                            {row.original.bill_to_name}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'total',
            header: ({ column }) => (
                <SortableHeader column={column} title="Amount" />
            ),
            cell: ({ row }) => (
                <span className="font-medium text-zinc-900 dark:text-zinc-100">
                    {money(
                        row.original.total,
                        row.original.currency === 'PHP'
                            ? '₱'
                            : `${row.original.currency} `,
                    )}
                </span>
            ),
        },
        {
            accessorKey: 'issue_date',
            header: ({ column }) => (
                <SortableHeader column={column} title="Issued" />
            ),
            cell: ({ row }) => (
                <span className="text-sm text-zinc-600 dark:text-zinc-400">
                    {shortDate(row.original.issue_date)}
                </span>
            ),
        },
        {
            accessorKey: 'due_date',
            header: ({ column }) => (
                <SortableHeader column={column} title="Due" />
            ),
            cell: ({ row }) => (
                <span
                    className={
                        isOverdue(row.original)
                            ? 'text-sm font-semibold text-red-600 dark:text-red-400'
                            : 'text-sm text-zinc-600 dark:text-zinc-400'
                    }
                >
                    {shortDate(row.original.due_date)}
                    {isOverdue(row.original) && ' · overdue'}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <span
                    className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${STATUS_STYLES[row.original.status]}`}
                >
                    {row.original.status}
                </span>
            ),
        },
        {
            id: 'actions',
            enableSorting: false,
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) => (
                <a
                    href={`${baseUrl}/${row.original.id}/download`}
                    className="inline-flex items-center gap-1.5 rounded-md border border-zinc-200 px-2.5 py-1.5 text-xs font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                >
                    <Download className="h-3.5 w-3.5" />
                    PDF
                </a>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoices" />

            <div className="p-4 md:p-6">
                <PageHeader
                    title="Invoices"
                    description={`Billing history for ${workspace.name}.`}
                />

                <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search invoice number…"
                            className="w-full rounded-md border border-zinc-200 bg-white py-2 pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950"
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
                </div>

                <div className="mt-4">
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
                                sort: params?.sort ?? undefined,
                            })
                        }
                    />
                </div>
            </div>
        </AppLayout>
    );
}
