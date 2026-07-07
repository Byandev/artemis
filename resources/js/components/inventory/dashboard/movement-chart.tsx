import { type ApexOptions } from 'apexcharts';
import { format, parseISO } from 'date-fns';
import Chart from 'react-apexcharts';
import { tooltipTheme } from './chart-theme';
import { EmptyState } from './dashboard-card';
import { type MovementData } from './types';

const COLORS = {
    inflow: '#10b981',
    outflow: '#f59e0b',
    losses: '#ef4444',
    remaining: '#6366f1',
};

/**
 * Combo chart: grouped columns for the day's flows (inflow up, outflow & losses
 * down) with the remaining-stock level as a line on a second axis. Deliberately
 * NOT stacked — a stacked column+line combo trips an ApexCharts bug that blanks
 * the chart.
 */
export default function MovementChart({ data }: { data: MovementData }) {
    const flowKeys = [
        'po_qty_in',
        'rts_goods_in',
        'po_qty_out',
        'rts_goods_out',
        'rts_bad',
        'lost',
    ] as const;
    const hasData = flowKeys.some((k) => data[k].some((v) => v !== 0));
    if (!hasData) {
        return <EmptyState message="No inventory movement in this period." />;
    }

    const categories = data.categories.map((d) => {
        try {
            return format(parseISO(d), 'd MMM');
        } catch {
            return d;
        }
    });

    const inflow = categories.map(
        (_, i) => data.po_qty_in[i] + data.rts_goods_in[i],
    );
    const outflow = categories.map(
        (_, i) => -(data.po_qty_out[i] + data.rts_goods_out[i]),
    );
    const losses = categories.map((_, i) => -(data.rts_bad[i] + data.lost[i]));

    const series = [
        { name: 'Inflow', type: 'column', data: inflow },
        { name: 'Outflow', type: 'column', data: outflow },
        { name: 'Losses', type: 'column', data: losses },
        { name: 'Remaining Stock', type: 'line', data: data.remaining_stock },
    ];

    const options: ApexOptions = {
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            type: 'line',
            stacked: false,
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        colors: [
            COLORS.inflow,
            COLORS.outflow,
            COLORS.losses,
            COLORS.remaining,
        ],
        plotOptions: {
            bar: { columnWidth: '58%', borderRadius: 2 },
        },
        stroke: {
            width: [0, 0, 0, 2.5],
            curve: 'smooth',
        },
        markers: { size: 0, hover: { size: 5 } },
        dataLabels: { enabled: false },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'center',
            fontSize: '11px',
            fontFamily: 'DM Sans, sans-serif',
            fontWeight: 500,
            labels: { colors: '#6B7280' },
            markers: { size: 6, shape: 'circle', offsetX: -2 },
            // Keep all four categories on one horizontal row (no vertical stacking).
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
            // Thin the labels so they never crowd, keep them horizontal, and
            // drop the separate axis-crosshair tooltip (the shared tooltip's
            // title already shows the date).
            tickAmount: Math.min(categories.length, 10),
            tooltip: { enabled: false },
            labels: {
                rotate: 0,
                hideOverlappingLabels: true,
                offsetY: 2,
                style: {
                    fontSize: '11px',
                    fontFamily: 'DM Mono, monospace',
                    colors: '#9CA3AF',
                },
            },
        },
        yaxis: [
            {
                seriesName: ['Inflow', 'Outflow', 'Losses'],
                labels: {
                    formatter: (v) => `${Math.round(v)}`,
                    style: {
                        fontSize: '11px',
                        fontFamily: 'DM Mono, monospace',
                        colors: ['#9CA3AF'],
                    },
                },
                title: {
                    text: 'Units moved',
                    style: {
                        fontSize: '11px',
                        color: '#9CA3AF',
                        fontWeight: 500,
                    },
                },
            },
            {
                seriesName: 'Remaining Stock',
                opposite: true,
                min: 0,
                labels: {
                    formatter: (v) => `${Math.round(v)}`,
                    style: {
                        fontSize: '11px',
                        fontFamily: 'DM Mono, monospace',
                        colors: ['#9CA3AF'],
                    },
                },
                title: {
                    text: 'Remaining',
                    style: {
                        fontSize: '11px',
                        color: '#9CA3AF',
                        fontWeight: 500,
                    },
                },
            },
        ],
        tooltip: {
            ...tooltipTheme,
            shared: true,
            intersect: false,
            y: {
                formatter: (v) =>
                    v == null ? '—' : `${Math.abs(Math.round(v))} units`,
            },
        },
    };

    return (
        <div className="custom-scrollbar max-w-full overflow-x-auto">
            <div className="min-w-[640px]">
                <Chart
                    options={options}
                    series={series}
                    type="line"
                    height={320}
                />
            </div>
        </div>
    );
}
