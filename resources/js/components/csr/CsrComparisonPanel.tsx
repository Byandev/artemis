import { Skeleton } from '@/components/ui/skeleton';

export interface ComparisonRow {
    /** The pancake user's UUID. */
    id: string;
    name: string;
    value: number;
    /** The same figure last period, or null when they have none. */
    previous_value: number | null;
    /** Percent, or percentage points for the metrics that are already rates. */
    change: number | null;
    /** Fixed per CSR across all four metrics — see BAR_COLORS. */
    color_slot: number;
}

export interface ComparisonMetric {
    key: string;
    label: string;
    format: 'currency' | 'percent' | 'duration';
    delta_unit: string;
    higher_is_better: boolean;
    /** Mean over everyone who qualified, not just the listed rows. */
    average: number | null;
    total: number;
    rows: ComparisonRow[];
}

export interface ComparisonResponse {
    range: { from: string; to: string };
    previous_period: { from: string; to: string };
    metrics: ComparisonMetric[];
}

/**
 * Eight categorical hues, light and dark steps, in a fixed order — assigned to
 * the CSR rather than to their rank, so switching metrics moves the bars
 * without repainting them.
 *
 * Written as literal class pairs so Tailwind keeps both steps and the theme
 * swap needs no JavaScript. Validated as a set against both surfaces: worst
 * adjacent CVD ΔE 9.1 light / 8.4 dark. Three light steps sit under 3:1 against
 * white, which is allowed here because every bar is labelled with its CSR and
 * its value — the colour never carries the identity on its own.
 */
const BAR_COLORS = [
    'bg-[#2a78d6] dark:bg-[#3987e5]',
    'bg-[#eb6834] dark:bg-[#d95926]',
    'bg-[#1baf7a] dark:bg-[#199e70]',
    'bg-[#eda100] dark:bg-[#c98500]',
    'bg-[#e87ba4] dark:bg-[#d55181]',
    'bg-[#008300] dark:bg-[#008300]',
    'bg-[#4a3aa7] dark:bg-[#9085e9]',
    'bg-[#e34948] dark:bg-[#e66767]',
];

const peso = (n: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(Number(n) || 0);

/** Seconds as `88h 32m` / `12m 05s` / `45s`, as the leader cards read them. */
const duration = (seconds: number) => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;

    if (h > 0) return `${h.toLocaleString()}h ${String(m).padStart(2, '0')}m`;
    return m > 0 ? `${m}m ${String(s).padStart(2, '0')}s` : `${s}s`;
};

function formatValue(value: number, format: ComparisonMetric['format']) {
    if (format === 'currency') return peso(value);
    if (format === 'duration') return duration(value);
    return `${value.toFixed(1)}%`;
}

/** "Aug 7 – Aug 14", the window the markers and the deltas compare against. */
function formatRange({ from, to }: { from: string; to: string }) {
    const opts: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' };
    const start = new Date(`${from}T00:00:00`).toLocaleDateString(
        'en-US',
        opts,
    );
    const end = new Date(`${to}T00:00:00`).toLocaleDateString('en-US', opts);

    return `${start} – ${end}`;
}

/**
 * The tabs before the response lands, in the order the endpoint returns them.
 * Keyed the same so the tab restored from the URL is already highlighted while
 * the skeleton is up, rather than snapping across once the metrics arrive.
 */
const PLACEHOLDER_TABS = [
    { key: 'sales', label: 'Sales' },
    { key: 'rts', label: 'RTS' },
    { key: 'rmo_called', label: 'RMO called' },
    { key: 'call_time', label: 'Call time' },
];

/**
 * Where a CSR sits against everyone else, on the metric you pick.
 *
 * The leader cards above name one winner each; this is the field behind them.
 * Three things are on every row: the bar's length (this period), a tick (the
 * same CSR last period) and the dashed line the whole panel shares (the
 * period's average across every CSR who qualified, not just the eight shown —
 * a top eight measured against its own mean would put half of them above
 * average by construction).
 *
 * All four metrics arrive in one response, so the tabs are instant and cost
 * nothing.
 *
 * The selected tab is owned by the page and lives in the URL, so a reload, a
 * date change or a shared link comes back on the metric that was being read.
 */
export default function CsrComparisonPanel({
    data,
    loading,
    metricKey,
    onMetricChange,
}: {
    data: ComparisonResponse | null;
    loading: boolean;
    metricKey: string;
    onMetricChange: (key: string) => void;
}) {
    const metrics = data?.metrics ?? [];
    // A key that names no metric — an old link, a hand-edited URL — reads as
    // the first tab rather than an empty panel.
    const metric =
        metrics.find((m) => m.key === metricKey) ?? metrics[0] ?? null;
    const activeKey = metric?.key ?? metricKey;

    return (
        <div className="mb-4">
            <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                    CSR comparison
                </h2>

                <div className="flex items-center gap-0.5 self-start rounded-[10px] bg-stone-100 p-0.5 dark:bg-zinc-800">
                    {(metrics.length === 0 ? PLACEHOLDER_TABS : metrics).map(
                        (tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                disabled={loading}
                                onClick={() => onMetricChange(tab.key)}
                                className={
                                    tab.key === activeKey
                                        ? 'rounded-lg bg-white px-3 py-1.5 text-[12px] font-medium text-emerald-700 shadow-sm disabled:opacity-70 dark:bg-zinc-900 dark:text-emerald-400'
                                        : 'cursor-pointer rounded-lg px-3 py-1.5 text-[12px] text-gray-500 hover:text-gray-800 disabled:cursor-default dark:text-gray-400 dark:hover:text-gray-200'
                                }
                            >
                                {tab.label}
                            </button>
                        ),
                    )}
                </div>
            </div>

            <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
                {loading ? (
                    <ComparisonSkeleton />
                ) : !metric || metric.rows.length === 0 ? (
                    <p className="py-10 text-center text-[12px] text-gray-400 dark:text-gray-500">
                        No CSR activity for this metric in the selected period.
                    </p>
                ) : (
                    <ComparisonChart
                        metric={metric}
                        previousPeriod={data?.previous_period}
                    />
                )}
            </div>
        </div>
    );
}

/**
 * Column widths shared by the rows, the average overlay and the footer. The
 * overlay is positioned off them, so the dashed line lands exactly over the
 * bars rather than approximately.
 */
const NAME_COL = 'w-28 shrink-0 sm:w-40';
const VALUE_COL = 'w-36 shrink-0 sm:w-40';
const TRACK_INSET =
    'left-[7.75rem] right-[9.75rem] sm:left-[10.75rem] sm:right-[10.75rem]';

/**
 * Ten rows (h-6 bar + mb-2) before the list starts scrolling — enough that the
 * usual roster never scrolls, and a big one stays a panel rather than a page.
 */
const ROWS_MAX_HEIGHT = 'max-h-80';

function ComparisonChart({
    metric,
    previousPeriod,
}: {
    metric: ComparisonMetric;
    previousPeriod?: { from: string; to: string };
}) {
    // Everything drawn shares one scale: the bars, the previous-period ticks
    // and the average line. Taking the max over all three keeps a tick or the
    // average from running off the end of its track.
    const max = Math.max(
        ...metric.rows.map((row) =>
            Math.max(row.value, row.previous_value ?? 0),
        ),
        metric.average ?? 0,
    );
    const pct = (value: number) => (max > 0 ? (value / max) * 100 : 0);

    return (
        <>
            {/* A long roster scrolls inside the card instead of pushing the
                average label — and everything below it — off the page. The
                scroll lives on the outer box so the inner one still stretches
                to the full list, keeping the average line spanning every row
                rather than just the visible ones. */}
            <div className={`overflow-y-auto ${ROWS_MAX_HEIGHT}`}>
                <div className="relative">
                    {/* One line across every track, so the average reads as the
                        panel's benchmark rather than a mark repeated per row.
                        Lifted above the bars — the rows paint after it — so it
                        stays readable where it crosses a filled bar. */}
                    {metric.average !== null && (
                        <div
                            className={`pointer-events-none absolute inset-y-0 z-10 ${TRACK_INSET}`}
                            aria-hidden
                        >
                            <div
                                className="absolute inset-y-0 border-l border-dashed border-gray-400/80 dark:border-zinc-400/70"
                                style={{ left: `${pct(metric.average)}%` }}
                            />
                        </div>
                    )}

                    {metric.rows.map((row) => (
                        <ComparisonBar
                            key={row.id}
                            row={row}
                            metric={metric}
                            pct={pct}
                            previousPeriod={previousPeriod}
                        />
                    ))}
                </div>
            </div>

            <div className="mt-1.5 flex items-center gap-3">
                <div className={NAME_COL} />
                <div className="relative h-4 flex-1">
                    {metric.average !== null && (
                        <span
                            className="absolute -translate-x-1/2 font-mono text-[10px] whitespace-nowrap text-gray-500 dark:text-gray-400"
                            style={{ left: `${pct(metric.average)}%` }}
                        >
                            Average {formatValue(metric.average, metric.format)}
                        </span>
                    )}
                </div>
                <div
                    className={`${VALUE_COL} flex items-center justify-end gap-1.5 font-mono text-[10px] whitespace-nowrap text-gray-400 dark:text-gray-500`}
                >
                    {previousPeriod && (
                        <>
                            <span className="inline-block h-3 w-px bg-gray-400 dark:bg-gray-500" />
                            vs {formatRange(previousPeriod)}
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

function ComparisonBar({
    row,
    metric,
    pct,
    previousPeriod,
}: {
    row: ComparisonRow;
    metric: ComparisonMetric;
    pct: (value: number) => number;
    previousPeriod?: { from: string; to: string };
}) {
    return (
        <div className="mb-2 flex items-center gap-3 last:mb-0">
            <p
                className={`${NAME_COL} truncate text-[12px] text-gray-700 dark:text-gray-300`}
                title={row.name}
            >
                {row.name}
            </p>

            <div className="relative h-6 flex-1 rounded-md bg-stone-100 dark:bg-zinc-800">
                <div
                    className={`absolute inset-y-0 left-0 rounded-md ${BAR_COLORS[row.color_slot % BAR_COLORS.length]}`}
                    style={{ width: `${pct(row.value)}%` }}
                    title={`${row.name}: ${formatValue(row.value, metric.format)}`}
                />
                {row.previous_value !== null && (
                    <div
                        className="absolute inset-y-0 w-[2px] rounded-full bg-gray-600/70 dark:bg-gray-300/60"
                        style={{ left: `${pct(row.previous_value)}%` }}
                        title={`${formatValue(row.previous_value, metric.format)}${
                            previousPeriod
                                ? ` (${formatRange(previousPeriod)})`
                                : ''
                        }`}
                    />
                )}
            </div>

            <div className={`${VALUE_COL} flex items-center justify-end gap-2`}>
                <span className="font-mono text-[12px] font-medium text-gray-800 tabular-nums dark:text-gray-100">
                    {formatValue(row.value, metric.format)}
                </span>
                <DeltaChip metric={metric} change={row.change} />
            </div>
        </div>
    );
}

/**
 * The change against last period. `higher_is_better` decides the colour, not
 * the sign — a return rate climbing is red where sales climbing is green.
 */
function DeltaChip({
    metric,
    change,
}: {
    metric: ComparisonMetric;
    change: number | null;
}) {
    if (change === null) {
        return (
            <span
                title="Nothing to compare against in the previous period"
                className="w-14 text-right font-mono text-[10px] text-gray-300 dark:text-gray-600"
            >
                —
            </span>
        );
    }

    const flat = Math.abs(change) < 0.05;
    const good = metric.higher_is_better ? change > 0 : change < 0;
    const sign = change > 0 ? '+' : '−';

    return (
        <span
            className={`w-14 rounded-full px-1.5 py-0.5 text-center font-mono text-[10px] font-medium ${
                flat
                    ? 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400'
                    : good
                      ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400'
                      : 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400'
            }`}
        >
            {flat
                ? `0${metric.delta_unit}`
                : `${sign}${Math.abs(change).toFixed(1)}${metric.delta_unit}`}
        </span>
    );
}

function ComparisonSkeleton() {
    return (
        <div className={`overflow-y-auto ${ROWS_MAX_HEIGHT}`}>
            {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="mb-2 flex items-center gap-3 last:mb-0">
                    <Skeleton className={`${NAME_COL} h-3`} />
                    <div className="h-6 flex-1">
                        <Skeleton
                            className="h-6 rounded-md"
                            style={{ width: `${90 - i * 8}%` }}
                        />
                    </div>
                    <Skeleton className={`${VALUE_COL} h-3`} />
                </div>
            ))}
        </div>
    );
}
