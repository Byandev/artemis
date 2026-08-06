import RefreshButton from '../refresh-button';
import { useInventoryStat } from '../use-inventory-stat';
import {
    days,
    EmptyState,
    KIND_COLOR,
    num,
    panelClass,
    PanelHead,
    pct,
    TableSkeleton,
} from './shared';
import type { PipelineData } from './types';

/**
 * The whole demand pipeline in one bar: what still needs ordering, what is
 * ordered but has not left the building, and what a supplier is holding.
 *
 * Segment width is quantity, so the pile you can see is the pile that exists.
 * Naming a blocked stage is the bottleneck panel's job at the top of the page —
 * this one just shows the shape.
 */
export default function PipelinePanel({ slug }: { slug: string }) {
    const { data, loading, error, refetch } = useInventoryStat<PipelineData>(
        slug,
        'po-flow/pipeline',
    );

    const stages = (data?.stages ?? []).filter((s) => s.units > 0);
    const gap = data?.po_needed ?? 0;

    return (
        <section className={panelClass}>
            <PanelHead
                title="The whole pipeline"
                help={
                    <>
                        <b>Every unit between demand and the shelf.</b> The
                        dashed block is demand with no purchase order behind it
                        yet. The amber blocks are orders raised but still in
                        your own approval and payment queues — no supplier has
                        seen them. The blue block is with a supplier.
                        <br />
                        <br />
                        Width is quantity, so the biggest block is the biggest
                        pile — though busy is not the same as blocked, and which
                        step is actually holding things up is the question the
                        panel at the top of the page answers.
                    </>
                }
                action={
                    <RefreshButton
                        onClick={refetch}
                        loading={loading}
                        error={error}
                        label="the pipeline"
                    />
                }
            >
                Left to right: what needs ordering, what is ordered but still
                inside the company, and what a supplier is holding. Bar width is
                quantity.
            </PanelHead>

            <div className="px-[18px] pb-6">
                {loading ? (
                    <TableSkeleton rows={2} cols={4} />
                ) : error ? (
                    <EmptyState message="Couldn't load the pipeline." />
                ) : !data || (stages.length === 0 && gap === 0) ? (
                    <EmptyState message="Nothing on order and nothing to reorder — the pipeline is clear." />
                ) : (
                    <>
                        <div className="flex h-[58px] gap-0.5">
                            {/* The gap comes first and is drawn as a hole, not a
                                fill: it is demand with no order behind it, so
                                showing it as stock would be a lie. */}
                            {gap > 0 && (
                                <Segment
                                    flex={gap}
                                    label="Not ordered yet"
                                    value={gap}
                                    title={`PO Needed — ${num(gap)} units the reorder maths says to buy, with no purchase order raised`}
                                    hollow
                                />
                            )}
                            {stages.map((stage) => (
                                <Segment
                                    key={stage.name}
                                    flex={stage.units}
                                    label={stage.name}
                                    value={stage.units}
                                    color={KIND_COLOR[stage.kind]}
                                    title={`${stage.name} — ${num(stage.units)} units across ${stage.orders} order${stage.orders === 1 ? '' : 's'}, oldest ${days(stage.oldest)}`}
                                />
                            ))}
                        </div>

                        {data.internal_units > 0 && data.supplier_units > 0 && (
                            <div className="mt-1.5 flex gap-0.5 text-[11px]">
                                {gap > 0 && (
                                    <div
                                        style={{ flex: gap }}
                                        className="border-t border-dashed border-black/10 pt-1.5 text-gray-400 dark:border-white/10 dark:text-gray-500"
                                    >
                                        not ordered
                                    </div>
                                )}
                                {/* Rule and text take the segment's own hue, so
                                    the brace reads as belonging to the bar
                                    above it rather than as separate furniture. */}
                                <div
                                    style={{
                                        flex: data.internal_units,
                                        borderTopColor: KIND_COLOR.internal,
                                        color: KIND_COLOR.internal,
                                    }}
                                    className="border-t pt-1.5 font-semibold"
                                >
                                    ↑ {num(data.internal_units)} inside the
                                    company ·{' '}
                                    {pct(data.internal_units, data.total_units)}
                                    %
                                </div>
                                <div
                                    style={{ flex: data.supplier_units }}
                                    className="border-t border-black/10 pt-1.5 text-gray-400 dark:border-white/10 dark:text-gray-500"
                                >
                                    {num(data.supplier_units)} with a supplier ·{' '}
                                    {pct(data.supplier_units, data.total_units)}
                                    %
                                </div>
                            </div>
                        )}

                        <div className="mt-4 flex flex-wrap gap-4 text-[11px] text-gray-500 dark:text-gray-400">
                            <Key hollow>Not ordered yet</Key>
                            <Key color={KIND_COLOR.internal}>
                                Inside the company
                            </Key>
                            <Key color={KIND_COLOR.supplier}>
                                With a supplier
                            </Key>
                        </div>
                    </>
                )}
            </div>
        </section>
    );
}

function Segment({
    flex,
    label,
    value,
    title,
    color,
    hollow = false,
}: {
    flex: number;
    label: string;
    value: number;
    title: string;
    color?: string;
    hollow?: boolean;
}) {
    return (
        <div
            tabIndex={0}
            title={title}
            style={{ flex, background: hollow ? undefined : color }}
            className={`relative flex min-w-0 flex-col justify-center rounded px-2.5 transition-[filter] hover:brightness-110 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:outline-none dark:focus-visible:ring-gray-100 ${
                hollow
                    ? 'border border-dashed border-gray-400 dark:border-gray-500'
                    : 'text-white'
            }`}
        >
            <span
                className={`font-mono text-sm leading-tight font-semibold tabular-nums ${hollow ? 'text-gray-600 dark:text-gray-300' : ''}`}
            >
                {num(value)}
            </span>
            <span
                className={`mt-0.5 truncate text-[9px] tracking-[0.07em] uppercase ${hollow ? 'text-gray-400 dark:text-gray-500' : 'opacity-90'}`}
            >
                {label}
            </span>
        </div>
    );
}

function Key({
    color,
    hollow,
    children,
}: {
    color?: string;
    hollow?: boolean;
    children: React.ReactNode;
}) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <i
                className={`h-2.5 w-2.5 shrink-0 rounded-[2px] ${hollow ? 'border border-dashed border-gray-400 dark:border-gray-500' : ''}`}
                style={hollow ? undefined : { background: color }}
            />
            {children}
        </span>
    );
}
