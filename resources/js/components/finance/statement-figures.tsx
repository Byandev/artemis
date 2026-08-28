import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { HelpCircle } from 'lucide-react';
import { useState } from 'react';

/**
 * The figures a statement shares with its per-product and per-user slices,
 * shown as a single column since there is only one workspace to show.
 *
 * The wording matches the two table pages deliberately: the same figure should
 * read the same way whichever of the three you are looking at.
 */
export interface StatementFigureSet {
    delivered_orders: number;
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
    /** The other basis: a share of delivered revenue rather than of margin. */
    advisory_share_on_delivered: number;
    /** Outflow on transaction types marked OPEX. */
    opex: number;
    net_profit_delivered_cogs: number;
    net_profit_bought_cogs: number;
}

type CogsView = 'delivered' | 'bought';

const COGS_VIEWS: { value: CogsView; label: string }[] = [
    { value: 'delivered', label: 'Delivered COGS' },
    { value: 'bought', label: 'Bought COGS' },
];

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

const CARD =
    'rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900';

export default function StatementFigures({
    figures,
    rates,
    monthLabel,
    gencysPartner = false,
}: {
    figures: StatementFigureSet;
    rates: {
        cod: number;
        vat: number;
        advisory: number;
        advisoryDelivered: number;
    };
    monthLabel: string;
    /** The advisory share is only taken on partner workspaces. */
    gencysPartner?: boolean;
}) {
    const [cogsView, setCogsView] = useState<CogsView>('delivered');

    // The share can be struck off the margin or off delivered revenue, and the
    // agreement takes the lower — so what is stored is already the cheaper of
    // the two. Comparing it back against the delivered-basis figure says which
    // one that was, which is what the label needs.
    const advisoryCharged =
        cogsView === 'bought'
            ? figures.gross_profit_bought_cogs_advisory_share
            : figures.gross_profit_delivered_cogs_advisory_share;
    const afterAdvisory =
        cogsView === 'bought'
            ? figures.gross_profit_bought_cogs_after_advisory_share
            : figures.gross_profit_delivered_cogs_after_advisory_share;
    const netProfit =
        cogsView === 'bought'
            ? figures.net_profit_bought_cogs
            : figures.net_profit_delivered_cogs;
    const onDeliveredBasis =
        advisoryCharged > 0 &&
        advisoryCharged === figures.advisory_share_on_delivered;

    // Only partners are charged an advisory share.
    const advisoryRows = gencysPartner
        ? [
              {
                  label: onDeliveredBasis
                      ? `Advisory Share — ${pct(rates.advisoryDelivered)} of Delivered`
                      : `Advisory Share — ${pct(rates.advisory)} of Gross Profit`,
                  help: `Struck two ways — ${pct(rates.advisory)} of a positive Gross Profit, or ${pct(rates.advisoryDelivered)} of Delivered Amount — and the lower of the two is charged. This month that is the ${onDeliveredBasis ? 'delivered' : 'gross profit'} basis. A loss owes nothing.`,
                  value: fmt(advisoryCharged),
              },
              {
                  label: 'Gross Profit after Advisory',
                  help: 'Gross Profit less the advisory share above.',
                  value: fmt(afterAdvisory),
                  emphasis: true,
                  signed: afterAdvisory,
              },
          ]
        : [];

    // Operating expenses and what they leave behind. Nothing to do with the
    // advisory — these close out every statement, partner or not.
    const closingRows = [
        {
            label: 'Less — OPEX',
            help: 'The month’s operating expenses — outflow on transaction types marked OPEX on the income statement. Untyped outflow is left out: it isn’t marked as anything, and guessing it in would overstate expenses.',
            value: fmt(figures.opex),
        },
        {
            label: '= Net Profit',
            help: 'Gross Profit less the advisory share and the month’s OPEX. On a workspace with no advisory this is simply Gross Profit less OPEX.',
            value: fmt(netProfit),
            emphasis: true,
            signed: netProfit,
        },
    ];

    const rows: {
        label: string;
        help: string;
        value: string;
        emphasis?: boolean;
        signed?: number;
    }[] = [
        {
            label: 'Delivered Orders',
            help: 'Parcels delivered this month, by the date the parcel was marked delivered.',
            value: int(figures.delivered_orders),
        },
        {
            label: 'Delivered Amount',
            help: 'Revenue from those parcels.',
            value: fmt(figures.delivered_amount),
            emphasis: true,
        },
        {
            label: 'Shipped Orders',
            help: 'Parcels shipped out this month, by shipped-out date and whatever became of them afterwards. A different set from Delivered Orders — the two are not expected to agree.',
            value: int(figures.shipped_orders),
        },
        {
            label: 'Total Shipping Fee',
            help: 'The courier fee on those parcels. Charged when a parcel ships, so a return is paid for too.',
            value: fmt(figures.total_shipping_fee),
        },
        {
            label: 'Ad Spent',
            help: 'Every Ad Spent transaction this month, tagged or not — this is the company figure, so nothing is left out.',
            value: fmt(figures.ad_spent),
        },
        {
            label: 'COD Fee',
            help: `The courier's fee for collecting on delivery — ${pct(rates.cod)} of Delivered Amount, at the rate saved on this statement.`,
            value: fmt(figures.cod_fee),
        },
        {
            label: 'COD Fee VAT',
            help: `VAT on the COD fee — ${pct(rates.vat)} of the fee itself, not of the delivered amount.`,
            value: fmt(figures.cod_fee_vat),
        },
        ...(cogsView === 'bought'
            ? [
                  {
                      label: 'Bought COGS',
                      help: 'Every Cost of Goods purchase this month — stock bought, which is not the same as stock sold.',
                      value: fmt(figures.total_bought_cogs),
                      emphasis: true,
                  },
                  {
                      label: 'Bought COGS Delivery Fee',
                      help: 'Freight paid on those purchases, from “Delivery of COG” transactions.',
                      value: fmt(figures.total_bought_cogs_delivery_fee),
                  },
                  {
                      label: 'Gross Profit',
                      help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less what was bought into stock this month and the freight on it. What the month cost in cash, not the margin on what sold.',
                      value: fmt(figures.gross_profit_bought_cogs),
                      emphasis: true,
                      signed: figures.gross_profit_bought_cogs,
                  },
                  ...advisoryRows,
                  ...closingRows,
              ]
            : [
                  {
                      label: 'Delivered COGS',
                      help: 'Cost of the goods that actually shipped, summed from the orders’ own cost figures.',
                      value: fmt(figures.total_delivered_cogs),
                      emphasis: true,
                  },
                  {
                      label: 'Gross Profit',
                      help: 'Delivered Amount less ad spend, shipping, the COD fee and its VAT, then less the cost of the goods that actually shipped. The margin on what was sold this month.',
                      value: fmt(figures.gross_profit_delivered_cogs),
                      emphasis: true,
                      signed: figures.gross_profit_delivered_cogs,
                  },
                  ...advisoryRows,
                  ...closingRows,
              ]),
    ];

    return (
        <div className={`${CARD} mb-6 overflow-hidden`}>
            <div className="flex items-center justify-between border-b border-black/6 px-5 py-4 dark:border-white/6">
                <div>
                    <div className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                        Figures
                    </div>
                    <div className="mt-0.5 text-[11px] text-gray-400">
                        {monthLabel} · workspace-wide
                    </div>
                </div>
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
            </div>

            <div className="divide-y divide-black/5 dark:divide-white/5">
                {rows.map((r) => (
                    <div
                        key={r.label}
                        className="flex items-center justify-between px-5 py-2.5"
                    >
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    type="button"
                                    className="flex items-center gap-1.5 text-[12px] text-gray-600 transition-colors hover:text-gray-800 focus-visible:text-gray-800 focus-visible:outline-none dark:text-gray-300 dark:hover:text-gray-100"
                                >
                                    {r.label}
                                    <HelpCircle className="h-3 w-3 shrink-0 opacity-50" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent className="max-w-xs font-mono text-[11px] leading-relaxed">
                                {r.help}
                            </TooltipContent>
                        </Tooltip>
                        <span
                            className={`text-[13px] tabular-nums ${
                                r.signed !== undefined
                                    ? `font-semibold ${
                                          r.signed < 0
                                              ? 'text-rose-600 dark:text-rose-400'
                                              : 'text-emerald-600 dark:text-emerald-400'
                                      }`
                                    : r.emphasis
                                      ? 'font-medium text-gray-800 dark:text-gray-100'
                                      : 'text-gray-500 dark:text-gray-400'
                            }`}
                        >
                            {r.value}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}
