import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { useMemo, useState, useEffect } from 'react';
import { Workspace } from '@/types/models/Workspace';
import { currencyFormatter, numberFormatter, percentageFormatter } from '@/lib/utils';
import MetricsCard from '@/components/workspaces/MetricsCard';
import LineComparisonChart from '@/components/charts/LineComparisonChart';
import SingleLineChart from '@/components/charts/SingleLineChart';

interface ChartDataPoint {
    date: string;
    sales: number;
    spend: number;
    roas: number;
    rts_rate: number;
}


type Props = {
    workspace: Workspace;
    stats: {
        total_sales: number;
        total_ad_spend: number;
        total_orders: number;
        roas: number;
        rts_rate_percentage: number;
        delivered_orders: number;
        sms_sent: number;
        chat_msg_sent: number;
    }
}

export default function Index({ workspace, stats }: Props) {
    const [chartData, setChartData] = useState<ChartDataPoint[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const analytics = useMemo(() => {
        return [
            { title: 'Total Sales', value: currencyFormatter(stats.total_sales) },
            { title: 'Total Add Spend', value: currencyFormatter(stats.total_ad_spend) },
            { title: 'Total Orders', value: numberFormatter(stats.total_orders) },
            { title: 'ROAS', value: stats.roas },
            { title: 'RTS Rate', value: percentageFormatter(stats.rts_rate_percentage / 100) },
            { title: 'Delivered Orders', value: numberFormatter(stats.delivered_orders) },
            { title: 'SMS Sent', value: numberFormatter(stats.sms_sent) },
            { title: 'Chat Messages Sent', value: numberFormatter(stats.chat_msg_sent) },
        ]
    }, [stats])

    useEffect(() => {
        const fetchChartData = async () => {
            try {
                setLoading(true);
                const response = await fetch(
                    `/workspaces/${workspace.slug}/api/chart-data?days=30`
                );

                if (!response.ok) {
                    throw new Error('Failed to fetch chart data');
                }

                const data = await response.json();
                console.log('Fetched chart data:', data);
                setChartData(data.chartData);
                setError(null);
            } catch (err) {
                setError(err instanceof Error ? err.message : 'An error occurred');
                console.error('Error fetching chart data:', err);
            } finally {
                setLoading(false);
            }
        };

        fetchChartData();
    }, [workspace.slug]);

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Dashboard</h1>
                    <p className="text-muted-foreground mt-1">Welcome back! Here's your workspace overview.</p>
                </div>

                <div className="grid auto-rows-min gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                    {analytics.map((data, key) => (
                        <MetricsCard key={key} title={data.title} value={data.value} />
                    ))}
                </div>

                <div className="grid gap-4 grid-cols-1 lg:grid-cols-2">
                    <LineComparisonChart<ChartDataPoint>
                        chartData={chartData}
                        loading={loading}
                        error={error}
                        title="Total Sales vs. Total Ad Spent"
                        description="Last 30 days comparison"
                        dataKeyLeft="sales"
                        dataKeyRight="spend"
                        labelLeft="Total Sales"
                        labelRight="Ad Spend"
                    />
                </div>

                <div className="grid gap-4 grid-cols-1 lg:grid-cols-2">
                    <SingleLineChart<ChartDataPoint>
                        chartData={chartData}
                        loading={loading}
                        error={error}
                        title="Total Sales"
                        description="Last 30 days"
                        dataKey="sales"
                        label="Total Sales"
                        color="#465FFF"
                    />
                    <SingleLineChart<ChartDataPoint>
                        chartData={chartData}
                        loading={loading}
                        error={error}
                        title="Total Ad Spend"
                        description="Last 30 days"
                        dataKey="spend"
                        label="Ad Spend"
                        color="#9CB9FF"
                    />
                </div>

                <div className="grid gap-4 grid-cols-1 lg:grid-cols-2">
                    <SingleLineChart<ChartDataPoint>
                        chartData={chartData}
                        loading={loading}
                        error={error}
                        title="ROAS (Return on Ad Spend)"
                        description="Last 30 days"
                        dataKey="roas"
                        label="ROAS"
                        color="#10B981"
                        formatter={(value: number) => value.toFixed(2)}
                    />
                    <SingleLineChart<ChartDataPoint>
                        chartData={chartData}
                        loading={loading}
                        error={error}
                        title="RTS Rate (Return to Sender)"
                        description="Last 30 days"
                        dataKey="rts_rate"
                        label="RTS Rate"
                        color="#EF4444"
                        formatter={(value: number) => value.toFixed(2) + '%'}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
