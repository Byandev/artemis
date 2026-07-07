import PageHeader from '@/components/common/PageHeader';
import {
    Field,
    Footer,
    inputCls,
} from '@/components/finance/account-form-dialog';
import { FinanceDeleteDialog } from '@/components/finance/delete-dialog';
import { transactionTypeStyle } from '@/components/finance/transaction-type';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Head, router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import { MoreHorizontal, Pencil, Search, Trash2 } from 'lucide-react';
import React, { useCallback, useEffect, useMemo, useState } from 'react';

interface TransactionType {
    id: number;
    name: string;
}

interface Props {
    workspace: Workspace;
    types: PaginatedData<TransactionType>;
    query?: {
        sort?: string | null;
        per_page?: number | string | null;
        filter?: { search?: string };
    };
}

export default function TransactionTypesIndex({
    workspace,
    types,
    query,
}: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<TransactionType | null>(null);
    const [toDelete, setToDelete] = useState<TransactionType | null>(null);
    const [search, setSearch] = useState(query?.filter?.search ?? '');

    const baseUrl = `/workspaces/${workspace.slug}/finance/transaction-types`;
    const canCreate = usePermission(PERMISSIONS.CreateFinanceTransactions);
    const canEdit = usePermission(PERMISSIONS.EditFinanceTransactions);
    const canDelete = usePermission(PERMISSIONS.DeleteFinanceTransactions);
    const showActions = canEdit || canDelete;

    const performQuery = useCallback(
        debounce((s: string) => {
            router.get(
                baseUrl,
                {
                    sort: query?.sort,
                    per_page: query?.per_page ?? undefined,
                    'filter[search]': s || undefined,
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['types'],
                },
            );
        }, 400),
        [baseUrl, query?.sort, query?.per_page],
    );

    useEffect(() => {
        performQuery(search);
        return () => performQuery.cancel();
    }, [search, performQuery]);

    const columns: ColumnDef<TransactionType>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Name" />
            ),
            cell: ({ row }) => {
                const s = transactionTypeStyle(row.original.name);
                return (
                    <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 font-mono text-[11px] uppercase ${s.cls}`}
                    >
                        {row.original.name}
                    </span>
                );
            },
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
                  } as ColumnDef<TransactionType>,
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Transaction Types`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Transaction Types"
                    description="Manage the transaction types available on finance transactions."
                >
                    {canCreate && (
                        <button
                            onClick={() => setCreateOpen(true)}
                            className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            Add Type
                        </button>
                    )}
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100"
                            placeholder="Search types..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={types.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(types, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    'filter[search]': search || undefined,
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
                    <TypeFormDialog
                        open={createOpen || editing !== null}
                        onOpenChange={(o) => {
                            if (!o) {
                                setCreateOpen(false);
                                setEditing(null);
                            }
                        }}
                        type={editing}
                        baseUrl={baseUrl}
                    />
                )}
                {canDelete && (
                    <FinanceDeleteDialog
                        open={!!toDelete}
                        onClose={() => setToDelete(null)}
                        title="Delete Transaction Type?"
                        description={`Delete "${toDelete?.name}"? Transactions already using it keep their stored value.`}
                        url={toDelete ? `${baseUrl}/${toDelete.id}` : ''}
                        successMessage="Transaction type deleted"
                    />
                )}
            </div>
        </AppLayout>
    );
}

function TypeFormDialog({
    open,
    onOpenChange,
    type,
    baseUrl,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    type?: TransactionType | null;
    baseUrl: string;
}) {
    const isEditing = !!type;
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm({ name: '' });

    useEffect(() => {
        if (open) {
            if (type) {
                setData('name', type.name);
            } else {
                reset();
                clearErrors();
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, type]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };
        if (isEditing) {
            put(`${baseUrl}/${type!.id}`, options);
        } else {
            post(baseUrl, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-md dark:bg-zinc-900">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing
                                ? 'Edit Transaction Type'
                                : 'Add Transaction Type'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {isEditing
                                ? 'Rename this transaction type.'
                                : 'Create a new transaction type for this workspace.'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        <Field label="Name" required error={errors.name}>
                            <input
                                type="text"
                                autoFocus
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="e.g. Adspent"
                                className={inputCls}
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
