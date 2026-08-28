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

type Nature = 'debit' | 'credit';

// Where the type lands on the income statement; null = excluded (not shown).
type IncomeStatementSection = 'cost_of_sales' | 'opex' | null;

interface TransactionType {
    id: number;
    name: string;
    nature: Nature;
    income_statement_section: IncomeStatementSection;
    // Which company metric an OPEX pool is split across products by; null =
    // the default. Only meaningful when the section is `opex`.
    opex_allocation_basis: string | null;
}

interface AllocationBasis {
    value: string;
    label: string;
}

interface Props {
    workspace: Workspace;
    types: PaginatedData<TransactionType>;
    allocationBases: AllocationBasis[];
    defaultAllocationBasis: string;
    query?: {
        sort?: string | null;
        per_page?: number | string | null;
        filter?: { search?: string };
    };
}

export default function TransactionTypesIndex({
    workspace,
    types,
    allocationBases,
    defaultAllocationBasis,
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
        {
            accessorKey: 'nature',
            enableSorting: false,
            header: () => (
                <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Nature
                </div>
            ),
            cell: ({ row }) =>
                row.original.nature === 'credit' ? (
                    <span className="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-0.5 font-mono text-[11px] text-sky-700 uppercase dark:bg-sky-950/40 dark:text-sky-300">
                        Credit
                    </span>
                ) : (
                    <span className="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 font-mono text-[11px] text-amber-700 uppercase dark:bg-amber-950/40 dark:text-amber-300">
                        Debit
                    </span>
                ),
        },
        {
            accessorKey: 'income_statement_section',
            enableSorting: false,
            header: () => (
                <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Income Statement
                </div>
            ),
            cell: ({ row }) => {
                const section = row.original.income_statement_section;
                if (section === 'cost_of_sales') {
                    return (
                        <span className="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 font-mono text-[11px] text-emerald-700 uppercase dark:bg-emerald-950/40 dark:text-emerald-300">
                            Cost of Sales
                        </span>
                    );
                }
                if (section === 'opex') {
                    return (
                        <span className="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-0.5 font-mono text-[11px] text-gray-500 uppercase dark:bg-zinc-800 dark:text-gray-400">
                            OPEX
                        </span>
                    );
                }
                return (
                    <span className="inline-flex items-center rounded-full bg-transparent px-2.5 py-0.5 font-mono text-[11px] text-gray-300 uppercase ring-1 ring-black/6 ring-inset dark:text-gray-600 dark:ring-white/6">
                        Excluded
                    </span>
                );
            },
        },
        {
            accessorKey: 'opex_allocation_basis',
            enableSorting: false,
            header: () => (
                <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Split by
                </div>
            ),
            cell: ({ row }) => {
                // Only OPEX pools get split across products.
                if (row.original.income_statement_section !== 'opex') {
                    return (
                        <span className="text-gray-300 dark:text-gray-600">
                            —
                        </span>
                    );
                }
                const basis =
                    row.original.opex_allocation_basis ??
                    defaultAllocationBasis;
                const label =
                    allocationBases.find((b) => b.value === basis)?.label ??
                    basis;

                return (
                    <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                        {label}
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
                        allocationBases={allocationBases}
                        defaultAllocationBasis={defaultAllocationBasis}
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
    allocationBases,
    defaultAllocationBasis,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    type?: TransactionType | null;
    baseUrl: string;
    allocationBases: AllocationBasis[];
    defaultAllocationBasis: string;
}) {
    const isEditing = !!type;
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<{
            name: string;
            nature: Nature;
            income_statement_section: IncomeStatementSection;
            opex_allocation_basis: string | null;
        }>({
            name: '',
            nature: 'debit',
            income_statement_section: 'opex',
            opex_allocation_basis: null,
        });

    useEffect(() => {
        if (open) {
            if (type) {
                setData('name', type.name);
                setData('nature', type.nature);
                setData(
                    'income_statement_section',
                    type.income_statement_section,
                );
                setData('opex_allocation_basis', type.opex_allocation_basis);
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

                        <Field label="Nature" required error={errors.nature}>
                            <div className="grid grid-cols-2 gap-2">
                                {(['debit', 'credit'] as Nature[]).map((n) => {
                                    const active = data.nature === n;
                                    return (
                                        <button
                                            key={n}
                                            type="button"
                                            onClick={() => setData('nature', n)}
                                            className={`h-9 rounded-[10px] border font-mono text-[12px] uppercase transition-all ${
                                                active
                                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                    : 'border-black/6 bg-stone-50 text-gray-500 hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-400'
                                            }`}
                                        >
                                            {n}
                                        </button>
                                    );
                                })}
                            </div>
                            <p className="mt-1.5 text-[11px] text-gray-400">
                                Debit — expenses &amp; assets. Credit — income,
                                liabilities &amp; equity.
                            </p>
                        </Field>

                        <Field
                            label="Income Statement"
                            error={errors.income_statement_section}
                        >
                            <div className="space-y-2">
                                {(
                                    [
                                        {
                                            value: 'cost_of_sales',
                                            title: 'Cost of Sales',
                                            desc: 'Deducted from Delivered to reach Gross Profit.',
                                        },
                                        {
                                            value: 'opex',
                                            title: 'OPEX',
                                            desc: 'Deducted from Gross Profit to reach Net Profit.',
                                        },
                                        {
                                            value: null,
                                            title: 'Excluded',
                                            desc: 'Not shown on the income statement at all.',
                                        },
                                    ] as {
                                        value: IncomeStatementSection;
                                        title: string;
                                        desc: string;
                                    }[]
                                ).map((opt) => {
                                    const active =
                                        data.income_statement_section ===
                                        opt.value;
                                    return (
                                        <button
                                            key={opt.title}
                                            type="button"
                                            onClick={() =>
                                                setData(
                                                    'income_statement_section',
                                                    opt.value,
                                                )
                                            }
                                            className={`flex w-full items-start gap-3 rounded-[10px] border p-3 text-left transition-all ${
                                                active
                                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30'
                                                    : 'border-black/6 bg-stone-50 hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800/50 dark:hover:bg-zinc-800'
                                            }`}
                                        >
                                            <span
                                                className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border ${
                                                    active
                                                        ? 'border-emerald-500'
                                                        : 'border-gray-300 dark:border-gray-600'
                                                }`}
                                            >
                                                {active && (
                                                    <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                                )}
                                            </span>
                                            <span className="text-[12px] leading-snug text-gray-600 dark:text-gray-300">
                                                <span className="font-medium text-gray-800 dark:text-gray-100">
                                                    {opt.title}
                                                </span>
                                                <br />
                                                {opt.desc}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        </Field>

                        {/* A shared OPEX pool is split across products by its
                            share of some company metric — and which metric fits
                            depends on the cost. A CSR's salary tracks every
                            order taken (returns included); warehouse and courier
                            costs track parcels actually delivered. */}
                        {data.income_statement_section === 'opex' && (
                            <Field
                                label="Split across products by"
                                error={errors.opex_allocation_basis}
                            >
                                <div className="space-y-2">
                                    {allocationBases.map((opt) => {
                                        const active =
                                            (data.opex_allocation_basis ??
                                                defaultAllocationBasis) ===
                                            opt.value;
                                        return (
                                            <button
                                                key={opt.value}
                                                type="button"
                                                onClick={() =>
                                                    setData(
                                                        'opex_allocation_basis',
                                                        opt.value,
                                                    )
                                                }
                                                className={`flex w-full items-center gap-3 rounded-[10px] border p-3 text-left transition-all ${
                                                    active
                                                        ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30'
                                                        : 'border-black/6 bg-stone-50 hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800/50 dark:hover:bg-zinc-800'
                                                }`}
                                            >
                                                <span
                                                    className={`flex h-4 w-4 shrink-0 items-center justify-center rounded-full border ${
                                                        active
                                                            ? 'border-emerald-500'
                                                            : 'border-gray-300 dark:border-gray-600'
                                                    }`}
                                                >
                                                    {active && (
                                                        <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                                    )}
                                                </span>
                                                <span className="text-[12px] text-gray-800 dark:text-gray-100">
                                                    {opt.label}
                                                    {opt.value ===
                                                        defaultAllocationBasis && (
                                                        <span className="ml-2 text-[10px] text-gray-400">
                                                            default
                                                        </span>
                                                    )}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                                <p className="mt-1.5 text-[11px] text-gray-400">
                                    Each product carries the slice of this pool
                                    matching its share of the chosen metric.
                                </p>
                            </Field>
                        )}
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
