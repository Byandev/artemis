import PageHeader from '@/components/common/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { SupportTicket } from '@/types/models/SupportTicket';
import { Head, router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import debounce from 'lodash/debounce';
import { Search } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { toast, Toaster } from 'sonner';

interface Props {
    tickets: PaginatedData<SupportTicket>;
    filters?: {
        search?: string | null;
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

const STATUS_LABELS: Record<SupportTicket['status'], string> = {
    open: 'Open',
    in_progress: 'In progress',
    resolved: 'Resolved',
    closed: 'Closed',
};

export default function AdminSupportTicketsIndex({
    tickets,
    filters,
    query,
}: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [search, setSearch] = useState(filters?.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters?.status ?? 'all');
    const [categoryFilter, setCategoryFilter] = useState(
        filters?.category ?? 'all',
    );
    const [selectedTicket, setSelectedTicket] = useState<SupportTicket | null>(
        null,
    );

    const statusForm = useForm({
        status: selectedTicket?.status ?? 'open',
    });

    const fetchTickets = useCallback(
        (overrides: Record<string, string | number | undefined> = {}) => {
            router.get(
                '/admin/support-tickets',
                {
                    sort: query?.sort ?? undefined,
                    page: 1,
                    per_page: query?.per_page,
                    'filter[search]': search || undefined,
                    'filter[status]':
                        statusFilter === 'all' ? undefined : statusFilter,
                    'filter[category]':
                        categoryFilter === 'all' ? undefined : categoryFilter,
                    ...overrides,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [categoryFilter, query?.per_page, query?.sort, search, statusFilter],
    );

    const debouncedSearch = useMemo(
        () => debounce(() => fetchTickets(), 400),
        [fetchTickets],
    );

    useEffect(() => {
        if (search !== (filters?.search ?? '')) {
            debouncedSearch();
        }

        return () => debouncedSearch.cancel();
    }, [debouncedSearch, filters?.search, search]);

    useEffect(() => {
        if (!selectedTicket) return;
        statusForm.setData('status', selectedTicket.status);
    }, [selectedTicket]);

    useEffect(() => {
        if (
            statusFilter === (filters?.status ?? 'all') &&
            categoryFilter === (filters?.category ?? 'all')
        ) {
            return;
        }

        fetchTickets();
    }, [
        categoryFilter,
        filters?.category,
        filters?.status,
        fetchTickets,
        statusFilter,
    ]);

    const handleStatusUpdate = () => {
        if (!selectedTicket) return;

        statusForm.patch(`/admin/support-tickets/${selectedTicket.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Support ticket updated.');
                setSelectedTicket({
                    ...selectedTicket,
                    status: statusForm.data.status as SupportTicket['status'],
                });
            },
            onError: () => {
                toast.error('Unable to update status.');
            },
        });
    };

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
            id: 'workspace',
            header: () => (
                <p className="font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Workspace
                </p>
            ),
            cell: ({ row }) => (
                <div className="space-y-1">
                    <p className="text-[12px] font-medium text-gray-800 dark:text-gray-100">
                        {row.original.workspace?.name ?? 'Unknown'}
                    </p>
                    <p className="font-mono text-[11px] text-gray-400">
                        /{row.original.workspace?.slug ?? 'unknown'}
                    </p>
                </div>
            ),
        },
        {
            id: 'reporter',
            header: () => (
                <p className="font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Reporter
                </p>
            ),
            cell: ({ row }) => (
                <div className="space-y-1">
                    <p className="text-[12px] font-medium text-gray-800 dark:text-gray-100">
                        {row.original.user?.name ?? 'Unknown'}
                    </p>
                    <p className="font-mono text-[11px] text-gray-400">
                        {row.original.user?.email ?? '-'}
                    </p>
                </div>
            ),
        },
        {
            accessorKey: 'subject',
            header: ({ column }) => (
                <SortableHeader column={column} title="Subject" />
            ),
            cell: ({ row }) => (
                <span className="font-medium text-gray-700 dark:text-gray-300">
                    {row.original.subject}
                </span>
            ),
        },
        {
            accessorKey: 'category',
            header: ({ column }) => (
                <SortableHeader column={column} title="Category" />
            ),
            cell: ({ row }) => CATEGORY_LABELS[row.original.category],
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <Badge className={STATUS_STYLES[row.original.status]}>
                    {STATUS_LABELS[row.original.status]}
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

    const hasStatusChange =
        !!selectedTicket && statusForm.data.status !== selectedTicket.status;

    return (
        <AdminSidebarLayout>
            <Head title="Admin | Support Tickets" />
            <Toaster position="top-right" richColors closeButton />

            <div className="p-4 md:p-6">
                <PageHeader
                    title="Support Tickets"
                    description="Review support requests across all workspaces."
                >
                    <div className="relative w-full sm:w-72">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            type="text"
                            placeholder="Search tickets..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-md border border-zinc-200 bg-white py-2 pr-4 pl-10 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950"
                        />
                    </div>
                </PageHeader>

                <div className="mt-6 mb-3 flex flex-wrap items-center gap-2">
                    <Select
                        value={statusFilter}
                        onValueChange={setStatusFilter}
                    >
                        <SelectTrigger className="h-9 w-[180px]">
                            <SelectValue placeholder="Filter by status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {Object.entries(STATUS_LABELS).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>

                    <Select
                        value={categoryFilter}
                        onValueChange={setCategoryFilter}
                    >
                        <SelectTrigger className="h-9 w-[200px]">
                            <SelectValue placeholder="Filter by category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All categories</SelectItem>
                            {Object.entries(CATEGORY_LABELS).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={tickets.data || []}
                        enableInternalPagination={false}
                        initialSorting={initialSorting}
                        meta={{ ...omit(tickets, ['data']) }}
                        onRowClick={(row) => setSelectedTicket(row)}
                        onFetch={(params) => {
                            const sortStr =
                                params?.sort && params.sort !== null
                                    ? String(params.sort)
                                    : null;

                            fetchTickets({
                                sort: sortStr ?? undefined,
                                page: Number(params?.page ?? 1),
                                per_page: Number(
                                    params?.per_page ??
                                        query?.per_page ??
                                        tickets.per_page,
                                ),
                            });
                        }}
                    />
                </div>
            </div>

            <Sheet
                open={!!selectedTicket}
                onOpenChange={(open) => !open && setSelectedTicket(null)}
            >
                <SheetContent className="border-black/6 bg-white sm:max-w-xl dark:border-white/8 dark:bg-zinc-900">
                    <SheetHeader className="border-b border-black/6 px-5 py-4 text-left dark:border-white/8">
                        <SheetTitle className="font-mono text-[14px] font-semibold tracking-wide text-gray-800 uppercase dark:text-gray-100">
                            {selectedTicket?.subject}
                        </SheetTitle>
                        <SheetDescription className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                            {selectedTicket
                                ? `Ticket ${selectedTicket.reference}`
                                : ''}
                        </SheetDescription>
                    </SheetHeader>

                    {selectedTicket && (
                        <div className="space-y-5 px-5 py-4 text-sm">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge
                                    className={
                                        STATUS_STYLES[selectedTicket.status]
                                    }
                                >
                                    {STATUS_LABELS[selectedTicket.status]}
                                </Badge>
                                <Badge variant="outline">
                                    {CATEGORY_LABELS[selectedTicket.category]}
                                </Badge>
                                <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    {new Date(
                                        selectedTicket.created_at,
                                    ).toLocaleString()}
                                </span>
                            </div>

                            <Detail label="Workspace">
                                <p className="text-[13px] font-medium text-gray-800 dark:text-gray-100">
                                    {selectedTicket.workspace?.name ?? '-'}
                                </p>
                                <p className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                                    /{selectedTicket.workspace?.slug ?? '-'}
                                </p>
                            </Detail>

                            <Detail label="Reporter">
                                <p className="text-[13px] font-medium text-gray-800 dark:text-gray-100">
                                    {selectedTicket.user?.name ?? '-'}
                                </p>
                                <p className="text-[12px] text-gray-500 dark:text-gray-400">
                                    {selectedTicket.user?.email ?? '-'}
                                </p>
                            </Detail>

                            <Detail label="Description">
                                <p className="mt-1 text-[13px] whitespace-pre-wrap text-gray-700 dark:text-gray-200">
                                    {selectedTicket.description}
                                </p>
                            </Detail>

                            <Detail label="Page URL">
                                <p className="mt-1 text-[12px] wrap-break-word text-gray-500 dark:text-gray-400">
                                    {selectedTicket.current_url ?? '-'}
                                </p>
                            </Detail>

                            <Detail label="User agent">
                                <p className="mt-1 text-[12px] wrap-break-word text-gray-500 dark:text-gray-400">
                                    {selectedTicket.user_agent ?? '-'}
                                </p>
                            </Detail>

                            <div className="space-y-2">
                                <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    Update status
                                </p>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Select
                                        value={statusForm.data.status}
                                        onValueChange={(value) =>
                                            statusForm.setData(
                                                'status',
                                                value as SupportTicket['status'],
                                            )
                                        }
                                    >
                                        <SelectTrigger className="h-9 min-w-[180px] flex-1 bg-stone-50 dark:bg-zinc-800">
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(STATUS_LABELS).map(
                                                ([value, label]) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        type="button"
                                        onClick={handleStatusUpdate}
                                        disabled={
                                            !hasStatusChange ||
                                            statusForm.processing
                                        }
                                        className={
                                            hasStatusChange
                                                ? 'h-9 bg-emerald-600 text-white hover:bg-emerald-700'
                                                : 'h-9 bg-stone-100 text-gray-400 hover:bg-stone-100 dark:bg-zinc-800 dark:text-gray-500'
                                        }
                                    >
                                        {statusForm.processing
                                            ? 'Saving...'
                                            : 'Update status'}
                                    </Button>
                                </div>
                                {statusForm.errors.status && (
                                    <p className="text-xs text-red-500">
                                        {statusForm.errors.status}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </AdminSidebarLayout>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {label}
            </p>
            {children}
        </div>
    );
}
