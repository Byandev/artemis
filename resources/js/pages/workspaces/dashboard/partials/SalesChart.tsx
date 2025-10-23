import { useEffect, useState } from 'react';
import { Line, LineChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';

interface SalesData {
    date: string;
    total_sales: number;
}

const chartConfig = {
    total_sales: {
        label: "Total Sales",
        color: "hsl(var(--chart-1))",
    },
};

const SalesChart = () => {
    const [salesData, setSalesData] = useState<SalesData[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetch(`/workspaces/my-workspace/records/sales`)
            .then((response) => response.json())
            .then((data) => {
                setSalesData(data);
                setLoading(false);
            })
            .catch((error) => {
                console.error('Error fetching sales data:', error);
                setLoading(false);
            });
    }, []);

    if (loading) {
        return <div>Loading...</div>;
    }

    return (
        <ChartContainer id={'total_sales'} config={chartConfig}>
            <LineChart data={salesData}>
                <CartesianGrid strokeDasharray="3 3" />
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
                <YAxis
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                    tickFormatter={(value) => `$${value.toLocaleString()}`}
                />
                <ChartTooltip
                    content={
                        <ChartTooltipContent
                            labelFormatter={(value) => {
                                return new Date(value).toLocaleDateString('en-US', {
                                    month: 'long',
                                    day: 'numeric',
                                    year: 'numeric',
                                });
                            }}
                        />
                    }
                />
                <Line
                    type="monotone"
                    dataKey="total_sales"
                    stroke="var(--color-total_sales)"
                    strokeWidth={2}
                    dot={false}
                />
            </LineChart>
        </ChartContainer>
    );
}

export default SalesChart;
