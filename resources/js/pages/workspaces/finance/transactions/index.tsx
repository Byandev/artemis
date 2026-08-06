import PageHeader from '@/components/common/PageHeader';
import { FinanceDeleteDialog } from '@/components/finance/delete-dialog';
import { ImportTransactionsDialog } from '@/components/finance/import-transactions-dialog';
import {
    SUB_CATEGORIES,
    SUB_CATEGORY_LABEL,
    SubCategory,
} from '@/components/finance/sub-category';
import { FinanceTransaction } from '@/components/finance/transaction-form';
import {
    buildTransactionTypeOptions,
    TransactionTypeItem,
    transactionTypeLabel,
    transactionTypeStyle,
} from '@/components/finance/transaction-type';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MultiSelect } from '@/components/ui/multi-select';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef, RowSelectionState } from '@tanstack/react-table';
import flatpickr from 'flatpickr';
import { debounce, omit } from 'lodash';
import {
    Download,
    MoreHorizontal,
    Pencil,
    Search,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import moment from 'moment';
import { useCallback, useEffect, useMemo, useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

interface Row extends FinanceTransaction {
    running_balance?: number | string | null;
    position?: number | null;
    account: { id: number; name: string; currency: string } | null;
    remittance: { id: number; courier: string; soa_number: string } | null;
}

interface AccountOpt {
    id: number;
    name: string;
    currency: string;
}

interface Totals {
    credit: number;
    debit: number;
}

interface Props {
    workspace: Workspace;
    transactions: PaginatedData<Row>;
    accounts: AccountOpt[];
    transactionTypes: TransactionTypeItem[];
    users: { id: number; name: string }[];
    products: string[];
    totals: Totals;
    query?: {
        sort?: string | null;
        filter?: {
            search?: string;
            type?: 'in' | 'out';
            account_id?: string | number;
            transaction_type_id?: string | string[];
            sub_category?: string | string[];
            missing_type?: string | boolean;
            expenses_missing_sub?: string | boolean;
            date_from?: string;
            date_to?: string;
        };
    };
}

const fmt = (v: number | string) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const STATUS_STYLE: Record<string, string> = {
    pending:
        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
    approved: 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400',
    posted: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
};

export default function TransactionsIndex({
    workspace,
    transactions,
    accounts,
    transactionTypes,
    totals,
    query,
}: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const typeOptions = useMemo(
        () => buildTransactionTypeOptions(transactionTypes),
        [transactionTypes],
    );
    const typeNameById = useMemo(
        () => new Map(transactionTypes.map((t) => [t.id, t.name])),
        [transactionTypes],
    );
    const [importOpen, setImportOpen] = useState(false);
    const [toDelete, setToDelete] = useState<Row | null>(null);
    const [search, setSearch] = useState(query?.filter?.search ?? '');
    const [typeFilter, setTypeFilter] = useState<'' | 'in' | 'out'>(
        query?.filter?.type ?? '',
    );
    const [accountFilter, setAccountFilter] = useState<string>(
        query?.filter?.account_id != null
            ? String(query.filter.account_id)
            : '',
    );
    const [txnTypeFilter, setTxnTypeFilter] = useState<string[]>(
        query?.filter?.transaction_type_id
            ? Array.isArray(query.filter.transaction_type_id)
                ? query.filter.transaction_type_id
                : [query.filter.transaction_type_id]
            : [],
    );
    const [subCategoryFilter, setSubCategoryFilter] = useState<string[]>(
        query?.filter?.sub_category
            ? Array.isArray(query.filter.sub_category)
                ? query.filter.sub_category
                : [query.filter.sub_category]
            : [],
    );
    const boolish = (v: string | boolean | undefined) =>
        v === true || v === '1' || v === 'true';
    const [missingType, setMissingType] = useState<boolean>(
        boolish(query?.filter?.missing_type),
    );
    const [expensesMissingSub, setExpensesMissingSub] = useState<boolean>(
        boolish(query?.filter?.expenses_missing_sub),
    );
    const [dateFrom, setDateFrom] = useState<string | undefined>(
        query?.filter?.date_from,
    );
    const [dateTo, setDateTo] = useState<string | undefined>(
        query?.filter?.date_to,
    );
    const defaultDate = useMemo(
        () => (dateFrom && dateTo ? [dateFrom, dateTo] : undefined),
        [],
    );
    const handleDateChange = (dates: Date[]) => {
        if (dates.length === 0) {
            setDateFrom(undefined);
            setDateTo(undefined);
            return;
        }
        if (dates.length !== 2) return;
        const from = moment(dates[0]).format('YYYY-MM-DD');
        const to = moment(dates[1]).format('YYYY-MM-DD');
        if (from === dateFrom && to === dateTo) return;
        setDateFrom(from);
        setDateTo(to);
    };
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [bulkType, setBulkType] = useState<string>('');
    const [bulkSubCategory, setBulkSubCategory] = useState<SubCategory | ''>(
        '',
    );
    const [bulkProcessing, setBulkProcessing] = useState(false);

    const baseUrl = `/workspaces/${workspace.slug}/finance/transactions`;
    const canCreateTransactions = usePermission(
        PERMISSIONS.CreateFinanceTransactions,
    );
    const canEditTransactions = usePermission(
        PERMISSIONS.EditFinanceTransactions,
    );
    const canDeleteTransactions = usePermission(
        PERMISSIONS.DeleteFinanceTransactions,
    );
    const canUseTransactionActions =
        canEditTransactions || canDeleteTransactions;
    const canExportTransactions =
        canCreateTransactions || canEditTransactions || canDeleteTransactions;

    const handleExport = () => {
        if (!canExportTransactions) return;

        const params = new URLSearchParams();
        if (search) params.set('filter[search]', search);
        if (typeFilter) params.set('filter[type]', typeFilter);
        if (accountFilter) params.set('filter[account_id]', accountFilter);
        txnTypeFilter.forEach((v) =>
            params.append('filter[transaction_type_id][]', v),
        );
        subCategoryFilter.forEach((v) =>
            params.append('filter[sub_category][]', v),
        );
        if (missingType) params.set('filter[missing_type]', '1');
        if (expensesMissingSub) params.set('filter[expenses_missing_sub]', '1');
        const qs = params.toString();
        window.location.href = `${baseUrl}/export${qs ? `?${qs}` : ''}`;
    };

    const selectedIds = useMemo(
        () => Object.keys(rowSelection).filter((id) => rowSelection[id]),
        [rowSelection],
    );
    const selectedCount = selectedIds.length;

    useEffect(() => {
        setRowSelection({});
    }, [transactions.data]);

    const applyBulkType = () => {
        if (!canEditTransactions || !selectedCount) return;
        setBulkProcessing(true);
        router.put(
            `${baseUrl}/bulk-update-type`,
            {
                ids: selectedIds.map(Number),
                transaction_type_id: bulkType === '' ? null : bulkType,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setBulkProcessing(false),
                onSuccess: () => {
                    setRowSelection({});
                    setBulkType('');
                },
            },
        );
    };

    const applyBulkSubCategory = () => {
        if (!canEditTransactions || !selectedCount) return;
        setBulkProcessing(true);
        router.put(
            `${baseUrl}/bulk-update-sub-category`,
            {
                ids: selectedIds.map(Number),
                sub_category: bulkSubCategory === '' ? null : bulkSubCategory,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setBulkProcessing(false),
                onSuccess: () => {
                    setRowSelection({});
                    setBulkSubCategory('');
                },
            },
        );
    };

    const performQuery = useCallback(
        debounce(
            (
                s: string,
                t: '' | 'in' | 'out',
                a: string,
                tt: string[],
                sc: string[],
                mt: boolean,
                ems: boolean,
                df: string | undefined,
                dt: string | undefined,
            ) => {
                router.get(
                    baseUrl,
                    {
                        sort: query?.sort,
                        'filter[search]': s || undefined,
                        'filter[type]': t || undefined,
                        'filter[account_id]': a || undefined,
                        'filter[transaction_type_id]': tt.length
                            ? tt
                            : undefined,
                        'filter[sub_category]': sc.length ? sc : undefined,
                        'filter[missing_type]': mt ? 1 : undefined,
                        'filter[expenses_missing_sub]': ems ? 1 : undefined,
                        'filter[date_from]': df || undefined,
                        'filter[date_to]': dt || undefined,
                        page: 1,
                    },
                    {
                        preserveState: true,
                        replace: true,
                        preserveScroll: true,
                        only: ['transactions', 'totals'],
                    },
                );
            },
            400,
        ),
        [baseUrl, query?.sort],
    );

    useEffect(() => {
        performQuery(
            search,
            typeFilter,
            accountFilter,
            txnTypeFilter,
            subCategoryFilter,
            missingType,
            expensesMissingSub,
            dateFrom,
            dateTo,
        );
        return () => performQuery.cancel();
    }, [
        search,
        typeFilter,
        accountFilter,
        txnTypeFilter,
        subCategoryFilter,
        missingType,
        expensesMissingSub,
        dateFrom,
        dateTo,
        performQuery,
    ]);

    const columns: ColumnDef<Row>[] = [
        ...(canEditTransactions
            ? [
                  {
                      id: 'select',
                      enableSorting: false,
                      header: ({ table }) => (
                          <div className="flex h-5 items-center justify-center">
                              <Checkbox
                                  checked={
                                      table.getRowModel().rows.length > 0 &&
                                      table
                                          .getRowModel()
                                          .rows.every((r) => r.getIsSelected())
                                  }
                                  onCheckedChange={(value) => {
                                      const next: RowSelectionState = {
                                          ...rowSelection,
                                      };
                                      table.getRowModel().rows.forEach((r) => {
                                          next[r.id] = !!value;
                                      });
                                      setRowSelection(next);
                                  }}
                                  aria-label="Select all"
                              />
                          </div>
                      ),
                      cell: ({ row }) => (
                          <div className="flex h-5 items-center justify-center">
                              <Checkbox
                                  checked={row.getIsSelected()}
                                  onCheckedChange={(value) =>
                                      row.toggleSelected(!!value)
                                  }
                                  aria-label="Select row"
                              />
                          </div>
                      ),
                  } as ColumnDef<Row>,
              ]
            : []),
        {
            accessorKey: 'date',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Posted Date" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.date}
                </span>
            ),
        },
        {
            id: 'account',
            header: () => (
                <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase">
                    Accounts
                </div>
            ),
            cell: ({ row }) => (
                <span className="text-[12px] text-gray-700 dark:text-gray-200">
                    {row.original.account?.name ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'description',
            enableSorting: false,
            header: () => (
                <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase">
                    Transaction
                </div>
            ),
            cell: ({ row }) => (
                <div className="flex max-w-[320px] flex-col gap-0.5">
                    <span
                        className="truncate text-[12px] text-gray-800 dark:text-gray-100"
                        title={row.original.description}
                    >
                        <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            [{row.original.position ?? '—'}]
                        </span>{' '}
                        {row.original.description}
                    </span>
                    {row.original.remittance && (
                        <span className="truncate text-[10px] text-gray-400">
                            SOA {row.original.remittance.soa_number} ·{' '}
                            {row.original.remittance.courier}
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: 'requested_by',
            header: () => (
                <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase">
                    Requested By
                </div>
            ),
            cell: ({ row }) => (
                <span className="text-[12px] text-gray-700 dark:text-gray-200">
                    {row.original.requester?.name || '—'}
                </span>
            ),
        },
        {
            id: 'approved_by',
            header: () => (
                <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase">
                    Approved By
                </div>
            ),
            cell: ({ row }) => (
                <span className="text-[12px] text-gray-700 dark:text-gray-200">
                    {row.original.approver?.name || '—'}
                </span>
            ),
        },
        {
            id: 'department',
            header: () => (
                <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase">
                    Department
                </div>
            ),
            cell: ({ row }) => (
                <span className="text-[12px] text-gray-700 dark:text-gray-200">
                    {row.original.department || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'transaction_type_id',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Type of Expense"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => {
                // Prefer the dynamic type (via FK); fall back to the legacy
                // enum value for un-linked rows.
                const typeName =
                    row.original.transaction_type_id != null
                        ? (typeNameById.get(row.original.transaction_type_id) ??
                          null)
                        : row.original.transaction_type;
                if (!typeName)
                    return (
                        <div className="text-center font-mono text-[10px] text-gray-300">
                            —
                        </div>
                    );
                const s = transactionTypeStyle(typeName);
                const label = transactionTypeLabel(typeName);
                return (
                    <div className="text-center">
                        <span
                            className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[10px] uppercase ${s.cls}`}
                        >
                            {label}
                        </span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'sub_category',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Sub Category"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    {row.original.sub_category ? (
                        <span className="inline-flex items-center rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] text-gray-500 uppercase dark:bg-zinc-800 dark:text-gray-400">
                            {SUB_CATEGORY_LABEL[row.original.sub_category]}
                        </span>
                    ) : (
                        <span className="font-mono text-[10px] text-gray-300">
                            —
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: 'credit',
            header: () => (
                <div className="text-right font-mono text-[10px] tracking-wider text-gray-300 uppercase">
                    Credit
                </div>
            ),
            cell: ({ row }) => (
                <div className="text-right font-mono text-[12px] text-emerald-600 dark:text-emerald-400">
                    {row.original.type === 'in' ? fmt(row.original.amount) : ''}
                </div>
            ),
        },
        {
            id: 'debit',
            header: () => (
                <div className="text-right font-mono text-[10px] tracking-wider text-gray-300 uppercase">
                    Debit
                </div>
            ),
            cell: ({ row }) => (
                <div className="text-right font-mono text-[12px] text-red-500 dark:text-red-400">
                    {row.original.type === 'out'
                        ? fmt(row.original.amount)
                        : ''}
                </div>
            ),
        },
        {
            id: 'running_balance',
            header: () => (
                <div className="text-right font-mono text-[10px] tracking-wider text-gray-300 uppercase">
                    Running Balance
                </div>
            ),
            cell: ({ row }) => {
                const bal = Number(row.original.running_balance ?? 0);
                return (
                    <div
                        className={`text-right font-mono text-[12px] font-semibold ${bal >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-500'}`}
                    >
                        {fmt(bal)}
                    </div>
                );
            },
        },
        {
            id: 'reference_no',
            header: () => (
                <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase">
                    Reference No.
                </div>
            ),
            // The fund request this entry settles sits under the reference, so
            // the ledger shows what a payout was authorised by.
            cell: ({ row }) => (
                <div className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    <span>{row.original.reference_no || '—'}</span>
                    {row.original.fund_request && (
                        <span className="mt-0.5 block text-[10px] text-emerald-600 dark:text-emerald-500">
                            {row.original.fund_request.reference_no}
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: 'charge_to',
            header: () => (
                <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase">
                    Charge To
                </div>
            ),
            cell: ({ row }) => {
                const charged = row.original.charge_to_users ?? [];

                if (charged.length === 0) {
                    return (
                        <span className="text-[12px] text-gray-700 dark:text-gray-200">
                            —
                        </span>
                    );
                }

                // A split shows each share so the row still reconciles at a glance.
                return (
                    <span
                        className="text-[12px] text-gray-700 dark:text-gray-200"
                        title={charged
                            .map(
                                (u) =>
                                    `${u.name}: ${Number(u.pivot?.amount ?? 0).toFixed(2)}`,
                            )
                            .join('\n')}
                    >
                        {charged.length === 1
                            ? charged[0].name
                            : charged.map((u) => u.name).join(', ')}
                    </span>
                );
            },
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
                const status = row.original.status ?? 'posted';
                const s = STATUS_STYLE[status] ?? STATUS_STYLE.posted;
                return (
                    <div className="text-center">
                        <span
                            className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[10px] uppercase ${s}`}
                        >
                            {status}
                        </span>
                    </div>
                );
            },
        },
        {
            id: 'remarks',
            header: () => (
                <div className="font-mono text-[10px] tracking-wider text-gray-300 uppercase">
                    Remarks
                </div>
            ),
            cell: ({ row }) => (
                <span
                    className="block max-w-[220px] truncate text-[12px] text-gray-600 dark:text-gray-400"
                    title={row.original.notes ?? ''}
                >
                    {row.original.notes || '—'}
                </span>
            ),
        },
        ...(canUseTransactionActions
            ? [
                  {
                      id: 'actions',
                      header: () => (
                          <div className="text-center font-mono text-[10px] tracking-wider text-gray-300 uppercase">
                              Actions
                          </div>
                      ),
                      cell: ({ row }) => (
                          <div className="flex justify-center">
                              <DropdownMenu>
                                  <DropdownMenuTrigger asChild>
                                      <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800">
                                          <MoreHorizontal className="h-3.5 w-3.5" />
                                      </button>
                                  </DropdownMenuTrigger>
                                  <DropdownMenuContent
                                      align="end"
                                      className="w-36"
                                  >
                                      {canEditTransactions && (
                                          <DropdownMenuItem asChild>
                                              <Link
                                                  href={`${baseUrl}/${row.original.id}/edit`}
                                              >
                                                  <Pencil className="mr-2 h-3.5 w-3.5" />{' '}
                                                  Edit
                                              </Link>
                                          </DropdownMenuItem>
                                      )}
                                      {canEditTransactions &&
                                          canDeleteTransactions && (
                                              <DropdownMenuSeparator />
                                          )}
                                      {canDeleteTransactions && (
                                          <DropdownMenuItem
                                              className="text-red-600 focus:text-red-600"
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
                  } as ColumnDef<Row>,
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Finance Transactions`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Transactions"
                    description="Ledger entries across all accounts."
                >
                    {canExportTransactions && (
                        <button
                            onClick={handleExport}
                            className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                        >
                            <Download className="h-3.5 w-3.5" /> Export CSV
                        </button>
                    )}
                    {canCreateTransactions && (
                        <>
                            <button
                                onClick={() => setImportOpen(true)}
                                className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                            >
                                <Upload className="h-3.5 w-3.5" /> Import CSV
                            </button>
                            <Link
                                href={`${baseUrl}/create`}
                                className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white hover:bg-emerald-700"
                            >
                                Add Transaction
                            </Link>
                        </>
                    )}
                </PageHeader>

                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100"
                            placeholder="Search by description..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <DatePicker
                        id="finance-transactions-date-range"
                        mode="range"
                        onChange={handleDateChange}
                        defaultDate={defaultDate as never as DateOption}
                    />
                    <select
                        value={accountFilter}
                        onChange={(e) => setAccountFilter(e.target.value)}
                        className="h-9 rounded-[10px] border border-black/6 bg-stone-100 px-2.5 font-mono! text-[11px]! text-gray-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                    >
                        <option value="">All accounts</option>
                        {accounts.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name} ({a.currency})
                            </option>
                        ))}
                    </select>
                    <MultiSelect
                        options={typeOptions}
                        selected={txnTypeFilter}
                        onChange={setTxnTypeFilter}
                        placeholder="All txn types"
                        className="w-44"
                        compact
                    />
                    <MultiSelect
                        options={SUB_CATEGORIES}
                        selected={subCategoryFilter}
                        onChange={setSubCategoryFilter}
                        placeholder="All sub categories"
                        className="w-48"
                        compact
                    />
                    <div className="inline-flex h-9 overflow-hidden rounded-[10px] border border-black/6 bg-stone-100 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800">
                        {(
                            [
                                { value: '', label: 'All' },
                                { value: 'in', label: 'Credit' },
                                { value: 'out', label: 'Debit' },
                            ] as const
                        ).map((opt) => (
                            <button
                                key={opt.value || 'all'}
                                onClick={() => setTypeFilter(opt.value)}
                                className={`px-3 transition-colors ${
                                    typeFilter === opt.value
                                        ? opt.value === 'in'
                                            ? 'bg-emerald-600 text-white'
                                            : opt.value === 'out'
                                              ? 'bg-red-500 text-white'
                                              : 'bg-gray-700 text-white dark:bg-gray-200 dark:text-gray-900'
                                        : 'text-gray-600 hover:bg-stone-200 dark:text-gray-300 dark:hover:bg-zinc-700'
                                }`}
                            >
                                {opt.label}
                            </button>
                        ))}
                    </div>
                    <button
                        onClick={() => setMissingType((v) => !v)}
                        className={`h-9 rounded-[10px] border px-3 font-mono! text-[11px]! transition-colors ${
                            missingType
                                ? 'border-amber-500 bg-amber-500 text-white'
                                : 'border-black/6 bg-stone-100 text-gray-600 hover:bg-stone-200 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700'
                        }`}
                    >
                        No Txn Type
                    </button>
                    <button
                        onClick={() => setExpensesMissingSub((v) => !v)}
                        className={`h-9 rounded-[10px] border px-3 font-mono! text-[11px]! transition-colors ${
                            expensesMissingSub
                                ? 'border-amber-500 bg-amber-500 text-white'
                                : 'border-black/6 bg-stone-100 text-gray-600 hover:bg-stone-200 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700'
                        }`}
                    >
                        Expenses w/o Sub Cat
                    </button>
                </div>

                {canEditTransactions && selectedCount > 0 && (
                    <div className="mb-3 flex flex-wrap items-center gap-2 rounded-[10px] border border-emerald-200 bg-emerald-50/60 px-3 py-2 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                        <span className="font-mono text-[11px] text-emerald-700 dark:text-emerald-300">
                            {selectedCount} selected
                        </span>
                        <span className="h-4 w-px bg-emerald-200 dark:bg-emerald-500/30" />
                        <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                            Txn type:
                        </span>
                        <select
                            value={bulkType}
                            onChange={(e) => setBulkType(e.target.value)}
                            className="h-8 rounded-lg border border-black/8 bg-white px-2 font-mono! text-[11px]! text-gray-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                        >
                            <option value="">— clear —</option>
                            {typeOptions.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                        <button
                            onClick={applyBulkType}
                            disabled={bulkProcessing}
                            className="flex h-8 items-center rounded-lg bg-emerald-600 px-3 font-mono! text-[11px]! font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {bulkProcessing ? 'Applying…' : 'Apply'}
                        </button>
                        <span className="h-4 w-px bg-emerald-200 dark:bg-emerald-500/30" />
                        <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                            Sub category:
                        </span>
                        <select
                            value={bulkSubCategory}
                            onChange={(e) =>
                                setBulkSubCategory(
                                    e.target.value as SubCategory | '',
                                )
                            }
                            className="h-8 rounded-lg border border-black/8 bg-white px-2 font-mono! text-[11px]! text-gray-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                        >
                            <option value="">— clear —</option>
                            {SUB_CATEGORIES.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </select>
                        <button
                            onClick={applyBulkSubCategory}
                            disabled={bulkProcessing}
                            className="flex h-8 items-center rounded-lg bg-emerald-600 px-3 font-mono! text-[11px]! font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {bulkProcessing ? 'Applying…' : 'Apply'}
                        </button>
                        <button
                            onClick={() => setRowSelection({})}
                            className="ml-auto flex h-8 items-center gap-1 rounded-lg border border-black/8 bg-white px-2.5 font-mono! text-[11px]! text-gray-600 hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            <X className="h-3 w-3" /> Clear
                        </button>
                    </div>
                )}

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={transactions.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(transactions, ['data']) }}
                        rowSelection={rowSelection}
                        onRowSelectionChange={setRowSelection}
                        getRowId={(row) => String(row.id)}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    'filter[search]': search || undefined,
                                    'filter[type]': typeFilter || undefined,
                                    'filter[account_id]':
                                        accountFilter || undefined,
                                    'filter[transaction_type_id]':
                                        txnTypeFilter.length
                                            ? txnTypeFilter
                                            : undefined,
                                    'filter[sub_category]':
                                        subCategoryFilter.length
                                            ? subCategoryFilter
                                            : undefined,
                                    'filter[missing_type]': missingType
                                        ? 1
                                        : undefined,
                                    'filter[expenses_missing_sub]':
                                        expensesMissingSub ? 1 : undefined,
                                    'filter[date_from]': dateFrom || undefined,
                                    'filter[date_to]': dateTo || undefined,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page ?? undefined,
                                },
                                {
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                    // Paging and sorting only move the ledger.
                                    // Without this the form's option lists
                                    // (accounts, users, products, fund
                                    // requests) are rebuilt on every click.
                                    only: ['transactions', 'totals'],
                                },
                            );
                        }}
                    />
                </div>

                <ul className="mt-3 flex flex-col items-start gap-1 rounded-[10px] border border-black/6 bg-stone-50 px-4 py-3 dark:border-white/6 dark:bg-zinc-900/60">
                    <li className="font-mono text-[12px] text-emerald-700 dark:text-emerald-400">
                        Total Credit : ₱{fmt(totals.credit)}
                    </li>
                    <li className="font-mono text-[12px] text-red-600 dark:text-red-400">
                        Total Debit : ₱{fmt(totals.debit)}
                    </li>
                </ul>

                {canCreateTransactions && (
                    <ImportTransactionsDialog
                        open={importOpen}
                        onOpenChange={setImportOpen}
                        workspaceSlug={workspace.slug}
                        accounts={accounts}
                    />
                )}
                {canDeleteTransactions && (
                    <FinanceDeleteDialog
                        open={!!toDelete}
                        onClose={() => setToDelete(null)}
                        title="Delete Transaction?"
                        description="Remove this ledger entry?"
                        url={toDelete ? `${baseUrl}/${toDelete.id}` : ''}
                        successMessage="Transaction deleted"
                    />
                )}
            </div>
        </AppLayout>
    );
}
