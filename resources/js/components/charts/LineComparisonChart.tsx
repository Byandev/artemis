import { TrendingUp } from "lucide-react"
import { CartesianGrid, Line, LineChart, XAxis, YAxis, Tooltip, Legend, ResponsiveContainer } from "recharts"
import { currencyFormatter } from '@/lib/utils';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card"
import {
    ChartContainer,
    type ChartConfig,
} from "@/components/ui/chart"


interface LineComparisonChartProps<T> {
    chartData: T[];
    loading: boolean;
    error: string | null;
    title?: string;
    description?: string;
    dataKeyLeft?: string;
    dataKeyRight?: string;
    labelLeft?: string;
    labelRight?: string;
}

interface LineComparisonChartConfig extends ChartConfig {
    [key: string]: any;
}

export default function LineComparisonChart<T>({
    chartData,
    loading,
    error,
    title = "Total Sales vs. Total Ad Spent",
    description = "Last 30 days comparison",
    dataKeyLeft = "sales",
    dataKeyRight = "spend",
    labelLeft = "Total Sales",
    labelRight = "Ad Spend",
}: LineComparisonChartProps<T>) {
    const chartConfig: LineComparisonChartConfig = {
        [dataKeyLeft]: {
            label: labelLeft,
            color: "var(--chart-1)",
        },
        [dataKeyRight]: {
            label: labelRight,
            color: "var(--chart-2)",
        },
    }
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
                    <ChartContainer config={chartConfig} className="h-80 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={chartData}>
                                <CartesianGrid vertical={false} />
                                <XAxis
                                    dataKey="date"
                                    tickLine={false}
                                    axisLine={false}
                                    tickMargin={8}
                                    tickFormatter={(value) => {
                                        const date = new Date(value);
                                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                                    }}
                                />
                                <YAxis />
                                <Tooltip
                                    formatter={(value: number) => currencyFormatter(value)}
                                    contentStyle={{
                                        backgroundColor: 'var(--background)',
                                        border: '1px solid var(--border)',
                                        borderRadius: 'var(--radius)',
                                    }}
                                />
                                <Legend />
                                <Line
                                    dataKey={dataKeyLeft}
                                    type="monotone"
                                    stroke="var(--chart-1)"
                                    strokeWidth={2}
                                    dot={false}
                                    name={labelLeft}
                                />
                                <Line
                                    dataKey={dataKeyRight}
                                    type="monotone"
                                    stroke="var(--chart-2)"
                                    strokeWidth={2}
                                    dot={false}
                                    name={labelRight}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </ChartContainer>
                ) : (
                    <div className="flex items-center justify-center h-64">
                        <p className="text-muted-foreground">No data available for the selected period</p>
                    </div>
                )}
            </CardContent>
            <CardFooter>
                <div className="flex w-full items-start gap-2 text-sm">
                    <div className="grid gap-2">
                        <div className="flex items-center gap-2 leading-none font-medium">
                            Line comparison chart <TrendingUp className="h-4 w-4" />
                        </div>
                        <div className="text-muted-foreground flex items-center gap-2 leading-none">
                            Showing data for the last 30 days
                        </div>
                    </div>
                </div>
            </CardFooter>
        </Card>
    );
}
