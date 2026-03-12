import { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

interface Props {
    series: ApexNonAxisChartSeries | undefined;
    categories: string[];
    formatValue: (value: number) => string;
}

export default function BarChart({ categories, series, formatValue }: Props) {
    const options: ApexOptions = {
        colors: ['#87c0a6'],
        chart: {
            fontFamily: 'Outfit, sans-serif',
            type: 'bar',
            toolbar: {
                show: false,
            },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '8%',
                borderRadius: 5,
                borderRadiusApplication: 'end',
            },
        },
        dataLabels: {
            enabled: false,
        },
        stroke: {
            show: true,
            width: 4,
            colors: ['transparent'],
        },
        xaxis: {
            categories,
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
        },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'left',
            fontFamily: 'Outfit',
        },
        yaxis: {
            labels: {
                formatter: formatValue,
            },
        },
        grid: {
            yaxis: {
                lines: {
                    show: true,
                },
            },
        },
        fill: {
            opacity: 1,
        },

        tooltip: {
            x: {
                show: false,
            },
            y: {
                formatter: formatValue,
            },
        },
    };

    return (
        <div className="custom-scrollbar max-w-full overflow-x-auto">
            <div id="chartOne" className="min-w-[1000px]">
                <Chart
                    options={options}
                    series={series}
                    type="bar"
                    height={300}
                />
            </div>
        </div>
    );
}
