import { type ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';
import { tooltipTheme } from './chart-theme';
import { EmptyState } from './dashboard-card';
import { type PoStatusSlice } from './types';

const STATUS_COLORS: Record<number, string> = {
    1: '#f59e0b',
    2: '#fbbf24',
    3: '#facc15',
    4: '#a3e635',
    5: '#4ade80',
    6: '#22d3ee',
    7: '#10b981',
    8: '#ef4444',
};

export default function PoStatusFunnel({ data }: { data: PoStatusSlice[] }) {
    if (data.every((d) => d.count === 0)) {
        return <EmptyState message="No purchase orders yet." />;
    }

    const options: ApexOptions = {
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            type: 'bar',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        colors: data.map((d) => STATUS_COLORS[d.status] ?? '#9CA3AF'),
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 3,
                borderRadiusApplication: 'end',
                distributed: true,
                barHeight: '62%',
            },
        },
        dataLabels: {
            enabled: true,
            style: {
                fontSize: '11px',
                fontFamily: 'DM Mono, monospace',
                colors: ['#fff'],
            },
        },
        legend: { show: false },
        grid: {
            borderColor: 'rgba(0,0,0,0.05)',
            strokeDashArray: 4,
            xaxis: { lines: { show: true } },
            yaxis: { lines: { show: false } },
            padding: { top: 0, right: 12, bottom: 0, left: 8 },
        },
        xaxis: {
            categories: data.map((d) => d.label),
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
                    fontSize: '11px',
                    fontFamily: 'DM Mono, monospace',
                    colors: ['#9CA3AF'],
                },
            },
        },
        tooltip: { ...tooltipTheme, y: { formatter: (v) => `${v} PO(s)` } },
    };

    return (
        <Chart
            options={options}
            series={[{ name: 'Orders', data: data.map((d) => d.count) }]}
            type="bar"
            height={300}
        />
    );
}
