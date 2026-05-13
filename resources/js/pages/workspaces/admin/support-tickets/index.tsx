import PageHeader from '@/components/common/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import supportTickets from '@/routes/admin/support-tickets';
import { PaginatedData } from '@/types';
import { SupportTicket } from '@/types/models/SupportTicket';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface Props {
    workspace: Workspace;
    tickets: PaginatedData<SupportTicket>;
    filters?: {
        status?: string | null;
        category?: string | null;
    };
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

export default function SupportTicketsAdminIndex({ workspace, tickets, filters, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);
    const [statusFilter, setStatusFilter] = useState(filters?.status ?? 'all');
    const [categoryFilter, setCategoryFilter] = useState(filters?.category ?? 'all');
    const [selectedTicket, setSelectedTicket] = useState<SupportTicket | null>(null);

    const statusForm = useForm({
        status: selectedTicket?.status ?? 'open',
    });

    useEffect(() => {
        if (!selectedTicket) return;
        statusForm.setData('status', selectedTicket.status);
    }, [selectedTicket]);

    useEffect(() => {
        router.get(
            supportTickets.index.url({ workspace: workspace.slug }),
            {
                sort: query?.sort,
                page: 1,
                per_page: query?.per_page,
                'filter[status]': statusFilter === 'all' ? undefined : statusFilter,
                'filter[category]': categoryFilter === 'all' ? undefined : categoryFilter,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }, [statusFilter, categoryFilter]);

    const handleStatusUpdate = () => {
        if (!selectedTicket) return;

        statusForm.patch(
            supportTickets.update.url({ workspace: workspace.slug, ticket: selectedTicket.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Support ticket updated.');
                },
                onError: () => {
                    toast.error('Unable to update status.');
                },
            },
        );
    };

    const columns: ColumnDef<SupportTicket>[] = [
        {
            accessorKey: 'reference',
            header: ({ column }) => <SortableHeader column={column} title="Reference" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500">{row.original.reference}</span>
            ),
        },
        {
            accessorKey: 'user',
            header: ({ column }) => <SortableHeader column={column} title="Reporter" />,
            cell: ({ row }) => (
                <div className="space-y-1">
                    <p className="text-[12px] font-medium text-gray-800 dark:text-gray-100">
                        {row.original.user?.name ?? 'Unknown'}
                    </p>
                    <p className="font-mono text-[11px] text-gray-400">
                        {row.original.user?.email ?? '—'}
                    </p>
                </div>
            ),
        },
        {
            accessorKey: 'category',
            header: ({ column }) => <SortableHeader column={column} title="Category" />,
            cell: ({ row }) => CATEGORY_LABELS[row.original.category],
        },
        {
            accessorKey: 'status',
            header: ({ column }) => <SortableHeader column={column} title="Status" />,
            cell: ({ row }) => (
                <Badge className={STATUS_STYLES[row.original.status]}>
                    {row.original.status.replace('_', ' ')}
                </Badge>
            ),
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) => <SortableHeader column={column} title="Created" />,
            cell: ({ row }) => new Date(row.original.created_at).toLocaleDateString(),
        },
    ];

    const hasStatusChange = !!selectedTicket && statusForm.data.status !== selectedTicket.status;

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Support Tickets`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Support Tickets"
                    description="Review and manage support requests across the workspace"
                />

                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <div className="min-w-[180px]">
                        <Select value={statusFilter} onValueChange={setStatusFilter}>
                            <SelectTrigger className="h-9">
                                <SelectValue placeholder="Filter by status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                <SelectItem value="open">Open</SelectItem>
                                <SelectItem value="in_progress">In progress</SelectItem>
                                <SelectItem value="resolved">Resolved</SelectItem>
                                <SelectItem value="closed">Closed</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="min-w-[200px]">
                        <Select value={categoryFilter} onValueChange={setCategoryFilter}>
                            <SelectTrigger className="h-9">
                                <SelectValue placeholder="Filter by category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All categories</SelectItem>
                                {Object.entries(CATEGORY_LABELS).map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={tickets.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(tickets, ['data']) }}
                        onRowClick={(row) => setSelectedTicket(row)}
                        onFetch={(params) => {
                            router.get(
                                supportTickets.index.url({ workspace: workspace.slug }),
                                {
                                    sort: params?.sort,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page,
                                    'filter[status]': statusFilter === 'all' ? undefined : statusFilter,
                                    'filter[category]': categoryFilter === 'all' ? undefined : categoryFilter,
                                },
                                { preserveState: true, replace: true, preserveScroll: true },
                            );
                        }}
                    />
                </div>
            </div>

            <Sheet open={!!selectedTicket} onOpenChange={(open) => !open && setSelectedTicket(null)}>
                <SheetContent className="bg-white border-black/6 dark:border-white/8 dark:bg-zinc-900 sm:max-w-xl">
                    <SheetHeader className="border-b border-black/6 px-5 py-4 text-left dark:border-white/8">
                        <SheetTitle className="font-mono text-[14px] font-semibold tracking-wide text-gray-800 uppercase dark:text-gray-100">
                            {selectedTicket?.subject}
                        </SheetTitle>
                        <SheetDescription className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                            {selectedTicket ? `Ticket ${selectedTicket.reference}` : ''}
                        </SheetDescription>
                    </SheetHeader>

                    {selectedTicket && (
                        <div className="space-y-5 px-5 py-4 text-sm">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge className={STATUS_STYLES[selectedTicket.status]}>
                                    {selectedTicket.status.replace('_', ' ')}
                                </Badge>
                                <Badge variant="outline">
                                    {CATEGORY_LABELS[selectedTicket.category]}
                                </Badge>
                                <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    {new Date(selectedTicket.created_at).toLocaleString()}
                                </span>
                            </div>

                            <div>
                                <p className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Reporter</p>
                                <p className="text-[13px] font-medium text-gray-800 dark:text-gray-100">
                                    {selectedTicket.user?.name}
                                </p>
                                <p className="text-[12px] text-gray-500 dark:text-gray-400">{selectedTicket.user?.email}</p>
                            </div>

                            <div>
                                <p className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Description</p>
                                <p className="mt-1 whitespace-pre-wrap text-[13px] text-gray-700 dark:text-gray-200">
                                    {selectedTicket.description}
                                </p>
                            </div>

                            <div>
                                <p className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Page URL</p>
                                <p className="mt-1 text-[12px] text-gray-500 dark:text-gray-400 wrap-break-word">
                                    {selectedTicket.current_url ?? '—'}
                                </p>
                            </div>

                            <div>
                                <p className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">User agent</p>
                                <p className="mt-1 text-[12px] text-gray-500 dark:text-gray-400 wrap-break-word">
                                    {selectedTicket.user_agent ?? '—'}
                                </p>
                            </div>

                            <div className="space-y-2">
                                <p className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Update status</p>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Select
                                        value={statusForm.data.status}
                                        onValueChange={(value) =>
                                            statusForm.setData('status', value as SupportTicket['status'])
                                        }
                                    >
                                        <SelectTrigger className="h-9 min-w-[180px] flex-1 bg-stone-50 dark:bg-zinc-800">
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="open">Open</SelectItem>
                                            <SelectItem value="in_progress">In progress</SelectItem>
                                            <SelectItem value="resolved">Resolved</SelectItem>
                                            <SelectItem value="closed">Closed</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        type="button"
                                        onClick={handleStatusUpdate}
                                        disabled={!hasStatusChange || statusForm.processing}
                                        className={hasStatusChange
                                            ? 'h-9 bg-emerald-600 text-white hover:bg-emerald-700'
                                            : 'h-9 bg-stone-100 text-gray-400 hover:bg-stone-100 dark:bg-zinc-800 dark:text-gray-500'}
                                    >
                                        {statusForm.processing ? 'Saving...' : 'Update status'}
                                    </Button>
                                </div>
                                {statusForm.errors.status && (
                                    <p className="text-xs text-red-500">{statusForm.errors.status}</p>
                                )}
                            </div>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
