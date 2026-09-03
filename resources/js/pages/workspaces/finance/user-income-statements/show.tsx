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
    /** A closed month — its figures are held, so nothing here is editable. */
    locked: boolean;
}

/** One transaction type's share of a column's OPEX; these sum to that OPEX. */
interface OpexLine {
    name: string;
    amount: number;
}

/**
 * One product this seller moved — a column on this page. The order-carried
 * figures are their own; the bought-goods and ad figures are the product's
 * total cut by their share of its delivered orders, so a product they share
 * with someone else shows only their slice.
 */
interface ProductRow {
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
    /** This pair's share of the month's OPEX, by its delivered orders. */
    opex: number;
    /** The share that produced it, as a percentage (0-100), not a fraction. */
    opex_share_percentage: number;
    /** That share opened up by transaction type; sums back to `opex`. */
    opex_breakdown: OpexLine[];
    /**
     * The deficit carried into the month for this person on this product —
     * entered on the Seller × Product page, which reports the same grain.
     */
    loss_brought_forward: number;
    cumulative_profit_delivered_cogs: number;
    cumulative_profit_bought_cogs: number;
    net_profit_delivered_cogs: number;
    net_profit_bought_cogs: number;
    /**
     * The product across every seller who ran it, and this person's `share` of
     * its delivered orders — the weight the allocated costs were cut by, so
     * `whole_* × share` lands on the figure below it. Null share when the
     * product delivered nothing: there is no ratio, and 0/0 is not 0%.
     */
    share: number | null;
    whole_delivered_orders: number;
    whole_delivered_amount: number;
    whole_total_bought_cogs: number;
    whole_total_bought_cogs_delivery_fee: number;
    whole_total_delivered_cogs: number;
}

interface Props {
    workspace: Workspace;
    incomeStatement: StatementContext;
    user: { id: number | null; name: string };
    products: ProductRow[];
    total: ProductRow;
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

/** A seller's share of a product, to one decimal. Null = no ratio to take. */
const share = (v: number | null) =>
    v === null ? '—' : `${(v * 100).toFixed(1)}%`;

/**
 * A stored OPEX share. Already a percentage — unlike `share` above, which takes
 * a fraction — so it is only trimmed, never multiplied.
 */
const sharePct = (percentage: number) =>
    `${Number(Number(percentage).toFixed(4))}%`;

const CARD =
    'rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900';
const BTN =
    'flex h-8 items-center gap-1.5 rounded-lg border border-black/6 bg-stone-50 px-3 font-mono text-[12px] text-gray-600 transition-all hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700';

// Two frozen columns — the figure label and the Total beside it — so a product
// far to the right still says what it is and what it is measured against.
// Their widths are fixed because the second column's `left` offset has to equal
// the first one's width exactly, and a shrink-wrapped cell can't promise that.
const LABEL_W = 'w-[248px] min-w-[248px]';
const VALUE_W = 'w-[150px] min-w-[150px]';
const LABEL_LEFT = 'sticky left-0';
const TOTAL_LEFT = 'sticky left-[248px]';

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
    help?: string;
    render: (r: ProductRow) => string;
    emphasis?: boolean;
    /** When set, the cell is coloured by the sign of this value. */
    signed?: (r: ProductRow) => number;
    /** A band splitting the table into sections; carries no figures. */
    heading?: boolean;
    /** Context about the whole product rather than this person's slice. */
    muted?: boolean;
    /** The row opens to show what makes it up, one sub-row per entry. */
    breakdown?: (r: ProductRow) => OpexLine[];
}

/**
 * The rows, in the order and wording the main income statement uses, so the
 * same figure reads the same way on both — down to the closing OPEX and net
 * profit lines.
 */
const buildRows = (
    rates: { cod: number; vat: number; advisory: number },
    cogsView: CogsView,
    gencysPartner: boolean,
    userName: string,
): FigureRow[] => {
    // Where spend is recorded per page — a page has both an owner and a
    // product — this is exactly what ran on their own pages. The ledger side
    // ties spend to a product or to a person but never to both, so there it
    // falls back to their share of the product's.
    const adSpentHelp = gencysPartner
        ? 'This person’s share of the product’s ad spend for the month, by their share of its delivered orders. The ledger ties spend to a product or to a person, never to both at once, so this grain has to apportion it.'
        : 'Ad spend on the pages this person owns for this product. Taken straight from the daily page records, not shared out — someone who delivered most of a product still carries only what ran on their own pages.';
    const advisoryRows: FigureRow[] = gencysPartner
        ? [
              {
                  label: `Advisory Share — ${pct(rates.advisory)} of Gross Profit`,
                  help: `A share of gross profit taken by the partner — ${pct(rates.advisory)} of a positive Gross Profit, at the rate saved on this statement. A loss owes nothing. (The workspace statement can also strike this off delivered revenue and charge the lower of the two; per product only the gross-profit basis is kept.)`,
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

    // The statement's closing lines. Nothing in the OPEX pool is booked against
    // one seller-and-product pair, so the share is allocated by delivered
    // orders — the same weight the bought-goods costs above are cut by.
    const closingRows: FigureRow[] = [
        {
            label: 'OPEX Share',
            help: 'This column’s delivered orders as a percentage of every row’s on the statement — the split the OPEX beside it was struck from. A different share from the one above, which is of this product alone.',
            render: (r) => sharePct(r.opex_share_percentage),
        },
        {
            label: 'Less — OPEX',
            help: 'This person’s share of the month’s operating expenses on this product, by its delivered orders over every row’s on the statement. A column that delivered nothing carries none of it. Open the row to see it by transaction type.',
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
        {
            label: 'Less — Loss Brought Forward',
            help: 'What this person carried into the month on this product. Entered on the Seller × Product page, which reports this same grain — this page reads it rather than setting it, so there is one place the figure is stated.',
            render: (r) => fmt(r.loss_brought_forward),
        },
        {
            label: '= Cumulative Profit',
            help: 'Net Profit less the loss carried in — what this person is actually up on this product, counting where they started the month.',
            render: (r) =>
                fmt(
                    cogsView === 'bought'
                        ? r.cumulative_profit_bought_cogs
                        : r.cumulative_profit_delivered_cogs,
                ),
            emphasis: true,
            signed: (r) =>
                cogsView === 'bought'
                    ? r.cumulative_profit_bought_cogs
                    : r.cumulative_profit_delivered_cogs,
        },
    ];

    // What each column is a share OF, and the share itself. Put above the
    // statement rather than tucked in a tooltip because the allocated costs
    // below are literally these figures times that percentage — showing the
    // working is what makes them checkable.
    const wholeProductRows: FigureRow[] = [
        {
            label: 'Whole product — all sellers',
            render: () => '',
            heading: true,
        },
        {
            label: 'Delivered Orders (product)',
            help: 'Parcels of this product delivered this month by everyone who runs it, not just this person. The denominator of the share below.',
            render: (r) => int(r.whole_delivered_orders),
            muted: true,
        },
        ...(cogsView === 'bought'
            ? ([
                  {
                      label: 'Bought COGS (product)',
                      help: 'Everything spent buying this product into stock this month, before it is split between its sellers.',
                      render: (r) => fmt(r.whole_total_bought_cogs),
                      muted: true,
                  },
                  {
                      label: 'Bought COGS Delivery Fee (product)',
                      help: 'The whole freight bill on those purchases, before it is split between its sellers.',
                      render: (r) =>
                          fmt(r.whole_total_bought_cogs_delivery_fee),
                      muted: true,
                  },
              ] as FigureRow[])
            : ([
                  {
                      label: 'Delivered COGS (product)',
                      help: 'The cost of every seller’s delivered goods for this product. Shown for scale only — this one is never split, because each seller’s delivered COGS is carried on their own orders.',
                      render: (r) => fmt(r.whole_total_delivered_cogs),
                      muted: true,
                  },
              ] as FigureRow[])),
        {
            label: 'Share of product',
            help: 'This person’s delivered orders as a percentage of the product’s. The bought-goods costs below are the product figure above times this. Blank when the product delivered nothing this month: there is no ratio to take.',
            render: (r) => share(r.share),
            emphasis: true,
        },
        {
            label: userName,
            render: () => '',
            heading: true,
        },
    ];

    return [
        ...wholeProductRows,
        {
            label: 'Delivered Orders',
            help: 'Parcels of this product delivered this month and credited to this person. A parcel holding two products counts once for each, so the columns can add up to more than their parcels.',
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
            help: 'Parcels of this product they shipped out this month, by shipped-out date and whatever became of them afterwards. A different set from Delivered Orders — the two are not expected to agree.',
            render: (r) => int(r.shipped_orders),
        },
        {
            label: 'Total Shipping Fee',
            help: 'The courier fee on those parcels. Charged when a parcel ships, so a return is paid for too — this is not limited to what was delivered.',
            render: (r) => fmt(r.total_shipping_fee),
        },
        {
            label: 'Ad Spent',
            help: adSpentHelp,
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
                      help: 'This person’s share of the product’s goods purchases for the month, by their share of its delivered orders. Stock bought, which is not the same as stock sold.',
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
                      help: 'Cost of the goods that actually shipped, taken from the orders’ own cost figures. Carried on the orders themselves, so it is entirely this person’s — not a share.',
                      render: (r) => fmt(r.total_delivered_cogs),
                      emphasis: true,
                  },
                  {
                      label: 'Gross Profit',
                      help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less the cost of the goods that actually shipped. The margin they made on this product.',
                      render: (r) => fmt(r.gross_profit_delivered_cogs),
                      emphasis: true,
                      signed: (r) => r.gross_profit_delivered_cogs,
                  },
              ] as FigureRow[])),
        ...advisoryRows,
        ...closingRows,
    ];
};

export default function UserProductBreakdown({
    workspace,
    incomeStatement,
    user,
    products,
    total,
    rates,
    gencysPartner,
}: Props) {
    const finance = `/workspaces/${workspace.slug}/finance`;
    const [cogsView, setCogsView] = useState<CogsView>(DEFAULT_COGS_VIEW);
    const [openRows, setOpenRows] = useState<Set<string>>(new Set());
    const ROWS = buildRows(rates, cogsView, gencysPartner, user.name);
    const named = products.filter((p) => p.product_id !== null);

    const toggleRow = (label: string) =>
        setOpenRows((current) => {
            const next = new Set(current);

            if (!next.delete(label)) next.add(label);

            return next;
        });

    /** The tone a figure cell takes, shared by the Total and product columns. */
    const cellTone = (row: FigureRow, r: ProductRow, muted: boolean) => {
        // Whole-product context sits behind this person's own figures.
        if (row.muted) return 'text-gray-400 italic dark:text-gray-500';
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
                title={`${workspace.name} - ${user.name} · ${incomeStatement.label}`}
            />
            <div className="w-full p-4 font-mono md:p-6">
                <PageHeader
                    title={user.name}
                    description={`${incomeStatement.label} · every product they moved`}
                >
                    <div className="flex items-center gap-2">
                        <Link
                            href={`${finance}/income-statements/${incomeStatement.id}/user-products`}
                            className={BTN}
                        >
                            All sellers
                        </Link>
                        <Link
                            href={`${finance}/income-statements/${incomeStatement.id}/users`}
                            className={BTN}
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            Back to per-user
                        </Link>
                    </div>
                </PageHeader>

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
                        {/* Only one basis is enabled, so there is nothing to pick —
                            see components/finance/cogs-view. */}
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
                        )}
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
                                {ROWS.map((row) =>
                                    row.heading ? (
                                        <tr key={row.label}>
                                            <th
                                                scope="row"
                                                className={`${LABEL_W} ${LABEL_LEFT} ${EDGE} ${Z_FROZEN} border-t border-black/6 bg-stone-100 px-5 py-2 text-left text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:border-white/6 dark:bg-zinc-800 dark:text-gray-400`}
                                            >
                                                {row.label}
                                            </th>
                                            <td
                                                className={`${VALUE_W} ${TOTAL_LEFT} ${EDGE} ${Z_FROZEN} border-t border-black/6 bg-stone-100 dark:border-white/6 dark:bg-zinc-800`}
                                            />
                                            {products.map((p) => (
                                                <td
                                                    key={
                                                        p.product_id ??
                                                        'unresolved'
                                                    }
                                                    className={`${VALUE_W} border-t border-black/6 bg-stone-100 dark:border-white/6 dark:bg-zinc-800`}
                                                />
                                            ))}
                                            {products.length === 0 && (
                                                <td
                                                    className={`${VALUE_W} border-t border-black/6 bg-stone-100 dark:border-white/6 dark:bg-zinc-800`}
                                                />
                                            )}
                                        </tr>
                                    ) : (
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
                                                                        row.muted
                                                                            ? 'pl-3 text-gray-500 italic dark:text-gray-400'
                                                                            : row.emphasis
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

                                                        {(row.breakdown?.(total)
                                                            ?.length ?? 0) >
                                                            0 && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    toggleRow(
                                                                        row.label,
                                                                    )
                                                                }
                                                                aria-expanded={openRows.has(
                                                                    row.label,
                                                                )}
                                                                className="flex items-center gap-0.5 text-[10px] text-gray-400 underline-offset-2 transition-colors hover:text-gray-600 hover:underline dark:hover:text-gray-300"
                                                            >
                                                                {openRows.has(
                                                                    row.label,
                                                                ) ? (
                                                                    <ChevronDown className="h-3 w-3" />
                                                                ) : (
                                                                    <ChevronRight className="h-3 w-3" />
                                                                )}
                                                                {int(
                                                                    row.breakdown!(
                                                                        total,
                                                                    ).length,
                                                                )}{' '}
                                                                {row.breakdown!(
                                                                    total,
                                                                ).length === 1
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

                                            {/* What the row is made of, one sub-row
                                            per transaction type. The parts sum
                                            to the row above. */}
                                            {openRows.has(row.label) &&
                                                (
                                                    row.breakdown?.(total) ?? []
                                                ).map((line, i) => (
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
                                    ),
                                )}
                            </tbody>
                        </table>
                    </div>

                    {products.length === 0 && (
                        <div className="border-t border-black/6 px-5 py-8 text-center text-[12px] text-gray-400 dark:border-white/6">
                            Nothing delivered in {incomeStatement.label}.
                        </div>
                    )}
                </div>

                <p className="mt-3 text-[11px] text-gray-400">
                    The rows are the workspace statement&rsquo;s, in the same
                    order, so a figure reads the same on both. Delivered orders,
                    revenue, shipping and delivered COGS are this person&rsquo;s
                    own, straight off their orders. Bought COGS, its freight and
                    ad spend belong to the product as a whole, so a product they
                    share shows only their slice &mdash; by their share of its
                    delivered orders. Total covers the named products; the
                    Unresolved column sits outside it.
                </p>
            </div>
        </AppLayout>
    );
}
