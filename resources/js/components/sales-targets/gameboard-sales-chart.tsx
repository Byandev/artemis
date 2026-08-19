import { formatPeso } from '@/pages/workspaces/sales-targets/shared';
import { useState } from 'react';

export interface SalesVsTargetPoint {
    team_id: number;
    name: string;
    sales: number;
    target: number;
}

/** Axis ticks want the shape of the number, not every digit of it. */
function compactPeso(value: number): string {
    if (value >= 1_000_000) return `₱${+(value / 1_000_000).toFixed(1)}M`;
    if (value >= 1_000) return `₱${Math.round(value / 1_000)}K`;

    return `₱${Math.round(value)}`;
}

/** A round-ish ceiling above the data, so the top gridline is a readable number. */
function niceCeiling(value: number): number {
    if (value <= 0) return 1;

    const magnitude = 10 ** Math.floor(Math.log10(value));

    return Math.ceil(value / (magnitude / 2)) * (magnitude / 2);
}

/*
 * Series colours, validated against both surfaces rather than picked by eye.
 * Sales carries the brand; target is deliberately desaturated — it is the
 * benchmark, not a rival category, so the eye lands on what was actually sold.
 *
 * light  #0d8264 / #94949e — CVD ΔE 10.9 (protan), normal-vision ΔE 17.2
 * dark   #5cd6b7 / #8b8b95 — CVD ΔE 15.8 (deutan), normal-vision ΔE 20.2
 *
 * brand-500 was rejected for light mode: 1.9:1 against white, so a thin bar
 * would fade into the panel.
 */
const SALES_FILL = 'bg-[#0d8264] dark:bg-[#5cd6b7]';
const TARGET_FILL = 'bg-[#94949e] dark:bg-[#8b8b95]';

/**
 * Two bars per team: what it sold, beside what it was asked to sell.
 *
 * One y-axis, because both bars are the same measure in the same pesos — the
 * height difference between the pair IS the answer, which is exactly what a
 * second scale would destroy.
 */
export function GameboardSalesChart({
    points,
}: {
    points: SalesVsTargetPoint[];
}) {
    const [hovered, setHovered] = useState<number | null>(null);

    if (points.length === 0) {
        return null;
    }

    const peak = Math.max(
        ...points.map((point) => Math.max(point.sales, point.target)),
        0,
    );
    const max = niceCeiling(peak || 1);
    const ticks = [1, 0.75, 0.5, 0.25, 0].map((fraction) => fraction * max);
    // Y axis, gridlines and bars are three stacked layers — one height for all.
    const plotHeight = 'h-[168px] 2xl:h-[230px]';

    const active = hovered === null ? null : points[hovered];
    const activeAchievement =
        active && active.target > 0
            ? Math.round((active.sales / active.target) * 1000) / 10
            : null;

    return (
        <section className="h-full">
            <div className="h-full rounded-[12px] border border-black/6 bg-white/85 px-3 py-2.5 shadow-[0_1px_2px_rgba(9,52,41,0.04),0_8px_24px_-12px_rgba(9,52,41,0.10)] dark:border-white/8 dark:bg-zinc-900/80 dark:shadow-[0_1px_2px_rgba(0,0,0,0.4),0_8px_24px_-12px_rgba(0,0,0,0.6)]">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="my-0! font-mono text-[10px]! font-medium tracking-[0.16em] text-gray-700 uppercase 2xl:text-[12px]! dark:text-gray-200">
                        Sales vs Target
                    </h2>
                    {/* Two series, so the legend is always present. */}
                    <div className="flex items-center gap-3">
                        <span className="flex items-center gap-1.5 text-[10px] text-gray-600 2xl:text-[12px] dark:text-gray-300">
                            <span
                                className={`h-2 w-3 rounded-[2px] 2xl:h-2.5 2xl:w-4 ${SALES_FILL}`}
                            />
                            Sales
                        </span>
                        <span className="flex items-center gap-1.5 text-[10px] text-gray-600 2xl:text-[12px] dark:text-gray-300">
                            <span
                                className={`h-2 w-3 rounded-[2px] 2xl:h-2.5 2xl:w-4 ${TARGET_FILL}`}
                            />
                            Target
                        </span>
                    </div>
                </div>

                <div className="relative mt-3 flex">
                    {/* Y axis */}
                    <div
                        className={`flex w-11 shrink-0 flex-col justify-between pr-1.5 text-right 2xl:w-14 ${plotHeight}`}
                    >
                        {ticks.map((tick) => (
                            <span
                                key={tick}
                                className="font-mono text-[8px] leading-none text-gray-400 tabular-nums 2xl:text-[10px] dark:text-gray-500"
                            >
                                {compactPeso(tick)}
                            </span>
                        ))}
                    </div>

                    <div className="relative min-w-0 flex-1">
                        {/* Gridlines, recessive. */}
                        <div
                            className={`pointer-events-none absolute inset-x-0 top-0 flex flex-col justify-between ${plotHeight}`}
                        >
                            {ticks.map((tick) => (
                                <div
                                    key={tick}
                                    className="h-px w-full bg-black/6 dark:bg-white/8"
                                />
                            ))}
                        </div>

                        <div
                            className={`relative flex items-end gap-1.5 ${plotHeight}`}
                        >
                            {points.map((point, index) => (
                                <div
                                    key={point.team_id}
                                    className="relative flex h-full flex-1 items-end justify-center"
                                    onMouseEnter={() => setHovered(index)}
                                    onMouseLeave={() => setHovered(null)}
                                >
                                    {/* Full-height hit area, bigger than the marks. */}
                                    <div
                                        className={`absolute inset-0 rounded-sm transition-colors ${
                                            hovered === index
                                                ? 'bg-black/4 dark:bg-white/6'
                                                : ''
                                        }`}
                                    />

                                    {/* 2px of surface between the pair keeps them
                                        two bars rather than one wide one. */}
                                    <div className="relative flex h-full w-full max-w-14 items-end justify-center gap-[2px]">
                                        <div
                                            className={`w-1/2 rounded-t-[4px] transition-[height] duration-500 ${SALES_FILL}`}
                                            style={{
                                                height: `${Math.max((point.sales / max) * 100, point.sales > 0 ? 1 : 0)}%`,
                                            }}
                                        />
                                        <div
                                            className={`w-1/2 rounded-t-[4px] transition-[height] duration-500 ${TARGET_FILL}`}
                                            style={{
                                                height: `${Math.max((point.target / max) * 100, point.target > 0 ? 1 : 0)}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* X axis */}
                        <div className="mt-1.5 flex gap-1.5">
                            {points.map((point) => (
                                <span
                                    key={point.team_id}
                                    title={point.name}
                                    className="min-w-0 flex-1 truncate text-center text-[8px] text-gray-500 2xl:text-[10px] dark:text-gray-400"
                                >
                                    {point.name}
                                </span>
                            ))}
                        </div>

                        {active && (
                            <div className="pointer-events-none absolute top-0 right-0 rounded-lg border border-black/8 bg-white px-2.5 py-1.5 shadow-sm dark:border-white/10 dark:bg-zinc-800">
                                <p className="max-w-32 truncate font-mono text-[9px] text-gray-500 2xl:text-[11px] dark:text-gray-400">
                                    {active.name}
                                </p>
                                <p className="font-mono text-[11px] font-semibold text-gray-900 tabular-nums 2xl:text-[13px] dark:text-white">
                                    {formatPeso(active.sales)}
                                </p>
                                <p className="font-mono text-[9px] text-gray-500 tabular-nums 2xl:text-[11px] dark:text-gray-400">
                                    Target {formatPeso(active.target)}
                                    {activeAchievement === null
                                        ? ''
                                        : ` · ${activeAchievement}%`}
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
