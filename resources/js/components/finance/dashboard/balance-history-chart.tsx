import { type ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';
import { tooltipTheme } from './chart-theme';
import { type BalanceHistory } from './types';
import { compact, money, periodLabel } from './utils';

/**
 * Cumulative net cash flow across the window — the running trajectory of cash
 * position, starting from zero at the range's beginning.
 */
export default function BalanceHistoryChart({
    data,
}: {
    data: BalanceHistory;
}) {
    const categories = data.points.map((p) => periodLabel(p.period, data.unit));
    const series = [
        { name: 'Cumulative Net', data: data.points.map((p) => p.balance) },
    ];

    const options: ApexOptions = {
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            type: 'area',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        colors: ['#6366f1'],
        stroke: { width: 2.5, curve: 'smooth' },
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.35, opacityTo: 0.02 },
        },
        dataLabels: { enabled: false },
        grid: {
            borderColor: 'rgba(0,0,0,0.05)',
            strokeDashArray: 4,
            xaxis: { lines: { show: false } },
            padding: { top: 0, right: 12, bottom: 8, left: 8 },
        },
        xaxis: {
            categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
            tickAmount: Math.min(categories.length, 10),
            tooltip: { enabled: false },
            labels: {
                rotate: 0,
                hideOverlappingLabels: true,
                style: {
                    fontSize: '11px',
                    fontFamily: 'DM Mono, monospace',
                    colors: '#9CA3AF',
                },
            },
        },
        yaxis: {
            labels: {
                formatter: (v) => compact(v),
                style: {
                    fontSize: '11px',
                    fontFamily: 'DM Mono, monospace',
                    colors: ['#9CA3AF'],
                },
            },
        },
        tooltip: {
            ...tooltipTheme,
            y: { formatter: (v) => (v == null ? '—' : `₱${money(v)}`) },
        },
    };

    return (
        <div className="custom-scrollbar max-w-full overflow-x-auto">
            <div className="min-w-[560px]">
                <Chart
                    options={options}
                    series={series}
                    type="area"
                    height={300}
                />
            </div>
        </div>
    );
}
