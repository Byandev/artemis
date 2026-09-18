import RefreshButton from '@/components/inventory/dashboard/refresh-button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { useMemo } from 'react';
import { sumComparisonRows, useProductSpendShown } from './comparison-panel';
import { formatKpi } from './kpi-card';
import {
    assignProductFills,
    productName,
    VISIBLE_PRODUCTS,
    type Fill,
    type ProductRow,
} from './product-palette';
import { useSalesMarketingStat } from './use-sales-marketing-stat';

interface Breakdown {
    rows: ProductRow[];
}

/**
 * Where a figure stops reading as healthy. Both are house rules rather than
 * anything the data implies, and they are the same two the team breakdown
 * calls out — a ROAS under 3× and an RTS over 20%.
 */
const ROAS_TARGET = 3;
const RTS_CEILING = 0.2;

/**
 * Row height in rem, fixed so the scroll cap can be stated in rows rather than
 * guessed in pixels. Header and footer are the same height, hence the +2.
 */
const ROW_H = 2.5;

/** Sticky cells need an opaque background, or scrolled rows show through. */
const STICKY_BG = 'bg-white dark:bg-zinc-900';

/**
 * The per-product table beneath the comparison chart: the same rows, stated
 * rather than plotted, with a sub-total.
 *
 * Every product is listed — nothing folds into an "Others" row. The table rests
 * at VISIBLE_PRODUCTS rows, the same depth the chart rests at, and scrolls to
 * the rest under a pinned header and a pinned sub-total, so the figure you are
 * reading always has its column name above it and the total below it.
 *
 * Each row carries the product's colour as a swatch, so the row you are reading
 * here is visibly the bar you were reading up there. Ranking is by sales — the
 * ordering the colours are assigned on — rather than a column of its own
 * choosing, so the two panels list the same products in the same order.
 *
 * Every derived figure — ROAS, RTS rate, and the whole sub-total row — is worked
 * out here from the raw sums. The sub-total's ROAS and RTS are blended (totals
 * divided), not means of the columns above: averaging a catalogue's ratios would
 * weight a product with 3,800 orders the same as one with 16,000.
 */
export default function ProductBreakdown({
    slug,
    dateRange,
}: {
    slug: string;
    /** `[start, end]` as YYYY-MM-DD. */
    dateRange: string[];
}) {
    // Gencys partners have no per-product spend to state, so the two columns
    // derived from it come out — the same rule that trims the chart's switcher.
    const spendShown = useProductSpendShown();

    const { data, loading, error, refetch } = useSalesMarketingStat<Breakdown>(
        slug,
        'product-breakdown',
        { start: dateRange[0], end: dateRange[1] },
    );

    const rows = useMemo(() => {
        const all = data?.rows ?? [];
        const fills = assignProductFills(all);

        return [...all]
            .sort((a, b) => b.sales - a.sales)
            .map((row) => ({
                key: String(row.product.id),
                name: productName(row),
                // Assigned off the same sales ranking these rows are in, so
                // every product has one.
                fill: fills.get(row.product.id),
                ...derive(row),
            }));
    }, [data]);

    // Summed off every raw row — the same set the table states, so the
    // sub-total covers what is scrolled past as well as what is in view — and
    // ratios derived after the sum, so its ROAS is total sales over total spend.
    const total = useMemo(
        () => derive(sumComparisonRows(data?.rows ?? [])),
        [data],
    );

    // Skeleton until the first answer; a later refetch dims rather than flashing.
    const firstLoad = loading && !data;

    return (
        <section className="mt-10">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-[11px] font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                    Product breakdown
                </h2>
                <RefreshButton
                    onClick={refetch}
                    loading={loading}
                    error={error}
                    label="the product breakdown"
                />
            </div>

            <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                {firstLoad ? (
                    <div className="space-y-3 p-[18px]">
                        {Array.from({ length: 6 }).map((_, i) => (
                            <Skeleton key={i} className="h-5 w-full" />
                        ))}
                    </div>
                ) : error ? (
                    <p className="p-[18px] text-[11px] text-red-500 dark:text-red-400">
                        Failed to load — retry with the refresh button above.
                    </p>
                ) : rows.length === 0 ? (
                    <p className="p-[18px] text-[11px] text-gray-400 dark:text-gray-500">
                        no product performance in this period
                    </p>
                ) : (
                    // Scrolls in its own container both ways: wider than the
                    // card on small screens, and taller than VISIBLE_PRODUCTS
                    // once the catalogue grows. Rows are a fixed height, so the
                    // cap is exactly that many of them plus the pinned header
                    // and sub-total — and the next row is cut at the fold,
                    // which is the table's own hint that it carries on.
                    <div
                        className={cn(
                            'custom-scrollbar overflow-auto rounded-[14px] transition-opacity',
                            loading && 'opacity-50',
                        )}
                        style={{
                            maxHeight: `${(VISIBLE_PRODUCTS + 2) * ROW_H}rem`,
                        }}
                    >
                        {/* border-separate, not collapse: a collapsed table
                            drops the borders of sticky cells as they move. */}
                        <table
                            className={cn(
                                'w-full border-separate border-spacing-0 text-[12px]',
                                spendShown ? 'min-w-[52rem]' : 'min-w-[38rem]',
                            )}
                        >
                            <thead>
                                <tr>
                                    <Th align="left" sticky>
                                        Product
                                    </Th>
                                    <Th>Orders</Th>
                                    <Th>Sales</Th>
                                    {spendShown && (
                                        <>
                                            <Th>Ad spend</Th>
                                            <Th>ROAS</Th>
                                        </>
                                    )}
                                    <Th>RTS rate</Th>
                                    <Th>RTS amount</Th>
                                </tr>
                            </thead>

                            <tbody>
                                {rows.map((r) => (
                                    <tr key={r.key}>
                                        <Td align="left" sticky>
                                            <span
                                                className="flex items-center gap-2"
                                                title={r.name}
                                            >
                                                <Swatch fill={r.fill} />
                                                <span className="truncate font-mono text-gray-700 uppercase dark:text-gray-200">
                                                    {r.name}
                                                </span>
                                            </span>
                                        </Td>
                                        <Td>{formatKpi(r.orders, 'number')}</Td>
                                        <Td>
                                            {formatKpi(
                                                r.sales,
                                                'currencyExact',
                                            )}
                                        </Td>
                                        {spendShown && (
                                            <>
                                                <Td>
                                                    {formatKpi(
                                                        r.adSpend,
                                                        'currencyExact',
                                                    )}
                                                </Td>
                                                {/* Colour is emphasis, never
                                                    the only carrier — the
                                                    figure itself is readable
                                                    without it. */}
                                                <Td
                                                    className={
                                                        r.roas === null
                                                            ? undefined
                                                            : r.roas >=
                                                                ROAS_TARGET
                                                              ? 'font-semibold text-emerald-600 dark:text-emerald-400'
                                                              : 'font-semibold text-red-600 dark:text-red-400'
                                                    }
                                                >
                                                    {r.roas === null
                                                        ? '—'
                                                        : formatKpi(
                                                              r.roas,
                                                              'ratio',
                                                          )}
                                                </Td>
                                            </>
                                        )}
                                        <Td
                                            className={
                                                r.rts !== null &&
                                                r.rts > RTS_CEILING
                                                    ? 'font-semibold text-red-600 dark:text-red-400'
                                                    : undefined
                                            }
                                        >
                                            {r.rts === null
                                                ? '—'
                                                : formatKpi(r.rts, 'percent')}
                                        </Td>
                                        <Td className="text-gray-500 dark:text-gray-400">
                                            {formatKpi(
                                                r.returned,
                                                'currencyExact',
                                            )}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>

                            <tfoot>
                                <tr className="font-semibold italic">
                                    <Td align="left" sticky foot>
                                        Sub-Total
                                    </Td>
                                    <Td foot>
                                        {formatKpi(total.orders, 'number')}
                                    </Td>
                                    <Td foot>
                                        {formatKpi(
                                            total.sales,
                                            'currencyExact',
                                        )}
                                    </Td>
                                    {spendShown && (
                                        <>
                                            <Td foot>
                                                {formatKpi(
                                                    total.adSpend,
                                                    'currencyExact',
                                                )}
                                            </Td>
                                            <Td foot>
                                                {total.roas === null
                                                    ? '—'
                                                    : formatKpi(
                                                          total.roas,
                                                          'ratio',
                                                      )}
                                            </Td>
                                        </>
                                    )}
                                    <Td foot>
                                        {total.rts === null
                                            ? '—'
                                            : formatKpi(total.rts, 'percent')}
                                    </Td>
                                    <Td foot>
                                        {formatKpi(
                                            total.returned,
                                            'currencyExact',
                                        )}
                                    </Td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </div>

            {/* Says how much is below the fold, since the cut edge of the next
                row is the only other sign that the table carries on. */}
            {rows.length > VISIBLE_PRODUCTS && (
                <p className="mt-2 text-right text-[11px] text-gray-400 dark:text-gray-500">
                    {rows.length} products — scroll for the rest
                </p>
            )}
        </section>
    );
}

/**
 * The figures a row states, derived from raw sums. Ratios are null where there
 * is nothing to divide by — a ROAS on no spend is not zero, it does not apply,
 * and the table says so with an em dash rather than a misleading 0.00.
 */
function derive(row: {
    ad_spend: number;
    sales: number;
    orders: number;
    returned_amount: number;
    delivered_amount: number;
}) {
    const outcomes = row.returned_amount + row.delivered_amount;

    return {
        orders: row.orders,
        sales: row.sales,
        adSpend: row.ad_spend,
        returned: row.returned_amount,
        roas: row.ad_spend > 0 ? row.sales / row.ad_spend : null,
        rts: outcomes > 0 ? row.returned_amount / outcomes : null,
    };
}

/**
 * The product's colour, as it appears on its bar in the chart above. Decorative
 * — the name sits right beside it — so it is hidden from assistive tech.
 */
function Swatch({ fill }: { fill?: Fill }) {
    if (!fill) return null;

    return (
        <span
            aria-hidden
            className="size-2.5 shrink-0 rounded-[3px] bg-[var(--swatch)] dark:bg-[var(--swatch-dark)]"
            style={
                {
                    '--swatch': fill.light,
                    '--swatch-dark': fill.dark,
                } as React.CSSProperties
            }
        />
    );
}

/**
 * Header cell, pinned to the top of the scroll container. The first column is
 * pinned left as well, so it outranks the others where the two overlap.
 */
function Th({
    children,
    align = 'right',
    sticky,
}: {
    children: React.ReactNode;
    align?: 'left' | 'right';
    /** Pin this column horizontally — the product name. */
    sticky?: boolean;
}) {
    return (
        <th
            scope="col"
            style={{ height: `${ROW_H}rem` }}
            className={cn(
                'sticky top-0 z-20 border-b border-black/6 px-4 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:border-white/6 dark:text-gray-500',
                STICKY_BG,
                align === 'left' ? 'text-left' : 'text-right',
                sticky &&
                    'left-0 z-30 border-r border-black/6 dark:border-white/6',
            )}
        >
            {children}
        </th>
    );
}

function Td({
    children,
    align = 'right',
    className,
    sticky,
    foot,
}: {
    children: React.ReactNode;
    align?: 'left' | 'right';
    className?: string;
    /** Pin this column horizontally — the product name. */
    sticky?: boolean;
    /** A sub-total cell: pinned to the bottom so it survives scrolling. */
    foot?: boolean;
}) {
    return (
        <td
            style={{ height: `${ROW_H}rem` }}
            className={cn(
                'px-4 font-mono text-gray-700 tabular-nums dark:text-gray-200',
                foot
                    ? cn(
                          'sticky bottom-0 z-20 border-t border-black/8 dark:border-white/10',
                          STICKY_BG,
                      )
                    : 'border-b border-black/4 dark:border-white/4',
                align === 'left' ? 'text-left' : 'text-right',
                sticky &&
                    cn(
                        'sticky left-0 w-56 max-w-56 border-r border-black/6 dark:border-white/6',
                        STICKY_BG,
                        foot ? 'z-30' : 'z-10',
                    ),
                className,
            )}
        >
            {children}
        </td>
    );
}
