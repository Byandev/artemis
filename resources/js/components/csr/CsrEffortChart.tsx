import { Skeleton } from '@/components/ui/skeleton';
import { ChartColumn, Table2 } from 'lucide-react';
import { useState, type ReactNode } from 'react';

/** Which calls the chart is drawing — both kinds, or either on its own. */
export type Scope = 'all' | 'rmo' | 'verification';

/** Whether the section draws its buckets or lists them as figures. */
export type View = 'chart' | 'table';

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
    /**
     * What the table prints in the first cell. A row has room the axis does not,
     * so "9:00 AM – 9:59 AM" can replace "9a". Falls back to `label`.
     */
    rowLabel?: string;
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
    /** The same, for the table — it has no bars and no colour to explain. */
    tableBlurb: string;
    /** What the empty state says was not placed. */
    noun: string;
}[] = [
    {
        key: 'all',
        label: 'All calls',
        blurb: 'The pale bar is every call placed; the solid bar beside it is the ones that became a conversation. Blue is chasing a parcel, green is confirming an order. Where the two are furthest apart, effort is being spent without return.',
        tableBlurb:
            'Every call placed beside the ones that became a conversation, split into chasing a parcel (RMO) and confirming an order (verification). Reached is conversations over calls placed.',
        noun: 'calls',
    },
    {
        key: 'rmo',
        label: 'RMO only',
        blurb: 'The pale bar is the RMO calls placed; the solid bar beside it is the ones that became a conversation. Where the two are furthest apart, effort is being spent without return.',
        tableBlurb:
            'The RMO calls placed beside the ones that became a conversation. Reached is conversations over calls placed.',
        noun: 'RMO calls',
    },
    {
        key: 'verification',
        label: 'Verification only',
        blurb: 'The pale bar is the order-verification calls placed; the solid bar beside it is the ones that became a conversation. Where the two are furthest apart, effort is being spent without return.',
        tableBlurb:
            'The order-verification calls placed beside the ones that became a conversation. Reached is conversations over calls placed.',
        noun: 'order-verification calls',
    },
];

/**
 * Draw the buckets, or list them.
 *
 * The chart answers "where is the gap widest" at a glance; the table answers
 * "by how much, on which row" — and it is what a screen reader, a copy-paste
 * and a colourblind reader get out of the section. Same numbers either way, so
 * flipping costs no request.
 */
const VIEWS: { key: View; label: string; icon: typeof ChartColumn }[] = [
    { key: 'chart', label: 'Graph', icon: ChartColumn },
    { key: 'table', label: 'Table', icon: Table2 },
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
    /** The long form, for a table row. */
    rowLabel: string;
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
        rowLabel: bucket.rowLabel ?? bucket.label,
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
    rowHeading,
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
    /** What a row of the table stands for — heads its first column. */
    rowHeading: string;
    buckets: EffortBucket[];
    loading: boolean;
    /** Slot width. Narrower where there are more of them to fit. */
    columnWidth?: number;
    /** Bar heights, as percentages, for the loading state. */
    skeletonBars?: number[];
}) {
    const [scope, setScope] = useState<Scope>('all');
    const [view, setView] = useState<View>('chart');

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

                {/* Two groups: which calls, then how to read them. They wrap
                    rather than squeeze when the row runs out of width. */}
                <div className="flex flex-wrap items-center gap-2">
                    <TabGroup>
                        {SCOPES.map((option) => (
                            <TabButton
                                key={option.key}
                                active={option.key === scope}
                                disabled={loading}
                                onClick={() => setScope(option.key)}
                            >
                                {option.label}
                            </TabButton>
                        ))}
                    </TabGroup>

                    <TabGroup>
                        {VIEWS.map((option) => (
                            <TabButton
                                key={option.key}
                                active={option.key === view}
                                disabled={loading}
                                onClick={() => setView(option.key)}
                            >
                                <option.icon className="h-3.5 w-3.5" />
                                {option.label}
                            </TabButton>
                        ))}
                    </TabGroup>
                </div>
            </div>

            <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
                <div>
                    <h3 className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                        {heading}
                    </h3>
                    <p className="mt-1 max-w-xl text-[13px] text-gray-500 dark:text-gray-400">
                        {view === 'chart' ? copy.blurb : copy.tableBlurb}
                        {note ? ` ${note}` : ''}
                    </p>
                </div>

                {/* The legend names bar fills, so it belongs to the chart —
                    the table heads its own columns instead. */}
                {view === 'chart' && (
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
                )}

                {loading ? (
                    view === 'chart' ? (
                        <EffortSkeleton bars={skeletonBars} />
                    ) : (
                        <TableSkeleton rows={skeletonBars.length} />
                    )
                ) : !hasCalls ? (
                    <p className="py-14 text-center text-[12px] text-gray-400 dark:text-gray-500">
                        No {copy.noun} were placed in the selected period.
                    </p>
                ) : view === 'chart' ? (
                    <EffortPlot
                        columns={columns}
                        scope={scope}
                        columnWidth={columnWidth}
                    />
                ) : (
                    <EffortTable
                        columns={columns}
                        scope={scope}
                        rowHeading={rowHeading}
                    />
                )}
            </div>
        </div>
    );
}

/** The pill the tabs sit in — one per group of them. */
function TabGroup({ children }: { children: ReactNode }) {
    return (
        <div className="flex items-center gap-0.5 self-start rounded-[10px] bg-stone-100 p-0.5 dark:bg-zinc-800">
            {children}
        </div>
    );
}

function TabButton({
    active,
    disabled,
    onClick,
    children,
}: {
    active: boolean;
    disabled: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={active}
            disabled={disabled}
            onClick={onClick}
            className={
                active
                    ? 'inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-[12px] font-medium text-emerald-700 shadow-sm disabled:opacity-70 dark:bg-zinc-900 dark:text-emerald-400'
                    : 'inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-[12px] text-gray-500 hover:text-gray-800 disabled:cursor-default dark:text-gray-400 dark:hover:text-gray-200'
            }
        >
            {children}
        </button>
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

/**
 * The same columns as figures.
 *
 * The scope filter applies here as it does to the bars, so a narrowed table is
 * a plain calls-and-conversations pair; the all-calls table carries the RMO and
 * verification split alongside the totals, because that split is what the two
 * stacked bars are saying.
 *
 * Quiet rows are listed rather than dropped — that a Sunday, or the small hours,
 * carried nothing is worth reading — so the rows scroll inside the card with the
 * header pinned above them and the period's totals pinned below.
 */
function EffortTable({
    columns,
    scope,
    rowHeading,
}: {
    columns: EffortColumnData[];
    scope: Scope;
    rowHeading: string;
}) {
    const split = scope === 'all';

    const totals = columns.reduce(
        (sum, column) => ({
            rmoCalls: sum.rmoCalls + column.rmoCalls,
            rmoReal: sum.rmoReal + column.rmoReal,
            verificationCalls: sum.verificationCalls + column.verificationCalls,
            verificationReal: sum.verificationReal + column.verificationReal,
            placed: sum.placed + column.placed,
            real: sum.real + column.real,
        }),
        {
            rmoCalls: 0,
            rmoReal: 0,
            verificationCalls: 0,
            verificationReal: 0,
            placed: 0,
            real: 0,
        },
    );

    // The single-kind tables wear that kind's hues, so a column means the same
    // thing it meant on the bar it replaces.
    const placedSwatch =
        scope === 'verification' ? VERIFICATION_PLACED : RMO_PLACED;
    const realSwatch = scope === 'verification' ? VERIFICATION_REAL : RMO_REAL;

    return (
        <div className="mt-4 max-h-96 overflow-auto rounded-[14px] border border-black/6 dark:border-white/6">
            <table className="w-full border-collapse">
                {/* The rule under the pinned header is a shadow rather than a
                    border: a collapsed border does not travel with a sticky
                    row, and the header would scroll into the figures. */}
                <thead className="sticky top-0 z-10 bg-white shadow-[inset_0_-1px_0_rgba(0,0,0,0.06)] dark:bg-zinc-900 dark:shadow-[inset_0_-1px_0_rgba(255,255,255,0.06)]">
                    <tr>
                        <Th align="left">{rowHeading}</Th>
                        {split ? (
                            <>
                                <Th swatch={RMO_PLACED}>RMO placed</Th>
                                <Th swatch={RMO_REAL}>RMO conversations</Th>
                                <Th swatch={VERIFICATION_PLACED}>
                                    Verification placed
                                </Th>
                                <Th swatch={VERIFICATION_REAL}>
                                    Verification conversations
                                </Th>
                                <Th>Total placed</Th>
                                <Th>Total conversations</Th>
                            </>
                        ) : (
                            <>
                                <Th swatch={placedSwatch}>Calls placed</Th>
                                <Th swatch={realSwatch}>Conversations</Th>
                            </>
                        )}
                        <Th>Reached</Th>
                    </tr>
                </thead>

                <tbody>
                    {columns.map((column) => (
                        <tr
                            key={column.key}
                            className="border-b border-black/5 last:border-0 dark:border-white/5"
                        >
                            <td className="px-4 py-3 text-left font-mono text-[12px] whitespace-nowrap text-gray-700 dark:text-gray-300">
                                {column.rowLabel}
                            </td>
                            {split ? (
                                <>
                                    <Td>{column.rmoCalls}</Td>
                                    <Td>{column.rmoReal}</Td>
                                    <Td>{column.verificationCalls}</Td>
                                    <Td>{column.verificationReal}</Td>
                                    <Td strong>{column.placed}</Td>
                                    <Td strong>{column.real}</Td>
                                </>
                            ) : (
                                <>
                                    <Td>{column.placed}</Td>
                                    <Td>{column.real}</Td>
                                </>
                            )}
                            <Reached
                                placed={column.placed}
                                real={column.real}
                            />
                        </tr>
                    ))}
                </tbody>

                {/* Pinned to the foot of the scroller: however far down a month
                    the reader is, the period's own figures stay in view. */}
                <tfoot className="sticky bottom-0 bg-stone-50 shadow-[inset_0_1px_0_rgba(0,0,0,0.06)] dark:bg-zinc-800/60 dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]">
                    <tr>
                        <td className="px-4 py-3 text-left font-mono text-[10px] tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                            Total
                        </td>
                        {split ? (
                            <>
                                <Td strong>{totals.rmoCalls}</Td>
                                <Td strong>{totals.rmoReal}</Td>
                                <Td strong>{totals.verificationCalls}</Td>
                                <Td strong>{totals.verificationReal}</Td>
                                <Td strong>{totals.placed}</Td>
                                <Td strong>{totals.real}</Td>
                            </>
                        ) : (
                            <>
                                <Td strong>{totals.placed}</Td>
                                <Td strong>{totals.real}</Td>
                            </>
                        )}
                        <Reached placed={totals.placed} real={totals.real} />
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

function Th({
    children,
    align = 'right',
    swatch,
}: {
    children: ReactNode;
    align?: 'left' | 'right';
    /** The fill this column stands for on the chart, where it has one. */
    swatch?: string;
}) {
    return (
        <th
            className={`px-4 py-3 font-mono text-[10px] font-normal tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500 ${
                align === 'left' ? 'text-left' : 'text-right'
            }`}
        >
            <span
                className={`flex items-center gap-1.5 whitespace-nowrap ${
                    align === 'left' ? 'justify-start' : 'justify-end'
                }`}
            >
                {swatch && (
                    <span
                        className={`h-2 w-2 shrink-0 rounded-[2px] ${swatch}`}
                        aria-hidden
                    />
                )}
                {children}
            </span>
        </th>
    );
}

/** A count. Zero goes pale, so the rows that carried work are the ones that read. */
function Td({ children, strong }: { children: number; strong?: boolean }) {
    const weight = strong
        ? 'font-semibold text-gray-900 dark:text-gray-100'
        : 'text-gray-700 dark:text-gray-300';

    return (
        <td
            className={`px-4 py-3 text-right font-mono text-[12px] tabular-nums ${
                children === 0
                    ? 'font-normal text-gray-300 dark:text-gray-600'
                    : weight
            }`}
        >
            {children.toLocaleString()}
        </td>
    );
}

/** What the effort bought, as a percentage. Nothing placed, nothing to divide. */
function Reached({ placed, real }: { placed: number; real: number }) {
    return (
        <td className="px-4 py-3 text-right font-mono text-[12px] font-semibold text-gray-900 tabular-nums dark:text-gray-100">
            {placed === 0 ? (
                <span className="font-normal text-gray-300 dark:text-gray-600">
                    —
                </span>
            ) : (
                <>
                    {((real / placed) * 100).toFixed(1)}
                    <span className="text-[10px] text-gray-400 dark:text-gray-500">
                        %
                    </span>
                </>
            )}
        </td>
    );
}

/**
 * The placeholder is a hint, not a measurement: a full round of the clock would
 * be two dozen grey rows, so it stops at the height a reader takes in at once.
 */
function TableSkeleton({ rows }: { rows: number }) {
    return (
        <div className="mt-4 overflow-hidden rounded-[14px] border border-black/6 dark:border-white/6">
            {Array.from({ length: Math.min(rows, 8) }).map((_, i) => (
                <div
                    key={i}
                    className="flex items-center gap-4 border-b border-black/5 px-4 py-3 last:border-0 dark:border-white/5"
                >
                    <Skeleton className="h-3 w-16" />
                    <div className="ml-auto flex items-center gap-4">
                        <Skeleton className="h-3 w-10" />
                        <Skeleton className="h-3 w-10" />
                        <Skeleton className="h-3 w-12" />
                    </div>
                </div>
            ))}
        </div>
    );
}
