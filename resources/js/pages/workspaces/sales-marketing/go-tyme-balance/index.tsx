import PageHeader from '@/components/common/PageHeader';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { TriangleDownIcon, TriangleUpIcon } from '@radix-ui/react-icons';
import flatpickr from 'flatpickr';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import moment from 'moment';
import { useMemo, useState } from 'react';
import BalanceCell from './balance-cell';
import { DeleteWalletDialog } from './delete-wallet-dialog';
import { ACCOUNT_LABELS, GridDate, WalletRow, WalletType, peso } from './types';
import { WalletFormDialog } from './wallet-form-dialog';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    workspace: Workspace;
    dates: GridDate[];
    rows: WalletRow[];
    totals: Record<string, number | null>;
    query: { start: string; end: string };
    canManage: boolean;
}

const cell = 'px-3 py-2 border-b border-black/5 dark:border-white/5';
const stickyLeft = 'sticky z-10 bg-white dark:bg-zinc-900';
const stickyTop = 'sticky top-0 z-20 bg-white dark:bg-zinc-900';
// The two name columns in the header sit in both planes, so they outrank each.
const stickyCorner = 'sticky top-0 z-30 bg-white dark:bg-zinc-900';
type NameSort = 'asc' | 'desc';

const SORT_PARAM = 'sort';
const DESC_VALUE = '-name';

/** Rendered on the server too, where there is no URL to read. */
const readNameSort = (): NameSort =>
    typeof window !== 'undefined' &&
    new URLSearchParams(window.location.search).get(SORT_PARAM) === DESC_VALUE
        ? 'desc'
        : 'asc';

const writeNameSort = (sort: NameSort): void => {
    if (typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);

    if (sort === 'desc') {
        params.set(SORT_PARAM, DESC_VALUE);
    } else {
        params.delete(SORT_PARAM);
    }

    const query = params.toString();

    // replaceState, not a visit: the rows are already here, so remembering the
    // sort must not cost a request.
    window.history.replaceState(
        window.history.state,
        '',
        `${window.location.pathname}${query ? `?${query}` : ''}`,
    );
};

const headText =
    'font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

function AccountBadge({ type }: { type: WalletType | null }) {
    if (!type) {
        return (
            <span className="font-mono text-[11px] text-gray-300 dark:text-gray-600">
                –
            </span>
        );
    }

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md px-2 py-1 font-mono text-[10px] font-medium whitespace-nowrap',
                type === 'main'
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                    : 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
            )}
        >
            {ACCOUNT_LABELS[type]}
        </span>
    );
}

/**
 * Go Tyme Balance — a day-by-day grid of wallet balances. Rows are Finance
 * accounts flagged `is_user_wallet`, so they never surface in Finance →
 * Accounts or in Finance transactions. Every cell is derived from the wallet's
 * liquidation ledger — its balance at the close of that date, starting from the
 * opening balance — so the grid is read here and written on the ledger.
 */
export default function GoTymeBalanceIndex({
    workspace,
    dates,
    rows,
    totals,
    query,
    canManage,
}: Props) {
    const [formOpen, setFormOpen] = useState(false);
    // The wallet the dialog is editing; null while it is creating one.
    const [editing, setEditing] = useState<WalletRow | null>(null);
    // The wallet awaiting delete confirmation; null keeps that dialog shut.
    const [deleting, setDeleting] = useState<WalletRow | null>(null);
    const [nameSort, setNameSort] = useState<NameSort>(readNameSort);

    // Sorted here rather than server-side: the rows are already in hand, so the
    // click costs nothing. Ties keep the server's order — Main above Backup for
    // the same holder, then id — so equal names never shuffle.
    const sortedRows = useMemo(() => {
        const direction = nameSort === 'asc' ? 1 : -1;
        const rank = (row: WalletRow) =>
            row.wallet_type === 'main'
                ? 0
                : row.wallet_type === 'backup'
                  ? 1
                  : 2;

        return [...rows].sort(
            (a, b) =>
                a.name.localeCompare(b.name) * direction ||
                rank(a) - rank(b) ||
                a.id - b.id,
        );
    }, [rows, nameSort]);

    const baseUrl = `/workspaces/${workspace.slug}/sales-marketing/go-tyme-balance`;

    // Every day up to today is typeable, blank ones included — typing into a
    // blank day is how a balance from before the wallet was created gets
    // recorded. Days after today have no ledger to correct.
    const today = moment().format('YYYY-MM-DD');

    const saveBalance = (
        accountId: number,
        date: string,
        balance: number | null,
    ) => {
        router.put(
            `${baseUrl}/${accountId}/balance`,
            { date, balance },
            {
                preserveScroll: true,
                preserveState: true,
                // The correction re-flows every day after it, and shifts the
                // column totals with them, so pull both back.
                only: ['rows', 'totals'],
            },
        );
    };

    const openForm = (wallet: WalletRow | null) => {
        setEditing(wallet);
        setFormOpen(true);
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Go Tyme Balance`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Go Tyme Balance"
                    description="Each wallet's balance at the close of every day, from its liquidation ledger. Click a cell to correct a day — the difference is posted as a ledger adjustment."
                >
                    <DatePicker
                        id={`go-tyme-range-${query.start}-${query.end}`}
                        key={`${query.start}-${query.end}`}
                        mode="range"
                        placeholder="Pick a date range"
                        defaultDate={
                            [query.start, query.end] as never as DateOption
                        }
                        onChange={(picked) => {
                            if (picked.length === 2) {
                                router.get(
                                    baseUrl,
                                    {
                                        start: moment(picked[0]).format(
                                            'YYYY-MM-DD',
                                        ),
                                        end: moment(picked[1]).format(
                                            'YYYY-MM-DD',
                                        ),
                                        // Ignored server-side; kept so the
                                        // visit does not drop the sort.
                                        ...(nameSort === 'desc'
                                            ? { [SORT_PARAM]: DESC_VALUE }
                                            : {}),
                                    },
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                    },
                                );
                            }
                        }}
                    />
                    {canManage && (
                        <button
                            onClick={() => openForm(null)}
                            className="flex h-8 items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            Create Account
                        </button>
                    )}
                </PageHeader>

                {rows.length === 0 ? (
                    <div className="rounded-[14px] border border-black/6 bg-white py-16 text-center text-[13px] text-gray-400 dark:border-white/6 dark:bg-zinc-900">
                        No accounts yet.
                        {canManage && ' Create one to start tracking balances.'}
                    </div>
                ) : (
                    <div className="grid-scrollbar max-h-[calc(100vh-15rem)] overflow-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        {/* Fixed widths so a cell switching into edit mode
                            cannot reflow the column. */}
                        <table className="w-full table-fixed border-collapse text-[12px]">
                            <colgroup>
                                <col className="w-[13rem]" />
                                <col className="w-[9rem]" />
                                {dates.map((d) => (
                                    <col key={d.date} className="w-[8.5rem]" />
                                ))}
                            </colgroup>
                            <thead>
                                <tr>
                                    <th
                                        className={cn(
                                            cell,
                                            stickyCorner,
                                            'left-0 text-left',
                                        )}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const next =
                                                    nameSort === 'asc'
                                                        ? 'desc'
                                                        : 'asc';

                                                setNameSort(next);
                                                writeNameSort(next);
                                            }}
                                            aria-label={`Sort by name, currently ${nameSort}ending`}
                                            className={cn(
                                                'flex w-full cursor-pointer items-center justify-between gap-1',
                                                headText,
                                            )}
                                        >
                                            Intern — Bank
                                            <span className="flex flex-col">
                                                <TriangleUpIcon
                                                    className={cn(
                                                        '-mb-1',
                                                        nameSort === 'asc'
                                                            ? 'text-brand-500'
                                                            : 'text-gray-300 dark:text-gray-600',
                                                    )}
                                                />
                                                <TriangleDownIcon
                                                    className={cn(
                                                        '-mt-1',
                                                        nameSort === 'desc'
                                                            ? 'text-brand-500'
                                                            : 'text-gray-300 dark:text-gray-600',
                                                    )}
                                                />
                                            </span>
                                        </button>
                                    </th>
                                    <th
                                        className={cn(
                                            cell,
                                            stickyCorner,
                                            'left-[13rem] border-r border-black/8 text-left dark:border-white/8',
                                            headText,
                                        )}
                                    >
                                        Account
                                    </th>
                                    {dates.map((d) => (
                                        <th
                                            key={d.date}
                                            className={cn(
                                                cell,
                                                stickyTop,
                                                'text-right whitespace-nowrap',
                                                d.is_today
                                                    ? 'font-mono text-[10px] font-semibold tracking-wider text-gray-700 uppercase dark:text-gray-200'
                                                    : headText,
                                            )}
                                        >
                                            {d.is_today
                                                ? `Today · ${d.label}`
                                                : d.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody>
                                {sortedRows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="group transition-colors hover:bg-stone-50/70 dark:hover:bg-zinc-800/40"
                                    >
                                        <td
                                            className={cn(
                                                cell,
                                                stickyLeft,
                                                'left-0 max-w-[13rem]',
                                            )}
                                        >
                                            <Link
                                                href={`${baseUrl}/${row.id}`}
                                                className={cn(
                                                    'block truncate text-[13px] transition-colors hover:text-emerald-600 hover:underline dark:hover:text-emerald-400',
                                                    // A closed wallet reads
                                                    // as dormant, so its
                                                    // name steps back.
                                                    row.is_active
                                                        ? 'text-gray-800 dark:text-gray-100'
                                                        : 'text-gray-400 dark:text-gray-500',
                                                )}
                                                title={row.name}
                                            >
                                                {row.name}
                                            </Link>
                                        </td>
                                        <td
                                            className={cn(
                                                cell,
                                                stickyLeft,
                                                'left-[13rem] border-r border-black/8 dark:border-white/8',
                                            )}
                                        >
                                            <div className="flex items-center justify-between gap-1">
                                                <AccountBadge
                                                    type={row.wallet_type}
                                                />
                                                {canManage && (
                                                    // Revealed on row hover
                                                    // so the grid stays a
                                                    // wall of figures at
                                                    // rest; focus-within
                                                    // keeps them reachable
                                                    // by keyboard.
                                                    <div className="flex shrink-0 gap-0.5 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                openForm(row)
                                                            }
                                                            title={`Edit ${row.name}`}
                                                            className="flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-stone-100 hover:text-gray-700 dark:hover:bg-zinc-800 dark:hover:text-gray-200"
                                                        >
                                                            <Pencil className="h-3 w-3" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setDeleting(row)
                                                            }
                                                            title={`Delete ${row.name}`}
                                                            className="flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                                        >
                                                            <Trash2 className="h-3 w-3" />
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                        {dates.map((d, i) => (
                                            <td
                                                key={d.date}
                                                className={cn(
                                                    cell,
                                                    'px-1 py-1.5',
                                                )}
                                            >
                                                <BalanceCell
                                                    value={row.days[d.date]}
                                                    previous={
                                                        i === 0
                                                            ? null
                                                            : row.days[
                                                                  dates[i - 1]
                                                                      .date
                                                              ]
                                                    }
                                                    isToday={d.is_today}
                                                    canEdit={
                                                        canManage &&
                                                        d.date <= today
                                                    }
                                                    onSave={(value) =>
                                                        saveBalance(
                                                            row.id,
                                                            d.date,
                                                            value,
                                                        )
                                                    }
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>

                            <tfoot>
                                <tr className="sticky bottom-0 z-20 border-t border-black/10 bg-white dark:border-white/10 dark:bg-zinc-900">
                                    <td
                                        className={cn(
                                            cell,
                                            stickyLeft,
                                            'left-0 z-30 text-[12px] font-semibold text-gray-700 dark:text-gray-200',
                                        )}
                                    >
                                        TOTAL
                                    </td>
                                    <td
                                        className={cn(
                                            cell,
                                            stickyLeft,
                                            'left-[13rem] z-30 border-r border-black/8 dark:border-white/8',
                                        )}
                                    />
                                    {dates.map((d) => (
                                        <td
                                            key={d.date}
                                            className={cn(
                                                cell,
                                                'px-3 text-right font-mono text-[12px] font-semibold whitespace-nowrap tabular-nums',
                                                totals[d.date] === null
                                                    ? 'text-gray-300 dark:text-gray-600'
                                                    : 'text-gray-900 dark:text-gray-100',
                                            )}
                                        >
                                            {totals[d.date] === null
                                                ? '–'
                                                : peso(
                                                      totals[d.date] as number,
                                                  )}
                                        </td>
                                    ))}
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </div>

            <WalletFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                workspaceSlug={workspace.slug}
                wallet={editing}
            />

            <DeleteWalletDialog
                workspaceSlug={workspace.slug}
                wallet={deleting}
                onClose={() => setDeleting(null)}
            />
        </AppLayout>
    );
}
