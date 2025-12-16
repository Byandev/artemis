import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState, useEffect, useCallback } from 'react';
import { Workspace } from '@/types/models/Workspace';
import { currencyFormatter, numberFormatter, percentageFormatter } from '@/lib/utils';
import MetricsCard from '@/components/workspaces/MetricsCard';
import LineComparisonChart from '@/components/charts/LineComparisonChart';
import SingleLineChart from '@/components/charts/SingleLineChart';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Button } from '@/components/ui/button';
import { format, parse } from 'date-fns';

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
    filters?: {
        start_date?: string;
        end_date?: string;
    }
}

export default function Index({ workspace, stats, filters }: Props) {
    const { url } = usePage();

    // Initialize dates from URL filters
    const [startDate, setStartDate] = useState<Date | undefined>(
        filters?.start_date ? parse(filters.start_date, 'yyyy-MM-dd', new Date()) : undefined
    );
    const [endDate, setEndDate] = useState<Date | undefined>(
        filters?.end_date ? parse(filters.end_date, 'yyyy-MM-dd', new Date()) : undefined
    );
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


    // Build query string for chart data
    const queryString = useMemo(() => {
        const params = new URLSearchParams();
        if (startDate) {
            params.append('start_date', format(startDate, 'yyyy-MM-dd'));
        }
        if (endDate) {
            params.append('end_date', format(endDate, 'yyyy-MM-dd'));
        }
        return params.toString();
    }, [startDate, endDate]);

    useEffect(() => {
        const fetchChartData = async () => {
            try {
                setLoading(true);
                const response = await fetch(
                    `/workspaces/${workspace.slug}/api/chart-data?${queryString}`
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
    }, [workspace.slug, queryString]);

    const clearFilters = useCallback(() => {
        setStartDate(undefined);
        setEndDate(undefined);
        router.get(url.split('?')[0], {}, { preserveState: true, preserveScroll: true });
    }, [url]);

    const handleDateRangeChange = useCallback((values: any) => {
        const start = values.range.from ?? undefined;
        const end = values.range.to ?? undefined;

        setStartDate(start);
        setEndDate(end);

        // Update URL with new filters
        const params: Record<string, any> = {};
        if (start) params.start_date = format(start, 'yyyy-MM-dd');
        if (end) params.end_date = format(end, 'yyyy-MM-dd');

        router.get(url, params, { preserveState: true, preserveScroll: true });
    }, [url]);

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Dashboard</h1>
                        <p className="text-muted-foreground mt-1">Welcome back! Here's your workspace overview.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        {(startDate || endDate) && (
                            <Button
                                variant="outline"
                                onClick={clearFilters}
                            >
                                Clear Filters
                            </Button>
                        )}
                        <DateRangePicker
                            onUpdate={handleDateRangeChange}
                            align="end"
                            initialDateTo={endDate}
                            initialDateFrom={startDate}
                        />
                    </div>
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
                        color="var(--chart-1)"
                    />
                    <SingleLineChart<ChartDataPoint>
                        chartData={chartData}
                        loading={loading}
                        error={error}
                        title="Total Ad Spend"
                        description="Last 30 days"
                        dataKey="spend"
                        label="Ad Spend"
                        color="var(--chart-2)"
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
                        color="var(--chart-3)"
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
                        color="var(--chart-4)"
                        formatter={(value: number) => value.toFixed(2) + '%'}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
