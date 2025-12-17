import { TrendingUp } from "lucide-react"
import { CartesianGrid, Line, LineChart, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts"
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

interface SingleLineChartProps<T extends Record<string, any>> {
    chartData: T[];
    loading: boolean;
    error: string | null;
    title: string;
    description: string;
    footerDescription?: string;
    dataKey: string;
    label: string;
    color?: string;
    formatter?: (value: number) => string | number;
}

export default function SingleLineChart<T extends Record<string, any>>({
    chartData,
    loading,
    error,
    title,
    description,
    footerDescription = "Showing data for the last 30 days",
    dataKey,
    label,
    color = "var(--chart-1)",
    formatter = currencyFormatter,
}: SingleLineChartProps<T>) {
    const chartConfig = {
        [dataKey]: {
            label: label,
            color: color,
        },
    } satisfies ChartConfig

    return (
        <Card>
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
                                    formatter={(value: number) => formatter(value)}
                                    contentStyle={{
                                        backgroundColor: 'var(--background)',
                                        border: '1px solid var(--border)',
                                        borderRadius: 'var(--radius)',
                                    }}
                                />
                                <Line
                                    dataKey={dataKey}
                                    type="monotone"
                                    stroke={color}
                                    strokeWidth={2}
                                    dot={false}
                                    name={label}
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
                            {label} <TrendingUp className="h-4 w-4" />
                        </div>
                        <div className="text-muted-foreground flex items-center gap-2 leading-none">
                            {footerDescription}
                        </div>
                    </div>
                </div>
            </CardFooter>
        </Card>
    );
}
