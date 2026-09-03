import { useMemo } from 'react';
import ComparisonPanel, {
    changeFrom,
    METRICS,
    sumComparisonRows,
    useComparisonMetric,
    type ComparisonBar,
    type ComparisonSums,
} from './comparison-panel';
import {
    previousWindow,
    useSalesMarketingStat,
} from './use-sales-marketing-stat';

/** One product's raw sums for a window, exactly as the endpoint answers. */
interface ProductRow extends ComparisonSums {
    product: { id: number; name: string | null };
}

interface ProductComparison {
    rows: ProductRow[];
}

/**
 * Categorical fills, one per plotted product, in fixed order — never cycled,
 * which is why the list ends where the folding begins.
 *
 * Both columns are validated as a set against their own surface (lightness
 * band, chroma floor, colour-vision separation and contrast); the light steps
 * sit under 3:1 on white, which is allowed here because every bar carries its
 * product's name and figure beside it, so nothing rests on colour alone.
 */
const PRODUCT_FILLS = [
    { light: '#2a78d6', dark: '#3987e5' }, // blue
    { light: '#eb6834', dark: '#d95926' }, // orange
    { light: '#1baf7a', dark: '#199e70' }, // aqua
    { light: '#eda100', dark: '#c98500' }, // yellow
    { light: '#e87ba4', dark: '#d55181' }, // magenta
    { light: '#008300', dark: '#008300' }, // green
    { light: '#4a3aa7', dark: '#9085e9' }, // violet
];

/**
 * The fold-in bar is deliberately grey: it is a remainder, not a product, and a
 * neutral says so where an eighth hue would claim it was one more of the same.
 */
const OTHERS_FILL = { light: '#9ca3af', dark: '#71717a' };

/** How many products get a bar of their own before the rest fold into one. */
const VISIBLE_PRODUCTS = PRODUCT_FILLS.length;

/**
 * Product comparison: every product on one scale for the selected metric,
 * against the same window last period.
 *
 * Unlike the team panel this one gives each product its own colour, because the
 * colour is what lets you follow a product across the metric switch — flip from
 * Sales to ROAS and the bars reorder, but the blue one is still the blue one.
 * That only works if the hue belongs to the product rather than to its rank, so
 * it is assigned once off the sales ranking and left alone while the metric
 * changes.
 *
 * Everything past the palette folds into a single "Others" bar. Folding is a sum
 * of the raw rows, not of the metric, so the bucket's ROAS and RTS are real
 * ratios of real totals rather than averages of averages.
 */
export default function ProductComparison({
    slug,
    dateRange,
}: {
    slug: string;
    /** `[start, end]` as YYYY-MM-DD. */
    dateRange: string[];
}) {
    const [metric, chooseMetric] = useComparisonMetric(
        `sm_dashboard_product_metric_${slug}`,
    );

    const previous = useMemo(() => previousWindow(dateRange), [dateRange]);

    const current = useSalesMarketingStat<ProductComparison>(
        slug,
        'product-comparison',
        { start: dateRange[0], end: dateRange[1] },
    );
    const prior = useSalesMarketingStat<ProductComparison>(
        slug,
        'product-comparison',
        { start: previous[0], end: previous[1] },
    );

    /**
     * Colour per product id, fixed for as long as the window's data is. Ranked
     * by sales rather than by the selected metric so switching metric reorders
     * the bars without repainting them.
     */
    const fills = useMemo(() => {
        const byProduct = new Map<number, (typeof PRODUCT_FILLS)[number]>();

        [...(current.data?.rows ?? [])]
            .sort((a, b) => b.sales - a.sales)
            .slice(0, VISIBLE_PRODUCTS)
            .forEach((row, i) =>
                byProduct.set(row.product.id, PRODUCT_FILLS[i]),
            );

        return byProduct;
    }, [current.data]);

    const { bars, total } = useMemo(() => {
        if (!current.data) return { bars: [] as ComparisonBar[], total: 0 };

        const spec = METRICS[metric];
        const before = new Map(
            (prior.data?.rows ?? []).map((r) => [r.product.id, r]),
        );

        const ranked = current.data.rows
            .map((row) => ({ row, value: spec.of(row) }))
            .filter((r) => r.value > 0)
            .sort((a, b) => b.value - a.value);

        const bars: ComparisonBar[] = ranked
            .slice(0, VISIBLE_PRODUCTS)
            .map(({ row, value }) => {
                const wasRow = before.get(row.product.id);
                const was = wasRow ? spec.of(wasRow) : null;

                return {
                    key: row.product.id,
                    name: row.product.name?.trim() || 'Unnamed product',
                    value,
                    was,
                    change: changeFrom(value, was),
                    // A product with more peers than the palette has hues can
                    // land outside it; grey is then the honest answer.
                    fill: fills.get(row.product.id) ?? OTHERS_FILL,
                };
            });

        // Everything else, added up as raw sums and only then turned into the
        // metric — the bucket's ROAS is its sales over its spend, not a mean.
        const rest = ranked.slice(VISIBLE_PRODUCTS).map((r) => r.row);

        if (rest.length) {
            const value = spec.of(sumComparisonRows(rest));
            const priorRest = rest
                .map((row) => before.get(row.product.id))
                .filter((row): row is ProductRow => row !== undefined);
            // Compared against the same products' figures last period, so the
            // bucket's delta is about those products rather than about which
            // products happened to fall into it.
            const was = priorRest.length
                ? spec.of(sumComparisonRows(priorRest))
                : null;

            bars.push({
                key: 'others',
                name: 'Others',
                value,
                was,
                change: changeFrom(value, was),
                fill: OTHERS_FILL,
            });
        }

        return { bars, total: ranked.length };
    }, [current.data, prior.data, metric, fills]);

    const firstLoad = (current.loading || prior.loading) && !current.data;

    return (
        <ComparisonPanel
            title="Product comparison"
            metric={metric}
            onMetric={chooseMetric}
            bars={bars}
            previous={previous}
            loading={current.loading || prior.loading}
            error={current.error || prior.error}
            firstLoad={firstLoad}
            // Both windows are refetched together — the comparison is
            // meaningless if one half is stale.
            onRefresh={() => {
                current.refetch();
                prior.refetch();
            }}
            refreshLabel="the product comparison"
            emptyHint="no product performance in this period"
            // Says what the grey bar stands for, since it is the one bar whose
            // name does not name a thing.
            note={
                total > VISIBLE_PRODUCTS
                    ? `others = ${total - VISIBLE_PRODUCTS} more products`
                    : undefined
            }
        />
    );
}
