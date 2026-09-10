import { Skeleton } from '@/components/ui/skeleton';
import { useState, type ReactNode } from 'react';

/** Which calls the chart is drawing — both kinds, or either on its own. */
export type Scope = 'all' | 'rmo' | 'verification';

/**
 * One slot on the axis: the four counts, and the two names put on them.
 *
 * The counts are the same four whatever the slot stands for, which is what
 * lets a day and an hour of the day be drawn by the one chart — the label is
 * the only thing that knows which it is.
 */
export interface EffortBucket {
    key: string;
    /** What the axis prints under the column — kept short, it repeats. */
    label: string;
    /** What the tooltip prints above the figures; there is room for the long form. */
    tooltip: string;
    /** Every RMO call placed, however short. */
    calls: number;
    /** The subset that lasted long enough to be a conversation. */
    real: number;
    /** Every order-verification call placed, however short. */
    verification_calls: number;
    /** The subset of those that lasted long enough to be a conversation. */
    verification_real: number;
}

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
    /** What the empty state says was not placed. */
    noun: string;
}[] = [
    {
        key: 'all',
        label: 'All calls',
        blurb: 'The pale bar is every call placed; the solid bar beside it is the ones that became a conversation. Blue is chasing a parcel, green is confirming an order. Where the two are furthest apart, effort is being spent without return.',
        noun: 'calls',
    },
    {
        key: 'rmo',
        label: 'RMO only',
        blurb: 'The pale bar is the RMO calls placed; the solid bar beside it is the ones that became a conversation. Where the two are furthest apart, effort is being spent without return.',
        noun: 'RMO calls',
    },
    {
        key: 'verification',
        label: 'Verification only',
        blurb: 'The pale bar is the order-verification calls placed; the solid bar beside it is the ones that became a conversation. Where the two are furthest apart, effort is being spent without return.',
        noun: 'order-verification calls',
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
 * of a pixel — it rounds away, and the slot reads as though nobody got through
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

/**
 * One slot reduced to what the selected scope draws: two stacks, each split the
 * same way.
 *
 * Both kinds of call arrive on every slot, so narrowing to RMO is arithmetic
 * rather than another request — verification drops out of both stacks and what
 * is left is a plain pair of bars.
 */
interface EffortColumnData {
    key: string;
    label: string;
    tooltip: string;
    rmoCalls: number;
    verificationCalls: number;
    /** The two together — what the effort stack measures. */
    placed: number;
    rmoReal: number;
    verificationReal: number;
    /** The two together — what the results stack measures. */
    real: number;
}

function toColumn(bucket: EffortBucket, scope: Scope): EffortColumnData {
    const rmoCalls = scope === 'verification' ? 0 : bucket.calls;
    const rmoReal = scope === 'verification' ? 0 : bucket.real;
    const verificationCalls = scope === 'rmo' ? 0 : bucket.verification_calls;
    const verificationReal = scope === 'rmo' ? 0 : bucket.verification_real;

    return {
        key: bucket.key,
        label: bucket.label,
        tooltip: bucket.tooltip,
        rmoCalls,
        verificationCalls,
        placed: rmoCalls + verificationCalls,
        rmoReal,
        verificationReal,
        real: rmoReal + verificationReal,
    };
}

/**
 * Effort against results — calls placed beside the ones that became a
 * conversation, over whatever the buckets are counted by.
 *
 * The call cards at the top of the page give the period's totals; this puts
 * them across the slots that made them. A stretch where the calls held up but
 * the conversations fell away reads here as the gap between the pale bar and
 * the solid one widening — which a period total cannot show.
 *
 * Both bars are stacked because a CSR's day is two jobs, not one: chasing a
 * parcel (RMO) and confirming an order (verification). Splitting the effort bar
 * as well as the results bar is what keeps the chart honest where the calls
 * went out and nothing came back — the mix of work still shows, where a single
 * neutral bar would say only that somebody dialled.
 *
 * The filter drops either kind out of all four marks, leaving a plain pair of
 * bars for the one that is left — the RMO tab agreeing with the RMO cards line
 * for line. It is arithmetic on a response that always carries both, so
 * switching costs no request.
 */
export default function CsrEffortChart({
    eyebrow,
    heading,
    note,
    control,
    emptySubject = 'in the selected period',
    buckets,
    loading,
    columnWidth = 52,
    skeletonBars = [62, 78, 50, 92, 70, 34, 22],
}: {
    /** The small caps line above the card — which cut of the data this is. */
    eyebrow: string;
    /** The card's own title: what the bars are counted by. */
    heading: string;
    /** An extra sentence under the blurb, where the cut needs explaining. */
    note?: string;
    /** A control of the chart's own, beside the heading — a day picker, say. */
    control?: ReactNode;
    /** How the empty state names what it was looking at: "on Fri, Aug 14". */
    emptySubject?: string;
    buckets: EffortBucket[];
    loading: boolean;
    /** Slot width. Narrower where there are more of them to fit. */
    columnWidth?: number;
    /** Bar heights, as percentages, for the loading state. */
    skeletonBars?: number[];
}) {
    const [scope, setScope] = useState<Scope>('all');

    const columns = buckets.map((bucket) => toColumn(bucket, scope));
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
                    {eyebrow}
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
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {heading}
                        </h3>
                        <p className="mt-1 max-w-xl text-[13px] text-gray-500 dark:text-gray-400">
                            {copy.blurb}
                            {note ? ` ${note}` : ''}
                        </p>
                    </div>

                    {control && <div className="shrink-0">{control}</div>}
                </div>

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
                    <EffortSkeleton bars={skeletonBars} />
                ) : !hasCalls ? (
                    <p className="py-14 text-center text-[12px] text-gray-400 dark:text-gray-500">
                        No {copy.noun} were placed {emptySubject}.
                    </p>
                ) : (
                    <EffortPlot
                        columns={columns}
                        scope={scope}
                        columnWidth={columnWidth}
                    />
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
    columnWidth,
}: {
    columns: EffortColumnData[];
    scope: Scope;
    columnWidth: number;
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

            {/* A month of days, or a full round of the clock, is wider than the
                card; it scrolls sideways rather than squeezing the bars into
                hairlines. */}
            <div className="min-w-0 flex-1 overflow-x-auto pb-1">
                <div
                    className="flex items-end border-b border-gray-200 dark:border-zinc-700"
                    style={{ height: PLOT_HEIGHT }}
                >
                    {columns.map((column) => (
                        <EffortColumn
                            key={column.key}
                            column={column}
                            scope={scope}
                            height={height}
                            columnWidth={columnWidth}
                        />
                    ))}
                </div>

                <div className="flex">
                    {columns.map((column) => (
                        <span
                            key={column.key}
                            className="flex-1 pt-2 text-center font-mono text-[10px] whitespace-nowrap text-gray-400 dark:text-gray-500"
                            style={{ minWidth: columnWidth }}
                        >
                            {column.label}
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
    columnWidth,
}: {
    column: EffortColumnData;
    scope: Scope;
    height: (value: number) => string;
    columnWidth: number;
}) {
    // What the effort bought — the reason the stacks are drawn side by side
    // rather than in two charts.
    const reach =
        column.placed > 0 ? (column.real / column.placed) * 100 : null;

    return (
        <div
            className="group relative flex h-full flex-1 items-end justify-center gap-1.5"
            style={{ minWidth: columnWidth }}
        >
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

            {/* Hovering anywhere in the slot's column, not just on the 10px bar
                itself — the target is the whole slot. */}
            <div className="pointer-events-none absolute bottom-2 left-1/2 z-10 hidden -translate-x-1/2 rounded-lg border border-black/6 bg-white px-2.5 py-1.5 whitespace-nowrap shadow-md group-hover:block dark:border-white/10 dark:bg-zinc-800">
                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                    {column.tooltip}
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

function EffortSkeleton({ bars }: { bars: number[] }) {
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
