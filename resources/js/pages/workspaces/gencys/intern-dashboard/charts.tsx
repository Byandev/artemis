import { ApexOptions } from 'apexcharts';
import { ArrowDown, ArrowUp, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import Chart from 'react-apexcharts';

type Delta = { pct: number | null; status: 'up' | 'down' | 'flat' };

interface Kpis {
    total_sales: number;
    total_ad_spent: number;
    roas: number | null;
    total_orders: number;
    deltas: {
        total_sales: Delta;
        total_ad_spent: Delta;
        roas: Delta;
        total_orders: Delta;
    };
}

export interface ChartsData {
    kpis: Kpis;
    // Per-advertiser sales & ad spend for the selected day (highest sales first).
    by_advertiser: { name: string; sales: number; ad_spent: number }[];
}

// Categorical series palette — validated colorblind-safe (see dataviz skill).
// Fixed order; hues assigned by position, never cycled. Colors follow the
// advertiser, so each person keeps their hue across every chart.
const SERIES_LIGHT = [
    '#2a78d6',
    '#1baf7a',
    '#eda100',
    '#4a3aa7',
    '#008300',
    '#e34948',
];
const SERIES_DARK = [
    '#3987e5',
    '#199e70',
    '#c98500',
    '#9085e9',
    '#008300',
    '#e66767',
];

/** Reactively track the app's dark theme (toggled as a class on <html>). */
function useIsDark(): boolean {
    const [isDark, setIsDark] = useState(
        () =>
            typeof document !== 'undefined' &&
            document.documentElement.classList.contains('dark'),
    );
    useEffect(() => {
        const el = document.documentElement;
        const obs = new MutationObserver(() =>
            setIsDark(el.classList.contains('dark')),
        );
        obs.observe(el, { attributes: true, attributeFilter: ['class'] });
        return () => obs.disconnect();
    }, []);
    return isDark;
}

const pesoFull = (v: number) =>
    `₱${v.toLocaleString('en-PH', { maximumFractionDigits: 0 })}`;

const pesoCompact = (v: number) => {
    const a = Math.abs(v);
    if (a >= 1e6) return `₱${(v / 1e6).toFixed(1)}M`;
    if (a >= 1e3) return `₱${(v / 1e3).toFixed(0)}K`;
    return `₱${v.toFixed(0)}`;
};

export default function DashboardCharts({
    data,
    loading,
}: {
    data: ChartsData;
    loading: boolean;
}) {
    const isDark = useIsDark();
    const colors = isDark ? SERIES_DARK : SERIES_LIGHT;
    const axisColor = isDark ? '#71717a' : '#9ca3af';
    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';
    const surface = isDark ? '#18181b' : '#ffffff';
    const themeMode: 'light' | 'dark' = isDark ? 'dark' : 'light';

    // Explicit tooltip chrome (inline-styled so app CSS can't strip it).
    const tipBg = isDark ? '#27272a' : '#ffffff';
    const tipBorder = isDark ? 'rgba(255,255,255,0.10)' : 'rgba(0,0,0,0.08)';
    const tipText = isDark ? '#e4e4e7' : '#374151';
    const tipShell = `background:${tipBg};border:1px solid ${tipBorder};border-radius:10px;padding:10px 13px;box-shadow:0 6px 24px rgba(0,0,0,0.14);font-family:'DM Sans',sans-serif;font-size:12px;`;

    const baseChart: ApexOptions['chart'] = {
        fontFamily: 'DM Sans, sans-serif',
        toolbar: { show: false },
        zoom: { enabled: false },
        background: 'transparent',
    };
    const axisLabelStyle = {
        fontSize: '11px',
        fontFamily: 'DM Mono, monospace',
        colors: axisColor,
    };

    const names = data.by_advertiser.map((a) => a.name);

    // Shared bar options — one distributed bar per advertiser so each keeps its
    // own colour. Used identically for sales & ad spend so the two charts read
    // as a matched pair (peso y-axis, one bar per advertiser).
    const barOptions = (): ApexOptions => ({
        chart: { ...baseChart, type: 'bar' },
        theme: { mode: themeMode },
        colors,
        plotOptions: {
            bar: {
                distributed: true,
                borderRadius: 4,
                columnWidth: '55%',
            },
        },
        legend: { show: false },
        dataLabels: { enabled: false },
        grid: {
            borderColor: gridColor,
            strokeDashArray: 4,
            xaxis: { lines: { show: false } },
            padding: { top: 0, right: 12, bottom: 0, left: 8 },
        },
        tooltip: {
            custom: ({
                dataPointIndex,
                w,
            }: {
                dataPointIndex: number;
                w: {
                    globals: {
                        labels: string[];
                        series: number[][];
                        colors: string[];
                    };
                };
            }) => {
                const g = w.globals;
                const name = g.labels[dataPointIndex] ?? '';
                const val = g.series[0]?.[dataPointIndex] ?? 0;
                const dot = g.colors[dataPointIndex] ?? colors[0];
                return `<div style="${tipShell}"><div style="display:flex;align-items:center;gap:6px;font-weight:600;color:${tipText};"><span style="width:8px;height:8px;border-radius:50%;background:${dot};flex-shrink:0;"></span>${name}</div><div style="margin-top:3px;font-family:'DM Mono',monospace;color:${tipText};">${pesoFull(val)}</div></div>`;
            },
        },
        xaxis: {
            categories: names,
            axisBorder: { show: false },
            axisTicks: { show: false },
            tooltip: { enabled: false },
            labels: {
                style: axisLabelStyle,
                rotate: -35,
                rotateAlways: names.length > 4,
                trim: true,
                hideOverlappingLabels: false,
            },
        },
        yaxis: {
            min: 0,
            labels: { formatter: pesoCompact, style: axisLabelStyle },
        },
    });

    const salesSeries = [
        { name: 'Sales', data: data.by_advertiser.map((a) => a.sales) },
    ];
    const adSeries = [
        { name: 'Ad spend', data: data.by_advertiser.map((a) => a.ad_spent) },
    ];

    // ── Ad spend share (donut), selected day ────────────────────────────────
    const pieTotal = data.by_advertiser.reduce((a, s) => a + s.ad_spent, 0);
    const pieOptions: ApexOptions = {
        chart: { ...baseChart, type: 'donut' },
        theme: { mode: themeMode },
        colors,
        labels: names,
        legend: {
            position: 'right',
            fontSize: '12px',
            fontFamily: 'DM Sans, sans-serif',
            fontWeight: 500,
            labels: { colors: axisColor },
            markers: { size: 6, strokeWidth: 0 },
            itemMargin: { vertical: 3 },
        },
        // On narrow screens a right-side legend starves the donut of width and
        // shrinks it to a dot — drop the legend below the donut instead.
        responsive: [
            {
                breakpoint: 640,
                options: {
                    legend: {
                        position: 'bottom',
                        horizontalAlign: 'center',
                        itemMargin: { horizontal: 6, vertical: 3 },
                    },
                },
            },
        ],
        stroke: { width: 2, colors: [surface] },
        dataLabels: {
            enabled: true,
            formatter: (val) => `${Number(val).toFixed(0)}%`,
            style: {
                fontSize: '11px',
                fontFamily: 'DM Sans, sans-serif',
                fontWeight: 600,
            },
            dropShadow: { enabled: false },
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '62%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total ad spend',
                            fontSize: '11px',
                            fontFamily: 'DM Sans, sans-serif',
                            color: axisColor,
                            formatter: () => pesoCompact(pieTotal),
                        },
                    },
                },
            },
        },
        tooltip: {
            custom: ({ seriesIndex }: { seriesIndex: number }) => {
                const s = data.by_advertiser[seriesIndex];
                if (!s) return '';
                const share =
                    pieTotal > 0
                        ? ((s.ad_spent / pieTotal) * 100).toFixed(1)
                        : '0';
                return `<div style="${tipShell}"><div style="display:flex;align-items:center;gap:6px;font-weight:600;color:${tipText};"><span style="width:8px;height:8px;border-radius:50%;background:${colors[seriesIndex % colors.length]};"></span>${s.name}</div><div style="margin-top:3px;color:${tipText};">${pesoFull(s.ad_spent)} · ${share}%</div></div>`;
            },
        },
    };
    const pieSeries = data.by_advertiser.map((s) => s.ad_spent);

    return (
        <div className="relative space-y-4">
            {loading && (
                <div className="absolute inset-0 z-10 flex items-center justify-center rounded-[14px] bg-white/50 dark:bg-zinc-950/50">
                    <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
                </div>
            )}

            {/* Sales by advertiser */}
            <ChartCard
                title="Sales by advertiser"
                subtitle="Each advertiser's sales for the selected day"
            >
                <Chart
                    options={barOptions()}
                    series={salesSeries}
                    type="bar"
                    height={320}
                />
            </ChartCard>

            {/* Ad spend by advertiser — same treatment as sales */}
            <ChartCard
                title="Ad spend by advertiser"
                subtitle="Each advertiser's ad spend for the selected day"
            >
                <Chart
                    options={barOptions()}
                    series={adSeries}
                    type="bar"
                    height={320}
                />
            </ChartCard>

            {/* Ad spend share — donut */}
            <ChartCard
                title="Ad spend share"
                subtitle="Each advertiser's share of ad spend (selected day)"
            >
                <Chart
                    options={pieOptions}
                    series={pieSeries}
                    type="donut"
                    height={320}
                />
            </ChartCard>
        </div>
    );
}

/** Summary stat tiles — rendered at the top of the dashboard. */
export function DashboardKpis({ kpis }: { kpis: ChartsData['kpis'] }) {
    const d = kpis.deltas;
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Kpi
                label="Total Sales"
                value={pesoFull(kpis.total_sales)}
                delta={d.total_sales}
            />
            <Kpi
                label="Total Ad Spend"
                value={pesoFull(kpis.total_ad_spent)}
                delta={d.total_ad_spent}
            />
            <Kpi
                label="Blended ROAS"
                value={kpis.roas != null ? kpis.roas.toFixed(2) : '—'}
                delta={d.roas}
            />
            <Kpi
                label="Total Orders"
                value={kpis.total_orders.toLocaleString('en-PH')}
                delta={d.total_orders}
            />
        </div>
    );
}

function Kpi({
    label,
    value,
    delta,
}: {
    label: string;
    value: string;
    delta?: Delta;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white px-4 py-3 dark:border-white/6 dark:bg-zinc-900">
            <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {label}
            </p>
            <div className="mt-1 flex items-baseline justify-between gap-2">
                <p className="font-mono text-lg font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                    {value}
                </p>
                {delta && <KpiDelta delta={delta} />}
            </div>
        </div>
    );
}

/** Up/down arrow + % change vs the previous day. */
function KpiDelta({ delta }: { delta: Delta }) {
    if (delta.status === 'flat') {
        return (
            <span
                title="vs previous day"
                className="shrink-0 font-mono text-[11px] font-medium text-gray-300 dark:text-gray-600"
            >
                —
            </span>
        );
    }
    const up = delta.status === 'up';
    const cls = up
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-red-600 dark:text-red-400';
    return (
        <span
            title="vs previous day"
            className={`inline-flex shrink-0 items-center gap-0.5 font-mono text-[11px] font-semibold ${cls}`}
        >
            {up ? (
                <ArrowUp className="h-3 w-3" strokeWidth={2.5} />
            ) : (
                <ArrowDown className="h-3 w-3" strokeWidth={2.5} />
            )}
            {delta.pct != null ? `${Math.abs(delta.pct).toFixed(1)}%` : ''}
        </span>
    );
}

function ChartCard({
    title,
    subtitle,
    children,
}: {
    title: string;
    subtitle: string;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-2 px-1">
                <h3 className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                    {title}
                </h3>
                <p className="text-[11px] text-gray-400 dark:text-gray-500">
                    {subtitle}
                </p>
            </div>
            {children}
        </div>
    );
}
