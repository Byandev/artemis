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

/**
 * One seller on one product — a column here. The order-carried figures are that
 * pair's own; the bought-goods figures are the product's total cut by the
 * seller's share of its delivered orders.
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
    /** This column's share of the month's OPEX, by its delivered orders. */
    opex: number;
    /** The share that produced it, as a percentage (0-100), not a fraction. */
    opex_share_percentage: number;
    /** That share opened up by transaction type; sums back to `opex`. */
    opex_breakdown: OpexLine[];
    net_profit_delivered_cogs: number;
    net_profit_bought_cogs: number;
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

// Two frozen columns — the figure label and the Total beside it — so a pair far
// to the right still says what the row is and what it is measured against.
// Their widths are fixed because the second column's `left` offset has to equal
// the first one's width exactly, and a shrink-wrapped cell can't promise that.
const LABEL_W = 'w-[268px] min-w-[268px]';
const VALUE_W = 'w-[160px] min-w-[160px]';
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
const HEAD_ROW = 'sticky top-0';

// The header is two rows deep — product above, seller below — so the seller row
// has to stick at exactly the product row's height. Fixed rather than measured:
// the offset and the height must agree, and only a stated number can promise it.
const PRODUCT_ROW_H = 'h-9';
const SELLER_ROW_TOP =
    'sticky top-9 shadow-[0_1px_0_0_rgba(0,0,0,0.06)] dark:shadow-[0_1px_0_0_rgba(255,255,255,0.06)]';
const CORNER_EDGE =
    'shadow-[1px_0_0_0_rgba(0,0,0,0.06),0_1px_0_0_rgba(0,0,0,0.06)] dark:shadow-[1px_0_0_0_rgba(255,255,255,0.06),0_1px_0_0_rgba(255,255,255,0.06)]';

const CELL =
    'px-4 py-2.5 text-right text-[12px] whitespace-nowrap tabular-nums';

/** A column key that is stable even when either side is unattributed. */
const columnKey = (r: Row) =>
    `${r.user_id ?? 'none'}|${r.product_id ?? 'none'}`;

interface FigureRow {
    label: string;
    help: string;
    render: (r: Row) => string;
    emphasis?: boolean;
    /** When set, the cell is coloured by the sign of this value. */
    signed?: (r: Row) => number;
    /** The row opens to show what makes it up, one sub-row per entry. */
    breakdown?: (r: Row) => OpexLine[];
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
            help: 'Parcels of this product delivered this month and credited to this seller. Also the weight the product’s bought-goods costs are shared out by.',
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
            help: 'Parcels of this product this seller shipped out this month, by shipped-out date and whatever became of them afterwards. A different set from Delivered Orders — the two are not expected to agree.',
            render: (r) => int(r.shipped_orders),
        },
        {
            label: 'Total Shipping Fee',
            help: 'The courier fee on those parcels. Charged when a parcel ships, so a return is paid for too — this is not limited to what was delivered.',
            render: (r) => fmt(r.total_shipping_fee),
        },
        {
            label: 'Ad Spent',
            help: 'Ad spend for this seller on this product. Where spend is recorded per page — a page has both an owner and a product — it is exactly what ran on their own pages; on the ledger side, where nothing ties a product’s spend to a person, it is their share of the product’s.',
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
                      help: 'This seller’s share of the product’s goods purchases for the month, by their share of its delivered orders. Stock bought, which is not the same as stock sold.',
                      render: (r) => fmt(r.total_bought_cogs),
                      emphasis: true,
                  },
                  {
                      label: 'Bought COGS Delivery Fee',
                      help: 'Freight paid on those purchases, shared out the same way.',
                      render: (r) => fmt(r.total_bought_cogs_delivery_fee),
                  },
                  {
                      label: 'Gross Profit',
                      help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less their share of what was bought into stock this month and the freight on it.',
                      render: (r) => fmt(r.gross_profit_bought_cogs),
                      emphasis: true,
                      signed: (r) => r.gross_profit_bought_cogs,
                  },
              ] as FigureRow[])
            : ([
                  {
                      label: 'Delivered COGS',
                      help: 'Cost of the goods that actually shipped, taken from the orders’ own cost figures. Carried on the orders themselves, so it is this seller’s own — not a share.',
                      render: (r) => fmt(r.total_delivered_cogs),
                      emphasis: true,
                  },
                  {
                      label: 'Gross Profit',
                      help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less the cost of the goods that actually shipped.',
                      render: (r) => fmt(r.gross_profit_delivered_cogs),
                      emphasis: true,
                      signed: (r) => r.gross_profit_delivered_cogs,
                  },
              ] as FigureRow[])),
        ...advisoryRows,
        {
            label: 'OPEX Share',
            help: 'This column’s delivered orders as a percentage of every column’s on this statement — the split the OPEX below was struck from. Saved with the statement, so it still reads back after a later sync moves the underlying counts.',
            render: (r) => sharePct(r.opex_share_percentage),
        },
        {
            label: 'Less — OPEX',
            help: 'This column’s share of the month’s operating expenses, by the percentage above. Nothing in the OPEX pool is booked against one seller or one product, so the share is allocated rather than measured. Open the row to see it by transaction type.',
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

export default function UserProductIncomeStatements({
    workspace,
    incomeStatement,
    rows,
    total,
    rates,
    gencysPartner,
}: Props) {
    const finance = `/workspaces/${workspace.slug}/finance`;
    const [cogsView, setCogsView] = useState<CogsView>(DEFAULT_COGS_VIEW);
    const [openRows, setOpenRows] = useState<Set<string>>(new Set());

    const ROWS = buildRows(rates, cogsView, gencysPartner);

    // The point of the page: products more than one person is running.
    const sellersPerProduct = new Map<string, number>();
    for (const r of rows) {
        if (r.product_id === null || r.user_id === null) continue;
        const key = String(r.product_id);
        sellersPerProduct.set(key, (sellersPerProduct.get(key) ?? 0) + 1);
    }
    const shared = [...sellersPerProduct.values()].filter((n) => n > 1).length;
    const products = sellersPerProduct.size;

    // The columns arrive ordered product-then-seller, so a product's run is
    // simply the consecutive columns sharing its id.
    const groups: {
        key: string;
        product: string;
        muted: boolean;
        span: number;
    }[] = [];

    for (const r of rows) {
        const key = String(r.product_id ?? 'none');
        const last = groups[groups.length - 1];

        if (last && last.key === key) {
            last.span++;
        } else {
            groups.push({
                key,
                product: r.product,
                muted: r.product_id === null,
                span: 1,
            });
        }
    }

    // Where each product's run begins, so the divider under the merged heading
    // carries down the figures and the groups stay legible while scrolling.
    const startsGroup = rows.map(
        (r, i) => i === 0 || rows[i - 1].product_id !== r.product_id,
    );
    const divider = (i: number) =>
        startsGroup[i] ? 'border-l border-black/6 dark:border-white/6' : '';

    const toggleRow = (label: string) =>
        setOpenRows((current) => {
            const next = new Set(current);

            if (!next.delete(label)) next.add(label);

            return next;
        });

    /** The tone a figure cell takes, shared by the Total and pair columns. */
    const cellTone = (row: FigureRow, r: Row, muted: boolean) => {
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

    /** A column is unattributed if either side of the pair is. */
    const isMuted = (r: Row) => r.user_id === null || r.product_id === null;

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
                                Figures, by seller and product
                            </div>
                            <div className="mt-0.5 text-[11px] text-gray-400">
                                {incomeStatement.label} · {int(products)}{' '}
                                {products === 1 ? 'product' : 'products'}
                                {shared > 0 &&
                                    ` · ${int(shared)} run by more than one person`}
                                {rows.length > 1 && ' · scroll right for more'}
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
                                {/* Two rows: the product spans its sellers above, each
                                    seller names itself below. The frozen pair on the
                                    left spans both. */}
                                <tr>
                                    <th
                                        rowSpan={2}
                                        className={`${LABEL_W} ${LABEL_LEFT} ${CORNER_EDGE} ${Z_CORNER} sticky top-0 bg-white px-5 text-left text-[10px] font-semibold tracking-wider text-gray-400 uppercase dark:bg-zinc-900`}
                                    >
                                        Figure
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className={`${VALUE_W} ${TOTAL_LEFT} ${CORNER_EDGE} ${Z_CORNER} sticky top-0 bg-stone-100 px-4 text-right text-[10px] font-semibold tracking-wider text-gray-600 uppercase dark:bg-zinc-800 dark:text-gray-300`}
                                    >
                                        Total
                                    </th>
                                    {groups.map((g) => (
                                        <th
                                            key={g.key}
                                            colSpan={g.span}
                                            className={`${HEAD_ROW} ${Z_HEAD} ${PRODUCT_ROW_H} truncate border-l border-black/6 px-4 text-center text-[10px] font-semibold tracking-wider uppercase dark:border-white/6 ${
                                                g.muted
                                                    ? 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-400'
                                                    : 'bg-stone-50 text-gray-500 dark:bg-zinc-800/60 dark:text-gray-300'
                                            }`}
                                        >
                                            <span className="flex items-center justify-center gap-1">
                                                {g.muted && (
                                                    <AlertTriangle className="h-3 w-3 shrink-0 text-amber-500" />
                                                )}
                                                <span className="truncate">
                                                    {g.product}
                                                </span>
                                                {g.span > 1 && (
                                                    <span className="shrink-0 font-normal opacity-60">
                                                        ({int(g.span)})
                                                    </span>
                                                )}
                                            </span>
                                        </th>
                                    ))}
                                    {rows.length === 0 && (
                                        <th
                                            className={`${VALUE_W} ${HEAD_ROW} ${Z_HEAD} ${PRODUCT_ROW_H} bg-stone-50 px-4 text-center text-[10px] text-gray-400 dark:bg-zinc-800/60`}
                                        >
                                            —
                                        </th>
                                    )}
                                </tr>
                                <tr>
                                    {rows.map((r, i) => {
                                        const muted = isMuted(r);

                                        return (
                                            <th
                                                key={columnKey(r)}
                                                className={`${VALUE_W} ${SELLER_ROW_TOP} ${Z_HEAD} px-4 py-2 text-right text-[10px] font-semibold tracking-wider uppercase ${divider(i)} ${
                                                    muted
                                                        ? 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-400'
                                                        : 'bg-white text-gray-400 dark:bg-zinc-900'
                                                }`}
                                            >
                                                {r.user_id === null ? (
                                                    <span className="block truncate">
                                                        {r.user}
                                                    </span>
                                                ) : (
                                                    <Link
                                                        href={`${finance}/income-statements/${incomeStatement.id}/users/${r.user_id}`}
                                                        className="block truncate underline-offset-2 hover:underline"
                                                    >
                                                        {r.user}
                                                    </Link>
                                                )}
                                            </th>
                                        );
                                    })}
                                    {rows.length === 0 && (
                                        <th
                                            className={`${VALUE_W} ${SELLER_ROW_TOP} ${Z_HEAD} bg-white px-4 py-2 text-right text-[10px] text-gray-400 dark:bg-zinc-900`}
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

                                                {rows.map((r, i) => (
                                                    <td
                                                        key={columnKey(r)}
                                                        className={`${VALUE_W} ${CELL} border-t border-black/5 dark:border-white/5 ${divider(i)} ${
                                                            isMuted(r)
                                                                ? 'bg-amber-50/60 dark:bg-amber-500/10'
                                                                : 'bg-white group-hover:bg-stone-50 dark:bg-zinc-900 dark:group-hover:bg-zinc-800'
                                                        } ${cellTone(row, r, isMuted(r))}`}
                                                    >
                                                        {row.render(r)}
                                                    </td>
                                                ))}
                                                {rows.length === 0 && (
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
                                                        {rows.map((r, col) => (
                                                            <td
                                                                key={columnKey(
                                                                    r,
                                                                )}
                                                                className={`${VALUE_W} ${CELL} py-1.5 text-[11px] text-gray-500 dark:text-gray-400 ${divider(col)} ${
                                                                    isMuted(r)
                                                                        ? 'bg-amber-50/40 dark:bg-amber-500/5'
                                                                        : 'bg-stone-50/60 dark:bg-zinc-800/30'
                                                                }`}
                                                            >
                                                                {fmt(
                                                                    r
                                                                        .opex_breakdown[
                                                                        i
                                                                    ]?.amount ??
                                                                        0,
                                                                )}
                                                            </td>
                                                        ))}
                                                        {rows.length === 0 && (
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

                    {rows.length === 0 && (
                        <div className="border-t border-black/6 px-5 py-8 text-center text-[12px] text-gray-400 dark:border-white/6">
                            Nothing delivered in {incomeStatement.label}.
                        </div>
                    )}
                </div>

                <p className="mt-3 text-[11px] text-gray-400">
                    The rows are the workspace statement&rsquo;s, in the same
                    order, so a figure reads the same on both. Columns are
                    grouped by product, so a product run by several people shows
                    them side by side; click a name for that person&rsquo;s own
                    page. Delivered orders, revenue, shipping and delivered COGS
                    are each seller&rsquo;s own; the bought-goods figures are
                    their share of the product&rsquo;s, by delivered orders. The
                    Total covers columns attributed on both sides.
                </p>
            </div>
        </AppLayout>
    );
}
