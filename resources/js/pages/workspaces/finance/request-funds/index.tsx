import PageHeader from '@/components/common/PageHeader';
import { FinanceDeleteDialog } from '@/components/finance/delete-dialog';
import {
    DepartmentOption,
    ProductOption,
    RequestFund,
    RequestFundFormDialog,
} from '@/components/finance/request-fund-form-dialog';
import {
    TransactionTypeItem,
    transactionTypeLabel,
} from '@/components/finance/transaction-type';
import { StatusFilter } from '@/components/finance/status-filter';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import {
    Check,
    ChevronDown,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface UserOption {
    id: number;
    name: string;
}

interface Props {
    workspace: Workspace;
    requestFunds: PaginatedData<RequestFund>;
    users: UserOption[];
    statuses: string[];
    products: ProductOption[];
    transactionTypes: TransactionTypeItem[];
    departments: DepartmentOption[];
    canApproveStatus: boolean;
    query?: {
        sort?: string | null;
        per_page?: number | string | null;
        filter?: { search?: string; status?: string | string[] };
    };
}

const STATUS_STYLES: Record<string, string> = {
    pending:
        'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
    approved: 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400',
    released:
        'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
    cancelled: 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
};

const STATUS_TEXT: Record<string, string> = {
    pending: 'text-amber-600 dark:text-amber-400',
    approved: 'text-blue-600 dark:text-blue-400',
    released: 'text-emerald-600 dark:text-emerald-400',
    cancelled: 'text-gray-500 dark:text-gray-400',
};

const fmt = (v: number | string) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const fmtDate = (v: string | null) =>
    v ? new Date(v).toLocaleDateString('en-PH') : '—';

export default function RequestFundsIndex({
    workspace,
    requestFunds,
    users,
    statuses,
    products,
    transactionTypes,
    departments,
    canApproveStatus,
    query,
}: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<RequestFund | null>(null);
    const [toDelete, setToDelete] = useState<RequestFund | null>(null);
    const [search, setSearch] = useState(query?.filter?.search ?? '');
    const [statusFilter, setStatusFilter] = useState<string[]>(() => {
        const s = query?.filter?.status;
        if (!s) return [];
        return Array.isArray(s) ? s : [s];
    });

    const statusOptions = useMemo(
        () =>
            statuses.map((s) => ({
                value: s,
                label: s.charAt(0).toUpperCase() + s.slice(1),
            })),
        [statuses],
    );

    const baseUrl = `/workspaces/${workspace.slug}/finance/request-funds`;
    const canCreate = usePermission(PERMISSIONS.CreateFinanceRequestFunds);
    const canEdit = usePermission(PERMISSIONS.EditFinanceRequestFunds);
    const canDelete = usePermission(PERMISSIONS.DeleteFinanceRequestFunds);
    const showActions = canEdit || canDelete;

    const changeStatus = (rf: RequestFund, next: string) => {
        if (next === rf.status) return;
        router.put(
            `${baseUrl}/${rf.id}/status`,
            { status: next },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['requestFunds'],
            },
        );
    };

    const performQuery = useCallback(
        debounce((s: string, st: string[]) => {
            router.get(
                baseUrl,
                {
                    sort: query?.sort,
                    per_page: query?.per_page ?? undefined,
                    'filter[search]': s || undefined,
                    'filter[status]': st.length ? st : undefined,
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['requestFunds'],
                },
            );
        }, 400),
        [baseUrl, query?.sort, query?.per_page],
    );

    useEffect(() => {
        performQuery(search, statusFilter);
        return () => performQuery.cancel();
    }, [search, statusFilter, performQuery]);

    const columns: ColumnDef<RequestFund>[] = [
        {
            accessorKey: 'reference_no',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Reference" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] font-medium text-gray-800 dark:text-gray-100">
                    {row.original.reference_no}
                </span>
            ),
        },
        {
            accessorKey: 'request_date',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Request Date" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500">
                    {fmtDate(row.original.request_date)}
                </span>
            ),
        },
        {
            id: 'requester',
            header: 'Requested By',
            cell: ({ row }) => (
                <span className="text-[12px] text-gray-700 dark:text-gray-200">
                    {row.original.requester?.name ?? '—'}
                </span>
            ),
        },
        {
            id: 'type',
            header: 'Type',
            cell: ({ row }) => (
                <span className="text-[12px] text-gray-700 dark:text-gray-200">
                    {transactionTypeLabel(row.original.transactionType?.name) ||
                        '—'}
                </span>
            ),
        },
        {
            id: 'department',
            header: 'Department',
            cell: ({ row }) => (
                <span className="text-[12px] text-gray-700 dark:text-gray-200">
                    {row.original.department?.name ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'amount_requested',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Amount"
                    className="justify-end"
                />
            ),
            cell: ({ row }) => (
                <div className="text-right font-mono text-[12px] font-medium text-gray-800 dark:text-gray-100">
                    {fmt(row.original.amount_requested)}
                </div>
            ),
        },
        {
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Status"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => {
                const rf = row.original;
                const badgeCls = `inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-mono text-[11px] font-medium capitalize ${
                    STATUS_STYLES[rf.status] ?? STATUS_STYLES.cancelled
                }`;

                if (!canApproveStatus) {
                    return (
                        <div className="text-center">
                            <span className={badgeCls}>{rf.status}</span>
                        </div>
                    );
                }

                return (
                    <div className="flex justify-center">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button
                                    className={`${badgeCls} transition-all hover:opacity-80`}
                                >
                                    {rf.status}
                                    <ChevronDown className="h-3 w-3 opacity-60" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="center"
                                className="w-40"
                            >
                                {statuses.map((s) => (
                                    <DropdownMenuItem
                                        key={s}
                                        onClick={() => changeStatus(rf, s)}
                                        className={`font-mono text-[11px] font-medium capitalize ${
                                            STATUS_TEXT[s] ??
                                            STATUS_TEXT.cancelled
                                        }`}
                                    >
                                        {s}
                                        {s === rf.status && (
                                            <Check className="ml-auto h-3.5 w-3.5" />
                                        )}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
        {
            id: 'approver',
            header: 'Approved By',
            cell: ({ row }) =>
                row.original.approver?.name ? (
                    <span className="text-[12px] text-gray-700 dark:text-gray-200">
                        {row.original.approver.name}
                    </span>
                ) : (
                    <span className="font-mono text-[11px] text-gray-300 dark:text-gray-600">
                        ---
                    </span>
                ),
        },
        ...(showActions
            ? [
                  {
                      id: 'actions',
                      header: () => (
                          <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                              Actions
                          </div>
                      ),
                      cell: ({ row }) => (
                          <div className="flex justify-center">
                              <DropdownMenu>
                                  <DropdownMenuTrigger asChild>
                                      <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500">
                                          <MoreHorizontal className="h-3.5 w-3.5" />
                                      </button>
                                  </DropdownMenuTrigger>
                                  <DropdownMenuContent
                                      align="end"
                                      className="w-36"
                                  >
                                      {canEdit && (
                                          <DropdownMenuItem
                                              onClick={() =>
                                                  setEditing(row.original)
                                              }
                                          >
                                              <Pencil className="mr-2 h-3.5 w-3.5" />{' '}
                                              Edit
                                          </DropdownMenuItem>
                                      )}
                                      {canEdit && canDelete && (
                                          <DropdownMenuSeparator />
                                      )}
                                      {canDelete && (
                                          <DropdownMenuItem
                                              className="text-red-600 focus:text-red-600 dark:text-red-400"
                                              onClick={() =>
                                                  setToDelete(row.original)
                                              }
                                          >
                                              <Trash2 className="mr-2 h-3.5 w-3.5" />{' '}
                                              Delete
                                          </DropdownMenuItem>
                                      )}
                                  </DropdownMenuContent>
                              </DropdownMenu>
                          </div>
                      ),
                  } as ColumnDef<RequestFund>,
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Fund Requests`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Fund Requests"
                    description="Create and track requests for funds and their approvals."
                >
                    {canCreate && (
                        <button
                            onClick={() => setCreateOpen(true)}
                            className="flex h-8 items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            New Request
                        </button>
                    )}
                </PageHeader>

                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100"
                            placeholder="Search reference..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <StatusFilter
                        options={statusOptions}
                        selected={statusFilter}
                        onChange={setStatusFilter}
                        placeholder="All statuses"
                        className="w-44"
                    />
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={requestFunds.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(requestFunds, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    'filter[search]': search || undefined,
                                    'filter[status]': statusFilter.length
                                        ? statusFilter
                                        : undefined,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page ?? undefined,
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

                {(canCreate || canEdit) && (
                    <RequestFundFormDialog
                        open={createOpen || editing !== null}
                        onOpenChange={(o) => {
                            if (!o) {
                                setCreateOpen(false);
                                setEditing(null);
                            }
                        }}
                        requestFund={editing}
                        workspaceSlug={workspace.slug}
                        users={users}
                        products={products}
                        transactionTypes={transactionTypes}
                        departments={departments}
                    />
                )}
                {canDelete && (
                    <FinanceDeleteDialog
                        open={!!toDelete}
                        onClose={() => setToDelete(null)}
                        title="Delete Fund Request?"
                        description={`Delete request "${toDelete?.reference_no}"? This cannot be undone.`}
                        url={toDelete ? `${baseUrl}/${toDelete.id}` : ''}
                        successMessage="Fund request deleted"
                    />
                )}
            </div>
        </AppLayout>
    );
}
