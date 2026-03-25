import { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

interface Props {
    series:
        | {
        name: string;
        data: number[];
    }[]
        | undefined;
    categories: string[];
    leftAxisTitle: string;
    rightAxisTitle: string;
    leftFormatter: (value: number) => string;
    rightFormatter: (value: number) => string;
}

export default function LineChart({
                                      categories,
                                      series,
                                      leftAxisTitle,
                                      rightAxisTitle,
                                      leftFormatter,
                                      rightFormatter,
                                  }: Props) {
    const colors = ['#10b981', '#818cf8'];

    const options: ApexOptions = {
        colors,
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            height: 310,
            type: 'area',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '12px',
            fontFamily: 'DM Sans, sans-serif',
            fontWeight: 500,
            labels: {
                colors: '#6B7280',
            },
            markers: {
                size: 6,
                shape: 'circle',
                strokeWidth: 0,
                offsetX: -2,
            },
            itemMargin: { horizontal: 12 },
        },
        stroke: {
            curve: 'smooth',
            width: 2.5,
        },
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
        markers: {
            size: 0,
            hover: { size: 5, sizeOffset: 2 },
        },
        grid: {
            borderColor: 'rgba(0,0,0,0.05)',
            strokeDashArray: 4,
            xaxis: { lines: { show: false } },
            yaxis: { lines: { show: true } },
            padding: { top: 0, right: 16, bottom: 0, left: 8 },
        },
        dataLabels: { enabled: false },
        tooltip: {
            enabled: true,
            shared: true,
            intersect: false,
            custom: ({ dataPointIndex, w }) => {
                const category = w.globals.categoryLabels[dataPointIndex] ?? w.globals.labels[dataPointIndex] ?? '';
                const rows = w.globals.seriesNames.map((name: string, i: number) => {
                    const val = w.globals.series[i][dataPointIndex];
                    const fmt = i === 0 ? leftFormatter(val) : rightFormatter(val);
                    return `
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:20px;margin-top:6px;">
                            <div style="display:flex;align-items:center;gap:7px;">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${colors[i]};flex-shrink:0;"></span>
                                <span style="font-size:12px;color:#6B7280;font-weight:500;">${name}</span>
                            </div>
                            <span style="font-size:12px;color:#111827;font-weight:600;font-family:'DM Mono',monospace;">${fmt}</span>
                        </div>`;
                }).join('');
                return `
                    <div style="background:#fff;border:1px solid rgba(0,0,0,0.07);border-radius:12px;padding:12px 14px;box-shadow:0 4px 24px rgba(0,0,0,0.09);font-family:'DM Sans',sans-serif;min-width:200px;">
                        <p style="font-family:'DM Mono',monospace;font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:0.08em;color:#9CA3AF;margin:0 0 2px;">${category}</p>
                        ${rows}
                    </div>`;
            },
        },
        xaxis: {
            type: 'category',
            categories,
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
        yaxis: [
            {
                seriesName: series?.[0]?.name,
                min: 0,
                labels: {
                    formatter: leftFormatter,
                    style: {
                        fontSize: '11px',
                        fontFamily: 'DM Mono, monospace',
                        colors: ['#9CA3AF'],
                    },
                },
                title: {
                    text: leftAxisTitle,
                    style: { fontSize: '11px', fontWeight: 500, color: '#9CA3AF' },
                },
            },
            {
                seriesName: series?.[1]?.name,
                min: 0,
                opposite: true,
                labels: {
                    formatter: rightFormatter,
                    style: {
                        fontSize: '11px',
                        fontFamily: 'DM Mono, monospace',
                        colors: ['#9CA3AF'],
                    },
                },
                title: {
                    text: rightAxisTitle,
                    style: { fontSize: '11px', fontWeight: 500, color: '#9CA3AF' },
                },
            },
        ],
    };

    return (
        <div className="custom-scrollbar max-w-full overflow-x-auto">
            <div id="chartEight" className="min-w-[1000px] 2xl:min-w-full">
                <Chart
                    options={options}
                    series={series}
                    type="area"
                    height={310}
                />
            </div>
        </div>
    );
}
