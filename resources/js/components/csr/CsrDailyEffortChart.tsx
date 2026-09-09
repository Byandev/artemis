import { Skeleton } from '@/components/ui/skeleton';
import { useState } from 'react';

export interface DailyEffortDay {
    /** `YYYY-MM-DD`. Every day in the range is present, zeros included. */
    date: string;
    /** Every RMO call placed that day, however short. */
    calls: number;
    /** The subset that lasted long enough to be a conversation. */
    real: number;
    /** Every order-verification call placed that day, however short. */
    verification_calls: number;
    /** The subset of those that lasted long enough to be a conversation. */
    verification_real: number;
}

export interface DailyEffortResponse {
    range: { from: string; to: string };
    days: DailyEffortDay[];
    totals: {
        calls: number;
        real: number;
        verification_calls: number;
        verification_real: number;
    };
}

/** Which calls the chart is drawing — both kinds, or either on its own. */
type Scope = 'all' | 'rmo' | 'verification';

/**
 * The tabs, and the copy that goes with each.
 *
 * The blurb and the empty state say different things per scope, so they live
 * beside the label rather than as a ternary at each of the three call sites.
 */
const SCOPES: {
    key: Scope;
    label: string;
    blurb: string;
    empty: string;
}[] = [
    {
        key: 'all',
        label: 'All calls',
        blurb: 'The pale bar is every call placed; the solid bar beside it is the ones that became a conversation. Blue is chasing a parcel, green is confirming an order. Where the two are furthest apart, effort is being spent without return.',
        empty: 'No calls were placed in the selected period.',
    },
    {
        key: 'rmo',
        label: 'RMO only',
        blurb: 'The pale bar is the RMO calls placed; the solid bar beside it is the ones that became a conversation. Where the two are furthest apart, effort is being spent without return.',
        empty: 'No RMO calls were placed in the selected period.',
    },
    {
        key: 'verification',
        label: 'Verification only',
        blurb: 'The pale bar is the order-verification calls placed; the solid bar beside it is the ones that became a conversation. Where the two are furthest apart, effort is being spent without return.',
        empty: 'No order-verification calls were placed in the selected period.',
    },
];

/**
 * Two hues, two weights, and between them the whole chart.
 *
 * The hue is the kind of call — blue for RMO work, green for order
 * verification, the same two the comparison panel draws from. The weight is how
 * far the call got: a pale step for every call placed, the full step for the
 * ones that became a conversation. So a pale bar is always the whole and the
 * solid bar beside it is always the part, whichever hue they are wearing.
 *
 * Validated against both surfaces. Each hue reads as a ramp (light-end 2.65:1
 * light / 2.16:1 dark, clear of the 2:1 ordinal floor); the two stacks separate
 * at each weight — pale pair and solid pair both pass CVD and normal-vision
 * gates in both modes.
 */
const RMO_PLACED = 'bg-[#86b6ef] dark:bg-[#184f95]';
const RMO_REAL = 'bg-[#2a78d6] dark:bg-[#3987e5]';
const VERIFICATION_PLACED = 'bg-[#53b05b] dark:bg-[#115d1e]';
const VERIFICATION_REAL = 'bg-[#008300] dark:bg-[#008300]';

/** Plot height in pixels. Bars are sized against it. */
const PLOT_HEIGHT = 240;

/**
 * The shortest a bar or segment is allowed to be while still standing for
 * something.
 *
 * One conversation against a range that peaked at four hundred is a twentieth
 * of a pixel — it rounds away, and the day reads as though nobody got through
 * at all. A floor costs a little accuracy at the bottom of the scale and buys
 * back the only thing the segment is there to say: this happened.
 */
const MIN_BAR_HEIGHT = 3;

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
 * One day reduced to what the selected scope draws: two stacks, each split the
 * same way.
 *
 * Both kinds of call arrive on every day, so narrowing to RMO is arithmetic
 * rather than another request — verification drops out of both stacks and what
 * is left is a plain pair of bars.
 */
interface EffortColumnData {
    date: string;
    rmoCalls: number;
    verificationCalls: number;
    /** The two together — what the effort stack measures. */
    placed: number;
    rmoReal: number;
    verificationReal: number;
    /** The two together — what the results stack measures. */
    real: number;
}

function toColumn(day: DailyEffortDay, scope: Scope): EffortColumnData {
    const rmoCalls = scope === 'verification' ? 0 : day.calls;
    const rmoReal = scope === 'verification' ? 0 : day.real;
    const verificationCalls = scope === 'rmo' ? 0 : day.verification_calls;
    const verificationReal = scope === 'rmo' ? 0 : day.verification_real;

    return {
        date: day.date,
        rmoCalls,
        verificationCalls,
        placed: rmoCalls + verificationCalls,
        rmoReal,
        verificationReal,
        real: rmoReal + verificationReal,
    };
}

/**
 * Effort against results, day by day.
 *
 * The call cards at the top of the page give the period's totals; this puts
 * them across the days that made them. A week where the calls held up but the
 * conversations fell away reads here as the gap between the pale bar and the
 * solid one widening — which a period total cannot show.
 *
 * Both bars are stacked because a CSR's day is two jobs, not one: chasing a
 * parcel (RMO) and confirming an order (verification). Splitting the effort bar
 * as well as the results bar is what keeps the chart honest on a day where the
 * calls went out and nothing came back — the mix of work still shows, where a
 * single neutral bar would say only that somebody dialled.
 *
 * The filter drops either kind out of all four marks, leaving a plain pair of
 * bars for the one that is left — the RMO tab agreeing with the RMO cards line
 * for line. It is arithmetic on a response that always carries both, so
 * switching costs no request.
 */
export default function CsrDailyEffortChart({
    data,
    loading,
}: {
    data: DailyEffortResponse | null;
    loading: boolean;
}) {
    const [scope, setScope] = useState<Scope>('all');

    const columns = (data?.days ?? []).map((day) => toColumn(day, scope));
    const hasCalls = columns.some((column) => column.placed > 0);

    const copy = SCOPES.find((option) => option.key === scope) ?? SCOPES[0];
    const showsRmo = scope !== 'verification';
    const showsVerification = scope !== 'rmo';
    // With one kind on screen the hue carries no meaning of its own, so the
    // legend drops the qualifier and names the two weights plainly.
    const qualify = showsRmo && showsVerification;

    return (
        <div className="mt-6 mb-4">
            <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                    Effort against results · Daily
                </h2>

                <div className="flex items-center gap-0.5 self-start rounded-[10px] bg-stone-100 p-0.5 dark:bg-zinc-800">
                    {SCOPES.map((option) => (
                        <button
                            key={option.key}
                            type="button"
                            disabled={loading}
                            onClick={() => setScope(option.key)}
                            className={
                                option.key === scope
                                    ? 'rounded-lg bg-white px-3 py-1.5 text-[12px] font-medium text-emerald-700 shadow-sm disabled:opacity-70 dark:bg-zinc-900 dark:text-emerald-400'
                                    : 'cursor-pointer rounded-lg px-3 py-1.5 text-[12px] text-gray-500 hover:text-gray-800 disabled:cursor-default dark:text-gray-400 dark:hover:text-gray-200'
                            }
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
                <h3 className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                    Effort against results, day by day
                </h3>
                <p className="mt-1 max-w-xl text-[13px] text-gray-500 dark:text-gray-400">
                    {copy.blurb}
                </p>

                <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5">
                    {showsRmo && (
                        <>
                            <LegendItem
                                swatch={RMO_PLACED}
                                label={
                                    qualify
                                        ? 'RMO calls placed'
                                        : 'Calls placed'
                                }
                            />
                            <LegendItem
                                swatch={RMO_REAL}
                                label={
                                    qualify
                                        ? 'RMO conversations'
                                        : 'Real conversations'
                                }
                            />
                        </>
                    )}
                    {showsVerification && (
                        <>
                            <LegendItem
                                swatch={VERIFICATION_PLACED}
                                label={
                                    qualify
                                        ? 'Verification calls placed'
                                        : 'Calls placed'
                                }
                            />
                            <LegendItem
                                swatch={VERIFICATION_REAL}
                                label={
                                    qualify
                                        ? 'Verification conversations'
                                        : 'Real conversations'
                                }
                            />
                        </>
                    )}
                </div>

                {loading ? (
                    <EffortSkeleton />
                ) : !hasCalls ? (
                    <p className="py-14 text-center text-[12px] text-gray-400 dark:text-gray-500">
                        {copy.empty}
                    </p>
                ) : (
                    <EffortPlot columns={columns} scope={scope} />
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

function EffortPlot({
    columns,
    scope,
}: {
    columns: EffortColumnData[];
    scope: Scope;
}) {
    // Each stack is measured whole, so the axis has to clear the two segments
    // together rather than the taller of them.
    const peak = Math.max(
        ...columns.map((column) => Math.max(column.placed, column.real)),
    );
    const ceiling = axisCeiling(peak);
    const ticks = [0, 1, 2, 3, 4].map((i) => (ceiling / 4) * i);

    /** True to the scale — what the axis labels are placed by. */
    const scale = (value: number) => (value / ceiling) * PLOT_HEIGHT;

    /** The same, floored so a non-zero segment cannot round away to nothing. */
    const height = (value: number) =>
        value > 0 ? `${Math.max(MIN_BAR_HEIGHT, scale(value))}px` : '0px';

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
                        style={{ bottom: `${scale(tick)}px` }}
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
                    {columns.map((column) => (
                        <EffortColumn
                            key={column.date}
                            column={column}
                            scope={scope}
                            height={height}
                        />
                    ))}
                </div>

                <div className="flex">
                    {columns.map((column) => (
                        <span
                            key={column.date}
                            className="min-w-[52px] flex-1 pt-2 text-center font-mono text-[10px] whitespace-nowrap text-gray-400 dark:text-gray-500"
                        >
                            {dayLabel(column.date)}
                        </span>
                    ))}
                </div>
            </div>
        </div>
    );
}

/**
 * RMO on the bottom, verification above it, parted by a 2px sliver of the card
 * so the two fills never touch. Only the segment that ends the stack is
 * rounded — the join stays square.
 */
function BarStack({
    base,
    top,
    baseSwatch,
    topSwatch,
    height,
}: {
    base: number;
    top: number;
    baseSwatch: string;
    topSwatch: string;
    height: (value: number) => string;
}) {
    return (
        <div className="flex h-full w-2.5 flex-col justify-end">
            {top > 0 && (
                <div
                    className={`w-full rounded-t-[4px] ${topSwatch} ${base > 0 ? 'mb-[2px]' : ''}`}
                    style={{ height: height(top) }}
                />
            )}
            <div
                className={`w-full ${baseSwatch} ${top > 0 ? '' : 'rounded-t-[4px]'}`}
                style={{ height: height(base) }}
            />
        </div>
    );
}

function EffortColumn({
    column,
    scope,
    height,
}: {
    column: EffortColumnData;
    scope: Scope;
    height: (value: number) => string;
}) {
    // What the effort bought that day — the reason the stacks are drawn side by
    // side rather than in two charts.
    const reach =
        column.placed > 0 ? (column.real / column.placed) * 100 : null;

    return (
        <div className="group relative flex h-full min-w-[52px] flex-1 items-end justify-center gap-1.5">
            <BarStack
                base={column.rmoCalls}
                top={column.verificationCalls}
                baseSwatch={RMO_PLACED}
                topSwatch={VERIFICATION_PLACED}
                height={height}
            />
            <BarStack
                base={column.rmoReal}
                top={column.verificationReal}
                baseSwatch={RMO_REAL}
                topSwatch={VERIFICATION_REAL}
                height={height}
            />

            {/* Hovering anywhere in the day's column, not just on the 10px bar
                itself — the target is the whole slot. */}
            <div className="pointer-events-none absolute bottom-2 left-1/2 z-10 hidden -translate-x-1/2 rounded-lg border border-black/6 bg-white px-2.5 py-1.5 whitespace-nowrap shadow-md group-hover:block dark:border-white/10 dark:bg-zinc-800">
                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                    {dayLabel(column.date)}
                </p>
                <p className="mt-0.5 text-[11px] text-gray-700 dark:text-gray-200">
                    {column.placed.toLocaleString()} placed ·{' '}
                    {column.real.toLocaleString()} real
                </p>
                {scope === 'all' && (
                    <>
                        <p className="text-[11px] text-gray-500 dark:text-gray-400">
                            RMO {column.rmoCalls.toLocaleString()} placed ·{' '}
                            {column.rmoReal.toLocaleString()} real
                        </p>
                        <p className="text-[11px] text-gray-500 dark:text-gray-400">
                            Verification{' '}
                            {column.verificationCalls.toLocaleString()} placed ·{' '}
                            {column.verificationReal.toLocaleString()} real
                        </p>
                    </>
                )}
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
