import Chart from "react-apexcharts";
import { ApexOptions } from "apexcharts";
import { currencyFormatter } from '@/lib/utils';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card"


interface LineComparisonChartProps<T> {
    chartData: T[];
    loading: boolean;
    error: string | null;
    title?: string;
    description?: string;
    footerDescription?: string;
    dataKeyLeft?: string;
    dataKeyRight?: string;
    labelLeft?: string;
    labelRight?: string;
}

export default function LineComparisonChart<T extends Record<string, any>>({
    chartData,
    loading,
    error,
    title = "Statistics",
    description = "Target you've set for each month",
    dataKeyLeft = "sales",
    dataKeyRight = "spend",
    labelLeft = "Sales",
    labelRight = "Revenue",
}: LineComparisonChartProps<T>) {

    const options: ApexOptions = {
        legend: {
            show: false,
            position: "top",
            horizontalAlign: "left",
        },
        colors: ["#465FFF", "#9CB9FF"],
        chart: {
            fontFamily: "Outfit, sans-serif",
            height: 310,
            type: "line",
            toolbar: {
                show: false,
            },
        },
        stroke: {
            curve: "straight",
            width: [2, 2],
        },
        fill: {
            type: "gradient",
            gradient: {
                opacityFrom: 0.55,
                opacityTo: 0,
            },
        },
        markers: {
            size: 0,
            strokeColors: "#fff",
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
            x: {
                format: "dd MMM yyyy",
            },
        },
        xaxis: {
            type: "category",
            categories: chartData.map((item: any) => {
                const date = new Date(item.date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }),
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
            labels: {
                style: {
                    fontSize: "12px",
                    colors: ["#6B7280"],
                },
                formatter: function (value: number) {
                    if (value >= 1000000) {
                        return (value / 1000000).toFixed(1) + 'M';
                    } else if (value >= 1000) {
                        return (value / 1000).toFixed(1) + 'K';
                    }
                    return value.toFixed(0);
                },
            },
            title: {
                text: "",
                style: {
                    fontSize: "0px",
                },
            },
        },
    };

    const series = [
        {
            name: labelLeft,
            data: chartData.map((item: any) => item[dataKeyLeft] || 0),
        },
        {
            name: labelRight,
            data: chartData.map((item: any) => item[dataKeyRight] || 0),
        },
    ];

    return (
        <Card className="lg:col-span-2">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                {loading ? (
                    <div className="flex items-center justify-center h-64">
                        <p className="text-muted-foreground">Loading chart data...</p>
                    </div>
                ) : error ? (
                    <div className="flex items-center justify-center h-64">
                        <p className="text-red-500">Error: {error}</p>
                    </div>
                ) : chartData.length > 0 ? (
                    <div className="max-w-full overflow-x-auto custom-scrollbar">
                        <div className="min-w-[1000px] xl:min-w-full">
                            <Chart options={options} series={series} type="area" height={310} />
                        </div>
                    </div>
                ) : (
                    <div className="flex items-center justify-center h-64">
                        <p className="text-muted-foreground">No data available for the selected period</p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
