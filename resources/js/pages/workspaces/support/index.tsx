import PageHeader from '@/components/common/PageHeader';
import { Badge } from '@/components/ui/badge';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { SupportTicket } from '@/types/models/SupportTicket';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { useMemo } from 'react';

interface Props {
    workspace: Workspace;
    tickets: PaginatedData<SupportTicket>;
    query?: {
        sort?: string | null;
        per_page?: number | string;
        page?: number | string;
    };
}

const STATUS_STYLES: Record<SupportTicket['status'], string> = {
    open: 'border-emerald-200/60 bg-emerald-50 text-emerald-700',
    in_progress: 'border-amber-200/60 bg-amber-50 text-amber-700',
    resolved: 'border-sky-200/60 bg-sky-50 text-sky-700',
    closed: 'border-stone-200/60 bg-stone-100 text-stone-700',
};

const CATEGORY_LABELS: Record<SupportTicket['category'], string> = {
    question: 'Question',
    bug: 'Bug',
    feature_request: 'Feature request',
    billing: 'Billing',
    other: 'Other',
};

export default function SupportTicketsIndex({
    workspace,
    tickets,
    query,
}: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const columns: ColumnDef<SupportTicket>[] = [
        {
            accessorKey: 'reference',
            header: ({ column }) => (
                <SortableHeader column={column} title="Reference" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500">
                    {row.original.reference}
                </span>
            ),
        },
        {
            accessorKey: 'subject',
            header: ({ column }) => (
                <SortableHeader column={column} title="Subject" />
            ),
            cell: ({ row }) => (
                <div className="space-y-1">
                    <p className="text-[12px] font-medium text-gray-800 dark:text-gray-100">
                        {row.original.subject}
                    </p>
                    <p className="font-mono text-[11px] text-gray-400">
                        {CATEGORY_LABELS[row.original.category]}
                    </p>
                </div>
            ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <Badge className={STATUS_STYLES[row.original.status]}>
                    {row.original.status.replace('_', ' ')}
                </Badge>
            ),
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) => (
                <SortableHeader column={column} title="Created" />
            ),
            cell: ({ row }) =>
                new Date(row.original.created_at).toLocaleDateString(),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Customer Support`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Customer Support"
                    description="View your workspace support requests"
                />

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={tickets.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(tickets, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                `/workspaces/${workspace.slug}/support`,
                                {
                                    sort: params?.sort,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page,
                                },
                                {
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                },
                            );
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
