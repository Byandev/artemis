import RefreshButton from '../refresh-button';
import { useInventoryStat } from '../use-inventory-stat';
import {
    EmptyState,
    headClass,
    num,
    panelClass,
    PanelHead,
    TableSkeleton,
} from './shared';
import type { PipelineData } from './types';

/**
 * Age intensity, one hue light to dark. Sequential rather than categorical:
 * the bands are ordered, so a rainbow would imply differences in kind where
 * there are only differences in degree.
 */
const HEAT = [
    'bg-amber-600/10 text-amber-800 dark:bg-amber-600/15 dark:text-amber-200/80',
    'bg-amber-600/22 text-amber-900 dark:bg-amber-600/28 dark:text-amber-100/85',
    'bg-amber-600/40 text-amber-950 dark:bg-amber-600/46 dark:text-amber-50',
    'bg-amber-600/62 text-white dark:bg-amber-600/68 dark:text-white',
    'bg-amber-600/88 text-white dark:bg-amber-600/92 dark:text-white',
];

/**
 * Open units by stage and age. Reading down a column finds what is old;
 * reading across a row finds the stage that lets it rot.
 *
 * The point of the grid is the top-right corner: a dark cell there is stock
 * that stopped moving inside a stage that should have passed it on.
 */
export default function AgingPanel({ slug }: { slug: string }) {
    const { data, loading, error, refetch } = useInventoryStat<PipelineData>(
        slug,
        'po-flow/pipeline',
    );

    const stages = data?.stages ?? [];
    const buckets = data?.buckets ?? [];
    const worst =
        stages
            .filter((s) => s.overdue_units > 0)
            .sort((a, b) => b.overdue_units - a.overdue_units)[0]?.name ?? null;

    return (
        <section className={panelClass}>
            <PanelHead
                title="What is going stale, and where"
                action={
                    <RefreshButton
                        onClick={refetch}
                        loading={loading}
                        error={error}
                        label="aging"
                    />
                }
            >
                Units by stage and age. Darker means older — a dark block on the
                right is stock that has stopped moving.
            </PanelHead>

            <div className="px-[18px] pb-6">
                {loading ? (
                    <TableSkeleton rows={4} cols={6} />
                ) : error ? (
                    <EmptyState message="Couldn't load aging." />
                ) : stages.length === 0 ? (
                    <EmptyState message="Nothing open to age." />
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse">
                                <thead>
                                    <tr>
                                        <th className={headClass}>Stage</th>
                                        {buckets.map((b) => (
                                            <th
                                                key={b.key}
                                                className={`${headClass} text-right!`}
                                            >
                                                {b.label}
                                            </th>
                                        ))}
                                        <th
                                            className={`${headClass} text-right!`}
                                        >
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {stages.map((stage) => (
                                        <tr key={stage.name}>
                                            <td
                                                className={`py-1 pr-3 text-[11px] whitespace-nowrap ${
                                                    stage.name === worst
                                                        ? 'font-semibold text-gray-900 shadow-[inset_2px_0_0_var(--color-red-600)] dark:text-gray-100'
                                                        : 'text-gray-500 dark:text-gray-400'
                                                }`}
                                            >
                                                <span className="pl-2">
                                                    {stage.name}
                                                </span>
                                            </td>
                                            {buckets.map((b, i) => {
                                                const units =
                                                    stage.buckets[b.key] ?? 0;

                                                return (
                                                    <td
                                                        key={b.key}
                                                        className="py-1 pr-1"
                                                    >
                                                        <div
                                                            title={`${stage.name}, ${b.label}: ${num(units)} units`}
                                                            className={`rounded px-2.5 py-2 text-right font-mono text-[11px] font-semibold tabular-nums ${
                                                                units
                                                                    ? HEAT[i]
                                                                    : 'text-gray-300 dark:text-gray-600'
                                                            }`}
                                                        >
                                                            {units
                                                                ? num(units)
                                                                : '—'}
                                                        </div>
                                                    </td>
                                                );
                                            })}
                                            <td className="py-1">
                                                <div className="px-2.5 py-2 text-right font-mono text-[11px] font-semibold text-gray-500 tabular-nums dark:text-gray-400">
                                                    {num(stage.units)}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-3.5 flex items-center gap-2 text-[10px] text-gray-400 dark:text-gray-500">
                            <span>newer</span>
                            {HEAT.map((cls, i) => (
                                <i
                                    key={i}
                                    className={`block h-[7px] w-5 rounded-[2px] ${cls}`}
                                />
                            ))}
                            <span>older</span>
                        </div>
                    </>
                )}
            </div>
        </section>
    );
}
