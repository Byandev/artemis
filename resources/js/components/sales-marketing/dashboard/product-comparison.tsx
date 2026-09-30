import { useMemo } from 'react';
import ComparisonPanel, {
    changeFrom,
    METRICS,
    useComparisonMetric,
    useProductComparisonMetrics,
    type ComparisonBar,
} from './comparison-panel';
import {
    assignProductFills,
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
 * Every product is plotted — nothing is folded into an "Others" bucket and
 * nothing is cut at a "top N". The chart comes to rest at VISIBLE_PRODUCTS rows
 * and scrolls to the rest, which keeps the panel a readable height without
 * deciding on your behalf which products are worth naming. The scale and the
 * average rule are taken across all of them, so scrolling reads on the same
 * footing as landing.
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

    const bars = useMemo<ComparisonBar[]>(() => {
        if (!current.data) return [];

        const spec = METRICS[metric];
        const before = new Map(
            (prior.data?.rows ?? []).map((r) => [r.product.id, r]),
        );

        // Nothing to plot for a product that did none of this metric in the
        // window — a zero-length bar states less than its absence does.
        return current.data.rows
            .map((row) => ({ row, value: spec.of(row) }))
            .filter((r) => r.value > 0)
            .sort((a, b) => b.value - a.value)
            .map(({ row, value }) => {
                const wasRow = before.get(row.product.id);
                const was = wasRow ? spec.of(wasRow) : null;

                return {
                    key: row.product.id,
                    name: productName(row),
                    value,
                    was,
                    change: changeFrom(value, was),
                    // Assigned off the sales ranking, which covers every row in
                    // this same answer — so there is always one to find.
                    fill: fills.get(row.product.id),
                };
            });
    }, [current.data, prior.data, metric, fills]);

    const firstLoad = (current.loading || prior.loading) && !current.data;

    return (
        <ComparisonPanel
            title="Product comparison"
            metric={metric}
            metrics={metrics}
            onMetric={chooseMetric}
            bars={bars}
            visibleRows={VISIBLE_PRODUCTS}
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
            // Says how much is below the fold, since the cut edge of the next
            // bar is the only other sign that the chart carries on.
            note={
                bars.length > VISIBLE_PRODUCTS
                    ? `${bars.length} products — scroll for the rest`
                    : undefined
            }
        />
    );
}
