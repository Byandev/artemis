import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import clsx from 'clsx';
import { Check, ChevronDown, Loader2, Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    INSIGHTS_OPTIONS,
    InsightsMetrics,
    formatMetricNumber,
    metricIsMoney,
    metricIsRatio,
    metricLabel,
    metricValue,
} from './_shared';

/** One day of raw insight columns, as the timeseries endpoint returns them. */
type TimeseriesPoint = InsightsMetrics & { date: string };

export interface TimelineTarget {
    /** The breakdown the row came from — becomes `scope_by`. */
    scopeBy: string;
    /** The row's id, or its name for the ad_name breakdown — becomes `scope`. */
    scope: string;
    label: string;
}

/**
 * Metrics the picker starts on. Spend alone by default: one line reads cleanly,
 * and the picker is right there to add the rest.
 */
const DEFAULT_METRICS = ['spend'];

/**
 * A panel per metric, each on its own scale. Metrics here span wildly different
 * magnitudes (impressions in the millions, ROAS around 3), and two y-scales on
 * one plot make the crossings meaningless — so extra metrics become small
 * multiples rather than a second axis.
 */
const PANEL_HEIGHT = 168;
const SOLO_HEIGHT = 260;
const OVERLAY_HEIGHT = 300;

/**
 * Past four lines a plot stops being readable and the palette runs out of
 * separable hues, so a wider selection falls back to small multiples.
 */
const MAX_OVERLAY = 4;

/**
 * Series hues, assigned in fixed order (never cycled). Both the light and dark
 * sets clear the validator on all pairs: lightness band, chroma floor, CVD
 * separation, and >= 3:1 against their own surface. Blue and violet are the
 * weakest pair under tritanopia, which is why every series is also named in the
 * legend and the hover card — identity never rests on colour alone.
 */
const SERIES_COLORS = [
    'var(--tl-s1)',
    'var(--tl-s2)',
    'var(--tl-s3)',
    'var(--tl-s4)',
];

/**
 * A metric's unit family. Two metrics can share one y-axis only if they measure
 * in the same unit — pesos against a ratio on a single scale is meaningless.
 */
function familyOf(metric: string): 'money' | 'ratio' | 'count' {
    if (metricIsMoney(metric)) return 'money';

    return metricIsRatio(metric) ? 'ratio' : 'count';
}

const compactFmt = new Intl.NumberFormat(undefined, {
    notation: 'compact',
    maximumFractionDigits: 1,
});

/**
 * The grid's formatters render 0 as an em-dash, which reads as "no data here".
 * On a timeline a zero day IS data — the endpoint fills every day in the range
 * precisely so a gap shows — so zeroes stay numeric.
 */
function formatValue(metric: string, value: number): string {
    return value === 0 ? '0' : formatMetricNumber(metric, value);
}

/**
 * Axis ticks get the compacted form: a full "₱1,234.56" is wider than the whole
 * axis gutter, and stacked ticks would collide. Ratios are already short.
 */
function axisTick(metric: string, value: number): string {
    if (value === 0) return '0';
    if (metricIsRatio(metric)) return formatMetricNumber(metric, value);

    return (metricIsMoney(metric) ? '₱' : '') + compactFmt.format(value);
}

/** Short axis date — "Aug 28" — from the endpoint's ISO day. */
function shortDate(iso: string): string {
    const d = new Date(`${iso}T00:00:00`);

    return isNaN(d.getTime())
        ? iso
        : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function longDate(iso: string): string {
    const d = new Date(`${iso}T00:00:00`);

    return isNaN(d.getTime())
        ? iso
        : d.toLocaleDateString(undefined, {
              weekday: 'short',
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          });
}

function TimelineTooltip({
    active,
    payload,
    metric,
}: {
    active?: boolean;
    payload?: { payload: { date: string; value: number } }[];
    metric: string;
}) {
    const point = active ? payload?.[0]?.payload : undefined;
    if (!point) return null;

    return (
        <div className="rounded-xl border border-black/7 bg-white px-3.5 py-2.5 font-mono text-[11px] shadow-lg dark:border-white/10 dark:bg-zinc-900">
            <p className="mb-1.5 text-[10px] tracking-wide text-gray-400 dark:text-gray-500">
                {longDate(point.date)}
            </p>
            <div className="flex items-center justify-between gap-6">
                <span className="flex items-center gap-1.5 text-gray-500 dark:text-gray-400">
                    <span className="h-2 w-2 rounded-full bg-[var(--tl-accent)]" />
                    {metricLabel(metric)}
                </span>
                <span className="text-gray-800 tabular-nums dark:text-gray-100">
                    {formatValue(metric, point.value)}
                </span>
            </div>
        </div>
    );
}

/**
 * Shared hover card for the overlay. Always reports the REAL value, never the
 * indexed one, and names every series — so a reader who can't separate two
 * hues still gets the numbers.
 */
function OverlayTooltip({
    active,
    payload,
    metrics,
}: {
    active?: boolean;
    payload?: { payload: Record<string, number | string> }[];
    metrics: string[];
}) {
    const row = active ? payload?.[0]?.payload : undefined;
    if (!row) return null;

    return (
        <div className="rounded-xl border border-black/7 bg-white px-3.5 py-2.5 font-mono text-[11px] shadow-lg dark:border-white/10 dark:bg-zinc-900">
            <p className="mb-1.5 text-[10px] tracking-wide text-gray-400 dark:text-gray-500">
                {longDate(String(row.date))}
            </p>
            <ul className="space-y-1">
                {metrics.map((m, i) => (
                    <li
                        key={m}
                        className="flex items-center justify-between gap-6"
                    >
                        <span className="flex items-center gap-1.5 text-gray-500 dark:text-gray-400">
                            <span
                                className="h-2 w-2 rounded-full"
                                style={{ background: SERIES_COLORS[i] }}
                            />
                            {metricLabel(m)}
                        </span>
                        <span className="text-gray-800 tabular-nums dark:text-gray-100">
                            {formatValue(m, Number(row[`raw:${m}`] ?? 0))}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * One metric's line over the range. A single series per panel, so the panel
 * heading carries identity and no legend is needed.
 */
function MetricPanel({
    metric,
    points,
    height,
}: {
    metric: string;
    points: TimeseriesPoint[];
    height: number;
}) {
    const data = points.map((p) => ({
        date: p.date,
        value: metricValue(p, metric),
    }));

    const total = data.reduce((sum, d) => sum + d.value, 0);
    const peak = data.reduce((max, d) => Math.max(max, d.value), 0);
    // Ratio metrics (ROAS, CTR, rates) don't add across days — a summed
    // percentage is meaningless — so those panels report a daily average and
    // the countable ones report a total.
    const ratio = metricIsRatio(metric);
    const summary = ratio
        ? `${formatValue(metric, data.length ? total / data.length : 0)} daily avg`
        : `${formatValue(metric, total)} total`;
    const gradientId = `tl-fill-${metric}`;

    return (
        <div className="rounded-[12px] border border-black/6 bg-white p-3 dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-1 flex items-baseline justify-between gap-3">
                <span className="font-mono text-[11px] font-medium text-gray-700 dark:text-gray-300">
                    {metricLabel(metric)}
                </span>
                <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                    peak {formatValue(metric, peak)}
                </span>
            </div>
            <ResponsiveContainer width="100%" height={height}>
                <AreaChart
                    data={data}
                    margin={{ top: 6, right: 8, bottom: 0, left: 0 }}
                >
                    <defs>
                        <linearGradient
                            id={gradientId}
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                stopColor="var(--tl-accent)"
                                stopOpacity={0.16}
                            />
                            <stop
                                offset="100%"
                                stopColor="var(--tl-accent)"
                                stopOpacity={0}
                            />
                        </linearGradient>
                    </defs>
                    <CartesianGrid
                        vertical={false}
                        stroke="var(--border)"
                        strokeWidth={1}
                    />
                    <XAxis
                        dataKey="date"
                        tickFormatter={shortDate}
                        tickLine={false}
                        axisLine={false}
                        minTickGap={16}
                        tick={{
                            fontSize: 10,
                            fill: 'var(--muted-foreground)',
                        }}
                    />
                    <YAxis
                        width={56}
                        tickLine={false}
                        axisLine={false}
                        tickFormatter={(v: number) => axisTick(metric, v)}
                        tick={{
                            fontSize: 10,
                            fill: 'var(--muted-foreground)',
                        }}
                    />
                    <Tooltip
                        cursor={{ stroke: 'var(--border)', strokeWidth: 1 }}
                        content={<TimelineTooltip metric={metric} />}
                    />
                    <Area
                        type="monotone"
                        dataKey="value"
                        stroke="var(--tl-accent)"
                        strokeWidth={2}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        fill={`url(#${gradientId})`}
                        // A lone day has no line to draw — show its dot instead.
                        dot={
                            data.length === 1
                                ? { r: 4, fill: 'var(--tl-accent)' }
                                : false
                        }
                        activeDot={{
                            r: 4,
                            fill: 'var(--tl-accent)',
                            stroke: 'var(--background)',
                            strokeWidth: 2,
                        }}
                        isAnimationActive={false}
                    />
                </AreaChart>
            </ResponsiveContainer>
            <p className="mt-1 px-1 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                {summary}
            </p>
        </div>
    );
}

/**
 * Several metrics on one plot.
 *
 * Two y-axes would be the easy way to do this and it is the single worst thing
 * you can do to a chart: with independent scales the crossing points are an
 * artefact of the axis ranges, not the data, and people read them as meaning.
 * So there is exactly one axis here. Metrics in the same unit and within an
 * order of magnitude of each other keep their real values; anything else is
 * indexed to each metric's own peak, which compares SHAPE honestly and leaves
 * the real numbers to the hover card.
 */
function OverlayPanel({
    metrics,
    points,
}: {
    metrics: string[];
    points: TimeseriesPoint[];
}) {
    const peaks = metrics.map((m) =>
        points.reduce((max, p) => Math.max(max, metricValue(p, m)), 0),
    );

    const sameUnit = new Set(metrics.map(familyOf)).size === 1;
    const live = peaks.filter((p) => p > 0);
    const spread = live.length ? Math.max(...live) / Math.min(...live) : 1;
    // Same unit is not enough on its own: impressions (millions) beside
    // purchases (dozens) would pin the smaller line flat to the baseline.
    const realValues = sameUnit && spread <= 25;

    const data = points.map((p) => {
        const row: Record<string, number | string> = { date: p.date };

        metrics.forEach((m, i) => {
            const value = metricValue(p, m);
            row[`raw:${m}`] = value;
            row[m] = realValues
                ? value
                : peaks[i]
                  ? (value / peaks[i]) * 100
                  : 0;
        });

        return row;
    });

    return (
        <div className="rounded-[12px] border border-black/6 bg-white p-3 dark:border-white/6 dark:bg-zinc-900">
            {/* A legend is the dependable identity channel and is always
                present once there are two or more series. */}
            <div className="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1.5">
                {metrics.map((m, i) => (
                    <span
                        key={m}
                        className="flex items-center gap-1.5 font-mono text-[11px] text-gray-600 dark:text-gray-300"
                    >
                        <span
                            className="h-0.5 w-3.5 rounded-full"
                            style={{ background: SERIES_COLORS[i] }}
                        />
                        {metricLabel(m)}
                    </span>
                ))}
            </div>

            <ResponsiveContainer width="100%" height={OVERLAY_HEIGHT}>
                <LineChart
                    data={data}
                    margin={{ top: 6, right: 8, bottom: 0, left: 0 }}
                >
                    <CartesianGrid
                        vertical={false}
                        stroke="var(--border)"
                        strokeWidth={1}
                    />
                    <XAxis
                        dataKey="date"
                        tickFormatter={shortDate}
                        tickLine={false}
                        axisLine={false}
                        minTickGap={16}
                        tick={{ fontSize: 10, fill: 'var(--muted-foreground)' }}
                    />
                    <YAxis
                        width={56}
                        tickLine={false}
                        axisLine={false}
                        tickFormatter={(v: number) =>
                            realValues
                                ? axisTick(metrics[0], v)
                                : `${Math.round(v)}%`
                        }
                        tick={{ fontSize: 10, fill: 'var(--muted-foreground)' }}
                    />
                    <Tooltip
                        cursor={{ stroke: 'var(--border)', strokeWidth: 1 }}
                        content={<OverlayTooltip metrics={metrics} />}
                    />
                    {metrics.map((m, i) => (
                        <Line
                            key={m}
                            type="monotone"
                            dataKey={m}
                            stroke={SERIES_COLORS[i]}
                            strokeWidth={2}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            dot={
                                data.length === 1
                                    ? { r: 4, fill: SERIES_COLORS[i] }
                                    : false
                            }
                            activeDot={{
                                r: 4,
                                fill: SERIES_COLORS[i],
                                stroke: 'var(--background)',
                                strokeWidth: 2,
                            }}
                            isAnimationActive={false}
                        />
                    ))}
                </LineChart>
            </ResponsiveContainer>

            <p className="mt-1.5 px-1 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                {realValues
                    ? 'Real values on a shared axis — these metrics share a unit and scale.'
                    : 'Each line indexed to its own peak (100%) so the shapes compare. Hover for real values.'}
            </p>
        </div>
    );
}

/** Multi-select over the same metric columns the grid offers. */
/**
 * A checkbox row. Deliberately NOT the shared Checkbox primitive: the whole row
 * is the click target, and a Radix checkbox inside that button would nest one
 * interactive element in another. This is the visual box; the row carries the
 * semantics via role="menuitemcheckbox".
 */
function CheckMark({ checked }: { checked: boolean }) {
    return (
        <span
            aria-hidden
            className={clsx(
                'flex size-4 shrink-0 items-center justify-center rounded-[4px] border transition-colors',
                checked
                    ? 'border-emerald-500 bg-emerald-500 text-white'
                    : 'border-gray-300 bg-white dark:border-zinc-600 dark:bg-zinc-800',
            )}
        >
            {checked && <Check className="size-3" strokeWidth={3} />}
        </span>
    );
}

/**
 * Multi-select over the same metric columns the grid offers. Every metric shows
 * a checkbox whether or not it's on, so the list reads as "what could be here"
 * rather than only marking the handful already picked — and with 90-odd metrics
 * across five categories, it filters.
 */
function MetricPicker({
    selected,
    onChange,
}: {
    selected: string[];
    onChange: (next: string[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const toggle = (id: string) => {
        // Never empty — the last metric can't be turned off, or there'd be
        // nothing to plot.
        if (selected.includes(id)) {
            if (selected.length > 1) onChange(selected.filter((m) => m !== id));

            return;
        }
        onChange([...selected, id]);
    };

    const categories = useMemo(() => {
        const term = search.trim().toLowerCase();

        return INSIGHTS_OPTIONS.filter(
            (o) => !term || o.label.toLowerCase().includes(term),
        ).reduce<Record<string, typeof INSIGHTS_OPTIONS>>((acc, o) => {
            // category is optional on ColumnOption; ungrouped metrics collect
            // under one heading rather than vanishing.
            (acc[o.category ?? 'Other'] ??= []).push(o);

            return acc;
        }, {});
    }, [search]);

    const matches = Object.values(categories).reduce(
        (n, list) => n + list.length,
        0,
    );

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) setSearch('');
            }}
        >
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-8 gap-1.5 font-mono! text-[11px]!"
                >
                    <span className="text-gray-400 dark:text-gray-500">
                        Metrics
                    </span>
                    <span className="font-medium">
                        {selected.length === 1
                            ? metricLabel(selected[0])
                            : `${selected.length} selected`}
                    </span>
                    <ChevronDown className="h-3 w-3 text-gray-400" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="end"
                className="w-72 overflow-hidden p-0 font-mono text-[11px]"
            >
                <div className="border-b border-black/6 p-2 dark:border-white/6">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2 h-3 w-3 -translate-y-1/2 text-gray-400" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search metrics…"
                            aria-label="Search metrics"
                            className="h-7 w-full rounded-[8px] border border-black/6 bg-stone-100 pr-2 pl-7 text-[11px] text-gray-800 outline-none placeholder:text-gray-400 focus:border-emerald-500 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                        />
                    </div>
                    <div className="mt-1.5 flex items-center justify-between px-0.5">
                        <span className="text-[10px] text-gray-400 dark:text-gray-500">
                            {selected.length} selected
                        </span>
                        {selected.length > 1 && (
                            <button
                                type="button"
                                onClick={() => onChange(DEFAULT_METRICS)}
                                className="text-[10px] text-gray-400 transition-colors hover:text-emerald-600 dark:hover:text-emerald-400"
                            >
                                Reset
                            </button>
                        )}
                    </div>
                </div>

                <div className="max-h-72 overflow-auto p-1">
                    {matches === 0 ? (
                        <p className="px-2 py-6 text-center text-[10px] text-gray-400 dark:text-gray-500">
                            No metric matches “{search.trim()}”.
                        </p>
                    ) : (
                        Object.entries(categories).map(
                            ([category, options]) => (
                                <div key={category}>
                                    <p className="px-2 pt-2 pb-1 text-[10px] tracking-wide text-gray-400 dark:text-gray-500">
                                        {category}
                                    </p>
                                    {options.map((o) => {
                                        const checked = selected.includes(o.id);
                                        // The last one standing stays on: an empty
                                        // chart has nothing to say.
                                        const locked =
                                            checked && selected.length === 1;

                                        return (
                                            <button
                                                key={o.id}
                                                type="button"
                                                role="menuitemcheckbox"
                                                aria-checked={checked}
                                                disabled={locked}
                                                onClick={() => toggle(o.id)}
                                                title={
                                                    locked
                                                        ? 'Add another metric before removing this one'
                                                        : undefined
                                                }
                                                className={clsx(
                                                    'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors',
                                                    locked
                                                        ? 'cursor-not-allowed text-gray-400 dark:text-gray-500'
                                                        : 'text-gray-700 hover:bg-stone-100 dark:text-gray-300 dark:hover:bg-zinc-700',
                                                )}
                                            >
                                                <CheckMark checked={checked} />
                                                <span className="flex-1 truncate">
                                                    {o.label}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            ),
                        )
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}

/** Small segmented control: one plot, or one plot per metric. */
function ViewToggle({
    value,
    onChange,
    overlayDisabled,
}: {
    value: 'overlay' | 'split';
    onChange: (next: 'overlay' | 'split') => void;
    overlayDisabled: boolean;
}) {
    const options: { id: 'overlay' | 'split'; label: string }[] = [
        { id: 'overlay', label: 'One chart' },
        { id: 'split', label: 'Separate' },
    ];

    return (
        <div className="flex items-center gap-0.5 rounded-[10px] bg-stone-100 p-0.5 dark:bg-zinc-800">
            {options.map((o) => {
                const disabled = o.id === 'overlay' && overlayDisabled;

                return (
                    <button
                        key={o.id}
                        type="button"
                        disabled={disabled}
                        onClick={() => onChange(o.id)}
                        title={
                            disabled
                                ? `Pick at most ${MAX_OVERLAY} metrics to draw them on one chart`
                                : undefined
                        }
                        className={clsx(
                            'rounded-[8px] px-2.5 py-1 font-mono text-[11px] transition-colors',
                            value === o.id
                                ? 'bg-white text-gray-800 shadow-sm dark:bg-zinc-900 dark:text-gray-100'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                            disabled && 'cursor-not-allowed opacity-40',
                        )}
                    >
                        {o.label}
                    </button>
                );
            })}
        </div>
    );
}

/**
 * Per-row timeline. Opened from a row's chart button, it plots that row's ads
 * day by day across the date range the grid is already under, for whichever
 * metric columns the user picks.
 */
export function RowTimelineModal({
    slug,
    target,
    dateRange,
    selectedAccounts,
    accountsTotal,
    groupLabel,
    metricFilters,
    dateFilters,
    onClose,
}: {
    slug: string;
    target: TimelineTarget | null;
    dateRange: { since: string; until: string };
    selectedAccounts: string[];
    accountsTotal: number;
    groupLabel: string;
    /** The grid's row-level date filters, already serialised, so the chart
     *  covers the same ads the row's totals came from. */
    dateFilters?: string | null;
    /** Serialized grid filters, so the chart covers the row's surviving ads. */
    metricFilters?: string;
    onClose: () => void;
}) {
    const [points, setPoints] = useState<TimeseriesPoint[] | null>(null);
    const [loading, setLoading] = useState(false);
    const [metrics, setMetrics] = useState<string[]>(DEFAULT_METRICS);
    const [view, setView] = useState<'overlay' | 'split'>('overlay');

    useEffect(() => {
        if (!target) {
            setPoints(null);

            return;
        }

        let active = true;
        setLoading(true);

        const qs = new URLSearchParams();
        qs.set('scope_by', target.scopeBy);
        qs.set('scope', target.scope);
        qs.set('since', dateRange.since);
        qs.set('until', dateRange.until);
        if (selectedAccounts.length !== accountsTotal) {
            selectedAccounts.forEach((a) => qs.append('accounts[]', a));
        }
        if (dateFilters) qs.set('date_filters', dateFilters);
        // Group charts follow the grid's filters so the line matches the row.
        // A single ad's chart doesn't: it is opened from the group's ad list,
        // which isn't filtered, so applying them would flatten it to zeroes.
        if (metricFilters && target.scopeBy !== 'ad') {
            qs.set('metric_filters', metricFilters);
        }

        fetch(
            `/workspaces/${slug}/integrations/meta/ads-manager/timeseries?${qs.toString()}`,
            { headers: { Accept: 'application/json' } },
        )
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((d: { points: TimeseriesPoint[] }) => {
                if (active) {
                    setPoints(d.points);
                    setLoading(false);
                }
            })
            .catch(() => active && setLoading(false));

        return () => {
            active = false;
        };
    }, [
        target,
        dateRange.since,
        dateRange.until,
        selectedAccounts,
        accountsTotal,
        metricFilters,
        dateFilters,
        slug,
    ]);

    const days = points?.length ?? 0;
    const solo = metrics.length === 1;
    // Too many series to overlay legibly — fall back to small multiples and
    // grey the toggle out rather than drawing something unreadable.
    const overlayDisabled = metrics.length > MAX_OVERLAY;
    const overlay = view === 'overlay' && !solo && !overlayDisabled;

    const body = useMemo(() => {
        if (loading || !points) {
            return (
                <div className="flex h-64 items-center justify-center">
                    <Loader2 className="h-4 w-4 animate-spin text-gray-400" />
                </div>
            );
        }

        if (points.length === 0) {
            return (
                <p className="py-20 text-center font-mono text-[11px] text-gray-400 dark:text-gray-500">
                    No days in this range.
                </p>
            );
        }

        if (overlay) {
            return <OverlayPanel metrics={metrics} points={points} />;
        }

        return (
            <div
                className={clsx(
                    solo && 'space-y-3',
                    !solo && 'grid gap-3 sm:grid-cols-2',
                    metrics.length >= 3 && 'xl:grid-cols-3',
                )}
            >
                {metrics.map((m) => (
                    <MetricPanel
                        key={m}
                        metric={m}
                        points={points}
                        height={solo ? SOLO_HEIGHT : PANEL_HEIGHT}
                    />
                ))}
            </div>
        );
    }, [loading, points, metrics, solo, overlay]);

    return (
        <Dialog open={target != null} onOpenChange={(o) => !o && onClose()}>
            {/* The accent is set per theme here so every panel — line, fill and
                tooltip dot — reads at >= 3:1 on its own surface. */}
            <DialogContent className="relative flex max-h-[90vh] w-[95vw] max-w-[1180px] flex-col gap-0 overflow-hidden p-0 [--tl-accent:#059669] [--tl-s1:#059669] [--tl-s2:#7c3aed] [--tl-s3:#ea580c] [--tl-s4:#0284c7] sm:max-w-[1180px] dark:[--tl-accent:#34d399] dark:[--tl-s1:#0ea47a] dark:[--tl-s2:#8b5cf6] dark:[--tl-s3:#d2700c] dark:[--tl-s4:#2f92d8] [&_[data-default-close]]:hidden">
                <div className="flex items-center justify-between gap-3 border-b border-black/6 py-3.5 pr-3 pl-5 dark:border-white/6">
                    <div className="min-w-0">
                        <DialogTitle className="truncate text-[14px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                            {target?.label || groupLabel}
                        </DialogTitle>
                        <p className="mt-0.5 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            {groupLabel} · {days} day{days === 1 ? '' : 's'} ·{' '}
                            {dateRange.since} → {dateRange.until}
                        </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        {!solo && (
                            <ViewToggle
                                value={view}
                                onChange={setView}
                                overlayDisabled={overlayDisabled}
                            />
                        )}
                        <MetricPicker
                            selected={metrics}
                            onChange={setMetrics}
                        />
                        <DialogClose asChild>
                            <button
                                type="button"
                                aria-label="Close timeline"
                                className="flex h-8 w-8 items-center justify-center rounded-[10px] text-gray-400 transition-colors hover:bg-stone-100 hover:text-gray-700 dark:text-gray-500 dark:hover:bg-zinc-800 dark:hover:text-gray-200"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </DialogClose>
                    </div>
                </div>

                <div className="flex-1 overflow-auto bg-stone-50 p-4 dark:bg-zinc-950">
                    {body}
                </div>
            </DialogContent>
        </Dialog>
    );
}
