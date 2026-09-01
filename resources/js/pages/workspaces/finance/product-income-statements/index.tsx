import PageHeader from '@/components/common/PageHeader';
import {
    COGS_VIEW_IS_CHOOSABLE,
    COGS_VIEWS,
    CogsView,
    DEFAULT_COGS_VIEW,
} from '@/components/finance/cogs-view';
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

/** One transaction type's share of a column's OPEX; these sum to that OPEX. */
interface OpexLine {
    name: string;
    amount: number;
}

/** A saved product — a column on this page. */
interface ProductRow {
    product_id: number | null;
    product: string;
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
    /** This column's share of the month's OPEX, by its delivered orders. */
    opex: number;
    /** The share that produced it, as a percentage (0-100), not a fraction. */
    opex_share_percentage: number;
    /** That share opened up by transaction type; sums back to `opex`. */
    opex_breakdown: OpexLine[];
    net_profit_delivered_cogs: number;
    net_profit_bought_cogs: number;
}

/**
 * One line of what the Unresolved column is made of. A sku can show shipping
 * against it and nothing delivered, or the other way round; a null sku is the
 * orders that carry no line items at all.
 */
interface UnresolvedRow {
    label: string | null;
    delivered_orders: number;
    delivered_units: number;
    delivered_amount: number;
    shipped_orders: number;
    shipping_fee: number;
}

interface Props {
    workspace: Workspace;
    incomeStatement: StatementContext;
    products: ProductRow[];
    total: ProductRow;
    /** The rates these rows were struck at, as fractions. */
    rates: { cod: number; vat: number; advisory: number };
    /** The advisory share is only taken on partner workspaces. */
    gencysPartner: boolean;
    unresolved: UnresolvedRow[];
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

const pct = (fraction: number) => `${Number((fraction * 100).toFixed(4))}%`;

/**
 * A stored OPEX share. Already a percentage — unlike `pct` above, which takes a
 * fraction — so it is only trimmed, never multiplied.
 */
const sharePct = (percentage: number) =>
    `${Number(Number(percentage).toFixed(4))}%`;

const CARD =
    'rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900';
const BTN =
    'flex h-8 items-center gap-1.5 rounded-lg border border-black/6 bg-stone-50 px-3 font-mono text-[12px] text-gray-600 transition-all hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700';

// Two frozen columns — the figure label and the Total beside it — so a product
// far to the right still says what the row is and what it is measured against.
// Their widths are fixed because the second column's `left` offset has to equal
// the first one's width exactly, and a shrink-wrapped cell can't promise that.
const LABEL_W = 'w-[268px] min-w-[268px]';
const VALUE_W = 'w-[150px] min-w-[150px]';
const LABEL_LEFT = 'sticky left-0';
const TOTAL_LEFT = 'sticky left-[268px]';

// Stacking, highest first: the frozen header corner sits above the scrolling
// header, which sits above the frozen body columns, which sit above the figures.
const Z_CORNER = 'z-40';
const Z_HEAD = 'z-30';
const Z_FROZEN = 'z-20';

// Box-shadow rather than a border: border-collapse drops borders on a sticky
// cell. The right-hand edge marks where the frozen pair ends.
const EDGE =
    'shadow-[1px_0_0_0_rgba(0,0,0,0.06)] dark:shadow-[1px_0_0_0_rgba(255,255,255,0.06)]';
const HEAD_ROW =
    'sticky top-0 shadow-[0_1px_0_0_rgba(0,0,0,0.06)] dark:shadow-[0_1px_0_0_rgba(255,255,255,0.06)]';
const CORNER_EDGE =
    'shadow-[1px_0_0_0_rgba(0,0,0,0.06),0_1px_0_0_rgba(0,0,0,0.06)] dark:shadow-[1px_0_0_0_rgba(255,255,255,0.06),0_1px_0_0_rgba(255,255,255,0.06)]';

const CELL =
    'px-4 py-2.5 text-right text-[12px] whitespace-nowrap tabular-nums';

interface FigureRow {
    label: string;
    help: string;
    render: (r: ProductRow) => string;
    emphasis?: boolean;
    /** When set, the cell is coloured by the sign of this value. */
    signed?: (r: ProductRow) => number;
    /** The row opens to show what makes it up, one sub-row per entry. */
    breakdown?: (r: ProductRow) => OpexLine[];
}

/**
 * The rows, in the order and wording the workspace statement uses, so the same
 * figure reads the same way on both — down to the closing OPEX and net profit.
 */
const buildRows = (
    rates: { cod: number; vat: number; advisory: number },
    cogsView: CogsView,
    gencysPartner: boolean,
): FigureRow[] => {
    const advisoryRows: FigureRow[] = gencysPartner
        ? [
              {
                  label: `Advisory Share — ${pct(rates.advisory)} of Gross Profit`,
                  help: `A share of gross profit taken by the partner. The workspace figure is struck as the lower of ${pct(rates.advisory)} of gross profit or a share of delivered revenue, and that one charge is then divided between these columns in proportion to the profit each made. A column that lost money carries none of it.`,
                  render: (r) =>
                      fmt(
                          cogsView === 'bought'
                              ? r.gross_profit_bought_cogs_advisory_share
                              : r.gross_profit_delivered_cogs_advisory_share,
                      ),
              },
              {
                  label: 'Gross Profit after Advisory',
                  help: 'Gross Profit less the advisory share above.',
                  render: (r) =>
                      fmt(
                          cogsView === 'bought'
                              ? r.gross_profit_bought_cogs_after_advisory_share
                              : r.gross_profit_delivered_cogs_after_advisory_share,
                      ),
                  emphasis: true,
                  signed: (r) =>
                      cogsView === 'bought'
                          ? r.gross_profit_bought_cogs_after_advisory_share
                          : r.gross_profit_delivered_cogs_after_advisory_share,
              },
          ]
        : [];

    return [
        {
            label: 'Delivered Orders',
            help: 'Parcels delivered this month that contained this product, across everyone selling it. A parcel holding two products counts once for each, so the columns can add up to more than the month’s parcels.',
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
            help: 'Parcels of this product shipped out this month, by shipped-out date and whatever became of them afterwards. A different set from Delivered Orders — the two are not expected to agree.',
            render: (r) => int(r.shipped_orders),
        },
        {
            label: 'Total Shipping Fee',
            help: 'The courier fee on those parcels. Charged when a parcel ships, so a return is paid for too — this is not limited to what was delivered.',
            render: (r) => fmt(r.total_shipping_fee),
        },
        {
            label: 'Ad Spent',
            help: 'Ad spend attributed to this product this month — the pages selling it where spend is recorded per page, otherwise the product tags on the ledger.',
            render: (r) => fmt(r.ad_spent),
        },
        {
            label: 'COD Fee',
            help: `The courier's fee for collecting on delivery — ${pct(rates.cod)} of the column's Delivered Amount, at the rate saved on this statement.`,
            render: (r) => fmt(r.cod_fee),
        },
        {
            label: 'COD Fee VAT',
            help: `VAT on the COD fee — ${pct(rates.vat)} of the fee itself, not of the delivered amount.`,
            render: (r) => fmt(r.cod_fee_vat),
        },
        ...(cogsView === 'bought'
            ? ([
                  {
                      label: 'Bought COGS',
                      help: 'Goods bought for this product this month — stock bought, which is not the same as stock sold. A purchase nobody tagged to a product sits under Unresolved rather than being spread across the others.',
                      render: (r) => fmt(r.total_bought_cogs),
                      emphasis: true,
                  },
                  {
                      label: 'Bought COGS Delivery Fee',
                      help: 'Freight paid on those purchases.',
                      render: (r) => fmt(r.total_bought_cogs_delivery_fee),
                  },
                  {
                      label: 'Gross Profit',
                      help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less what was bought into stock this month and the freight on it. What the month cost in cash, not the margin on what sold.',
                      render: (r) => fmt(r.gross_profit_bought_cogs),
                      emphasis: true,
                      signed: (r) => r.gross_profit_bought_cogs,
                  },
              ] as FigureRow[])
            : ([
                  {
                      label: 'Delivered COGS',
                      help: 'Cost of the goods that actually shipped, taken from the orders’ own cost figures and divided across a multi-product parcel the same way revenue is.',
                      render: (r) => fmt(r.total_delivered_cogs),
                      emphasis: true,
                  },
                  {
                      label: 'Gross Profit',
                      help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less the cost of the goods that actually shipped. The margin on what was sold this month.',
                      render: (r) => fmt(r.gross_profit_delivered_cogs),
                      emphasis: true,
                      signed: (r) => r.gross_profit_delivered_cogs,
                  },
              ] as FigureRow[])),
        ...advisoryRows,
        {
            label: 'OPEX Share',
            help: 'This product’s delivered orders as a percentage of every column’s on this statement — the split the OPEX below was struck from. Saved with the statement, so it still reads back after a later sync moves the underlying counts.',
            render: (r) => sharePct(r.opex_share_percentage),
        },
        {
            label: 'Less — OPEX',
            help: 'This product’s share of the month’s operating expenses, by the percentage above. Nothing in the OPEX pool is booked against one product, so the share is allocated rather than measured — a column that delivered nothing carries none of it. Open the row to see it by transaction type.',
            render: (r) => fmt(r.opex),
            breakdown: (r) => r.opex_breakdown,
        },
        {
            label: '= Net Profit',
            help: 'Gross Profit less the advisory share and the OPEX above.',
            render: (r) =>
                fmt(
                    cogsView === 'bought'
                        ? r.net_profit_bought_cogs
                        : r.net_profit_delivered_cogs,
                ),
            emphasis: true,
            signed: (r) =>
                cogsView === 'bought'
                    ? r.net_profit_bought_cogs
                    : r.net_profit_delivered_cogs,
        },
    ];
};

export default function ProductIncomeStatements({
    workspace,
    incomeStatement,
    products,
    total,
    rates,
    gencysPartner,
    unresolved: unresolvedRows,
}: Props) {
    const finance = `/workspaces/${workspace.slug}/finance`;
    const [cogsView, setCogsView] = useState<CogsView>(DEFAULT_COGS_VIEW);
    const [openRows, setOpenRows] = useState<Set<string>>(new Set());
    const [unresolvedOpen, setUnresolvedOpen] = useState(false);

    const ROWS = buildRows(rates, cogsView, gencysPartner);
    const named = products.filter((p) => p.product_id !== null);
    const unmappedSkus = unresolvedRows.filter((u) => u.label !== null).length;

    const toggleRow = (label: string) =>
        setOpenRows((current) => {
            const next = new Set(current);

            if (!next.delete(label)) next.add(label);

            return next;
        });

    /** The tone a figure cell takes, shared by the Total and product columns. */
    const cellTone = (row: FigureRow, r: ProductRow, muted: boolean) => {
        if (row.signed) {
            return `font-semibold ${
                row.signed(r) < 0
                    ? 'text-rose-600 dark:text-rose-400'
                    : 'text-emerald-600 dark:text-emerald-400'
            }`;
        }
        if (row.emphasis) {
            return `font-medium ${
                muted
                    ? 'text-amber-700 dark:text-amber-400'
                    : 'text-gray-800 dark:text-gray-100'
            }`;
        }

        return 'text-gray-500 dark:text-gray-400';
    };

    return (
        <AppLayout>
            <Head
                title={`${workspace.name} - Product Statement ${incomeStatement.label}`}
            />
            <div className="w-full p-4 font-mono md:p-6">
                <PageHeader
                    title="Product Statement"
                    description={`${incomeStatement.label} · every product, down the same lines as the statement`}
                >
                    <div className="flex items-center gap-2">
                        <Link
                            href={`${finance}/income-statements/${incomeStatement.id}/users`}
                            className={BTN}
                        >
                            Per-user
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

                {/* Unit codes with no product behind them — the items using
                    them can't be attributed, so they land in Unresolved. */}
                {unmappedSkus > 0 && (
                    <div className="mb-6 rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 dark:border-amber-800/60 dark:bg-amber-950/40">
                        <div className="flex items-center gap-2 text-[12px] text-amber-800 dark:text-amber-200">
                            <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                            {int(unmappedSkus)} unit code
                            {unmappedSkus === 1 ? '' : 's'} on this
                            month&rsquo;s orders map to no product.{' '}
                            <Link
                                href={`/workspaces/${workspace.slug}/gencys/unit-codes`}
                                className="underline underline-offset-2"
                            >
                                Map them
                            </Link>{' '}
                            to clear the Unresolved column.
                        </div>
                    </div>
                )}

                <div className={`${CARD} overflow-hidden`}>
                    <div className="flex items-center justify-between border-b border-black/6 px-5 py-4 dark:border-white/6">
                        <div>
                            <div className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                                Figures, by product
                            </div>
                            <div className="mt-0.5 text-[11px] text-gray-400">
                                {incomeStatement.label} · {int(named.length)}{' '}
                                {named.length === 1 ? 'product' : 'products'}
                                {named.length > 1 && ' · scroll right for more'}
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            {/* Only one basis is enabled, so there is nothing to
                                pick — see components/finance/cogs-view. */}
                            {COGS_VIEW_IS_CHOOSABLE && (
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
                                                onClick={() =>
                                                    setCogsView(v.value)
                                                }
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
                            )}
                            <span className="rounded-full border border-black/6 px-2.5 py-0.5 text-[10px] tracking-wider text-gray-400 uppercase dark:border-white/6">
                                Saved
                            </span>
                        </div>
                    </div>

                    <div className="overflow-auto">
                        <table className="border-separate border-spacing-0">
                            <thead>
                                <tr>
                                    <th
                                        className={`${LABEL_W} ${LABEL_LEFT} ${CORNER_EDGE} ${Z_CORNER} sticky top-0 bg-white px-5 py-3 text-left text-[10px] font-semibold tracking-wider text-gray-400 uppercase dark:bg-zinc-900`}
                                    >
                                        Figure
                                    </th>
                                    <th
                                        className={`${VALUE_W} ${TOTAL_LEFT} ${CORNER_EDGE} ${Z_CORNER} sticky top-0 bg-stone-100 px-4 py-3 text-right text-[10px] font-semibold tracking-wider text-gray-600 uppercase dark:bg-zinc-800 dark:text-gray-300`}
                                    >
                                        Total
                                    </th>
                                    {products.map((p) => {
                                        const unresolved =
                                            p.product_id === null;

                                        return (
                                            <th
                                                key={
                                                    p.product_id ?? 'unresolved'
                                                }
                                                className={`${VALUE_W} ${HEAD_ROW} ${Z_HEAD} px-4 py-3 text-right text-[10px] font-semibold tracking-wider uppercase ${
                                                    unresolved
                                                        ? 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-400'
                                                        : 'bg-white text-gray-400 dark:bg-zinc-900'
                                                }`}
                                            >
                                                <span className="flex items-center justify-end gap-1">
                                                    {unresolved && (
                                                        <AlertTriangle className="h-3 w-3 shrink-0 text-amber-500" />
                                                    )}
                                                    <span className="truncate">
                                                        {p.product}
                                                    </span>
                                                </span>
                                            </th>
                                        );
                                    })}
                                    {products.length === 0 && (
                                        <th
                                            className={`${VALUE_W} ${HEAD_ROW} ${Z_HEAD} bg-white px-4 py-3 text-right text-[10px] text-gray-400 dark:bg-zinc-900`}
                                        >
                                            —
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {ROWS.map((row) => {
                                    const lines = row.breakdown?.(total) ?? [];
                                    const expandable = lines.length > 0;
                                    const open = openRows.has(row.label);

                                    return (
                                        <Fragment key={row.label}>
                                            <tr className="group border-t border-black/5 dark:border-white/5">
                                                <th
                                                    scope="row"
                                                    className={`${LABEL_W} ${LABEL_LEFT} ${EDGE} ${Z_FROZEN} border-t border-black/5 bg-white px-5 py-2.5 text-left font-normal group-hover:bg-stone-50 dark:border-white/5 dark:bg-zinc-900 dark:group-hover:bg-zinc-800`}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <button
                                                                    type="button"
                                                                    className={`flex items-center gap-1.5 text-left text-[12px] transition-colors hover:text-gray-800 focus-visible:text-gray-800 focus-visible:outline-none dark:hover:text-gray-100 ${
                                                                        row.emphasis
                                                                            ? 'font-medium text-gray-700 dark:text-gray-200'
                                                                            : 'text-gray-600 dark:text-gray-300'
                                                                    }`}
                                                                >
                                                                    {row.label}
                                                                    <HelpCircle className="h-3 w-3 shrink-0 opacity-50" />
                                                                </button>
                                                            </TooltipTrigger>
                                                            <TooltipContent className="max-w-xs font-mono text-[11px] leading-relaxed">
                                                                {row.help}
                                                            </TooltipContent>
                                                        </Tooltip>

                                                        {expandable && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    toggleRow(
                                                                        row.label,
                                                                    )
                                                                }
                                                                aria-expanded={
                                                                    open
                                                                }
                                                                className="flex items-center gap-0.5 text-[10px] text-gray-400 underline-offset-2 transition-colors hover:text-gray-600 hover:underline dark:hover:text-gray-300"
                                                            >
                                                                {open ? (
                                                                    <ChevronDown className="h-3 w-3" />
                                                                ) : (
                                                                    <ChevronRight className="h-3 w-3" />
                                                                )}
                                                                {int(
                                                                    lines.length,
                                                                )}{' '}
                                                                {lines.length ===
                                                                1
                                                                    ? 'type'
                                                                    : 'types'}
                                                            </button>
                                                        )}
                                                    </div>
                                                </th>

                                                <td
                                                    className={`${VALUE_W} ${TOTAL_LEFT} ${CELL} ${EDGE} ${Z_FROZEN} border-t border-black/5 bg-stone-100 font-semibold dark:border-white/5 dark:bg-zinc-800 ${cellTone(row, total, false)}`}
                                                >
                                                    {row.render(total)}
                                                </td>

                                                {products.map((p) => (
                                                    <td
                                                        key={
                                                            p.product_id ??
                                                            'unresolved'
                                                        }
                                                        className={`${VALUE_W} ${CELL} border-t border-black/5 dark:border-white/5 ${
                                                            p.product_id ===
                                                            null
                                                                ? 'bg-amber-50/60 dark:bg-amber-500/10'
                                                                : 'bg-white group-hover:bg-stone-50 dark:bg-zinc-900 dark:group-hover:bg-zinc-800'
                                                        } ${cellTone(row, p, p.product_id === null)}`}
                                                    >
                                                        {row.render(p)}
                                                    </td>
                                                ))}
                                                {products.length === 0 && (
                                                    <td
                                                        className={`${VALUE_W} ${CELL} border-t border-black/5 bg-white text-gray-400 dark:border-white/5 dark:bg-zinc-900`}
                                                    >
                                                        —
                                                    </td>
                                                )}
                                            </tr>

                                            {/* What the row is made of, one
                                                sub-row per transaction type.
                                                The parts sum to the row above. */}
                                            {expandable &&
                                                open &&
                                                lines.map((line, i) => (
                                                    <tr
                                                        key={line.name}
                                                        className="bg-stone-50/60 dark:bg-zinc-800/30"
                                                    >
                                                        <th
                                                            scope="row"
                                                            className={`${LABEL_W} ${LABEL_LEFT} ${EDGE} ${Z_FROZEN} bg-stone-50 py-1.5 pr-4 pl-11 text-left text-[11px] font-normal text-gray-500 dark:bg-zinc-800 dark:text-gray-400`}
                                                        >
                                                            {line.name}
                                                        </th>
                                                        <td
                                                            className={`${VALUE_W} ${TOTAL_LEFT} ${CELL} ${EDGE} ${Z_FROZEN} bg-stone-100 py-1.5 text-[11px] text-gray-500 dark:bg-zinc-800 dark:text-gray-400`}
                                                        >
                                                            {fmt(line.amount)}
                                                        </td>
                                                        {products.map((p) => (
                                                            <td
                                                                key={
                                                                    p.product_id ??
                                                                    'unresolved'
                                                                }
                                                                className={`${VALUE_W} ${CELL} py-1.5 text-[11px] text-gray-500 dark:text-gray-400 ${
                                                                    p.product_id ===
                                                                    null
                                                                        ? 'bg-amber-50/40 dark:bg-amber-500/5'
                                                                        : 'bg-stone-50/60 dark:bg-zinc-800/30'
                                                                }`}
                                                            >
                                                                {fmt(
                                                                    p
                                                                        .opex_breakdown[
                                                                        i
                                                                    ]?.amount ??
                                                                        0,
                                                                )}
                                                            </td>
                                                        ))}
                                                        {products.length ===
                                                            0 && (
                                                            <td
                                                                className={`${VALUE_W} ${CELL} py-1.5 text-[11px] text-gray-400`}
                                                            >
                                                                —
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {products.length === 0 && (
                        <div className="border-t border-black/6 px-5 py-8 text-center text-[12px] text-gray-400 dark:border-white/6">
                            Nothing delivered in {incomeStatement.label}.
                        </div>
                    )}
                </div>

                {/* The Unresolved column can't hold its own breakdown once the
                    products are columns, so what it is made of sits here. */}
                {unresolvedRows.length > 0 && (
                    <div className={`${CARD} mt-4 overflow-hidden`}>
                        <button
                            type="button"
                            onClick={() => setUnresolvedOpen((o) => !o)}
                            aria-expanded={unresolvedOpen}
                            className="flex w-full items-center gap-2 px-5 py-3 text-left"
                        >
                            {unresolvedOpen ? (
                                <ChevronDown className="h-3.5 w-3.5 text-gray-400" />
                            ) : (
                                <ChevronRight className="h-3.5 w-3.5 text-gray-400" />
                            )}
                            <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-amber-500" />
                            <span className="text-[12px] text-amber-800 dark:text-amber-200">
                                What&rsquo;s in Unresolved —{' '}
                                {int(unresolvedRows.length)}{' '}
                                {unresolvedRows.length === 1 ? 'line' : 'lines'}
                                {unmappedSkus > 0 &&
                                    `, ${int(unmappedSkus)} of them a unit code mapping to no product`}
                            </span>
                        </button>

                        {unresolvedOpen && (
                            <div className="overflow-x-auto border-t border-black/6 px-5 py-4 dark:border-white/6">
                                <table className="text-[11px]">
                                    <thead>
                                        <tr className="text-gray-400">
                                            <th className="pr-6 pb-1 text-left font-medium">
                                                Unit code on the order
                                            </th>
                                            <th className="px-3 pb-1 text-right font-medium">
                                                Delivered
                                            </th>
                                            <th className="px-3 pb-1 text-right font-medium">
                                                Units
                                            </th>
                                            <th className="px-3 pb-1 text-right font-medium">
                                                Amount
                                            </th>
                                            <th className="px-3 pb-1 text-right font-medium">
                                                Shipped
                                            </th>
                                            <th className="pb-1 pl-3 text-right font-medium">
                                                Shipping Fee
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {unresolvedRows.map((u) => (
                                            <tr key={u.label ?? 'no-items'}>
                                                <td className="py-1 pr-6">
                                                    {u.label ? (
                                                        <span className="text-gray-800 dark:text-gray-100">
                                                            {u.label}
                                                        </span>
                                                    ) : (
                                                        <span className="text-gray-500 italic dark:text-gray-400">
                                                            orders with no items
                                                            recorded
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-1 text-right tabular-nums">
                                                    {int(u.delivered_orders)}
                                                </td>
                                                <td className="px-3 py-1 text-right tabular-nums">
                                                    {int(u.delivered_units)}
                                                </td>
                                                <td className="px-3 py-1 text-right tabular-nums">
                                                    {fmt(u.delivered_amount)}
                                                </td>
                                                <td className="px-3 py-1 text-right tabular-nums">
                                                    {int(u.shipped_orders)}
                                                </td>
                                                <td className="py-1 pl-3 text-right tabular-nums">
                                                    {fmt(u.shipping_fee)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                <div className="mt-2.5 text-[10px] text-gray-400">
                                    Map a code on the{' '}
                                    <Link
                                        href={`/workspaces/${workspace.slug}/gencys/unit-codes`}
                                        className="underline underline-offset-2"
                                    >
                                        unit codes
                                    </Link>{' '}
                                    page and its figures move onto that
                                    product&rsquo;s column at the next
                                    regenerate.
                                </div>
                            </div>
                        )}
                    </div>
                )}

                <p className="mt-3 text-[11px] text-gray-400">
                    The rows are the workspace statement&rsquo;s, in the same
                    order, so a figure reads the same on both. Every column is
                    the product&rsquo;s own, across all sellers. The Total
                    covers the named products; the Unresolved column sits
                    outside it.
                </p>
            </div>
        </AppLayout>
    );
}
