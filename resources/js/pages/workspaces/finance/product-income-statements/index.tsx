import PageHeader from '@/components/common/PageHeader';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, HelpCircle } from 'lucide-react';
import { useState } from 'react';

interface StatementContext {
    id: number;
    period_month: string; // YYYY-MM-DD
    month: string; // YYYY-MM
    label: string; // "July 2026"
}

/**
 * A saved product row. Goods bought this month are tracked apart from the cost
 * of the goods that actually shipped — in any one month the two rarely match.
 */
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
    gross_profit_bought_cogs: number;
}

interface MissingUnitCode {
    unit_code: string;
    orders: number;
}

interface Props {
    workspace: Workspace;
    incomeStatement: StatementContext;
    products: ProductRow[];
    total: ProductRow;
    /** The rates these rows were struck at, as fractions. */
    rates: { cod: number; vat: number };
    missingUnitCodes: MissingUnitCode[];
}

const fmt = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const int = (v: number) => Number(v).toLocaleString('en-PH');

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
    },
    cogsView: CogsView,
): {
    label: string;
    help: string;
    render: (r: ProductRow) => string;
    emphasis?: boolean;
    /** When set, the cell is coloured by the sign of this value. */
    signed?: (r: ProductRow) => number;
}[] => [
    {
        label: 'Delivered Orders',
        help: 'Parcels delivered this month that contained this product. A parcel holding two products counts once for each, so this column can add up to more than the month’s parcels.',
        render: (r) => int(r.delivered_orders),
    },
    {
        label: 'Delivered Units',
        help: 'Pieces of this product delivered this month, from the line-item quantities. Three of one product in a single parcel is one order but three units — this is the figure stock is drawn down by.',
        render: (r) => int(r.delivered_units),
    },
    {
        label: 'Delivered Amount',
        help: 'Revenue from those parcels. A parcel carrying several products has its value divided between them by item quantity, so nothing is counted twice.',
        render: (r) => fmt(r.delivered_amount),
        emphasis: true,
    },
    {
        label: 'Ad Spent',
        help: 'Ad Spent transactions tagged to this product this month. Only tagged shares count — ad spend nobody attributed to a product is left out rather than spread across them on a guess.',
        render: (r) => fmt(r.ad_spent),
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
                  help: 'Cost of Goods purchases tagged to this product this month — stock bought, which is not the same as stock sold.',
                  render: (r: ProductRow) => fmt(r.total_bought_cogs),
                  emphasis: true,
              },
              {
                  label: 'Bought COGS Delivery Fee',
                  help: 'Freight paid on those purchases, from “Delivery of COG” transactions tagged to this product.',
                  render: (r: ProductRow) =>
                      fmt(r.total_bought_cogs_delivery_fee),
              },
              {
                  label: 'Gross Profit',
                  help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less what was bought into stock this month and the freight on it. What the month cost in cash, not the margin on what sold.',
                  render: (r: ProductRow) => fmt(r.gross_profit_bought_cogs),
                  emphasis: true,
                  signed: (r: ProductRow) => r.gross_profit_bought_cogs,
              },
          ]
        : [
              {
                  label: 'Delivered COGS',
                  help: 'Cost of the goods that actually shipped, taken from the orders’ own cost figures and divided across a multi-product parcel the same way revenue is.',
                  render: (r: ProductRow) => fmt(r.total_delivered_cogs),
                  emphasis: true,
              },
              {
                  label: 'Gross Profit',
                  help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less the cost of the goods that actually shipped. The margin on what was sold this month.',
                  render: (r: ProductRow) => fmt(r.gross_profit_delivered_cogs),
                  emphasis: true,
                  signed: (r: ProductRow) => r.gross_profit_delivered_cogs,
              },
          ]),
];

export default function ProductIncomeStatements({
    workspace,
    incomeStatement,
    products,
    total,
    rates,
    missingUnitCodes,
}: Props) {
    const finance = `/workspaces/${workspace.slug}/finance`;
    const [cogsView, setCogsView] = useState<CogsView>('delivered');
    const COLUMNS = buildColumns(rates, cogsView);
    const named = products.filter((p) => p.product_id !== null);

    // The cost of what shipped against what was bought — the gap is inventory
    // moving in or out of stock, not profit.
    const boughtAll =
        total.total_bought_cogs + total.total_bought_cogs_delivery_fee;

    return (
        <AppLayout>
            <Head
                title={`${workspace.name} - Product Statement ${incomeStatement.label}`}
            />
            <div className="w-full p-4 font-mono md:p-6">
                <PageHeader
                    title="Product Statement"
                    description={`${incomeStatement.label} · every product, across all interns`}
                >
                    <Link
                        href={`${finance}/income-statements/${incomeStatement.id}`}
                        className={BTN}
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back to statement
                    </Link>
                </PageHeader>

                {/* Unit codes with no product behind them — the items using
                    them can't be attributed, so they land in Unresolved. */}
                {missingUnitCodes.length > 0 && (
                    <div className="mb-6 rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 dark:border-amber-800/60 dark:bg-amber-950/40">
                        <div className="flex items-center gap-2 text-[12px] text-amber-800 dark:text-amber-200">
                            <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                            {int(missingUnitCodes.length)} unit code
                            {missingUnitCodes.length === 1 ? '' : 's'} on this
                            month&rsquo;s delivered orders map to no product.{' '}
                            <Link
                                href={`/workspaces/${workspace.slug}/gencys/unit-codes`}
                                className="underline underline-offset-2"
                            >
                                Map them
                            </Link>{' '}
                            to clear the Unresolved row.
                        </div>
                    </div>
                )}

                <div className={`${CARD} overflow-hidden`}>
                    <div className="flex items-center justify-between border-b border-black/6 px-5 py-4 dark:border-white/6">
                        <div>
                            <div className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                                Per-product figures
                            </div>
                            <div className="mt-0.5 text-[11px] text-gray-400">
                                {incomeStatement.label} · {int(named.length)}{' '}
                                {named.length === 1 ? 'product' : 'products'}
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
                                        Product
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
                                {products.length === 0 && (
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

                                {products.map((r) => {
                                    const unresolved = r.product_id === null;
                                    const tone = unresolved
                                        ? 'text-amber-700 dark:text-amber-400'
                                        : 'text-gray-800 dark:text-gray-100';
                                    return (
                                        <tr
                                            key={r.product_id ?? 'unresolved'}
                                            className={
                                                unresolved
                                                    ? 'group bg-amber-50/70 dark:bg-amber-500/10'
                                                    : 'group transition-colors hover:bg-stone-50 dark:hover:bg-zinc-800/40'
                                            }
                                        >
                                            <td
                                                className={`${FROZEN} py-3 pr-4 pl-5 ${
                                                    unresolved
                                                        ? 'bg-amber-50 dark:bg-amber-950'
                                                        : 'bg-white group-hover:bg-stone-50 dark:bg-zinc-900 dark:group-hover:bg-zinc-800'
                                                }`}
                                            >
                                                <div className="flex items-center gap-2">
                                                    {unresolved && (
                                                        <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-amber-500" />
                                                    )}
                                                    <span className="min-w-0">
                                                        <span
                                                            className={`block truncate text-[13px] font-medium ${tone}`}
                                                        >
                                                            {r.product}
                                                        </span>
                                                        {unresolved && (
                                                            <span className="text-[10px] text-gray-400">
                                                                items that
                                                                resolve to no
                                                                product
                                                            </span>
                                                        )}
                                                    </span>
                                                </div>
                                            </td>
                                            {COLUMNS.map((c, i) => (
                                                <td
                                                    key={c.label}
                                                    className={`${COL} ${
                                                        i === COLUMNS.length - 1
                                                            ? 'pr-5'
                                                            : ''
                                                    }${
                                                        c.signed
                                                            ? `font-semibold ${
                                                                  c.signed(r) <
                                                                  0
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
                    Every figure is the product&rsquo;s own, across all interns
                    &mdash; hover a column heading for what it counts. Bought{' '}
                    {fmt(boughtAll)} against {fmt(total.total_delivered_cogs)}{' '}
                    delivered this month; the gap is stock moving in or out of
                    the warehouse, not profit. The Total excludes the Unresolved
                    row.
                </p>
            </div>
        </AppLayout>
    );
}
