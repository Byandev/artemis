import { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

interface Row {
    label: string;
    rts_rate_percentage: number;
}

interface Props {
    rows: Row[];
}

export default function RtsBreakdownChart({ rows }: Props) {
    const categories = rows.map((r) => r.label);
    const rates      = rows.map((r) => r.rts_rate_percentage);

    const options: ApexOptions = {
        chart: {
            type: 'bar',
            fontFamily: 'DM Sans, sans-serif',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        colors: ['#ef4444'],
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: categories.length > 10 ? '70%' : '45%',
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
            max: 100,
            labels: {
                formatter: (v) => `${v}%`,
                style: { fontSize: '11px', fontFamily: 'DM Mono, monospace', colors: ['#9CA3AF'] },
            },
        },
        legend: { show: false },
        grid: {
            borderColor: 'rgba(0,0,0,0.05)',
            strokeDashArray: 4,
            yaxis: { lines: { show: true } },
            xaxis: { lines: { show: false } },
            padding: { top: 0, right: 8, bottom: 0, left: 8 },
        },
        tooltip: {
            custom: ({ dataPointIndex, w }) => {
                const category = w.globals.labels[dataPointIndex] ?? '';
                const rate     = w.globals.series[0][dataPointIndex];
                return `<div style="background:#fff;border:1px solid rgba(0,0,0,0.07);border-radius:12px;padding:11px 14px;box-shadow:0 4px 20px rgba(0,0,0,0.08);font-family:'DM Sans',sans-serif;min-width:160px;">
                    <p style="font-family:'DM Mono',monospace;font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:0.08em;color:#9CA3AF;margin:0 0 5px;">${category}</p>
                    <div style="display:flex;align-items:center;">
                        <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#ef4444;flex-shrink:0;margin-right:6px;"></span>
                        <span style="font-size:12px;color:#6B7280;flex:1;">RTS Rate</span>
                        <span style="font-size:12px;color:#111827;font-weight:600;font-family:'DM Mono',monospace;margin-left:16px;">${rate}%</span>
                    </div>
                </div>`;
            },
        },
    };

    return (
        <Chart options={options} series={[{ name: 'RTS Rate', data: rates }]} type="bar" height={280} />
    );
}
