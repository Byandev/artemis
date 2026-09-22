import { useMemo } from 'react';
import ComparisonPanel, {
    changeFrom,
    METRICS,
    useComparisonMetric,
    type ComparisonBar,
    type ComparisonSums,
} from './comparison-panel';
import {
    previousWindow,
    useSalesMarketingStat,
} from './use-sales-marketing-stat';

/** One advertiser's raw sums for a window, exactly as the endpoint answers. */
interface TeamRow extends ComparisonSums {
    advertiser: { id: number; name: string | null };
}

interface TeamComparison {
    rows: TeamRow[];
}

/** How many members stand at rest before the chart scrolls. */
const VISIBLE_ROWS = 8;

/**
 * Team member comparison: every advertiser on one scale for the selected
 * metric, against the same window last period.
 *
 * One series, so one colour — the names are already labelled down the side, and
 * a hue per member would encode nothing the bar length doesn't. The tick on each
 * bar is that member's previous-period figure, and the dashed rule is the
 * average across everyone.
 *
 * Every member is plotted — nothing is cut at a "top N". The chart comes to rest
 * at VISIBLE_ROWS rows and scrolls to the rest, which keeps the panel a readable
 * height without deciding on your behalf whose figures are worth naming. The
 * scale and the average rule are taken across all of them, so scrolling reads on
 * the same footing as landing.
 */
export default function TeamComparison({
    slug,
    dateRange,
}: {
    slug: string;
    /** `[start, end]` as YYYY-MM-DD. */
    dateRange: string[];
}) {
    const [metric, chooseMetric] = useComparisonMetric(
        `sm_dashboard_team_metric_${slug}`,
    );

    const previous = useMemo(() => previousWindow(dateRange), [dateRange]);

    const current = useSalesMarketingStat<TeamComparison>(
        slug,
        'team-comparison',
        { start: dateRange[0], end: dateRange[1] },
    );
    const prior = useSalesMarketingStat<TeamComparison>(
        slug,
        'team-comparison',
        { start: previous[0], end: previous[1] },
    );

    const bars = useMemo<ComparisonBar[]>(() => {
        if (!current.data) return [];

        const spec = METRICS[metric];
        const before = new Map(
            (prior.data?.rows ?? []).map((r) => [r.advertiser.id, spec.of(r)]),
        );

        // Nothing to plot for a member who did none of this metric in the
        // window — a zero-length bar states less than its absence does.
        return current.data.rows
            .map((row) => {
                const value = spec.of(row);
                const was = before.get(row.advertiser.id) ?? null;

                return {
                    key: row.advertiser.id,
                    name: row.advertiser.name?.trim() || 'Unnamed advertiser',
                    value,
                    was,
                    change: changeFrom(value, was),
                };
            })
            .filter((r) => r.value > 0)
            .sort((a, b) => b.value - a.value);
    }, [current.data, prior.data, metric]);

    const firstLoad = (current.loading || prior.loading) && !current.data;

    return (
        <ComparisonPanel
            title="Team member comparison"
            metric={metric}
            onMetric={chooseMetric}
            bars={bars}
            visibleRows={VISIBLE_ROWS}
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
            refreshLabel="the team comparison"
            emptyHint="nothing to compare in this period"
            // Says how much is below the fold, since the cut edge of the next
            // bar is the only other sign that the chart carries on.
            note={
                bars.length > VISIBLE_ROWS
                    ? `${bars.length} members — scroll for the rest`
                    : undefined
            }
        />
    );
}
