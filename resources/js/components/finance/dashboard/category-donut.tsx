import { type ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';
import { CATEGORY_COLORS, tooltipTheme } from './chart-theme';
import { type CategorySlice } from './types';
import { humanizeCategory, money } from './utils';

/**
 * Donut breakdown of amounts by category — reused for both the expense and
 * income splits. `totalLabel` names the centre total (e.g. "Expenses").
 */
export default function CategoryDonut({
    data,
    totalLabel,
}: {
    data: CategorySlice[];
    totalLabel: string;
}) {
    const labels = data.map((d) => humanizeCategory(d.category));
    const values = data.map((d) => d.amount);

    const options: ApexOptions = {
        chart: { fontFamily: 'DM Sans, sans-serif', type: 'donut' },
        labels,
        colors: CATEGORY_COLORS,
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
            formatter: (val) => `${Math.round(Number(val))}%`,
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
                            label: totalLabel,
                            fontSize: '11px',
                            fontFamily: 'DM Mono, monospace',
                            color: '#9CA3AF',
                            formatter: (w) =>
                                `₱${money(
                                    w.globals.seriesTotals.reduce(
                                        (a: number, b: number) => a + b,
                                        0,
                                    ),
                                )}`,
                        },
                    },
                },
            },
        },
        tooltip: {
            ...tooltipTheme,
            y: { formatter: (v) => `₱${money(v)}` },
        },
    };

    return (
        <Chart options={options} series={values} type="donut" height={300} />
    );
}
