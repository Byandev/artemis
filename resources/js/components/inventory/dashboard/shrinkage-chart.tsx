import { type ApexOptions } from 'apexcharts';
import { format, parseISO } from 'date-fns';
import Chart from 'react-apexcharts';
import { tooltipTheme } from './chart-theme';
import { EmptyState } from './dashboard-card';
import { type TrendData } from './types';

export default function ShrinkageChart({ data }: { data: TrendData }) {
    if (data.values.every((v) => v === 0)) {
        return <EmptyState message="No shrinkage recorded in this period." />;
    }

    const categories = data.categories.map((d) => {
        try {
            return format(parseISO(d), 'd MMM');
        } catch {
            return d;
        }
    });

    const options: ApexOptions = {
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            type: 'area',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        colors: ['#ef4444'],
        stroke: { curve: 'smooth', width: 2.5 },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.2,
                opacityTo: 0,
                stops: [0, 100],
            },
        },
        markers: { size: 0, hover: { size: 4 } },
        dataLabels: { enabled: false },
        grid: {
            borderColor: 'rgba(0,0,0,0.05)',
            strokeDashArray: 4,
            xaxis: { lines: { show: false } },
            padding: { top: 0, right: 16, bottom: 0, left: 8 },
        },
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
            min: 0,
            labels: {
                formatter: (v) => `${Math.round(v)}`,
                style: {
                    fontSize: '11px',
                    fontFamily: 'DM Mono, monospace',
                    colors: ['#9CA3AF'],
                },
            },
        },
        tooltip: {
            ...tooltipTheme,
            y: { formatter: (v) => `${Math.round(v)} unit(s)` },
        },
    };

    return (
        <Chart
            options={options}
            series={[{ name: 'Shrinkage', data: data.values }]}
            type="area"
            height={260}
        />
    );
}
