import { type ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';
import { tooltipTheme } from './chart-theme';
import { EmptyState } from './dashboard-card';
import { type TopDiscrepancy } from './types';

/** Surplus (counted over ledger) reads green; shortage reads red. */
export default function TopDiscrepanciesChart({
    data,
}: {
    data: TopDiscrepancy[];
}) {
    if (data.length === 0) {
        return <EmptyState message="No count discrepancies recorded." />;
    }

    const options: ApexOptions = {
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            type: 'bar',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 3,
                barHeight: '60%',
                colors: {
                    ranges: [
                        { from: -1e9, to: -0.001, color: '#ef4444' },
                        { from: 0.001, to: 1e9, color: '#10b981' },
                    ],
                },
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (v: number) => (v > 0 ? `+${v}` : `${v}`),
            style: { fontSize: '10px', fontFamily: 'DM Mono, monospace' },
            offsetX: 12,
        },
        legend: { show: false },
        grid: {
            borderColor: 'rgba(0,0,0,0.05)',
            strokeDashArray: 4,
            padding: { top: 0, right: 16, bottom: 0, left: 8 },
        },
        xaxis: {
            categories: data.map((d) => d.sku),
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
                style: {
                    fontSize: '10px',
                    fontFamily: 'DM Mono, monospace',
                    colors: ['#9CA3AF'],
                },
            },
        },
        tooltip: {
            ...tooltipTheme,
            y: {
                formatter: (v) => `${v > 0 ? '+' : ''}${v} unit(s) vs ledger`,
            },
        },
    };

    return (
        <Chart
            options={options}
            series={[
                { name: 'Discrepancy', data: data.map((d) => d.discrepancy) },
            ]}
            type="bar"
            height={Math.max(220, data.length * 34)}
        />
    );
}
