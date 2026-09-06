import { Skeleton } from '@/components/ui/skeleton';

export type CallMixGranularity = 'daily' | 'hourly';

export interface CallMixBucket {
    /** `YYYY-MM-DD` for a day, `00`–`23` for an hour. */
    key: string;
    /** What the axis prints: `Aug 27`, or `09:00`. */
    label: string;
    customer: number;
    rider: number;
    verification: number;
}

export interface CallMixResponse {
    range: { from: string; to: string };
    granularity: CallMixGranularity;
    /** The day the hours belong to; null when daily. */
    day: string | null;
    buckets: CallMixBucket[];
    totals: { customer: number; rider: number; verification: number };
}

/** The three series, in the order they stack — bottom first. */
type SeriesId = 'customer' | 'rider' | 'verification';

/**
 * Slots 1–3 of the categorical palette the comparison panel already uses, taken
 * in its fixed order so a CSR chart and this one never disagree about what blue
 * means. Dark is its own step against the zinc-900 card, not a flip of light.
 *
 * Validated as a set on both surfaces: worst adjacent CVD ΔE 9.2 light / 9.4
 * dark (deutan) against a floor of 8, and normal-vision ΔE 27.6 / 26.5. The
 * green sits at 2.74:1 on the light card, under the 3:1 mark — which is why
 * every bar carries its total as a printed number and the legend carries each
 * series' own, so no reading depends on telling two fills apart.
 */
const SERIES: {
    id: SeriesId;
    label: string;
    swatch: string;
    /** What it counts, for the legend's title attribute. */
    hint: string;
}[] = [
    {
        id: 'customer',
        label: 'RMO · customer',
        swatch: 'bg-[#2a78d6] dark:bg-[#3987e5]',
        hint: 'Calls about a delivery that reached the customer',
    },
    {
        id: 'rider',
        label: 'RMO · rider',
        swatch: 'bg-[#eb6834] dark:bg-[#d95926]',
        hint: 'Calls about a delivery that reached the rider',
    },
    {
        id: 'verification',
        label: 'Verification',
        swatch: 'bg-[#1baf7a] dark:bg-[#199e70]',
        hint: 'Calls against an order with no delivery behind it yet',
    },
];

const PLOT_HEIGHT = 240;

/**
 * A round number at the top of the axis, at or above the tallest bar, that
 * divides into four clean ticks — 120 rather than 87, so the gridline labels
 * are numbers a reader can hold onto.
 */
function axisCeiling(peak: number) {
    if (peak <= 0) return 4;

    const magnitude = Math.pow(10, Math.floor(Math.log10(peak / 4)));
    const step =
        [1, 2, 2.5, 5, 10]
            .map((m) => m * magnitude)
            .find((candidate) => candidate * 4 >= peak) ?? peak / 4;

    return step * 4;
}

/**
 * Every call placed, split by who it reached.
 *
 * The three series are a partition of Total Called rather than a sample of it,
 * so the stack's height is that card's figure and the split underneath says
 * where the effort went — chasing parcels with customers, chasing them with
 * riders, or confirming orders before they ever ship.
 *
 * Hourly answers a different question from daily: not "which days were busy"
 * but "when in the day do we call". It reads one day at a time — the tabs pick
 * which — because the hours of several days averaged together are not any
 * day's shape.
 */
export default function CsrCallMixChart({
    data,
    loading,
    granularity,
    onGranularityChange,
    days,
    day,
    onDayChange,
    hidden,
    onToggleSeries,
}: {
    data: CallMixResponse | null;
    loading: boolean;
    granularity: CallMixGranularity;
    onGranularityChange: (next: CallMixGranularity) => void;
    /** Every day in the picked range, `YYYY-MM-DD`, earliest first. */
    days: string[];
    /** Which of them the hours are read from. */
    day: string;
    onDayChange: (next: string) => void;
    /** Series switched off in the legend. Colour stays with the entity. */
    hidden: SeriesId[];
    onToggleSeries: (id: SeriesId) => void;
}) {
    const buckets = data?.buckets ?? [];
    const shown = SERIES.filter((series) => !hidden.includes(series.id));
    const hasCalls = buckets.some((bucket) =>
        shown.some((series) => bucket[series.id] > 0),
    );

    return (
        <div className="mt-6 mb-4">
            <h2 className="mb-3 font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                Total called over time
            </h2>

            <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Every call placed, by who it reached
                        </h3>
                        <p className="mt-1 max-w-xl text-[13px] text-gray-500 dark:text-gray-400">
                            The three parts add up to Total Called — a call
                            about a delivery reached the customer or the rider,
                            and one with no delivery behind it is order
                            verification. Switch a part off in the legend to
                            read the rest on its own.
                        </p>
                    </div>

                    <GranularityToggle
                        value={granularity}
                        onChange={onGranularityChange}
                    />
                </div>

                <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                    {SERIES.map((series) => (
                        <LegendToggle
                            key={series.id}
                            label={series.label}
                            hint={series.hint}
                            swatch={series.swatch}
                            total={data?.totals[series.id] ?? null}
                            on={!hidden.includes(series.id)}
                            onClick={() => onToggleSeries(series.id)}
                        />
                    ))}
                </div>

                {granularity === 'hourly' && days.length > 1 && (
                    <DayTabs days={days} value={day} onChange={onDayChange} />
                )}

                {loading ? (
                    <CallMixSkeleton />
                ) : shown.length === 0 ? (
                    <p className="py-14 text-center text-[12px] text-gray-400 dark:text-gray-500">
                        Every part is switched off — turn one back on in the
                        legend.
                    </p>
                ) : !hasCalls ? (
                    <p className="py-14 text-center text-[12px] text-gray-400 dark:text-gray-500">
                        No calls were placed in the selected period.
                    </p>
                ) : (
                    <CallMixPlot
                        buckets={buckets}
                        shown={shown}
                        granularity={granularity}
                    />
                )}
            </div>
        </div>
    );
}

/**
 * Daily or hourly, as one control above the plot.
 *
 * Always available. Hourly over a range of days is not several days folded
 * together — the day tabs below pick which day the hours belong to, so the
 * reading is always one day's shape.
 */
function GranularityToggle({
    value,
    onChange,
}: {
    value: CallMixGranularity;
    onChange: (next: CallMixGranularity) => void;
}) {
    return (
        <div
            className="flex shrink-0 items-center rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800"
            role="group"
            aria-label="Time granularity"
        >
            {(['daily', 'hourly'] as const).map((option) => {
                const active = value === option;

                return (
                    <button
                        key={option}
                        type="button"
                        aria-pressed={active}
                        onClick={() => onChange(option)}
                        className={`rounded-md px-3 py-1 font-mono text-[11px] capitalize transition-colors ${
                            active
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-zinc-900 dark:text-gray-100'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                        }`}
                    >
                        {option}
                    </button>
                );
            })}
        </div>
    );
}

/**
 * One series in the legend, and the switch that hides it.
 *
 * Carries its own total, so the three figures are readable as numbers whether
 * or not the fills can be told apart — the relief the green's contrast asks
 * for. A switched-off series keeps its colour and dims instead, because the
 * colour belongs to the series rather than to its place in the stack.
 */
/**
 * Which day the hours are read from.
 *
 * Only shown for hourly over more than one day; a single-day range already has
 * its answer. The strip scrolls rather than wrapping, so a long range stays one
 * row and the plot below it does not move down as the range grows.
 */
function DayTabs({
    days,
    value,
    onChange,
}: {
    days: string[];
    value: string;
    onChange: (next: string) => void;
}) {
    return (
        <div
            className="mt-3 -mb-1 flex gap-1 overflow-x-auto pb-1"
            role="tablist"
            aria-label="Day"
        >
            {days.map((day) => {
                const active = day === value;

                return (
                    <button
                        key={day}
                        type="button"
                        role="tab"
                        aria-selected={active}
                        onClick={() => onChange(day)}
                        className={`shrink-0 rounded-md px-2.5 py-1 font-mono text-[11px] whitespace-nowrap transition-colors ${
                            active
                                ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                : 'text-gray-500 hover:bg-black/4 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'
                        }`}
                    >
                        {new Date(`${day}T00:00:00`).toLocaleDateString(
                            'en-US',
                            { month: 'short', day: 'numeric' },
                        )}
                    </button>
                );
            })}
        </div>
    );
}

function LegendToggle({
    label,
    hint,
    swatch,
    total,
    on,
    onClick,
}: {
    label: string;
    hint: string;
    swatch: string;
    total: number | null;
    on: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={hint}
            aria-pressed={on}
            className={`flex items-center gap-1.5 rounded-md px-1.5 py-1 transition-opacity hover:bg-black/3 dark:hover:bg-white/5 ${
                on ? '' : 'opacity-40'
            }`}
        >
            <span
                className={`h-2.5 w-2.5 shrink-0 rounded-[3px] ${swatch}`}
                aria-hidden
            />
            <span className="text-[12px] text-gray-600 dark:text-gray-300">
                {label}
            </span>
            {total !== null && (
                <span className="font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                    {total.toLocaleString()}
                </span>
            )}
        </button>
    );
}

function CallMixPlot({
    buckets,
    shown,
    granularity,
}: {
    buckets: CallMixBucket[];
    shown: typeof SERIES;
    granularity: CallMixGranularity;
}) {
    const stackOf = (bucket: CallMixBucket) =>
        shown.reduce((sum, series) => sum + bucket[series.id], 0);

    const peak = Math.max(...buckets.map(stackOf));
    const ceiling = axisCeiling(peak);
    const ticks = [0, 1, 2, 3, 4].map((i) => (ceiling / 4) * i);
    const height = (value: number) => `${(value / ceiling) * 100}%`;

    // Twenty-four hours need to fit more narrowly than a fortnight of days, but
    // both scroll rather than shrinking the bars to hairlines.
    const slot = granularity === 'hourly' ? 'min-w-[44px]' : 'min-w-[52px]';

    return (
        <div className="mt-4 flex gap-2">
            {/* The scale sits outside the scroller so it stays put while a long
                range is scrolled — the axis it labels does not move. */}
            <div
                className="relative w-9 shrink-0"
                style={{ height: PLOT_HEIGHT }}
                aria-hidden
            >
                {ticks.map((tick) => (
                    <span
                        key={tick}
                        className="absolute right-0 translate-y-1/2 font-mono text-[10px] text-gray-400 tabular-nums dark:text-gray-500"
                        style={{ bottom: height(tick) }}
                    >
                        {tick.toLocaleString()}
                    </span>
                ))}
            </div>

            <div className="min-w-0 flex-1 overflow-x-auto pb-1">
                <div
                    className="flex items-end border-b border-gray-200 dark:border-zinc-700"
                    style={{ height: PLOT_HEIGHT }}
                >
                    {buckets.map((bucket) => (
                        <CallMixColumn
                            key={bucket.key}
                            bucket={bucket}
                            shown={shown}
                            total={stackOf(bucket)}
                            height={height}
                            slot={slot}
                        />
                    ))}
                </div>

                <div className="flex">
                    {buckets.map((bucket) => (
                        <span
                            key={bucket.key}
                            className={`${slot} flex-1 pt-2 text-center font-mono text-[10px] whitespace-nowrap text-gray-400 dark:text-gray-500`}
                        >
                            {bucket.label}
                        </span>
                    ))}
                </div>
            </div>
        </div>
    );
}

function CallMixColumn({
    bucket,
    shown,
    total,
    height,
    slot,
}: {
    bucket: CallMixBucket;
    shown: typeof SERIES;
    total: number;
    height: (value: number) => string;
    slot: string;
}) {
    // Drawn top-down so the first series sits at the bottom of the stack, and
    // only the segments that have a value are drawn — an empty one would still
    // take its 2px separator and read as a hairline of colour that isn't there.
    const segments = shown.filter((series) => bucket[series.id] > 0).reverse();

    return (
        <div
            className={`group relative flex h-full ${slot} flex-1 flex-col items-center justify-end`}
        >
            {total > 0 && (
                <span className="pb-1 font-mono text-[10px] text-gray-400 tabular-nums dark:text-gray-500">
                    {total.toLocaleString()}
                </span>
            )}

            <div
                className="flex w-2.5 flex-col justify-end"
                style={{ flex: 1 }}
            >
                {segments.map((series, index) => (
                    <div
                        key={series.id}
                        className={`w-full ${series.swatch} ${
                            // Only the top of the stack is rounded; the rest
                            // are butted together and separated by the surface.
                            index === 0 ? 'rounded-t-[4px]' : ''
                        } ${index > 0 ? 'mt-[2px]' : ''}`}
                        style={{ height: height(bucket[series.id]) }}
                    />
                ))}
            </div>

            {/* Hovering anywhere in the slot, not just on the 10px bar — the
                target is the whole column. */}
            <div className="pointer-events-none absolute bottom-2 left-1/2 z-10 hidden -translate-x-1/2 rounded-lg border border-black/6 bg-white px-2.5 py-1.5 whitespace-nowrap shadow-md group-hover:block dark:border-white/10 dark:bg-zinc-800">
                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                    {bucket.label}
                </p>
                <p className="mt-0.5 text-[11px] font-medium text-gray-700 dark:text-gray-200">
                    {total.toLocaleString()} call{total === 1 ? '' : 's'}
                </p>
                {shown.map((series) => (
                    <p
                        key={series.id}
                        className="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400"
                    >
                        <span
                            className={`h-2 w-2 shrink-0 rounded-[2px] ${series.swatch}`}
                            aria-hidden
                        />
                        {series.label}
                        <span className="ml-auto pl-2 font-mono tabular-nums">
                            {bucket[series.id].toLocaleString()}
                        </span>
                    </p>
                ))}
            </div>
        </div>
    );
}

function CallMixSkeleton() {
    // Heights that read as a plausible run of buckets rather than a flat block.
    const bars = [62, 78, 50, 92, 70, 34, 22];

    return (
        <div className="mt-4 flex gap-2">
            <div className="w-9 shrink-0" />
            <div
                className="flex flex-1 items-end border-b border-gray-200 dark:border-zinc-700"
                style={{ height: PLOT_HEIGHT }}
            >
                {bars.map((tall, i) => (
                    <div
                        key={i}
                        className="flex h-full flex-1 flex-col items-center justify-end"
                    >
                        <Skeleton
                            className="w-2.5 rounded-t-[4px]"
                            style={{ height: `${tall}%` }}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}
