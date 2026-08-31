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
import { Fragment, useMemo, useState } from 'react';

interface StatementContext {
    id: number;
    period_month: string; // YYYY-MM-DD
    month: string; // YYYY-MM
    label: string; // "July 2026"
}

/**
 * One seller's figures on one product. The order-carried columns are that
 * pair's own; the bought-goods and ad columns are the product's total, cut by
 * the seller's share of its delivered orders.
 */
interface Row {
    user_id: number | null;
    user: string;
    product_id: number | null;
    product: string;
    delivered_orders: number;
    delivered_units: number;
    delivered_amount: number;
    shipped_orders: number;
    total_shipping_fee: number;
    ad_spent: number;
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

interface Props {
    workspace: Workspace;
    incomeStatement: StatementContext;
    /** Already ordered: biggest product first, its sellers together. */
    rows: Row[];
    total: Row;
    /** The rates these rows were struck at, as fractions. */
    rates: { cod: number; vat: number; advisory: number };
    /** The advisory share is only taken on partner workspaces. */
    gencysPartner: boolean;
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

/** One product with the sellers who ran it, and its own totals across them. */
interface Group {
    product_id: number | null;
    product: string;
    rows: Row[];
    subtotal: Row;
}

const SUM_KEYS = [
    'delivered_orders',
    'delivered_units',
    'delivered_amount',
    'shipped_orders',
    'total_shipping_fee',
    'ad_spent',
    'cod_fee',
    'cod_fee_vat',
    'total_bought_cogs',
    'total_bought_cogs_delivery_fee',
    'total_delivered_cogs',
    'gross_profit_delivered_cogs',
    'gross_profit_delivered_cogs_advisory_share',
    'gross_profit_delivered_cogs_after_advisory_share',
    'gross_profit_bought_cogs',
    'gross_profit_bought_cogs_advisory_share',
    'gross_profit_bought_cogs_after_advisory_share',
] as const;

/**
 * Fold the flat rows into one group per product, keeping the order the server
 * sent (biggest product first, its sellers together). The subtotal a group
 * shows is the same figure the product statement carries for that product —
 * the seller rows beneath it are how it was divided.
 */
function groupByProduct(rows: Row[]): Group[] {
    const groups: Group[] = [];

    for (const row of rows) {
        let group = groups[groups.length - 1];

        if (!group || group.product_id !== row.product_id) {
            group = {
                product_id: row.product_id,
                product: row.product,
                rows: [],
                subtotal: {
                    ...row,
                    user_id: null,
                    user: row.product,
                    ...Object.fromEntries(SUM_KEYS.map((k) => [k, 0])),
                } as Row,
            };
            groups.push(group);
        }

        group.rows.push(row);
        for (const key of SUM_KEYS) {
            group.subtotal[key] = Number(
                (group.subtotal[key] + row[key]).toFixed(2),
            );
        }
    }

    return groups;
}

const buildColumns = (
    rates: { cod: number; vat: number; advisory: number },
    cogsView: CogsView,
    gencysPartner: boolean,
): {
    label: string;
    help: string;
    render: (r: Row) => string;
    emphasis?: boolean;
    /** When set, the cell is coloured by the sign of this value. */
    signed?: (r: Row) => number;
}[] => [
    {
        label: 'Delivered Orders',
        help: 'Parcels of this product delivered this month that are credited to this seller. Also the weight the product’s bought-goods and ad costs are shared out by.',
        render: (r) => int(r.delivered_orders),
    },
    {
        label: 'Delivered Amount',
        help: 'Revenue from those parcels. A parcel carrying several products has its value divided between them by item quantity, so nothing is counted twice.',
        render: (r) => fmt(r.delivered_amount),
        emphasis: true,
    },
    {
        label: 'Shipped Orders',
        help: 'Parcels of this product shipped out by this seller this month, by shipped-out date and whatever became of them afterwards. A different set from Delivered Orders — the two are not expected to agree.',
        render: (r) => int(r.shipped_orders),
    },
    {
        label: 'Total Shipping Fee',
        help: 'The courier fee on those parcels. Charged when a parcel ships, so a return is paid for too — this is not limited to what was delivered.',
        render: (r) => fmt(r.total_shipping_fee),
    },
    {
        label: 'Ad Spent',
        help: 'The product’s ad spend for the month, shared between its sellers by their share of its delivered orders. Only spend tagged to the product counts — untagged spend is left out rather than spread across products on a guess.',
        render: (r) => fmt(r.ad_spent),
    },
    {
        label: 'COD Fee',
        help: `The courier's fee for collecting on delivery — ${pct(rates.cod)} of this row's Delivered Amount, at the rate saved on this statement.`,
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
                  help: 'The product’s goods purchases for the month, shared between its sellers by their share of its delivered orders. Stock bought, which is not the same as stock sold.',
                  render: (r: Row) => fmt(r.total_bought_cogs),
                  emphasis: true,
              },
              {
                  label: 'Bought COGS Delivery Fee',
                  help: 'Freight paid on those purchases, shared out the same way.',
                  render: (r: Row) => fmt(r.total_bought_cogs_delivery_fee),
              },
              {
                  label: 'Gross Profit',
                  help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less this seller’s share of what was bought into stock this month and the freight on it.',
                  render: (r: Row) => fmt(r.gross_profit_bought_cogs),
                  emphasis: true,
                  signed: (r: Row) => r.gross_profit_bought_cogs,
              },
              ...(gencysPartner
                  ? [
                        {
                            label: 'Advisory Share',
                            help: `A share of gross profit taken by the partner — ${pct(rates.advisory)} of a positive Gross Profit, at the rate saved on this statement. A loss owes nothing.`,
                            render: (r: Row) =>
                                fmt(r.gross_profit_bought_cogs_advisory_share),
                        },
                        {
                            label: 'Gross Profit after Advisory',
                            help: 'Gross Profit less the advisory share.',
                            render: (r: Row) =>
                                fmt(
                                    r.gross_profit_bought_cogs_after_advisory_share,
                                ),
                            emphasis: true,
                            signed: (r: Row) =>
                                r.gross_profit_bought_cogs_after_advisory_share,
                        },
                    ]
                  : []),
          ]
        : [
              {
                  label: 'Delivered COGS',
                  help: 'Cost of the goods that actually shipped, taken from the orders’ own cost figures. Carried on the orders themselves, so it is this seller’s own — not a share of the product’s.',
                  render: (r: Row) => fmt(r.total_delivered_cogs),
                  emphasis: true,
              },
              {
                  label: 'Gross Profit',
                  help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less the cost of the goods that actually shipped. The margin this seller made on this product.',
                  render: (r: Row) => fmt(r.gross_profit_delivered_cogs),
                  emphasis: true,
                  signed: (r: Row) => r.gross_profit_delivered_cogs,
              },
              ...(gencysPartner
                  ? [
                        {
                            label: 'Advisory Share',
                            help: `A share of gross profit taken by the partner — ${pct(rates.advisory)} of a positive Gross Profit, at the rate saved on this statement. A loss owes nothing.`,
                            render: (r: Row) =>
                                fmt(
                                    r.gross_profit_delivered_cogs_advisory_share,
                                ),
                        },
                        {
                            label: 'Gross Profit after Advisory',
                            help: 'Gross Profit less the advisory share.',
                            render: (r: Row) =>
                                fmt(
                                    r.gross_profit_delivered_cogs_after_advisory_share,
                                ),
                            emphasis: true,
                            signed: (r: Row) =>
                                r.gross_profit_delivered_cogs_after_advisory_share,
                        },
                    ]
                  : []),
          ]),
];

export default function UserProductIncomeStatements({
    workspace,
    incomeStatement,
    rows,
    total,
    rates,
    gencysPartner,
}: Props) {
    const finance = `/workspaces/${workspace.slug}/finance`;
    const [cogsView, setCogsView] = useState<CogsView>('delivered');
    const [collapsed, setCollapsed] = useState<Set<string>>(new Set());

    const COLUMNS = buildColumns(rates, cogsView, gencysPartner);
    const groups = useMemo(() => groupByProduct(rows), [rows]);

    const namedGroups = groups.filter((g) => g.product_id !== null);
    // The point of the page: products more than one person is running.
    const shared = namedGroups.filter(
        (g) => g.rows.filter((r) => r.user_id !== null).length > 1,
    ).length;

    const toggle = (key: string) =>
        setCollapsed((current) => {
            const next = new Set(current);

            if (!next.delete(key)) next.add(key);

            return next;
        });

    return (
        <AppLayout>
            <Head
                title={`${workspace.name} - Seller × Product Statement ${incomeStatement.label}`}
            />
            <div className="w-full p-4 font-mono md:p-6">
                <PageHeader
                    title="Seller × Product Statement"
                    description={`${incomeStatement.label} · every product, split between the people running it`}
                >
                    <div className="flex items-center gap-2">
                        <Link
                            href={`${finance}/income-statements/${incomeStatement.id}/products`}
                            className={BTN}
                        >
                            Per-product
                        </Link>
                        <Link
                            href={`${finance}/income-statements/${incomeStatement.id}`}
                            className={BTN}
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            Back to statement
                        </Link>
                    </div>
                </PageHeader>

                <div className={`${CARD} overflow-hidden`}>
                    <div className="flex items-center justify-between border-b border-black/6 px-5 py-4 dark:border-white/6">
                        <div>
                            <div className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                                Per-seller figures, by product
                            </div>
                            <div className="mt-0.5 text-[11px] text-gray-400">
                                {incomeStatement.label} ·{' '}
                                {int(namedGroups.length)}{' '}
                                {namedGroups.length === 1
                                    ? 'product'
                                    : 'products'}
                                {shared > 0 &&
                                    ` · ${int(shared)} run by more than one person`}
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
                                        Product / Seller
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
                                {groups.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={COLUMNS.length + 1}
                                            className="px-5 py-12 text-center text-[12px] text-gray-400"
                                        >
                                            Nothing delivered in{' '}
                                            {incomeStatement.label}.
                                        </td>
                                    </tr>
                                )}

                                {groups.map((g) => {
                                    const key = String(g.product_id ?? 'none');
                                    const open = !collapsed.has(key);
                                    const unresolved = g.product_id === null;

                                    return (
                                        <Fragment key={key}>
                                            {/* The product's own line — the
                                                figure the product statement
                                                carries. The sellers under it
                                                are how it was divided. */}
                                            <tr
                                                className={
                                                    unresolved
                                                        ? 'group bg-amber-50/70 dark:bg-amber-500/10'
                                                        : 'group bg-stone-50/80 dark:bg-zinc-800/40'
                                                }
                                            >
                                                <td
                                                    className={`${FROZEN} py-2.5 pr-4 pl-5 ${
                                                        unresolved
                                                            ? 'bg-amber-50 dark:bg-amber-950'
                                                            : 'bg-stone-50 dark:bg-zinc-800'
                                                    }`}
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            toggle(key)
                                                        }
                                                        aria-expanded={open}
                                                        className="flex items-center gap-1.5 text-left"
                                                    >
                                                        {open ? (
                                                            <ChevronDown className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                                        ) : (
                                                            <ChevronRight className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                                        )}
                                                        {unresolved && (
                                                            <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-amber-500" />
                                                        )}
                                                        <span className="min-w-0">
                                                            <span
                                                                className={`block truncate text-[13px] font-semibold ${
                                                                    unresolved
                                                                        ? 'text-amber-700 dark:text-amber-400'
                                                                        : 'text-gray-800 dark:text-gray-100'
                                                                }`}
                                                            >
                                                                {g.product}
                                                            </span>
                                                            <span className="block text-[10px] text-gray-400">
                                                                {int(
                                                                    g.rows
                                                                        .length,
                                                                )}{' '}
                                                                {g.rows
                                                                    .length ===
                                                                1
                                                                    ? 'seller'
                                                                    : 'sellers'}
                                                            </span>
                                                        </span>
                                                    </button>
                                                </td>
                                                {COLUMNS.map((c, i) => (
                                                    <td
                                                        key={c.label}
                                                        className={`${COL} ${
                                                            i ===
                                                            COLUMNS.length - 1
                                                                ? 'pr-5'
                                                                : ''
                                                        } font-semibold ${
                                                            c.signed
                                                                ? c.signed(
                                                                      g.subtotal,
                                                                  ) < 0
                                                                    ? 'text-rose-600 dark:text-rose-400'
                                                                    : 'text-emerald-600 dark:text-emerald-400'
                                                                : 'text-gray-700 dark:text-gray-200'
                                                        }`}
                                                    >
                                                        {c.render(g.subtotal)}
                                                    </td>
                                                ))}
                                            </tr>

                                            {open &&
                                                g.rows.map((r) => {
                                                    const unassigned =
                                                        r.user_id === null;
                                                    const tone = unassigned
                                                        ? 'text-amber-700 dark:text-amber-400'
                                                        : 'text-gray-800 dark:text-gray-100';
                                                    const shareOfProduct =
                                                        g.subtotal
                                                            .delivered_orders >
                                                        0
                                                            ? (r.delivered_orders /
                                                                  g.subtotal
                                                                      .delivered_orders) *
                                                              100
                                                            : null;

                                                    return (
                                                        <tr
                                                            key={`${key}-${r.user_id ?? 'none'}`}
                                                            className="group transition-colors hover:bg-stone-50 dark:hover:bg-zinc-800/40"
                                                        >
                                                            <td
                                                                className={`${FROZEN} bg-white py-3 pr-4 pl-5 group-hover:bg-stone-50 dark:bg-zinc-900 dark:group-hover:bg-zinc-800`}
                                                            >
                                                                <div className="flex items-center gap-2 pl-5">
                                                                    {unassigned && (
                                                                        <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-amber-500" />
                                                                    )}
                                                                    <span className="min-w-0">
                                                                        <span
                                                                            className={`block truncate text-[13px] ${tone}`}
                                                                        >
                                                                            {
                                                                                r.user
                                                                            }
                                                                        </span>
                                                                        <span className="block text-[10px] text-gray-400">
                                                                            {unassigned
                                                                                ? 'credited to nobody'
                                                                                : shareOfProduct !==
                                                                                    null
                                                                                  ? `${shareOfProduct.toFixed(1)}% of this product’s delivered orders`
                                                                                  : 'no delivered orders'}
                                                                        </span>
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            {COLUMNS.map(
                                                                (c, i) => (
                                                                    <td
                                                                        key={
                                                                            c.label
                                                                        }
                                                                        className={`${COL} ${
                                                                            i ===
                                                                            COLUMNS.length -
                                                                                1
                                                                                ? 'pr-5'
                                                                                : ''
                                                                        }${
                                                                            c.signed
                                                                                ? `font-semibold ${
                                                                                      c.signed(
                                                                                          r,
                                                                                      ) <
                                                                                      0
                                                                                          ? 'text-rose-600 dark:text-rose-400'
                                                                                          : 'text-emerald-600 dark:text-emerald-400'
                                                                                  }`
                                                                                : c.emphasis
                                                                                  ? `font-medium ${tone}`
                                                                                  : 'text-gray-500 dark:text-gray-400'
                                                                        }`}
                                                                    >
                                                                        {c.render(
                                                                            r,
                                                                        )}
                                                                    </td>
                                                                ),
                                                            )}
                                                        </tr>
                                                    );
                                                })}
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
                    Each product&rsquo;s line is the figure the{' '}
                    <Link
                        href={`${finance}/income-statements/${incomeStatement.id}/products`}
                        className="underline underline-offset-2"
                    >
                        per-product statement
                    </Link>{' '}
                    carries; the sellers beneath it are how it divides.
                    Delivered orders, revenue, shipping and delivered COGS are
                    each seller&rsquo;s own, straight off their orders. Bought
                    COGS, its freight and ad spend belong to the product as a
                    whole, so each seller takes the share matching their share
                    of its delivered orders. The Total excludes rows credited to
                    nobody or to no product.
                </p>
            </div>
        </AppLayout>
    );
}
