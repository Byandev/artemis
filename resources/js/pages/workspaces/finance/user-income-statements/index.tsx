import PageHeader from '@/components/common/PageHeader';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    ChevronDown,
    ChevronRight,
    HelpCircle,
} from 'lucide-react';
import { Fragment, useState } from 'react';

interface StatementContext {
    id: number;
    period_month: string; // YYYY-MM-DD
    month: string; // YYYY-MM
    label: string; // "July 2026"
}

/**
 * A saved user row. Goods bought this month are tracked apart from the cost of
 * the goods that actually shipped — in any one month the two rarely match.
 */
interface UserRow {
    user_id: number | null;
    user: string;
    delivered_orders: number;
    delivered_units: number;
    delivered_amount: number;
    ad_spent: number;
    shipped_orders: number;
    total_shipping_fee: number;
    cod_fee: number;
    cod_fee_vat: number;
    total_bought_cogs: number;
    total_bought_cogs_delivery_fee: number;
    total_delivered_cogs: number;
    gross_profit_delivered_cogs: number;
    gross_profit_delivered_cogs_advisory_share: number;
    gross_profit_delivered_cogs_after_advisory_share: number;
    gross_profit_bought_cogs: number;
    gross_profit_bought_cogs_advisory_share: number;
    gross_profit_bought_cogs_after_advisory_share: number;
}

/**
 * One line of what the Unassigned row is made of: an intern name written on
 * orders that matches no user. It can show shipping against it and nothing
 * delivered, or the other way round; a null cell is orders with no name at all.
 */
interface UnassignedRow {
    cell: string | null;
    delivered_orders: number;
    delivered_amount: number;
    shipped_orders: number;
    shipping_fee: number;
}

interface Props {
    workspace: Workspace;
    incomeStatement: StatementContext;
    users: UserRow[];
    total: UserRow;
    /** The rates these rows were struck at, as fractions. */
    rates: { cod: number; vat: number; advisory: number };
    /** The advisory share is only taken on partner workspaces. */
    gencysPartner: boolean;
    unassigned: UnassignedRow[];
}

const fmt = (v: number) =>
    Number.isFinite(Number(v))
        ? Number(v).toLocaleString('en-PH', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          })
        : '—';

const int = (v: number) =>
    Number.isFinite(Number(v)) ? Number(v).toLocaleString('en-PH') : '—';

const CARD =
    'rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900';
const BTN =
    'flex h-8 items-center gap-1.5 rounded-lg border border-black/6 bg-stone-50 px-3 font-mono text-[12px] text-gray-600 transition-all hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700';
const COL = 'px-4 py-3 text-right text-[12px] whitespace-nowrap tabular-nums';

// The frozen first column and the frozen header row. Both use a box-shadow in
// place of a border: border-collapse drops borders on a sticky cell.
//
// Stacking, highest first: the corner cell sits above the header row, which
// sits above the frozen column, which sits above the scrolling figures.
const FROZEN =
    'sticky left-0 z-10 shadow-[1px_0_0_0_rgba(0,0,0,0.06)] dark:shadow-[1px_0_0_0_rgba(255,255,255,0.06)]';
const HEAD_BG = 'bg-white dark:bg-zinc-900';
const STICKY_HEAD =
    'sticky top-0 z-20 shadow-[0_1px_0_0_rgba(0,0,0,0.06)] dark:shadow-[0_1px_0_0_rgba(255,255,255,0.06)]';
const STICKY_CORNER =
    'sticky top-0 left-0 z-30 shadow-[1px_0_0_0_rgba(0,0,0,0.06),0_1px_0_0_rgba(0,0,0,0.06)] dark:shadow-[1px_0_0_0_rgba(255,255,255,0.06),0_1px_0_0_rgba(255,255,255,0.06)]';
const HEAD =
    'px-4 py-3 text-right text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap';

/**
 * The figure columns, each with the note behind its question mark. Header, body
 * and total all render from this one list, so a column can't say one thing and
 * show another.
 */
const pct = (fraction: number) => `${Number((fraction * 100).toFixed(4))}%`;

/**
 * Which side of cost-of-goods the table is showing. They answer different
 * questions — what was sold this month, or what was purchased into stock — and
 * putting both up at once made the row hard to read, so it's one or the other.
 */
type CogsView = 'delivered' | 'bought';

const COGS_VIEWS: { value: CogsView; label: string }[] = [
    { value: 'delivered', label: 'Delivered COGS' },
    { value: 'bought', label: 'Bought COGS' },
];

const buildColumns = (
    rates: {
        cod: number;
        vat: number;
        advisory: number;
    },
    cogsView: CogsView,
    gencysPartner: boolean,
): {
    label: string;
    help: string;
    render: (r: UserRow) => string;
    emphasis?: boolean;
    /** When set, the cell is coloured by the sign of this value. */
    signed?: (r: UserRow) => number;
}[] => [
    {
        label: 'Delivered Orders',
        help: 'Parcels delivered this month credited to this user, matched by the intern name written on the order.',
        render: (r) => int(r.delivered_orders),
    },
    // Delivered Units is hidden for now, matching the product statement. It is
    // still computed and stored on `finance_income_user_statements.delivered_units`
    // — put this entry back to bring the column back:
    // { label: 'Delivered Units', help: '…', render: (r) => int(r.delivered_units) },
    {
        label: 'Delivered Amount',
        help: 'Revenue from those parcels. An order belongs to one intern, so nothing is split — the whole order’s value goes to them.',
        render: (r) => fmt(r.delivered_amount),
        emphasis: true,
    },
    {
        label: 'Shipped Orders',
        help: 'Parcels credited to this user shipped out this month, by shipped-out date and whatever became of them afterwards. A different set from Delivered Orders — the two are not expected to agree.',
        render: (r) => int(r.shipped_orders),
    },
    {
        label: 'Total Shipping Fee',
        help: 'The courier fee on those parcels. Charged when a parcel ships, so a return is paid for too — this is not limited to what was delivered.',
        render: (r) => fmt(r.total_shipping_fee),
    },
    {
        label: 'Ad Spent',
        help: 'Ad Spent transactions charged to this user this month, from the charge-to shares. Only charged shares count — spend nobody was charged for is left out rather than spread around on a guess.',
        render: (r) => fmt(r.ad_spent),
    },
    {
        label: 'COD Fee',
        help: `The courier's fee for collecting on delivery — ${pct(rates.cod)} of Delivered Amount, at the rate saved on this statement.`,
        render: (r) => fmt(r.cod_fee),
    },
    {
        label: 'COD Fee VAT',
        help: `VAT on the COD fee — ${pct(rates.vat)} of the fee itself, not of the delivered amount.`,
        render: (r) => fmt(r.cod_fee_vat),
    },
    ...(cogsView === 'bought'
        ? [
              {
                  label: 'Bought COGS',
                  help: 'Cost of Goods purchases charged to this user this month — stock bought, which is not the same as stock sold.',
                  render: (r: UserRow) => fmt(r.total_bought_cogs),
                  emphasis: true,
              },
              {
                  label: 'Bought COGS Delivery Fee',
                  help: 'Freight paid on those purchases, from “Delivery of COG” transactions charged to this user.',
                  render: (r: UserRow) => fmt(r.total_bought_cogs_delivery_fee),
              },
              {
                  label: 'Gross Profit',
                  help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less what was bought into stock this month and the freight on it. What the month cost in cash, not the margin on what sold.',
                  render: (r: UserRow) => fmt(r.gross_profit_bought_cogs),
                  emphasis: true,
                  signed: (r: UserRow) => r.gross_profit_bought_cogs,
              },
              ...(gencysPartner
                  ? [
                        {
                            label: 'Advisory Share',
                            help: `A share of gross profit taken by the partner — ${pct(rates.advisory)} of a positive Gross Profit, at the rate saved on this statement. A loss owes nothing.`,
                            render: (r: UserRow) =>
                                fmt(r.gross_profit_bought_cogs_advisory_share),
                        },
                        {
                            label: 'Gross Profit after Advisory',
                            help: 'Gross Profit less the advisory share.',
                            render: (r: UserRow) =>
                                fmt(
                                    r.gross_profit_bought_cogs_after_advisory_share,
                                ),
                            emphasis: true,
                            signed: (r: UserRow) =>
                                r.gross_profit_bought_cogs_after_advisory_share,
                        },
                    ]
                  : []),
          ]
        : [
              {
                  label: 'Delivered COGS',
                  help: 'Cost of the goods on this user’s delivered orders, taken from the orders’ own cost figures.',
                  render: (r: UserRow) => fmt(r.total_delivered_cogs),
                  emphasis: true,
              },
              {
                  label: 'Gross Profit',
                  help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less the cost of the goods that actually shipped. The margin on what was sold this month.',
                  render: (r: UserRow) => fmt(r.gross_profit_delivered_cogs),
                  emphasis: true,
                  signed: (r: UserRow) => r.gross_profit_delivered_cogs,
              },
              ...(gencysPartner
                  ? [
                        {
                            label: 'Advisory Share',
                            help: `A share of gross profit taken by the partner — ${pct(rates.advisory)} of a positive Gross Profit, at the rate saved on this statement. A loss owes nothing.`,
                            render: (r: UserRow) =>
                                fmt(
                                    r.gross_profit_delivered_cogs_advisory_share,
                                ),
                        },
                        {
                            label: 'Gross Profit after Advisory',
                            help: 'Gross Profit less the advisory share.',
                            render: (r: UserRow) =>
                                fmt(
                                    r.gross_profit_delivered_cogs_after_advisory_share,
                                ),
                            emphasis: true,
                            signed: (r: UserRow) =>
                                r.gross_profit_delivered_cogs_after_advisory_share,
                        },
                    ]
                  : []),
          ]),
];

export default function UserIncomeStatements({
    workspace,
    incomeStatement,
    users,
    total,
    rates,
    gencysPartner,
    unassigned: unassignedRows,
}: Props) {
    const finance = `/workspaces/${workspace.slug}/finance`;
    const [cogsView, setCogsView] = useState<CogsView>('delivered');
    const [unassignedOpen, setUnassignedOpen] = useState(false);
    const unlinkedCells = unassignedRows.filter((u) => u.cell !== null).length;
    const COLUMNS = buildColumns(rates, cogsView, gencysPartner);
    const named = users.filter((u) => u.user_id !== null);

    // The cost of what shipped against what was bought — the gap is inventory
    // moving in or out of stock, not profit.
    const boughtAll =
        total.total_bought_cogs + total.total_bought_cogs_delivery_fee;

    return (
        <AppLayout>
            <Head
                title={`${workspace.name} - User Statement ${incomeStatement.label}`}
            />
            <div className="w-full p-4 font-mono md:p-6">
                <PageHeader
                    title="User Statement"
                    description={`${incomeStatement.label} · every intern, for the month`}
                >
                    <Link
                        href={`${finance}/income-statements/${incomeStatement.id}`}
                        className={BTN}
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back to statement
                    </Link>
                </PageHeader>

                {/* Intern names with no user behind them — the orders carrying
                    them can't be credited, so they land in Unassigned. */}
                {unlinkedCells > 0 && (
                    <div className="mb-6 rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 dark:border-amber-800/60 dark:bg-amber-950/40">
                        <div className="flex items-center gap-2 text-[12px] text-amber-800 dark:text-amber-200">
                            <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                            {int(unlinkedCells)} intern name
                            {unlinkedCells === 1 ? '' : 's'} on this
                            month&rsquo;s orders match no user.{' '}
                            <Link
                                href={`/workspaces/${workspace.slug}/gencys/interns`}
                                className="underline underline-offset-2"
                            >
                                Link them
                            </Link>{' '}
                            to clear the Unassigned row.
                        </div>
                    </div>
                )}

                <div className={`${CARD} overflow-hidden`}>
                    <div className="flex items-center justify-between border-b border-black/6 px-5 py-4 dark:border-white/6">
                        <div>
                            <div className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                                Per-user figures
                            </div>
                            <div className="mt-0.5 text-[11px] text-gray-400">
                                {incomeStatement.label} · {int(named.length)}{' '}
                                {named.length === 1 ? 'user' : 'users'}
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <div
                                role="group"
                                aria-label="Cost of goods view"
                                className="flex items-center gap-0.5 rounded-lg border border-black/6 bg-stone-50 p-0.5 dark:border-white/6 dark:bg-zinc-800"
                            >
                                {COGS_VIEWS.map((v) => {
                                    const active = cogsView === v.value;
                                    return (
                                        <button
                                            key={v.value}
                                            type="button"
                                            aria-pressed={active}
                                            onClick={() => setCogsView(v.value)}
                                            className={`rounded-md px-2.5 py-1 text-[11px] transition-colors ${
                                                active
                                                    ? 'bg-white text-gray-800 shadow-sm dark:bg-zinc-900 dark:text-gray-100'
                                                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                                            }`}
                                        >
                                            {v.label}
                                        </button>
                                    );
                                })}
                            </div>
                            <span className="rounded-full border border-black/6 px-2.5 py-0.5 text-[10px] tracking-wider text-gray-400 uppercase dark:border-white/6">
                                Saved
                            </span>
                        </div>
                    </div>

                    <div className="max-h-[70vh] overflow-auto">
                        <table className="w-full">
                            <thead>
                                <tr>
                                    <th
                                        className={`${STICKY_CORNER} ${HEAD_BG} px-5 py-3 text-left text-[10px] font-semibold tracking-wider text-gray-400 uppercase`}
                                    >
                                        User
                                    </th>
                                    {COLUMNS.map((c, i) => (
                                        <th
                                            key={c.label}
                                            className={`${HEAD} ${STICKY_HEAD} ${HEAD_BG} ${i === COLUMNS.length - 1 ? 'pr-5' : ''}`}
                                        >
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center gap-1 transition-colors hover:text-gray-600 focus-visible:text-gray-600 focus-visible:outline-none dark:hover:text-gray-300 dark:focus-visible:text-gray-300"
                                                    >
                                                        {c.label}
                                                        <HelpCircle className="h-3 w-3 shrink-0 opacity-60" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent className="max-w-xs font-mono text-[11px] leading-relaxed normal-case">
                                                    {c.help}
                                                </TooltipContent>
                                            </Tooltip>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-black/5 dark:divide-white/5">
                                {users.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-5 py-12 text-center text-[12px] text-gray-400"
                                        >
                                            Nothing delivered in{' '}
                                            {incomeStatement.label}.
                                        </td>
                                    </tr>
                                )}

                                {users.map((r) => {
                                    const unassigned = r.user_id === null;
                                    const tone = unassigned
                                        ? 'text-amber-700 dark:text-amber-400'
                                        : 'text-gray-800 dark:text-gray-100';
                                    return (
                                        <Fragment
                                            key={r.user_id ?? 'unassigned'}
                                        >
                                            <tr
                                                className={
                                                    unassigned
                                                        ? 'group bg-amber-50/70 dark:bg-amber-500/10'
                                                        : 'group transition-colors hover:bg-stone-50 dark:hover:bg-zinc-800/40'
                                                }
                                            >
                                                <td
                                                    className={`${FROZEN} py-3 pr-4 pl-5 ${
                                                        unassigned
                                                            ? 'bg-amber-50 dark:bg-amber-950'
                                                            : 'bg-white group-hover:bg-stone-50 dark:bg-zinc-900 dark:group-hover:bg-zinc-800'
                                                    }`}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        {unassigned && (
                                                            <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-amber-500" />
                                                        )}
                                                        <span className="min-w-0">
                                                            <span
                                                                className={`block truncate text-[13px] font-medium ${tone}`}
                                                            >
                                                                {r.user}
                                                            </span>
                                                            {unassigned &&
                                                                (unassignedRows.length >
                                                                0 ? (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            setUnassignedOpen(
                                                                                (
                                                                                    o,
                                                                                ) =>
                                                                                    !o,
                                                                            )
                                                                        }
                                                                        aria-expanded={
                                                                            unassignedOpen
                                                                        }
                                                                        className="mt-0.5 flex items-center gap-1 text-[10px] text-amber-700 underline-offset-2 hover:underline dark:text-amber-400"
                                                                    >
                                                                        {unassignedOpen ? (
                                                                            <ChevronDown className="h-3 w-3" />
                                                                        ) : (
                                                                            <ChevronRight className="h-3 w-3" />
                                                                        )}
                                                                        {unassignedOpen
                                                                            ? 'hide'
                                                                            : 'show'}{' '}
                                                                        {int(
                                                                            unassignedRows.length,
                                                                        )}{' '}
                                                                        unlinked{' '}
                                                                        {unassignedRows.length ===
                                                                        1
                                                                            ? 'name'
                                                                            : 'names'}
                                                                    </button>
                                                                ) : (
                                                                    <span className="text-[10px] text-gray-400">
                                                                        items
                                                                        that
                                                                        resolve
                                                                        to no
                                                                        nobody
                                                                    </span>
                                                                ))}
                                                        </span>
                                                    </div>
                                                </td>
                                                {COLUMNS.map((c, i) => (
                                                    <td
                                                        key={c.label}
                                                        className={`${COL} ${
                                                            i ===
                                                            COLUMNS.length - 1
                                                                ? 'pr-5'
                                                                : ''
                                                        }${
                                                            c.signed
                                                                ? `font-semibold ${
                                                                      c.signed(
                                                                          r,
                                                                      ) < 0
                                                                          ? 'text-rose-600 dark:text-rose-400'
                                                                          : 'text-emerald-600 dark:text-emerald-400'
                                                                  }`
                                                                : c.emphasis
                                                                  ? `font-medium ${tone}`
                                                                  : 'text-gray-500 dark:text-gray-400'
                                                        }`}
                                                    >
                                                        {c.render(r)}
                                                    </td>
                                                ))}
                                            </tr>

                                            {unassigned && unassignedOpen && (
                                                <tr className="bg-amber-50/40 dark:bg-amber-500/5">
                                                    <td
                                                        colSpan={
                                                            COLUMNS.length + 1
                                                        }
                                                        className="p-0"
                                                    >
                                                        {/* Pinned left so it stays
                                                        readable however far the
                                                        figures are scrolled. */}
                                                        <div className="sticky left-0 w-max min-w-full px-5 py-4">
                                                            <div className="mb-2 text-[10px] font-semibold tracking-wider text-amber-700 uppercase dark:text-amber-400">
                                                                What&rsquo;s in
                                                                Unassigned
                                                            </div>
                                                            <table className="text-[11px]">
                                                                <thead>
                                                                    <tr className="text-gray-400">
                                                                        <th className="pr-6 pb-1 text-left font-medium">
                                                                            Intern
                                                                            name
                                                                            on
                                                                            the
                                                                            order
                                                                        </th>
                                                                        <th className="px-3 pb-1 text-right font-medium">
                                                                            Delivered
                                                                        </th>
                                                                        <th className="px-3 pb-1 text-right font-medium">
                                                                            Amount
                                                                        </th>
                                                                        <th className="px-3 pb-1 text-right font-medium">
                                                                            Shipped
                                                                        </th>
                                                                        <th className="pb-1 pl-3 text-right font-medium">
                                                                            Shipping
                                                                            Fee
                                                                        </th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    {unassignedRows.map(
                                                                        (u) => (
                                                                            <tr
                                                                                key={
                                                                                    u.cell ??
                                                                                    'blank'
                                                                                }
                                                                            >
                                                                                <td className="py-1 pr-6">
                                                                                    {u.cell ? (
                                                                                        <span className="text-gray-800 dark:text-gray-100">
                                                                                            {
                                                                                                u.cell
                                                                                            }
                                                                                        </span>
                                                                                    ) : (
                                                                                        <span className="text-gray-500 italic dark:text-gray-400">
                                                                                            orders
                                                                                            with
                                                                                            no
                                                                                            items
                                                                                            recorded
                                                                                        </span>
                                                                                    )}
                                                                                </td>
                                                                                <td className="px-3 py-1 text-right tabular-nums">
                                                                                    {int(
                                                                                        u.delivered_orders,
                                                                                    )}
                                                                                </td>
                                                                                <td className="px-3 py-1 text-right tabular-nums">
                                                                                    {fmt(
                                                                                        u.delivered_amount,
                                                                                    )}
                                                                                </td>
                                                                                <td className="px-3 py-1 text-right tabular-nums">
                                                                                    {int(
                                                                                        u.shipped_orders,
                                                                                    )}
                                                                                </td>
                                                                                <td className="py-1 pl-3 text-right tabular-nums">
                                                                                    {fmt(
                                                                                        u.shipping_fee,
                                                                                    )}
                                                                                </td>
                                                                            </tr>
                                                                        ),
                                                                    )}
                                                                </tbody>
                                                            </table>
                                                            <div className="mt-2.5 text-[10px] text-gray-400">
                                                                Link an intern
                                                                on the{' '}
                                                                <Link
                                                                    href={`/workspaces/${workspace.slug}/gencys/interns`}
                                                                    className="underline underline-offset-2"
                                                                >
                                                                    interns
                                                                </Link>{' '}
                                                                page and its
                                                                figures move
                                                                onto that user
                                                                at the next
                                                                regenerate.
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                            <tfoot>
                                <tr className="border-t border-black/6 bg-stone-100 dark:border-white/6 dark:bg-zinc-800/60">
                                    <td
                                        className={`${FROZEN} bg-stone-100 py-3.5 pr-4 pl-5 text-[12px] font-semibold tracking-wide text-gray-700 uppercase dark:bg-zinc-800 dark:text-gray-200`}
                                    >
                                        Total
                                    </td>
                                    {COLUMNS.map((c, i) => (
                                        <td
                                            key={c.label}
                                            className={`${COL} ${
                                                i === COLUMNS.length - 1
                                                    ? 'pr-5'
                                                    : ''
                                            }font-semibold ${
                                                c.signed
                                                    ? c.signed(total) < 0
                                                        ? 'text-rose-600 dark:text-rose-400'
                                                        : 'text-emerald-600 dark:text-emerald-400'
                                                    : c.emphasis
                                                      ? 'text-gray-800 dark:text-gray-100'
                                                      : 'text-gray-600 dark:text-gray-300'
                                            }`}
                                        >
                                            {c.render(total)}
                                        </td>
                                    ))}
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <p className="mt-3 text-[11px] text-gray-400">
                    Every figure is the user&rsquo;s own, matched through the
                    intern name written on the order &mdash; hover a column
                    heading for what it counts. Bought {fmt(boughtAll)} against{' '}
                    {fmt(total.total_delivered_cogs)} delivered this month; the
                    gap is stock moving in or out of the warehouse, not profit.
                    The Total excludes the Unassigned row.
                </p>
            </div>
        </AppLayout>
    );
}
