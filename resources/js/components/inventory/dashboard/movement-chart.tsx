import { Skeleton } from '@/components/ui/skeleton';
import { type ApexOptions } from 'apexcharts';
import { format, parseISO } from 'date-fns';
import { RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';
import Chart from 'react-apexcharts';
import { useInventoryStat } from './use-inventory-stat';

interface MovementDay {
    date: string;
    in: number;
    out: number;
}

interface MovementData {
    days: MovementDay[];
}

/**
 * Diverging pair — warm/cool poles that read as opposite, with the zero line as
 * the neutral midpoint. Both modes are selected steps validated against their
 * own surface (not an automatic flip of the light values).
 */
const COLORS = {
    light: { in: '#2a78d6', out: '#e34948' },
    dark: { in: '#3987e5', out: '#e66767' },
};

const INK = {
    light: { text: '#6B7280', grid: 'rgba(0,0,0,0.06)' },
    dark: { text: '#9CA3AF', grid: 'rgba(255,255,255,0.08)' },
};

/** Trailing windows on offer. Must match MOVEMENT_WINDOWS server-side. */
const WINDOWS = [7, 14, 30] as const;
type WindowDays = (typeof WINDOWS)[number];

/** Tracks the `dark` class the appearance hook stamps on <html>. */
function useIsDark(): boolean {
    const [isDark, setIsDark] = useState(
        () =>
            typeof document !== 'undefined' &&
            document.documentElement.classList.contains('dark'),
    );

    useEffect(() => {
        const el = document.documentElement;
        const observer = new MutationObserver(() =>
            setIsDark(el.classList.contains('dark')),
        );
        observer.observe(el, {
            attributes: true,
            attributeFilter: ['class'],
        });

        return () => observer.disconnect();
    }, []);

    return isDark;
}

export default function MovementChart({ slug }: { slug: string }) {
    const [windowDays, setWindow] = useState<WindowDays>(WINDOWS[0]);
    const { data, loading, error, refetch } = useInventoryStat<MovementData>(
        slug,
        'movement',
        { days: windowDays },
    );
    const isDark = useIsDark();

    return (
        // `inv-chart-panel` opts this panel's tooltip into the solid bordered
        // card in app.css — the global rule strips ApexCharts tooltips bare.
        <div className="inv-chart-panel rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Items In / Out
                    </h3>
                    <p className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                        Units moved per day over the last {windowDays} days, up
                        to yesterday — in above the line, out below.
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <RefreshButton
                        onClick={refetch}
                        loading={loading}
                        error={error}
                    />
                    <WindowPicker value={windowDays} onChange={setWindow} />
                </div>
            </div>

            {loading ? (
                <ChartSkeleton columns={windowDays} />
            ) : error ? (
                <EmptyState message="Couldn't load inventory movement." />
            ) : (
                <MovementPlot
                    days={data?.days ?? []}
                    window={windowDays}
                    isDark={isDark}
                />
            )}
        </div>
    );
}

/**
 * Manual refetch. Always available rather than error-only, so a stale panel can
 * be pulled fresh after a delivery or adjustment lands. Doubles as the retry
 * affordance when the fetch failed — one control, two states.
 */
function RefreshButton({
    onClick,
    loading,
    error,
}: {
    onClick: () => void;
    loading: boolean;
    error: boolean;
}) {
    const label = error ? 'Retry loading movement' : 'Refresh movement';

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={loading}
            title={label}
            aria-label={label}
            className={
                error
                    ? 'flex items-center justify-center rounded-md p-1.5 text-red-500 hover:bg-red-50 disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-950/40'
                    : 'flex items-center justify-center rounded-md p-1.5 text-gray-400 hover:bg-zinc-100 hover:text-gray-700 disabled:opacity-50 dark:text-gray-500 dark:hover:bg-zinc-800 dark:hover:text-gray-200'
            }
        >
            <RotateCcw
                className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`}
            />
        </button>
    );
}

/** Segmented control for the trailing window. */
function WindowPicker({
    value,
    onChange,
}: {
    value: WindowDays;
    onChange: (days: WindowDays) => void;
}) {
    return (
        <div
            role="group"
            aria-label="Movement window"
            className="flex items-center gap-0.5 rounded-lg bg-zinc-100 p-0.5 dark:bg-zinc-800"
        >
            {WINDOWS.map((days) => {
                const active = days === value;

                return (
                    <button
                        key={days}
                        type="button"
                        aria-pressed={active}
                        onClick={() => onChange(days)}
                        className={
                            active
                                ? 'rounded-md bg-white px-2.5 py-1 text-[11px] font-medium text-gray-900 shadow-sm dark:bg-zinc-950 dark:text-gray-100'
                                : 'rounded-md px-2.5 py-1 text-[11px] font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
                        }
                    >
                        {days}d
                    </button>
                );
            })}
        </div>
    );
}

function MovementPlot({
    days,
    window,
    isDark,
}: {
    days: MovementDay[];
    window: WindowDays;
    isDark: boolean;
}) {
    if (!days.some((d) => d.in !== 0 || d.out !== 0)) {
        return (
            <EmptyState
                message={`No inventory movement in the last ${window} days.`}
            />
        );
    }

    const colors = isDark ? COLORS.dark : COLORS.light;
    const ink = isDark ? INK.dark : INK.light;

    const categories = days.map((d) => {
        try {
            return format(parseISO(d.date), 'd MMM');
        } catch {
            return d.date;
        }
    });

    // "Out" is plotted negative so it mirrors below the baseline. The tooltip
    // reads it back as an absolute "N units", so nobody sees "-40 units out".
    const series = [
        { name: 'In', data: days.map((d) => d.in) },
        { name: 'Out', data: days.map((d) => -d.out) },
    ];

    // A diverging axis has to carry equal steps per arm, or the two halves are
    // read on different scales. Left to itself Apex only ticks the taller side,
    // so bound the axis symmetrically on a round step.
    const peak = Math.max(1, ...days.map((d) => Math.max(d.in, d.out)));
    const step = niceStep(peak);
    const bound = Math.ceil(peak / step) * step;

    const options: ApexOptions = {
        chart: {
            type: 'bar',
            stacked: true,
            fontFamily: 'DM Sans, sans-serif',
            toolbar: { show: false },
            zoom: { enabled: false },
            background: 'transparent',
        },
        colors: [colors.in, colors.out],
        plotOptions: {
            bar: {
                horizontal: false,
                // Widen the columns as the window grows, so 30 days reads as a
                // bar chart rather than 30 hairlines.
                columnWidth: window <= 7 ? '45%' : window <= 14 ? '60%' : '78%',
                borderRadius: 4,
                borderRadiusApplication: 'end',
            },
        },
        // A surface-coloured hairline between the two arms, so the fills never
        // touch across the baseline.
        stroke: {
            show: true,
            width: 2,
            colors: [isDark ? '#18181b' : '#ffffff'],
        },
        dataLabels: { enabled: false },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'center',
            fontSize: '11px',
            fontFamily: 'DM Sans, sans-serif',
            fontWeight: 500,
            labels: { colors: ink.text },
            markers: { size: 6, shape: 'circle', offsetX: -2 },
            itemMargin: { horizontal: 12, vertical: 0 },
        },
        grid: {
            borderColor: ink.grid,
            strokeDashArray: 4,
            xaxis: { lines: { show: false } },
            padding: { top: 0, right: 12, bottom: 0, left: 8 },
        },
        xaxis: {
            categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
            tooltip: { enabled: false },
            // Beyond a week there is no room for a label per column, so thin
            // them to ~7 evenly spaced dates; the tooltip still names the day.
            tickAmount: Math.min(window, 7),
            labels: {
                rotate: 0,
                hideOverlappingLabels: true,
                style: { colors: ink.text, fontSize: '11px' },
            },
        },
        yaxis: {
            min: -bound,
            max: bound,
            tickAmount: (bound / step) * 2,
            labels: {
                // Signed, NOT Math.abs(): mirroring the arms makes "100" appear
                // twice, and ApexCharts silently drops duplicate y-axis label
                // text — which leaves the whole lower arm unlabelled. The
                // tooltip carries the absolute "N units" reading instead.
                formatter: (v: number) => Math.round(v).toLocaleString('en-PH'),
                style: { colors: ink.text, fontSize: '11px' },
            },
        },
        tooltip: {
            // Always 'light' — app.css styles the card off
            // `.apexcharts-theme-light` and swaps its surface under `.dark`,
            // so Apex's own dark theme would bypass the panel styling entirely.
            theme: 'light',
            shared: true,
            intersect: false,
            fillSeriesColor: false,
            style: { fontSize: '12px', fontFamily: 'DM Sans, sans-serif' },
            marker: { show: true },
            y: {
                formatter: (v: number) =>
                    `${Math.abs(v).toLocaleString('en-PH')} units`,
            },
        },
    };

    return <Chart options={options} series={series} type="bar" height={300} />;
}

/**
 * A round axis step (1/2/5 × a power of ten) aiming for ~3 ticks per arm, so
 * the labels read 100/200/300 rather than 89/178/267.
 */
function niceStep(peak: number): number {
    const raw = peak / 3;
    const magnitude = 10 ** Math.floor(Math.log10(raw));
    const normalized = raw / magnitude;
    const step =
        normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;

    return step * magnitude;
}

/** Column placeholder mirrored around a baseline, matching the chart's shape. */
function ChartSkeleton({ columns }: { columns: number }) {
    // Tighter gutters as the column count climbs, mirroring the real chart.
    const gap = columns <= 7 ? 'gap-3' : columns <= 14 ? 'gap-2' : 'gap-1';

    return (
        <div className="h-[300px]">
            <div className={`flex h-1/2 items-end ${gap}`}>
                {Array.from({ length: columns }).map((_, i) => (
                    <Skeleton
                        key={i}
                        className="flex-1"
                        style={{ height: `${35 + ((i * 29) % 55)}%` }}
                    />
                ))}
            </div>
            <div className={`flex h-1/2 items-start pt-0.5 ${gap}`}>
                {Array.from({ length: columns }).map((_, i) => (
                    <Skeleton
                        key={i}
                        className="flex-1"
                        style={{ height: `${25 + ((i * 41) % 45)}%` }}
                    />
                ))}
            </div>
        </div>
    );
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex h-[300px] items-center justify-center rounded-[12px] border border-dashed border-zinc-200 dark:border-zinc-800">
            <p className="text-sm text-zinc-500 dark:text-zinc-400">
                {message}
            </p>
        </div>
    );
}
