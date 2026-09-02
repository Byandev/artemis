import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DeleteEntryDialog } from './delete-entry-dialog';
import { EntryFormDialog } from './entry-form-dialog';
import LedgerFilters, {
    applyFilters,
    hasActiveFilters,
} from './ledger-filters';
import {
    ACCOUNT_LABELS,
    LiquidationEntry,
    LiquidationTotals,
    TransactionTypeOption,
    WalletType,
    peso,
    postedDate,
} from './types';
import { useLedgerUrlState } from './use-ledger-url-state';

interface Props {
    workspace: Workspace;
    account: {
        id: number;
        name: string;
        currency: string;
        notes: string | null;
        is_active: boolean;
        opening_balance: number;
        wallet_type: WalletType | null;
    };
    entries: LiquidationEntry[];
    totals: LiquidationTotals;
    departments: string[];
    transactionTypes: TransactionTypeOption[];
    canManage: boolean;
}

const LABEL =
    'font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500';
const MUTED = 'text-gray-300 dark:text-gray-600';

function Tile({
    label,
    value,
    sub,
    accent,
}: {
    label: string;
    value: React.ReactNode;
    sub?: React.ReactNode;
    accent?: string;
}) {
    return (
        <div className="rounded-[12px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <p className={LABEL}>{label}</p>
            <p
                className={cn(
                    'mt-1.5 font-mono text-[20px] leading-none font-semibold tracking-tight tabular-nums',
                    accent ?? 'text-gray-900 dark:text-gray-100',
                )}
            >
                {value}
            </p>
            {sub && (
                <p className="mt-1.5 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                    {sub}
                </p>
            )}
        </div>
    );
}

/** "4 funding entries", "1 expense entry". */
const countOf = (n: number, kind: string) =>
    `${n} ${kind} ${n === 1 ? 'entry' : 'entries'}`;

const dash = <span className={MUTED}>–</span>;

/**
 * A wallet's liquidation summary: every funding and expense line against it,
 * with the running actual balance.
 *
 * The server hands over the rows and nothing else — sorting, filtering, the
 * date range and the tiles all run here, so narrowing the ledger never costs a
 * round trip.
 */
export default function GoTymeBalanceShow({
    workspace,
    account,
    entries,
    totals,
    departments,
    transactionTypes,
    canManage,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/sales-marketing/go-tyme-balance`;
    const entriesUrl = `${baseUrl}/${account.id}/entries`;

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<LiquidationEntry | null>(null);
    // The entry awaiting delete confirmation; null keeps that dialog shut.
    const [deleting, setDeleting] = useState<LiquidationEntry | null>(null);

    // Search, filters, the date range, the sort and the page all live in the
    // URL, so a reload — or a shared link — lands on the same view.
    const { filters, setFilters, setSorting, setPagination, initial } =
        useLedgerUrlState();

    const rows = useMemo(
        () => applyFilters(entries, filters),
        [entries, filters],
    );

    const filtered = hasActiveFilters(filters);

    // Unfiltered, the tiles are the server's aggregate. Filtered, they follow
    // what is on screen — so a date range doubles as a period summary.
    const summary = useMemo(() => {
        if (!filtered) {
            return {
                totalCredit: totals.total_credit,
                creditCount: totals.credit_count,
                totalDebit: totals.total_debit,
                debitCount: totals.debit_count,
                actualBalance: totals.actual_balance,
                firstDate: totals.first_date ?? undefined,
                lastDate: totals.last_date ?? undefined,
            };
        }

        const sum = (list: LiquidationEntry[]) =>
            list.reduce((total, r) => total + r.amount, 0);
        const credits = rows.filter((r) => r.type === 'in');
        const debits = rows.filter((r) => r.type === 'out');

        // rows arrive in ledger order, so the last one carries the balance as
        // of the end of the visible range.
        const last = rows[rows.length - 1];

        return {
            totalCredit: sum(credits),
            creditCount: credits.length,
            totalDebit: sum(debits),
            debitCount: debits.length,
            // Nothing in view leaves no running balance to read, so the tile
            // falls back to where the wallet started rather than to zero.
            actualBalance: last?.running_balance ?? account.opening_balance,
            firstDate: rows[0]?.date,
            lastDate: last?.date,
        };
    }, [filtered, rows, totals, account.opening_balance]);

    const openCreate = () => {
        setEditing(null);
        setDialogOpen(true);
    };

    const columns: ColumnDef<LiquidationEntry>[] = [
        {
            accessorKey: 'date',
            header: ({ column }) => (
                <SortableHeader column={column} title="Posted Date" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] whitespace-nowrap text-gray-500 dark:text-gray-400">
                    {postedDate(row.original.date)}
                </span>
            ),
        },
        {
            accessorKey: 'transaction_type_name',
            header: ({ column }) => (
                <SortableHeader column={column} title="Transaction" />
            ),
            cell: ({ row }) => (
                <span
                    className={cn(
                        'font-mono text-[12px] font-medium whitespace-nowrap',
                        row.original.type === 'in'
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-gray-700 dark:text-gray-200',
                    )}
                >
                    {row.original.transaction_type_name ?? dash}
                </span>
            ),
        },
        {
            accessorKey: 'description',
            header: ({ column }) => (
                <SortableHeader column={column} title="Type Expenses" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                    {row.original.description || dash}
                </span>
            ),
        },
        {
            accessorKey: 'department',
            header: ({ column }) => (
                <SortableHeader column={column} title="Department" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-400 dark:text-gray-500">
                    {row.original.department || dash}
                </span>
            ),
        },
        {
            id: 'credit',
            // null on debit rows so they sort together rather than as zeroes.
            accessorFn: (row) => (row.type === 'in' ? row.amount : null),
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Credit (Income)"
                    className="justify-end gap-1"
                />
            ),
            cell: ({ row }) => (
                <div className="text-right font-mono text-[12px] font-medium text-emerald-600 tabular-nums dark:text-emerald-400">
                    {row.original.type === 'in'
                        ? peso(row.original.amount)
                        : ''}
                </div>
            ),
        },
        {
            id: 'debit',
            accessorFn: (row) => (row.type === 'out' ? row.amount : null),
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Debit (Expenses)"
                    className="justify-end gap-1"
                />
            ),
            cell: ({ row }) => (
                <div className="text-right font-mono text-[12px] text-red-500 tabular-nums dark:text-red-400">
                    {row.original.type === 'out'
                        ? peso(row.original.amount)
                        : ''}
                </div>
            ),
        },
        {
            accessorKey: 'running_balance',
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Actual Balance"
                    className="justify-end gap-1"
                    help="The wallet's balance after this entry, in date order. It stays with its own row however the table is sorted."
                />
            ),
            cell: ({ row }) => (
                <div className="text-right font-mono text-[12px] font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                    {peso(row.original.running_balance)}
                </div>
            ),
        },
        {
            accessorKey: 'notes',
            header: ({ column }) => (
                <SortableHeader column={column} title="Remarks" />
            ),
            cell: ({ row }) => (
                <span
                    className="block max-w-[16rem] truncate font-mono text-[12px] text-gray-400 dark:text-gray-500"
                    title={row.original.notes ?? ''}
                >
                    {row.original.notes || dash}
                </span>
            ),
        },
        ...(canManage
            ? [
                  {
                      id: 'actions',
                      header: () => <div />,
                      cell: ({ row }) => (
                          <div className="flex justify-end gap-1">
                              <button
                                  onClick={() => {
                                      setEditing(row.original);
                                      setDialogOpen(true);
                                  }}
                                  title="Edit entry"
                                  className="flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-stone-100 hover:text-gray-700 dark:hover:bg-zinc-800 dark:hover:text-gray-200"
                              >
                                  <Pencil className="h-3 w-3" />
                              </button>
                              <button
                                  onClick={() => setDeleting(row.original)}
                                  title="Delete entry"
                                  className="flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                              >
                                  <Trash2 className="h-3 w-3" />
                              </button>
                          </div>
                      ),
                  } as ColumnDef<LiquidationEntry>,
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title={`${account.name} - Liquidation Summary`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                {/* Labelled, above the title — matching the Sales Targets and
                    Ad Spend Goals detail pages in the same sidebar group. */}
                <Link
                    href={baseUrl}
                    className="inline-flex items-center gap-1.5 font-mono text-[11px] font-medium text-gray-400 transition-colors hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-200"
                >
                    <ArrowLeft className="h-3.5 w-3.5" />
                    Go Tyme Balance
                </Link>

                <div className="mt-3 flex flex-wrap items-start justify-between gap-3 border-b border-black/6 pb-5 dark:border-white/6">
                    <div className="min-w-0">
                        <h1 className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-50">
                            Liquidation Summary
                        </h1>
                        <div className="mt-1.5 flex flex-wrap items-center gap-2.5">
                            <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                                {account.name}
                            </span>
                            {account.wallet_type && (
                                <span
                                    className={cn(
                                        'rounded-md px-2 py-0.5 font-mono text-[10px] font-medium',
                                        account.wallet_type === 'main'
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                                            : 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
                                    )}
                                >
                                    {ACCOUNT_LABELS[account.wallet_type]}
                                </span>
                            )}
                            {!account.is_active && (
                                <span className="rounded-full bg-stone-100 px-2.5 py-0.5 font-mono text-[10px] font-medium text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                                    inactive
                                </span>
                            )}
                        </div>
                    </div>

                    {canManage && (
                        <button
                            onClick={openCreate}
                            className="flex h-8 shrink-0 items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            Add Entry
                        </button>
                    )}
                </div>

                <div className="mt-6 mb-5 grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-5">
                    {/* First, because it is where the Actual Balance beside it
                        starts counting from — without it the arithmetic on this
                        row does not add up. */}
                    <Tile
                        label="Opening Balance"
                        value={peso(account.opening_balance)}
                        sub="before any entry"
                    />
                    <Tile
                        label="Total Credit"
                        value={peso(summary.totalCredit)}
                        accent="text-emerald-600 dark:text-emerald-400"
                        sub={countOf(summary.creditCount, 'funding')}
                    />
                    <Tile
                        label="Total Debit"
                        value={peso(summary.totalDebit)}
                        accent="text-red-500 dark:text-red-400"
                        sub={countOf(summary.debitCount, 'expense')}
                    />
                    <Tile
                        label="Actual Balance"
                        value={peso(summary.actualBalance)}
                        sub={
                            summary.lastDate
                                ? `as of ${postedDate(summary.lastDate)}`
                                : 'nothing recorded yet'
                        }
                    />
                    <Tile
                        label="Entries"
                        value={filtered ? rows.length : totals.entry_count}
                        sub={
                            summary.firstDate && summary.lastDate
                                ? `${postedDate(summary.firstDate)} – ${postedDate(summary.lastDate)}`
                                : undefined
                        }
                    />
                </div>

                {entries.length > 0 && (
                    <LedgerFilters
                        entries={entries}
                        value={filters}
                        onChange={setFilters}
                    />
                )}

                {entries.length === 0 ? (
                    <div className="rounded-[14px] border border-black/6 bg-white py-16 text-center text-[13px] text-gray-400 dark:border-white/6 dark:bg-zinc-900">
                        No entries yet.
                        {canManage && ' Add one to start the ledger.'}
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                            <DataTable
                                columns={columns}
                                data={rows}
                                enableInternalPagination
                                initialSorting={initial.sorting}
                                initialPagination={initial.pagination}
                                onSortChange={setSorting}
                                onInternalPaginationChange={setPagination}
                            />
                        </div>
                        {filtered && (
                            <p className="mt-2 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                Showing {rows.length} of {entries.length}{' '}
                                entries · totals above follow the filter
                            </p>
                        )}
                    </>
                )}
            </div>

            <EntryFormDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                entry={editing}
                endpoint={entriesUrl}
                departments={departments}
                transactionTypes={transactionTypes}
            />

            <DeleteEntryDialog
                entriesUrl={entriesUrl}
                entry={deleting}
                onClose={() => setDeleting(null)}
            />
        </AppLayout>
    );
}
