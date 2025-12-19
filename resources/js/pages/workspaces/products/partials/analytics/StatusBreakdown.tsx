import Chart from "react-apexcharts";
import { ApexOptions } from "apexcharts";
import { useMemo } from 'react';

type Props = {
    scaling_product_count: number;
    testing_product_count: number;
    inactive_product_count: number;
}

export default function StatusBreakdown({ scaling_product_count, testing_product_count, inactive_product_count }: Props) {
    const series = useMemo(() => [scaling_product_count, testing_product_count, inactive_product_count], [scaling_product_count, testing_product_count, inactive_product_count]);

    const options: ApexOptions = {
        colors: ["#0eaa82", "#8cd9c5", "#ecf3f1"],
        labels: ["Active", "Testing", "Inactive"],
        chart: {
            fontFamily: "Outfit, sans-serif",
            type: "donut",
            width: 445,
            height: 290,
        },
        plotOptions: {
            pie: {
                donut: {
                    size: "65%",
                    background: "transparent",
                    labels: {
                        show: true,
                        value: {
                            show: true,
                            offsetY: 0,
                        },
                    },
                },
            },
        },
        states: {
            hover: {
                filter: {
                    type: "none",
                },
            },
            active: {
                allowMultipleDataPointsSelection: false,
                filter: {
                    type: "darken",
                },
            },
        },
        dataLabels: {
            enabled: false,
        },
        tooltip: {
            enabled: false,
        },
        stroke: {
            show: false,
            width: 4, // Creates a gap between the series
        },

        legend: {
            show: true,
            position: "bottom",
            horizontalAlign: "center",
            fontFamily: "Outfit",
            fontSize: "14px",
            fontWeight: 400,
            markers: {
                size: 4,
                shape: "circle",
                strokeWidth: 0,
            },
            itemMargin: {
                horizontal: 10,
                vertical: 0,
            },
            labels: {
                useSeriesColors: true, // Optional: this makes each label color match the corresponding segment color
            },
        },
        responsive: [
            {
                breakpoint: 640,
                options: {
                    chart: {
                        width: 370,
                        height: 290,
                    },
                },
            },
        ],
    };

    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <div className="flex items-center justify-between mb-9">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Products Breakdown
                </h3>
            </div>
            <div>
                <div className="flex justify-center mx-auto">
                    <Chart options={options} series={series} type="donut" height={290} />
                </div>
            </div>
        </div>
    );
}
