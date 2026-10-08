import { ApexOptions } from 'apexcharts';
import moment from 'moment';
import { useEffect, useState } from 'react';
import Chart from 'react-apexcharts';
import type { DailyPoint } from '../index';

/**
 * Outcome colours are status colours (good / warning / info / critical),
 * stepped per theme and checked with the dataviz palette validator. Dark mode's
 * green↔amber pair sits just under the colour-blind target, so stacked
 * segments are separated by a 2px surface gap and the legend is always shown.
 */
const OUTCOMES = [
    { key: 'filled', label: 'Filled', light: '#10b981', dark: '#059669' },
    {
        key: 'needs_review',
        label: 'Needs review',
        light: '#f59e0b',
        dark: '#d97706',
    },
    {
        key: 'no_address',
        label: 'No address',
        light: '#3b82f6',
        dark: '#3b82f6',
    },
    { key: 'failed', label: 'Failed', light: '#ef4444', dark: '#ef4444' },
] as const;

const SURFACE = { light: '#ffffff', dark: '#18181b' };
const INK = { light: '#9CA3AF', dark: '#71717a' };
const GRID = { light: '#f3f4f6', dark: '#27272a' };

/** Follows the `dark` class the app's appearance setting toggles. */
function useDarkMode(): boolean {
    const [dark, setDark] = useState(() =>
        document.documentElement.classList.contains('dark'),
    );

    useEffect(() => {
        const observer = new MutationObserver(() =>
            setDark(document.documentElement.classList.contains('dark')),
        );
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });
        return () => observer.disconnect();
    }, []);

    return dark;
}

function baseOptions(dark: boolean, daily: DailyPoint[]): ApexOptions {
    const mode = dark ? 'dark' : 'light';

    return {
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            toolbar: { show: false },
            zoom: { enabled: false },
            background: 'transparent',
        },
        theme: { mode },
        dataLabels: { enabled: false },
        grid: {
            borderColor: GRID[mode],
            strokeDashArray: 0,
            xaxis: { lines: { show: false } },
        },
        xaxis: {
            categories: daily.map((d) => moment(d.date).format('MMM D')),
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                style: {
                    fontSize: '11px',
                    fontFamily: 'DM Mono, monospace',
                    colors: INK[mode],
                },
                hideOverlappingLabels: true,
            },
        },
        tooltip: { theme: mode, shared: true, intersect: false },
    };
}

export function DailyOutcomeChart({ daily }: { daily: DailyPoint[] }) {
    const dark = useDarkMode();
    const mode = dark ? 'dark' : 'light';
    const base = baseOptions(dark, daily);

    const options: ApexOptions = {
        ...base,
        chart: { ...base.chart, type: 'bar', stacked: true },
        colors: OUTCOMES.map((o) => o[mode]),
        plotOptions: {
            bar: {
                columnWidth: daily.length > 20 ? '75%' : '45%',
                borderRadius: 4,
                borderRadiusApplication: 'end',
                borderRadiusWhenStacked: 'last',
            },
        },
        // The 2px surface gap between stacked segments.
        stroke: { show: true, width: 2, colors: [SURFACE[mode]] },
        legend: {
            show: true,
            showForSingleSeries: true,
            position: 'top',
            horizontalAlign: 'left',
            fontSize: '12px',
            labels: { colors: INK[mode] },
            markers: { size: 5 },
        },
        yaxis: {
            labels: {
                style: { fontSize: '11px', colors: INK[mode] },
                formatter: (v) => String(Math.round(v)),
            },
            forceNiceScale: true,
        },
    };

    const series = OUTCOMES.map((o) => ({
        name: o.label,
        data: daily.map((d) => d[o.key]),
    }));

    return (
        <Chart
            key={mode}
            type="bar"
            height={260}
            options={options}
            series={series}
        />
    );
}

export function DailyCostChart({ daily }: { daily: DailyPoint[] }) {
    const dark = useDarkMode();
    const mode = dark ? 'dark' : 'light';
    const base = baseOptions(dark, daily);

    const options: ApexOptions = {
        ...base,
        chart: { ...base.chart, type: 'bar' },
        colors: [dark ? '#6b7280' : '#4b5563'],
        plotOptions: {
            bar: {
                columnWidth: daily.length > 20 ? '75%' : '45%',
                borderRadius: 4,
                borderRadiusApplication: 'end',
            },
        },
        legend: { show: false },
        yaxis: {
            labels: {
                style: { fontSize: '11px', colors: INK[mode] },
                formatter: (v) => `$${v.toFixed(3)}`,
            },
        },
        tooltip: {
            ...base.tooltip,
            y: { formatter: (v) => `$${v.toFixed(5)}` },
        },
    };

    return (
        <Chart
            key={mode}
            type="bar"
            height={260}
            options={options}
            series={[{ name: 'AI cost', data: daily.map((d) => d.cost_usd) }]}
        />
    );
}
