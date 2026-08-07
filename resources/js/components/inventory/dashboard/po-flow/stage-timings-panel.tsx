import RefreshButton from '../refresh-button';
import { useInventoryStat } from '../use-inventory-stat';
import {
    days,
    EmptyState,
    KIND_COLOR,
    panelClass,
    PanelHead,
    TableSkeleton,
} from './shared';
import type { StageTimingData, TimingStep } from './types';

/** Above this share of moves landing on three days, a stage is a queue. */
const BATCH_THRESHOLD = 40;

/**
 * How long each workflow step takes, from the ERP's own status trail.
 *
 * One bar for typical time, one tick for the slowest one in ten — layered
 * percentile bars were harder to read than they were informative. Steps that
 * always complete instantly never appear: they are one click, not a queue, and
 * the API filters them out.
 */
export default function StageTimingsPanel({ slug }: { slug: string }) {
    const { data, loading, error, refetch } = useInventoryStat<StageTimingData>(
        slug,
        'po-flow/stage-timings',
    );

    /**
     * Internal steps, the internal total, then the supplier leg. A separator is
     * drawn above each summary block so the per-step rows read as adding up to
     * the row beneath them.
     */
    const rows: { step: TimingStep; separated: boolean }[] = data
        ? [
              ...data.steps
                  .filter((s) => s.samples > 0)
                  .map((step) => ({ step, separated: false })),
              ...(data.internal_total.samples > 0
                  ? [{ step: data.internal_total, separated: true }]
                  : []),
              ...(data.released_to_delivery.samples > 0
                  ? [{ step: data.released_to_delivery, separated: true }]
                  : []),
              ...data.delivery_steps.map((step, i) => ({
                  step,
                  separated: i === 0 && data.released_to_delivery.samples === 0,
              })),
          ]
        : [];
    const max = Math.max(...rows.map((r) => r.step.p90 ?? 0), 1);
    const busiest = Math.max(
        ...(data?.clustering ?? []).map((c) => c.busiest_three_pct),
        0,
    );

    return (
        <section className={panelClass}>
            <PanelHead
                title="How long each step takes"
                help={
                    <>
                        <b>How long each hand-off actually takes</b>, from the
                        ERP&rsquo;s own status trail. The bar is the typical
                        order; the tick is the slowest one in ten, so a short
                        bar with a far-right tick means most orders fly through
                        and a few get stranded.
                        <br />
                        <br />
                        Supplier rows restart the clock at release, so they
                        measure the supplier alone. The fill levels show the
                        shape of a delivery: an order that lands 90% in a week
                        and dribbles the last 10% over a month reads very
                        differently from one that arrives whole.
                        <br />
                        <br />
                        Steps that always complete instantly are left out — they
                        are one click, not a queue.
                    </>
                }
                action={
                    <RefreshButton
                        onClick={refetch}
                        loading={loading}
                        error={error}
                        label="step timings"
                    />
                }
            >
                Typical time per step, with a marker showing the slowest one in
                ten. The grey rows run door to door from the issue date, so they
                answer &ldquo;how long until stock actually turns up&rdquo;.
            </PanelHead>

            <div className="px-[18px] pb-6">
                {loading ? (
                    <TableSkeleton rows={4} cols={3} />
                ) : error ? (
                    <EmptyState message="Couldn't load step timings." />
                ) : rows.length === 0 ? (
                    <EmptyState message="No status history yet — step timings appear once orders carry a trail." />
                ) : (
                    <>
                        <div className="flex flex-col gap-3.5">
                            {rows.map(({ step, separated }) => (
                                <Bar
                                    key={step.label}
                                    step={step}
                                    max={max}
                                    separated={separated}
                                />
                            ))}
                        </div>

                        <div className="mt-3.5 flex flex-wrap gap-4 text-[11px] text-gray-400 dark:text-gray-500">
                            <span className="inline-flex items-center gap-1.5">
                                <i
                                    className="h-2.5 w-2.5 rounded-[2px]"
                                    style={{ background: KIND_COLOR.internal }}
                                />
                                inside the company
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <i
                                    className="h-2.5 w-2.5 rounded-[2px]"
                                    style={{ background: KIND_COLOR.supplier }}
                                />
                                at the supplier
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <i
                                    className="h-2.5 w-2.5 rounded-[2px]"
                                    style={{ background: KIND_COLOR.total }}
                                />
                                raised through to delivered
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <i className="block h-3 w-0.5 bg-gray-400 dark:bg-gray-500" />
                                slowest 1 in 10
                            </span>
                        </div>

                        {data && data.clustering.length > 0 && (
                            <p className="mt-4 border-t border-black/5 pt-3.5 text-xs leading-relaxed text-gray-500 dark:border-white/5 dark:text-gray-400">
                                <b className="font-semibold text-gray-900 dark:text-gray-100">
                                    {busiest >= BATCH_THRESHOLD
                                        ? 'This is a queue, not a workload.'
                                        : 'Work is spread evenly — no queue here.'}
                                </b>{' '}
                                {data.clustering.map((c, i) => (
                                    <span key={c.label}>
                                        {i > 0 && ', '}
                                        {c.label}{' '}
                                        <b className="font-semibold text-gray-900 dark:text-gray-100">
                                            {c.busiest_three_pct}%
                                        </b>
                                    </span>
                                ))}{' '}
                                of all moves landed on just three days.{' '}
                                {busiest >= BATCH_THRESHOLD
                                    ? 'Real work spreads out; bursts like these mean orders are waiting for someone to sit down and do a batch.'
                                    : 'Nothing is piling up waiting for a batch run.'}
                            </p>
                        )}
                    </>
                )}
            </div>
        </section>
    );
}

function Bar({
    step,
    max,
    separated,
}: {
    step: TimingStep;
    max: number;
    separated: boolean;
}) {
    const colour = KIND_COLOR[step.kind];
    const p50 = step.p50 ?? 0;
    const p90 = step.p90 ?? 0;

    return (
        <div
            className={`grid grid-cols-[168px_1fr_48px] items-center gap-2.5 ${
                separated
                    ? 'border-t border-black/5 pt-3.5 dark:border-white/5'
                    : ''
            }`}
        >
            <span className="text-[11px] text-gray-500 dark:text-gray-400">
                {step.label}
            </span>
            <div
                className="relative h-[18px] rounded bg-black/5 dark:bg-white/5"
                title={`${step.label} — typical ${days(step.p50)}, slowest 10% ${days(step.p90)}, from ${step.samples} order${step.samples === 1 ? '' : 's'}`}
            >
                <div
                    className="absolute top-0 left-0 h-[18px] rounded"
                    style={{
                        width: `${Math.max((p50 / max) * 100, 1.5)}%`,
                        background: colour,
                    }}
                />
                <div
                    className="absolute -top-[3px] w-0.5 rounded-sm opacity-60"
                    style={{
                        left: `calc(${(p90 / max) * 100}% - 1px)`,
                        height: 24,
                        background: colour,
                    }}
                />
            </div>
            <span className="text-right font-mono text-xs font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                {days(step.p50)}
            </span>
        </div>
    );
}
