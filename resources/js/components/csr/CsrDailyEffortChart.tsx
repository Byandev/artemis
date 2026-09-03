import { Skeleton } from '@/components/ui/skeleton';

export interface DailyEffortDay {
    /** `YYYY-MM-DD`. Every day in the range is present, zeros included. */
    date: string;
    /** Every RMO call placed that day, however short. */
    calls: number;
    /** The subset that lasted long enough to be a conversation. */
    real: number;
}

export interface DailyEffortResponse {
    range: { from: string; to: string };
    days: DailyEffortDay[];
    totals: { calls: number; real: number };
}

/**
 * Two series, and only one of them is an identity.
 *
 * Calls placed is the effort baseline, so it takes a deliberate neutral; the
 * conversations that came of it take the same blue the comparison panel gives
 * its first CSR, which is what the eye should land on. Validated as a pair
 * against both surfaces: worst CVD ΔE 10.7 light / 13.5 dark, both clear of the
 * separation floor, and each step clears 3:1 against its own surface.
 */
const CALLS_BAR = 'bg-[#8a8a93] dark:bg-[#71717a]';
const REAL_BAR = 'bg-[#2a78d6] dark:bg-[#3987e5]';

/** Plot height in pixels. Bars are sized as a percentage of it. */
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

/** "Aug 14" — the axis reads days, so the year would be noise. */
const dayLabel = (date: string) =>
    new Date(`${date}T00:00:00`).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });

/**
 * Effort against results, day by day.
 *
 * The two call cards at the top of the page give the period's totals; this puts
 * the same two figures across the days that made them. A week where the calls
 * held up but the conversations fell away reads here as the gap between the
 * bars widening — which a pair of period totals cannot show.
 */
export default function CsrDailyEffortChart({
    data,
    loading,
}: {
    data: DailyEffortResponse | null;
    loading: boolean;
}) {
    const days = data?.days ?? [];
    const hasCalls = days.some((day) => day.calls > 0);

    return (
        <div className="mt-6 mb-4">
            <h2 className="mb-3 font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                Effort against results · Daily
            </h2>

            <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
                <h3 className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                    Effort against results, day by day
                </h3>
                <p className="mt-1 max-w-xl text-[13px] text-gray-500 dark:text-gray-400">
                    The grey bars are calls placed. The blue bars are
                    conversations that actually happened. Where the two are
                    furthest apart, effort is being spent without return.
                </p>

                <div className="mt-3 flex items-center gap-4">
                    <LegendItem swatch={CALLS_BAR} label="Calls placed" />
                    <LegendItem swatch={REAL_BAR} label="Real conversations" />
                </div>

                {loading ? (
                    <EffortSkeleton />
                ) : !hasCalls ? (
                    <p className="py-14 text-center text-[12px] text-gray-400 dark:text-gray-500">
                        No RMO calls were placed in the selected period.
                    </p>
                ) : (
                    <EffortPlot days={days} />
                )}
            </div>
        </div>
    );
}

function LegendItem({ swatch, label }: { swatch: string; label: string }) {
    return (
        <span className="flex items-center gap-1.5">
            <span
                className={`h-2.5 w-2.5 rounded-[3px] ${swatch}`}
                aria-hidden
            />
            <span className="text-[12px] text-gray-600 dark:text-gray-300">
                {label}
            </span>
        </span>
    );
}

function EffortPlot({ days }: { days: DailyEffortDay[] }) {
    const peak = Math.max(...days.map((day) => Math.max(day.calls, day.real)));
    const ceiling = axisCeiling(peak);
    const ticks = [0, 1, 2, 3, 4].map((i) => (ceiling / 4) * i);
    const height = (value: number) => `${(value / ceiling) * 100}%`;

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

            {/* A month of days is wider than the card; it scrolls sideways
                rather than squeezing the bars into hairlines. */}
            <div className="min-w-0 flex-1 overflow-x-auto pb-1">
                <div
                    className="flex items-end border-b border-gray-200 dark:border-zinc-700"
                    style={{ height: PLOT_HEIGHT }}
                >
                    {days.map((day) => (
                        <EffortColumn
                            key={day.date}
                            day={day}
                            height={height}
                        />
                    ))}
                </div>

                <div className="flex">
                    {days.map((day) => (
                        <span
                            key={day.date}
                            className="min-w-[52px] flex-1 pt-2 text-center font-mono text-[10px] whitespace-nowrap text-gray-400 dark:text-gray-500"
                        >
                            {dayLabel(day.date)}
                        </span>
                    ))}
                </div>
            </div>
        </div>
    );
}

function EffortColumn({
    day,
    height,
}: {
    day: DailyEffortDay;
    height: (value: number) => string;
}) {
    // What the effort bought that day — the reason the two bars are drawn side
    // by side rather than in two charts.
    const reach = day.calls > 0 ? (day.real / day.calls) * 100 : null;

    return (
        <div className="group relative flex h-full min-w-[52px] flex-1 items-end justify-center gap-1.5">
            <div
                className={`w-2.5 rounded-t-[4px] ${CALLS_BAR}`}
                style={{ height: height(day.calls) }}
            />
            <div
                className={`w-2.5 rounded-t-[4px] ${REAL_BAR}`}
                style={{ height: height(day.real) }}
            />

            {/* Hovering anywhere in the day's column, not just on the 10px bar
                itself — the target is the whole slot. */}
            <div className="pointer-events-none absolute bottom-2 left-1/2 z-10 hidden -translate-x-1/2 rounded-lg border border-black/6 bg-white px-2.5 py-1.5 whitespace-nowrap shadow-md group-hover:block dark:border-white/10 dark:bg-zinc-800">
                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                    {dayLabel(day.date)}
                </p>
                <p className="mt-0.5 text-[11px] text-gray-700 dark:text-gray-200">
                    {day.calls.toLocaleString()} placed ·{' '}
                    {day.real.toLocaleString()} real
                </p>
                {reach !== null && (
                    <p className="text-[11px] text-gray-500 dark:text-gray-400">
                        {reach.toFixed(1)}% reached
                    </p>
                )}
            </div>
        </div>
    );
}

function EffortSkeleton() {
    // Heights that read as a plausible run of days rather than a flat block.
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
                        className="flex h-full flex-1 items-end justify-center gap-1.5"
                    >
                        <Skeleton
                            className="w-2.5 rounded-t-[4px]"
                            style={{ height: `${tall}%` }}
                        />
                        <Skeleton
                            className="w-2.5 rounded-t-[4px]"
                            style={{ height: `${tall / 8}%` }}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}
