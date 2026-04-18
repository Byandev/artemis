import { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

interface Props {
    categories: string[];
    inSeries: number[];
    outSeries: number[];
    formatValue: (value: number) => string;
}

const IN_COLOR = '#10b981';
const OUT_COLOR = '#f43f5e';

export default function CashflowChart({ categories, inSeries, outSeries, formatValue }: Props) {
    const columnWidth =
        categories.length > 20 ? '80%' : categories.length > 10 ? '60%' : '40%';

    const options: ApexOptions = {
        colors: [IN_COLOR, OUT_COLOR],
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            type: 'bar',
            stacked: false,
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth,
                borderRadius: 4,
                borderRadiusApplication: 'end',
            },
        },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: {
            categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                style: { fontSize: '11px', fontFamily: 'DM Mono, monospace', colors: '#9CA3AF' },
            },
        },
        yaxis: {
            labels: {
                formatter: formatValue,
                style: { fontSize: '11px', fontFamily: 'DM Mono, monospace', colors: ['#9CA3AF'] },
            },
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '11px',
            fontFamily: 'DM Mono, monospace',
            markers: { size: 6 },
            labels: { colors: '#6B7280' },
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
            shared: true,
            intersect: false,
            custom: ({ dataPointIndex, w }) => {
                const category = w.globals.labels[dataPointIndex] ?? '';
                const inVal = w.globals.series[0]?.[dataPointIndex] ?? 0;
                const outVal = w.globals.series[1]?.[dataPointIndex] ?? 0;
                const net = Number(inVal) - Number(outVal);
                const fmtIn = formatValue(Number(inVal));
                const fmtOut = formatValue(Number(outVal));
                const fmtNet = formatValue(net);
                const netColor = net >= 0 ? '#059669' : '#dc2626';
                const row = (label: string, value: string, color: string) =>
                    `<div style="display:flex;align-items:center;margin-top:4px;"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:${color};flex-shrink:0;margin-right:6px;"></span><span style="font-size:12px;color:#6B7280;flex:1;">${label}</span><span style="font-size:12px;color:#111827;font-weight:600;font-family:'DM Mono',monospace;margin-left:16px;">${value}</span></div>`;
                return `<div style="background:#fff;border:1px solid rgba(0,0,0,0.07);border-radius:12px;padding:10px 14px;box-shadow:0 4px 20px rgba(0,0,0,0.08);font-family:'DM Sans',sans-serif;min-width:180px;"><p style="font-family:'DM Mono',monospace;font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:0.08em;color:#9CA3AF;margin:0 0 4px;">${category}</p>${row('IN', fmtIn, IN_COLOR)}${row('OUT', fmtOut, OUT_COLOR)}${row('Net', fmtNet, netColor)}</div>`;
            },
        },
    };

    const series = [
        { name: 'IN', data: inSeries },
        { name: 'OUT', data: outSeries },
    ];

    return (
        <div className="custom-scrollbar max-w-full overflow-x-auto">
            <div className={categories.length > 15 ? 'min-w-[900px]' : ''}>
                <Chart options={options} series={series} type="bar" height={300} />
            </div>
        </div>
    );
}
