import { type ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';
import { tooltipTheme } from './chart-theme';
import { type CashFlowSeries } from './types';
import { compact, money, periodLabel } from './utils';

/**
 * Cash in (up) and cash out (down) as columns, with net cash flow as a line —
 * the money movement across the selected window at a glance.
 */
export default function CashFlowChart({ data }: { data: CashFlowSeries }) {
    const categories = data.points.map((p) => periodLabel(p.period, data.unit));

    const series = [
        { name: 'Cash In', type: 'column', data: data.points.map((p) => p.in) },
        {
            name: 'Cash Out',
            type: 'column',
            data: data.points.map((p) => -p.out),
        },
        { name: 'Net', type: 'line', data: data.points.map((p) => p.net) },
    ];

    const options: ApexOptions = {
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            type: 'line',
            stacked: false,
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        colors: ['#10b981', '#ef4444', '#6366f1'],
        plotOptions: { bar: { columnWidth: '55%', borderRadius: 2 } },
        stroke: { width: [0, 0, 2.5], curve: 'smooth' },
        markers: { size: 0, hover: { size: 5 } },
        dataLabels: { enabled: false },
        legend: {
            position: 'top',
            horizontalAlign: 'center',
            fontSize: '11px',
            fontFamily: 'DM Sans, sans-serif',
            fontWeight: 500,
            labels: { colors: '#6B7280' },
            markers: { size: 6, shape: 'circle' },
            itemMargin: { horizontal: 12, vertical: 0 },
        },
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
            shared: true,
            intersect: false,
            y: {
                formatter: (v) => (v == null ? '—' : `₱${money(Math.abs(v))}`),
            },
        },
    };

    return (
        <div className="custom-scrollbar max-w-full overflow-x-auto">
            <div className="min-w-[560px]">
                <Chart
                    options={options}
                    series={series}
                    type="line"
                    height={300}
                />
            </div>
        </div>
    );
}
