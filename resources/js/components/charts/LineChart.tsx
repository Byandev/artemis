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
    formatValue: (value: number) => string;
}

export default function LineChartOne({
    categories,
    series,
    formatValue,
}: Props) {
    const options: ApexOptions = {
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'left',
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
                opacityFrom: 0.40,
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
                formatter: formatValue,
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
        yaxis: {
            min: 0,
            labels: {
                formatter: formatValue,
                style: {
                    fontSize: '12px',
                    colors: ['#6B7280'],
                },
            },
            title: {
                text: '',
                style: {
                    fontSize: '0px',
                },
            },
        },
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
