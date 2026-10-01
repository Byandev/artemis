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
    /**
     * Every call placed, however short — the rollup's own total rather than the
     * two kinds added up. What the all-calls view draws.
     */
    total_calls: number;
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
    /** The same, for the table — it has no bars to explain. */
    tableBlurb: string;
    /** What the empty state says was not placed. */
    noun: string;
    /** The two figure columns, named for the cut they are counting. */
    placedColumn: string;
    realColumn: string;
}[] = [
    {
        key: 'all',
        label: 'All calls',
        blurb: 'The pale bar is every call placed, as the rollup counted them; the solid bar beside it is the ones that became a conversation. Where the two are furthest apart, effort is being spent without return.',
        tableBlurb:
            'Every call placed, as the rollup counted them, beside the ones that became a conversation. Reached is conversations over calls placed.',
        noun: 'calls',
        placedColumn: 'Total placed',
        realColumn: 'Total conversations',
    },
    {
        key: 'rmo',
        label: 'RMO only',
        blurb: 'The pale bar is the RMO calls placed; the solid bar beside it is the ones that became a conversation. Where the two are furthest apart, effort is being spent without return.',
        tableBlurb:
            'The RMO calls placed beside the ones that became a conversation. Reached is conversations over calls placed.',
        noun: 'RMO calls',
        placedColumn: 'Calls placed',
        realColumn: 'Conversations',
    },
    {
        key: 'verification',
        label: 'Verification only',
        blurb: 'The pale bar is the order-verification calls placed; the solid bar beside it is the ones that became a conversation. Where the two are furthest apart, effort is being spent without return.',
        tableBlurb:
            'The order-verification calls placed beside the ones that became a conversation. Reached is conversations over calls placed.',
        noun: 'order-verification calls',
        placedColumn: 'Calls placed',
        realColumn: 'Conversations',
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
 * The weight is how far the call got: a pale step for every call placed, the
 * full step for the ones that became a conversation. So the pale bar is always
 * the whole and the solid bar beside it is always the part.
 *
 * The hue is which calls are on screen — blue for every call and for the RMO
 * cut of them, green when the view narrows to order verification, the same two
 * the comparison panel draws from. One pair of bars per slot either way: a slot
 * is a single figure and its subset, not a mix to be stacked.
 *
 * Validated against both surfaces. Each hue reads as a ramp (light-end 2.65:1
 * light / 2.16:1 dark, clear of the 2:1 ordinal floor), and the two hues
 * separate at each weight — pale pair and solid pair both pass CVD and
 * normal-vision gates in both modes.
 */
const CALLS_PLACED = 'bg-[#86b6ef] dark:bg-[#184f95]';
const CALLS_REAL = 'bg-[#2a78d6] dark:bg-[#3987e5]';
const VERIFICATION_PLACED = 'bg-[#53b05b] dark:bg-[#115d1e]';
const VERIFICATION_REAL = 'bg-[#008300] dark:bg-[#008300]';

/** Which pair of fills a scope wears. */
const swatches = (scope: Scope) =>
    scope === 'verification'
        ? { placed: VERIFICATION_PLACED, real: VERIFICATION_REAL }
        : { placed: CALLS_PLACED, real: CALLS_REAL };

/** The pale fill and the solid one a scope draws with. */
type Fill = { placed: string; real: string };

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
 * One slot reduced to what the selected scope draws: the calls placed, and the
 * ones that became a conversation.
 *
 * Every slot arrives carrying both kinds of call and the rollup's own total of
 * them, so narrowing the scope is arithmetic rather than another request.
 * All calls reads that total rather than adding the kinds up — the figure the
 * rollup recorded, which is also what the cards at the top of the page report.
 */
interface EffortColumnData {
    key: string;
    label: string;
    tooltip: string;
    /** The long form, for a table row. */
    rowLabel: string;
    /** Every call the scope counts, however short — the whole. */
    placed: number;
    /** The ones that lasted long enough to be a conversation — the part. */
    real: number;
}

function toColumn(bucket: EffortBucket, scope: Scope): EffortColumnData {
    const [placed, real] =
        scope === 'rmo'
            ? [bucket.calls, bucket.real]
            : scope === 'verification'
              ? [bucket.verification_calls, bucket.verification_real]
              : [bucket.total_calls, bucket.real + bucket.verification_real];

    return {
        key: bucket.key,
        label: bucket.label,
        tooltip: bucket.tooltip,
        rowLabel: bucket.rowLabel ?? bucket.label,
        placed,
        real,
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
    const fill = swatches(scope);

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
                        <LegendItem
                            swatch={fill.placed}
                            label={copy.placedColumn}
                        />
                        <LegendItem
                            swatch={fill.real}
                            label={copy.realColumn}
                        />
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
                        fill={fill}
                        columnWidth={columnWidth}
                    />
                ) : (
                    <EffortTable
                        columns={columns}
                        copy={copy}
                        fill={fill}
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
    fill,
    columnWidth,
}: {
    columns: EffortColumnData[];
    fill: Fill;
    columnWidth: number;
}) {
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
                            fill={fill}
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

function Bar({
    value,
    swatch,
    height,
}: {
    value: number;
    swatch: string;
    height: (value: number) => string;
}) {
    return (
        <div className="flex h-full w-2.5 flex-col justify-end">
            <div
                className={`w-full rounded-t-[4px] ${swatch}`}
                style={{ height: height(value) }}
            />
        </div>
    );
}

function EffortColumn({
    column,
    fill,
    height,
    columnWidth,
}: {
    column: EffortColumnData;
    fill: Fill;
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
            <Bar value={column.placed} swatch={fill.placed} height={height} />
            <Bar value={column.real} swatch={fill.real} height={height} />

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
 * Three of them whatever the scope — what was placed, what it came to, and the
 * one over the other — because that is what a slot is. The headings take the
 * scope's own wording, so the all-calls table says it is reading totals.
 *
 * Quiet rows are listed rather than dropped — that a Sunday, or the small hours,
 * carried nothing is worth reading — so the rows scroll inside the card with the
 * header pinned above them and the period's totals pinned below.
 */
function EffortTable({
    columns,
    copy,
    fill,
    rowHeading,
}: {
    columns: EffortColumnData[];
    copy: (typeof SCOPES)[number];
    fill: Fill;
    rowHeading: string;
}) {
    const totals = columns.reduce(
        (sum, column) => ({
            placed: sum.placed + column.placed,
            real: sum.real + column.real,
        }),
        { placed: 0, real: 0 },
    );

    return (
        <div className="mt-4 max-h-96 overflow-auto rounded-[14px] border border-black/6 dark:border-white/6">
            <table className="w-full border-collapse">
                {/* The rule under the pinned header is a shadow rather than a
                    border: a collapsed border does not travel with a sticky
                    row, and the header would scroll into the figures. */}
                <thead className="sticky top-0 z-10 bg-white shadow-[inset_0_-1px_0_rgba(0,0,0,0.06)] dark:bg-zinc-900 dark:shadow-[inset_0_-1px_0_rgba(255,255,255,0.06)]">
                    <tr>
                        <Th align="left">{rowHeading}</Th>
                        <Th swatch={fill.placed}>{copy.placedColumn}</Th>
                        <Th swatch={fill.real}>{copy.realColumn}</Th>
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
                            <Td>{column.placed}</Td>
                            <Td>{column.real}</Td>
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
                        <Td strong>{totals.placed}</Td>
                        <Td strong>{totals.real}</Td>
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
