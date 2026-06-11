import { ApexOptions } from 'apexcharts';
import { useMemo } from 'react';
import Chart from 'react-apexcharts';
import { Throughput } from './types';

const COLORS = ['#6366f1', '#10b981'];

/** Weekly output trend, video vs image, as a smooth line chart. */
export default function ThroughputChart({
    throughput,
}: {
    throughput: Throughput;
}) {
    const hasData = useMemo(
        () =>
            throughput.video.some((v) => v > 0) ||
            throughput.image.some((v) => v > 0),
        [throughput],
    );

    const series = useMemo(
        () => [
            { name: 'Video', data: throughput.video },
            { name: 'Image', data: throughput.image },
        ],
        [throughput],
    );

    const options: ApexOptions = useMemo(
        () => ({
            colors: COLORS,
            chart: {
                fontFamily: 'DM Sans, sans-serif',
                type: 'line',
                toolbar: { show: false },
                zoom: { enabled: false },
            },
            stroke: { curve: 'smooth', width: 2.5 },
            markers: { size: 4, strokeWidth: 0, hover: { size: 6 } },
            dataLabels: { enabled: false },
            xaxis: {
                categories: throughput.categories,
                axisBorder: { show: false },
                axisTicks: { show: false },
                tooltip: { enabled: false },
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
                    formatter: (v) => String(Math.round(v)),
                    style: {
                        fontSize: '11px',
                        fontFamily: 'DM Mono, monospace',
                        colors: ['#9CA3AF'],
                    },
                },
            },
            legend: {
                show: true,
                position: 'top',
                horizontalAlign: 'right',
                fontFamily: 'DM Sans, sans-serif',
                fontSize: '12px',
                markers: { size: 5 },
            },
            grid: {
                borderColor: 'rgba(0,0,0,0.05)',
                strokeDashArray: 4,
                yaxis: { lines: { show: true } },
                xaxis: { lines: { show: false } },
                padding: { top: 0, right: 8, bottom: 0, left: 8 },
            },
            tooltip: { theme: 'light' },
        }),
        [throughput.categories],
    );

    if (!hasData) {
        return (
            <p className="py-12 text-center text-[12px] text-gray-400 dark:text-gray-500">
                No creatives produced in this period.
            </p>
        );
    }

    return (
        <div className="custom-scrollbar max-w-full overflow-x-auto">
            <Chart options={options} series={series} type="line" height={280} />
        </div>
    );
}
