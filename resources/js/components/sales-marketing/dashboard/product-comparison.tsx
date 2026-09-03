import { useMemo } from 'react';
import ComparisonPanel, {
    changeFrom,
    METRICS,
    sumComparisonRows,
    useComparisonMetric,
    useProductComparisonMetrics,
    type ComparisonBar,
} from './comparison-panel';
import {
    assignProductFills,
    OTHERS_FILL,
    productName,
    VISIBLE_PRODUCTS,
    type ProductRow,
} from './product-palette';
import {
    previousWindow,
    useSalesMarketingStat,
} from './use-sales-marketing-stat';

interface ProductComparison {
    rows: ProductRow[];
}

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
    const metrics = useProductComparisonMetrics();
    const [metric, chooseMetric] = useComparisonMetric(
        `sm_dashboard_product_metric_${slug}`,
        metrics,
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

    // Shared with the breakdown table below, so a product's bar and its swatch
    // are always the same colour.
    const fills = useMemo(
        () => assignProductFills(current.data?.rows ?? []),
        [current.data],
    );

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
                    name: productName(row),
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
            metrics={metrics}
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
