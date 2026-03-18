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
    const options: ApexOptions = {
        legend: {
            show: true,
            position: 'bottom',
            horizontalAlign: 'center',
        },
        colors: ['#87c0a6', '#9CB9FF'],
        chart: {
            fontFamily: 'Outfit, sans-serif',
            height: 310,
            type: 'area',
            toolbar: {
                show: false,
            },
        },
        stroke: {
            curve: 'smooth',
            width: 3,
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                inverseColors: false,
                opacityFrom: 0.4,
                opacityTo: 0.02,
                stops: [0, 90, 100],
            },
        },
        markers: {
            size: 3,
            strokeColors: '#fff',
            strokeWidth: 2,
            hover: {
                size: 6,
            },
        },
        grid: {
            xaxis: {
                lines: {
                    show: false,
                },
            },
            yaxis: {
                lines: {
                    show: true,
                },
            },
        },
        dataLabels: {
            enabled: false,
        },
        tooltip: {
            enabled: true,
            y: {
                formatter: (value, { seriesIndex }) => {
                    return seriesIndex === 0
                        ? leftFormatter(value)
                        : rightFormatter(value);
                },
            },
        },
        xaxis: {
            type: 'category',
            categories,
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
            tooltip: {
                enabled: false,
            },
        },
        yaxis: [
            {
                seriesName: series?.[0]?.name,
                min: 0,
                labels: {
                    formatter: leftFormatter,
                    style: {
                        fontSize: '12px',
                        colors: ['#6B7280'],
                    },
                },
                title: {
                    text: leftAxisTitle,
                    style: {
                        fontSize: '12px',
                        fontWeight: 500,
                        color: '#6B7280',
                    },
                },
            },
            {
                seriesName: series?.[1]?.name,
                min: 0,
                opposite: true,
                labels: {
                    formatter: rightFormatter,
                    style: {
                        fontSize: '12px',
                        colors: ['#6B7280'],
                    },
                },
                title: {
                    text: rightAxisTitle,
                    style: {
                        fontSize: '12px',
                        fontWeight: 500,
                        color: '#6B7280',
                    },
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
