import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState, useEffect } from 'react';
import { Workspace } from '@/types/models/Workspace';
import { currencyFormatter, numberFormatter, percentageFormatter } from '@/lib/utils';
import MetricsCard from '@/components/workspaces/MetricsCard';
import LineComparisonChart from '@/components/charts/LineComparisonChart';
import SingleLineChart from '@/components/charts/SingleLineChart';
import { SimpleDateRangePicker } from '@/components/ui/simple-date-range-picker';
import { Button } from '@/components/ui/button';
import { format, parse, differenceInDays } from 'date-fns';
import DashboardFilters from '@/components/workspaces/DashboardFilters';
import { type DateRange } from "react-day-picker";
import moment from 'moment';
import workspaces from '@/routes/workspace';

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
        team_ids?: string;
        product_ids?: string;
        page_ids?: string;
        shop_ids?: string;
    }
    availableTeams: { id: number; name: string }[];
    availableProducts: { id: number; name: string }[];
    availablePages: { id: number; name: string }[];
    availableShops: { id: number; name: string }[];
}

export default function Index({ workspace, stats, filters, availableTeams, availableProducts, availablePages, availableShops }: Props) {

    // Initialize dates from URL filters
    const [dateRange, setDateRange] = useState<DateRange | undefined>({
        from: filters?.start_date ? new Date(filters.start_date) : moment().startOf('month').toDate(),
        to: filters?.end_date ? new Date(filters.end_date) : moment().toDate()
    });

    const dateRangeStr = useMemo(() => ({
        to: moment(dateRange?.to).format('YYYY-MM-DD'),
        from: moment(dateRange?.from).format('YYYY-MM-DD'),
    }), [dateRange]);

    // Initialize entity filters from URL
    const [selectedTeams, setSelectedTeams] = useState<number[]>(
        filters?.team_ids ? filters.team_ids.split(',').map(Number) : []
    );
    const [selectedProducts, setSelectedProducts] = useState<number[]>(
        filters?.product_ids ? filters.product_ids.split(',').map(Number) : []
    );
    const [selectedPages, setSelectedPages] = useState<number[]>(
        filters?.page_ids ? filters.page_ids.split(',').map(Number) : []
    );
    const [selectedShops, setSelectedShops] = useState<number[]>(
        filters?.shop_ids ? filters.shop_ids.split(',').map(Number) : []
    );

    const [chartData, setChartData] = useState<ChartDataPoint[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const analytics = useMemo(() => {
        return [
            { title: 'Total Sales', value: currencyFormatter(stats.total_sales) },
            { title: 'Total Ad Spend', value: currencyFormatter(stats.total_ad_spend) },
            { title: 'Total Orders', value: numberFormatter(stats.total_orders) },
            { title: 'ROAS', value: stats.roas },
            { title: 'RTS Rate', value: percentageFormatter(stats.rts_rate_percentage / 100) },
            { title: 'Delivered Orders', value: numberFormatter(stats.delivered_orders) },
            { title: 'SMS Sent', value: numberFormatter(stats.sms_sent) },
            { title: 'Chat Messages Sent', value: numberFormatter(stats.chat_msg_sent) },
        ]
    }, [stats])

    // Calculate dynamic chart description based on date range
    const chartDescription = useMemo(() => {
        if (dateRange?.from && dateRange?.to) {
            const days = differenceInDays(dateRange.to, dateRange.from) + 1;
            return `${format(dateRange.from, 'MMM d, yyyy')} - ${format(dateRange.to, 'MMM d, yyyy')}`;
        } else if (dateRange?.from) {
            return `From ${format(dateRange.from, 'MMM d, yyyy')}`;
        } else if (dateRange?.to) {
            return `Until ${format(dateRange.to, 'MMM d, yyyy')}`;
        }
        return 'Last 30 days';
    }, [dateRange]);

    // Build query string for chart data
    const queryString = useMemo(() => {
        const params = new URLSearchParams();
        if (dateRange?.from) {
            params.append('start_date', dateRangeStr.from);
        }
        if (dateRange?.to) {
            params.append('end_date', dateRangeStr.to);
        }
        // If no date range is set, default to last 30 days
        if (!dateRange?.from && !dateRange?.to) {
            params.append('days', '30');
        }
        if (selectedTeams.length > 0) {
            params.append('team_ids', selectedTeams.join(','));
        }
        if (selectedProducts.length > 0) {
            params.append('product_ids', selectedProducts.join(','));
        }
        if (selectedPages.length > 0) {
            params.append('page_ids', selectedPages.join(','));
        }
        if (selectedShops.length > 0) {
            params.append('shop_ids', selectedShops.join(','));
        }
        return params.toString();
    }, [dateRange, dateRangeStr, selectedTeams, selectedProducts, selectedPages, selectedShops]);

    useEffect(() => {
        const fetchChartData = async () => {
            try {
                setLoading(true);

                const response = await fetch(
                    `/workspaces/${workspace.slug}/chart-data?${queryString}`
                );

                if (!response.ok) {
                    throw new Error('Failed to fetch chart data');
                }

                const data = await response.json();
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

    // Update URL and refetch data when filters change
    useEffect(() => {
        router.get(
            workspaces.dashboard(workspace.slug),
            {
                team_ids: selectedTeams.length > 0 ? selectedTeams.join(',') : undefined,
                product_ids: selectedProducts.length > 0 ? selectedProducts.join(',') : undefined,
                page_ids: selectedPages.length > 0 ? selectedPages.join(',') : undefined,
                shop_ids: selectedShops.length > 0 ? selectedShops.join(',') : undefined,
                start_date: dateRange?.from ? dateRangeStr.from : undefined,
                end_date: dateRange?.to ? dateRangeStr.to : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['stats']
            }
        );
    }, [workspace.slug, selectedTeams, selectedProducts, selectedPages, selectedShops, dateRange, dateRangeStr]);

    const clearFilters = () => {
        setDateRange({
            from: moment().startOf('month').toDate(),
            to: moment().toDate()
        });
        setSelectedTeams([]);
        setSelectedProducts([]);
        setSelectedPages([]);
        setSelectedShops([]);
        router.get(
            workspaces.dashboard(workspace.slug),
            {},
            { preserveState: true, preserveScroll: true }
        );
    };

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
                        {(selectedTeams.length > 0 || selectedProducts.length > 0 || selectedPages.length > 0 || selectedShops.length > 0 || dateRangeStr.from !== moment().startOf('month').format('YYYY-MM-DD') || dateRangeStr.to !== moment().format('YYYY-MM-DD')) && (
                            <Button
                                variant="outline"
                                onClick={clearFilters}
                            >
                                Clear Filters
                            </Button>
                        )}
                        <DashboardFilters
                            availableTeams={availableTeams}
                            availableProducts={availableProducts}
                            availablePages={availablePages}
                            availableShops={availableShops}
                            selectedTeams={selectedTeams}
                            setSelectedTeams={setSelectedTeams}
                            selectedProducts={selectedProducts}
                            setSelectedProducts={setSelectedProducts}
                            selectedPages={selectedPages}
                            setSelectedPages={setSelectedPages}
                            selectedShops={selectedShops}
                            setSelectedShops={setSelectedShops}
                        />
                        <SimpleDateRangePicker
                            value={dateRange}
                            onChange={setDateRange}
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
                        description={chartDescription}
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
                        description={chartDescription}
                        dataKey="sales"
                        label="Total Sales"
                        color="#465FFF"
                    />
                    <SingleLineChart<ChartDataPoint>
                        chartData={chartData}
                        loading={loading}
                        error={error}
                        title="Total Ad Spend"
                        description={chartDescription}
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
                        description={chartDescription}
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
                        description={chartDescription}
                        dataKey="rts_rate"
                        label="RTS Rate"
                        color="#EF4444"
                        formatter={(value: number) => value.toFixed(2) + '%'}
                        yAxisFormatter={(value: number) => value.toFixed(0) + '%'}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
