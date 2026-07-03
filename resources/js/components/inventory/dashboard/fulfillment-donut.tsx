import { type ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';
import { tooltipTheme } from './chart-theme';
import { EmptyState } from './dashboard-card';
import { type Fulfillment } from './types';

export default function FulfillmentDonut({ data }: { data: Fulfillment }) {
    const values = [data.waiting, data.partial, data.delivered];
    if (values.every((v) => v === 0)) {
        return <EmptyState message="No open order lines to fulfil." />;
    }

    const options: ApexOptions = {
        chart: { fontFamily: 'DM Sans, sans-serif', type: 'donut' },
        labels: ['Waiting', 'Partial', 'Delivered'],
        colors: ['#94a3b8', '#f59e0b', '#10b981'],
        stroke: { width: 0 },
        legend: {
            position: 'bottom',
            fontSize: '12px',
            fontFamily: 'DM Sans, sans-serif',
            labels: { colors: '#6B7280' },
            markers: { size: 6, shape: 'circle' },
            itemMargin: { horizontal: 8 },
        },
        dataLabels: {
            enabled: true,
            style: { fontSize: '11px', fontFamily: 'DM Mono, monospace' },
            dropShadow: { enabled: false },
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Order lines',
                            fontSize: '11px',
                            fontFamily: 'DM Mono, monospace',
                            color: '#9CA3AF',
                            formatter: (w) =>
                                `${w.globals.seriesTotals.reduce(
                                    (a: number, b: number) => a + b,
                                    0,
                                )}`,
                        },
                    },
                },
            },
        },
        tooltip: { ...tooltipTheme, y: { formatter: (v) => `${v} line(s)` } },
    };

    return (
        <Chart options={options} series={values} type="donut" height={300} />
    );
}
