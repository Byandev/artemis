import { ApexOptions } from 'apexcharts';
import { Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import Chart from 'react-apexcharts';

interface Kpis {
    total_sales: number;
    total_ad_spent: number;
    roas: number | null;
    total_orders: number;
    avg_rts_rate: number | null;
}

type Trend = { dates: string[]; series: { name: string; data: number[] }[] };

export interface ChartsData {
    kpis: Kpis;
    sales_trend: Trend;
    ad_spend_trend: Trend;
    ad_spend_share: { name: string; ad_spent: number }[];
}

// Categorical series palette — validated colorblind-safe (see dataviz skill).
// Fixed order; hues assigned by position, never cycled. Colors follow the
// intern, so each person keeps their hue across every chart.
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

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
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

const shortDate = (iso: string) => {
    const [, m, d] = iso.split('-');
    return `${MONTHS[+m - 1]} ${+d}`;
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

    // Shared multi-line options — used identically for sales & ad spend so the
    // two charts read as a matched pair (peso y-axis, one line per intern).
    const lineOptions = (dates: string[]): ApexOptions => ({
        chart: { ...baseChart, type: 'line' },
        theme: { mode: themeMode },
        colors,
        stroke: { curve: 'smooth', width: 2 },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '12px',
            fontFamily: 'DM Sans, sans-serif',
            fontWeight: 500,
            labels: { colors: axisColor },
            markers: { size: 6, strokeWidth: 0 },
            itemMargin: { horizontal: 10 },
        },
        markers: { size: 0, hover: { size: 5 } },
        dataLabels: { enabled: false },
        grid: {
            borderColor: gridColor,
            strokeDashArray: 4,
            xaxis: { lines: { show: false } },
            padding: { top: 0, right: 12, bottom: 0, left: 8 },
        },
        tooltip: {
            shared: true,
            intersect: false,
            custom: ({
                dataPointIndex,
                w,
            }: {
                dataPointIndex: number;
                w: {
                    globals: {
                        seriesNames: string[];
                        series: number[][];
                        colors: string[];
                        categoryLabels?: string[];
                        labels?: string[];
                    };
                };
            }) => {
                const g = w.globals;
                const label =
                    g.categoryLabels?.[dataPointIndex] ??
                    g.labels?.[dataPointIndex] ??
                    '';
                const rows = g.seriesNames
                    .map((name, i) => {
                        const val = g.series[i]?.[dataPointIndex];
                        if (val == null) return '';
                        return `<div style="display:flex;align-items:center;gap:6px;margin-top:4px;"><span style="width:8px;height:8px;border-radius:50%;background:${g.colors[i]};flex-shrink:0;"></span><span style="flex:1;color:${axisColor};">${name}</span><span style="font-weight:600;font-family:'DM Mono',monospace;margin-left:14px;color:${tipText};">${pesoFull(val)}</span></div>`;
                    })
                    .join('');
                return `<div style="${tipShell}min-width:196px;"><div style="font-family:'DM Mono',monospace;font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:${axisColor};margin-bottom:2px;">${label}</div>${rows}</div>`;
            },
        },
        xaxis: {
            categories: dates.map(shortDate),
            axisBorder: { show: false },
            axisTicks: { show: false },
            tooltip: { enabled: false },
            labels: {
                style: axisLabelStyle,
                rotate: 0,
                hideOverlappingLabels: true,
            },
        },
        yaxis: {
            min: 0,
            labels: { formatter: pesoCompact, style: axisLabelStyle },
        },
    });

    const salesOptions = lineOptions(data.sales_trend.dates);
    const adOptions = lineOptions(data.ad_spend_trend.dates);

    // ── Ad spend share (donut) ──────────────────────────────────────────────
    const pieTotal = data.ad_spend_share.reduce((a, s) => a + s.ad_spent, 0);
    const pieOptions: ApexOptions = {
        chart: { ...baseChart, type: 'donut' },
        theme: { mode: themeMode },
        colors,
        labels: data.ad_spend_share.map((s) => s.name),
        legend: {
            position: 'right',
            fontSize: '12px',
            fontFamily: 'DM Sans, sans-serif',
            fontWeight: 500,
            labels: { colors: axisColor },
            markers: { size: 6, strokeWidth: 0 },
            itemMargin: { vertical: 3 },
        },
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
                const s = data.ad_spend_share[seriesIndex];
                if (!s) return '';
                const share =
                    pieTotal > 0
                        ? ((s.ad_spent / pieTotal) * 100).toFixed(1)
                        : '0';
                return `<div style="${tipShell}"><div style="display:flex;align-items:center;gap:6px;font-weight:600;color:${tipText};"><span style="width:8px;height:8px;border-radius:50%;background:${colors[seriesIndex % colors.length]};"></span>${s.name}</div><div style="margin-top:3px;color:${tipText};">${pesoFull(s.ad_spent)} · ${share}%</div></div>`;
            },
        },
    };
    const pieSeries = data.ad_spend_share.map((s) => s.ad_spent);

    return (
        <div className="relative space-y-4">
            {loading && (
                <div className="absolute inset-0 z-10 flex items-center justify-center rounded-[14px] bg-white/50 dark:bg-zinc-950/50">
                    <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
                </div>
            )}

            {/* Sales trend */}
            <ChartCard
                title="Sales trend"
                subtitle="Daily sales per intern (month-to-date)"
            >
                <Chart
                    options={salesOptions}
                    series={data.sales_trend.series}
                    type="line"
                    height={320}
                />
            </ChartCard>

            {/* Ad spend trend — same treatment as sales */}
            <ChartCard
                title="Ad spend trend"
                subtitle="Daily ad spend per intern (month-to-date)"
            >
                <Chart
                    options={adOptions}
                    series={data.ad_spend_trend.series}
                    type="line"
                    height={320}
                />
            </ChartCard>

            {/* Ad spend share — donut */}
            <ChartCard
                title="Ad spend share"
                subtitle="Each intern's share of total ad spend (month-to-date)"
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
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <Kpi label="Total Sales" value={pesoFull(kpis.total_sales)} />
            <Kpi label="Total Ad Spend" value={pesoFull(kpis.total_ad_spent)} />
            <Kpi
                label="Blended ROAS"
                value={kpis.roas != null ? kpis.roas.toFixed(2) : '—'}
            />
            <Kpi
                label="Total Orders"
                value={kpis.total_orders.toLocaleString('en-PH')}
            />
            <Kpi
                label="Avg RTS Rate"
                value={
                    kpis.avg_rts_rate != null
                        ? `${kpis.avg_rts_rate.toFixed(2)}%`
                        : '—'
                }
            />
        </div>
    );
}

function Kpi({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white px-4 py-3 dark:border-white/6 dark:bg-zinc-900">
            <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {label}
            </p>
            <p className="mt-1 font-mono text-lg font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                {value}
            </p>
        </div>
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
