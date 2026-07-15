import { ApexOptions } from 'apexcharts';
import { format, parse } from 'date-fns';
import Chart from 'react-apexcharts';

export interface RtsMonthlyPoint {
    period: string; // 'YYYY-MM'
    rate: number; // 0..1 ratio
}

// Single-series trend: one hue carries magnitude, the title names the series so
// no legend is needed. Emerald matches the shared chart palette.
const COLOR = '#10b981';

const monthLabel = (period: string) => {
    const parsed = parse(period, 'yyyy-MM', new Date());
    return Number.isNaN(parsed.getTime()) ? period : format(parsed, 'MMM yyyy');
};

const asPercent = (rate: number) => `${(rate * 100).toFixed(1)}%`;

export default function RtsTrendChart({ data }: { data: RtsMonthlyPoint[] }) {
    const categories = data.map((d) => monthLabel(d.period));
    const series = [
        {
            name: 'RTS Rate',
            data: data.map((d) => Number((d.rate * 100).toFixed(2))),
        },
    ];

    const options: ApexOptions = {
        colors: [COLOR],
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            type: 'bar',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: data.length <= 3 ? '38%' : '55%',
                borderRadius: 4,
                borderRadiusApplication: 'end',
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (val) => `${Number(val).toFixed(1)}%`,
            offsetY: -18,
            style: {
                fontSize: '11px',
                fontFamily: 'DM Mono, monospace',
                colors: ['#9CA3AF'],
            },
        },
        states: { hover: { filter: { type: 'darken' } } },
        xaxis: {
            categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                style: {
                    fontSize: '11px',
                    fontFamily: 'DM Mono, monospace',
                    colors: '#9CA3AF',
                },
            },
        },
        yaxis: {
            labels: {
                formatter: (val) => `${Number(val).toFixed(0)}%`,
                style: {
                    fontSize: '11px',
                    fontFamily: 'DM Mono, monospace',
                    colors: ['#9CA3AF'],
                },
            },
        },
        grid: {
            borderColor: 'rgba(0,0,0,0.05)',
            strokeDashArray: 4,
            yaxis: { lines: { show: true } },
            xaxis: { lines: { show: false } },
            padding: { top: 0, right: 8, bottom: 0, left: 8 },
        },
        legend: { show: false },
        fill: { opacity: 1 },
        tooltip: {
            // Custom HTML with explicit white bg + shadow so it stays legible in
            // dark mode regardless of ApexCharts theme CSS.
            custom: ({ series, seriesIndex, dataPointIndex, w }) => {
                const label = w.globals.labels[dataPointIndex];
                const val = series[seriesIndex][dataPointIndex];
                return `
                    <div style="
                        background:#ffffff;
                        border:1px solid #e5e7eb;
                        border-radius:8px;
                        box-shadow:0 4px 12px rgba(0,0,0,0.15);
                        padding:8px 12px;
                        font-family:'DM Sans, sans-serif';
                    ">
                        <div style="font-size:11px;color:#6b7280;margin-bottom:2px;">${label}</div>
                        <div style="font-size:13px;font-weight:600;color:#111827;">
                            <span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:${COLOR};margin-right:6px;"></span>
                            RTS Rate: ${asPercent(val / 100)}
                        </div>
                    </div>
                `;
            },
        },
    };

    if (data.length === 0) {
        return (
            <div className="flex h-[280px] items-center justify-center text-sm text-zinc-400 dark:text-zinc-500">
                No RTS data for this period.
            </div>
        );
    }

    return <Chart options={options} series={series} type="bar" height={280} />;
}
