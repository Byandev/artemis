import { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

interface Props {
    series: ApexNonAxisChartSeries | undefined;
    categories: string[];
    formatValue: (value: number) => string;
}

export default function BarChart({ categories, series, formatValue }: Props) {
    const columnWidth =
        categories.length > 20 ? '80%' : categories.length > 10 ? '60%' : '40%';

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
                columnWidth: columnWidth,
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
            labels: {
                show: false, // ❌ hide bottom labels
            },
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
        },

        legend: {
            show: false, // ❌ hide series legend
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
                show: true, // keep default tooltip category
            },
            y: {
                formatter: formatValue,
                title: {
                    formatter: () => '', // remove series name inside tooltip
                },
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
