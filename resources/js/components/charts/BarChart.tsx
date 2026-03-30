import { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

interface Props {
    series: ApexNonAxisChartSeries | undefined;
    categories: string[];
    formatValue: (value: number) => string;
    abbreviateLabels?: boolean;
}

const toAcronym = (name: string) =>
    name.split(/\s+/).map((w) => w[0]?.toUpperCase() ?? '').join('');

export default function BarChart({ categories, series, formatValue, abbreviateLabels = false }: Props) {
    const columnWidth =
        categories.length > 20 ? '80%' : categories.length > 10 ? '60%' : '40%';

    const color = '#10b981';

    const options: ApexOptions = {
        colors: [color],
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            type: 'bar',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth,
                borderRadius: 5,
                borderRadiusApplication: 'end',
            },
        },
        dataLabels: { enabled: false },
        stroke: {
            show: true,
            width: 3,
            colors: ['transparent'],
        },
        xaxis: {
            categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                ...(abbreviateLabels && { formatter: toAcronym }),
                style: {
                    fontSize: '11px',
                    fontFamily: 'DM Mono, monospace',
                    colors: '#9CA3AF',
                },
            },
        },
        legend: { show: false },
        yaxis: {
            labels: {
                formatter: formatValue,
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
        fill: { opacity: 1 },
        tooltip: {
            custom: ({ dataPointIndex, w }) => {
                const category = w.globals.labels[dataPointIndex] ?? '';
                const val = w.globals.series[0][dataPointIndex];
                const fmt = formatValue(val);
                return `<div style="background:#fff;border:1px solid rgba(0,0,0,0.07);border-radius:12px;padding:11px 14px;box-shadow:0 4px 20px rgba(0,0,0,0.08);font-family:'DM Sans',sans-serif;min-width:160px;"><p style="font-family:'DM Mono',monospace;font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:0.08em;color:#9CA3AF;margin:0 0 5px;">${category}</p><div style="display:flex;align-items:center;"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:${color};flex-shrink:0;margin-right:6px;"></span><span style="font-size:12px;color:#6B7280;font-weight:500;flex:1;">Value</span><span style="font-size:12px;color:#111827;font-weight:600;font-family:'DM Mono',monospace;margin-left:16px;">${fmt}</span></div></div>`;
            },
        },
    };

    return (
        <div className="custom-scrollbar max-w-full overflow-x-auto">
            <div id="chartOne" className="min-w-[1000px]">
                <Chart
                    options={options}
                    series={series}
                    type="bar"
                    height={300}
                />
            </div>
        </div>
    );
}
