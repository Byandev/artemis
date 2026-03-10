import { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

interface Props {
    series: ApexNonAxisChartSeries | undefined;
    categories: string[];
}
export default function LineChartOne({categories, series} : Props) {
    const options: ApexOptions = {
        legend: {
            show: false, // Hide legend
            position: 'top',
            horizontalAlign: 'left',
        },
        colors: ['#059669', '#9CB9FF'], // Define line colors
        chart: {
            fontFamily: 'Outfit, sans-serif',
            height: 310,
            type: 'line', // Set the chart type to 'line'
            toolbar: {
                show: false, // Hide chart toolbar
            },
        },
        stroke: {
            curve: 'smooth', // Define the line style (straight, smooth, or step)
            width: [1, 10], // Line width for each dataset
        },

        fill: {
            type: 'gradient',
            gradient: {
                opacityFrom: 0.55,
                opacityTo: 0,
            },
        },
        markers: {
            size: 0, // Size of the marker points
            strokeColors: '#fff', // Marker border color
            strokeWidth: 2,
            hover: {
                size: 6, // Marker size on hover
            },
        },
        grid: {
            xaxis: {
                lines: {
                    show: false, // Hide grid lines on x-axis
                },
            },
            yaxis: {
                lines: {
                    show: true, // Show grid lines on y-axis
                },
            },
        },
        dataLabels: {
            enabled: false, // Disable data labels
        },
        tooltip: {
            enabled: true, // Enable tooltip
            x: {
                format: 'dd MMM yyyy', // Format for x-axis tooltip
            },
        },
        xaxis: {
            type: 'category', // Category-based x-axis
            categories: categories,
            axisBorder: {
                show: false, // Hide x-axis border
            },
            axisTicks: {
                show: false, // Hide x-axis ticks
            },
            tooltip: {
                enabled: false, // Disable tooltip for x-axis points
            },
        },
        yaxis: {
            labels: {
                style: {
                    fontSize: '12px', // Adjust font size for y-axis labels
                    colors: ['#6B7280'], // Color of the labels
                },
            },
            title: {
                text: '', // Remove y-axis title
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
