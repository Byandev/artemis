import { Workspace } from '@/types/models/Workspace';
import { ApexOptions } from 'apexcharts';
import { useEffect, useMemo, useState } from 'react';
import Chart from 'react-apexcharts';

interface TrendItem {
    date: string;
    count: number;
}

interface Props {
    workspace: Workspace;
}

export default function DeliveryTrend({ workspace }: Props) {
    const [data, setData] = useState<TrendItem[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);

        fetch(
            `/workspaces/${workspace.slug}/rts/rmo-management/analytics/delivery-trend`,
            { credentials: 'same-origin', signal: controller.signal },
        )
            .then((res) => res.json())
            .then((result) => setData(result))
            .catch((err) => {
                if (err.name !== 'AbortError') console.error(err);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [workspace.slug]);

    const categories = useMemo(() => data.map((d) => d.date), [data]);
    const series = useMemo(
        () => [{ name: 'Orders', data: data.map((d) => Number(d.count)) }],
        [data],
    );

    const color = '#3b82f6';

    const options: ApexOptions = {
        colors: [color],
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            type: 'area',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        stroke: { curve: 'smooth', width: 2.5 },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                inverseColors: false,
                opacityFrom: 0.18,
                opacityTo: 0,
                stops: [0, 100],
            },
        },
        markers: { size: 0, hover: { size: 5, sizeOffset: 2 } },
        grid: {
            borderColor: 'rgba(0,0,0,0.05)',
            strokeDashArray: 4,
            xaxis: { lines: { show: false } },
            yaxis: { lines: { show: true } },
            padding: { top: 0, right: 8, bottom: 0, left: 8 },
        },
        dataLabels: { enabled: false },
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
            labels: {
                formatter: (v: number) => v.toLocaleString(),
                style: {
                    fontSize: '11px',
                    fontFamily: 'DM Mono, monospace',
                    colors: ['#9CA3AF'],
                },
            },
        },
        tooltip: {
            custom: ({ dataPointIndex, w }) => {
                const category = w.globals.categoryLabels[dataPointIndex] ?? w.globals.labels[dataPointIndex] ?? '';
                const val = w.globals.series[0][dataPointIndex];
                return `<div style="background:#fff;border:1px solid rgba(0,0,0,0.07);border-radius:12px;padding:11px 14px;box-shadow:0 4px 20px rgba(0,0,0,0.08);font-family:'DM Sans',sans-serif;min-width:160px;"><p style="font-family:'DM Mono',monospace;font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:0.08em;color:#9CA3AF;margin:0 0 5px;">${category}</p><div style="display:flex;align-items:center;"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:${color};flex-shrink:0;margin-right:6px;"></span><span style="font-size:12px;color:#6B7280;font-weight:500;flex:1;">Orders</span><span style="font-size:12px;color:#111827;font-weight:600;font-family:'DM Mono',monospace;margin-left:16px;">${val.toLocaleString()}</span></div></div>`;
            },
        },
    };

    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-4">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    Delivery Trend
                </h3>
                <p className="text-[11px] text-gray-400 dark:text-gray-500">
                    Daily order volume over time
                </p>
            </div>

            {loading ? (
                <div className="h-60 w-full animate-pulse rounded-lg bg-gray-200 dark:bg-zinc-800" />
            ) : data.length === 0 ? (
                <div className="flex h-60 items-center justify-center">
                    <p className="text-sm text-gray-400 dark:text-gray-500">No data available</p>
                </div>
            ) : (
                <div className="custom-scrollbar max-w-full overflow-x-auto">
                    <div className="min-w-[600px]">
                        <Chart options={options} series={series} type="area" height={300} />
                    </div>
                </div>
            )}
        </div>
    );
}