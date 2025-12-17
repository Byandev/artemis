import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState, useEffect, useCallback, useRef } from 'react';
import { Workspace } from '@/types/models/Workspace';
import { currencyFormatter, numberFormatter, percentageFormatter } from '@/lib/utils';
import MetricsCard from '@/components/workspaces/MetricsCard';
import LineComparisonChart from '@/components/charts/LineComparisonChart';
import SingleLineChart from '@/components/charts/SingleLineChart';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Button } from '@/components/ui/button';
import { format, parse, differenceInDays } from 'date-fns';
import DashboardFilters from '@/components/workspaces/DashboardFilters';

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
    const { url } = usePage();
    const abortControllerRef = useRef<AbortController | null>(null);
    const isInitialMount = useRef(true);

    // Initialize dates from URL filters
    const [startDate, setStartDate] = useState<Date | undefined>(
        filters?.start_date ? parse(filters.start_date, 'yyyy-MM-dd', new Date()) : undefined
    );
    const [endDate, setEndDate] = useState<Date | undefined>(
        filters?.end_date ? parse(filters.end_date, 'yyyy-MM-dd', new Date()) : undefined
    );

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
        if (startDate && endDate) {
            const days = differenceInDays(endDate, startDate) + 1;
            return `${format(startDate, 'MMM d, yyyy')} - ${format(endDate, 'MMM d, yyyy')}`;
        } else if (startDate) {
            return `From ${format(startDate, 'MMM d, yyyy')}`;
        } else if (endDate) {
            return `Until ${format(endDate, 'MMM d, yyyy')}`;
        }
        return 'Last 30 days';
    }, [startDate, endDate]);

    // Calculate dynamic chart footer description
    const chartFooterDescription = useMemo(() => {
        if (startDate && endDate) {
            const days = differenceInDays(endDate, startDate) + 1;
            return `Showing ${days} ${days === 1 ? 'day' : 'days'} of data`;
        } else if (startDate) {
            return `Showing data from ${format(startDate, 'MMM d, yyyy')}`;
        } else if (endDate) {
            return `Showing data until ${format(endDate, 'MMM d, yyyy')}`;
        }
        return 'Showing last 30 days of data';
    }, [startDate, endDate]);


    // Build query string for chart data
    const queryString = useMemo(() => {
        const params = new URLSearchParams();
        if (startDate) {
            params.append('start_date', format(startDate, 'yyyy-MM-dd'));
        }
        if (endDate) {
            params.append('end_date', format(endDate, 'yyyy-MM-dd'));
        }
        // If no date range is set, default to last 30 days
        if (!startDate && !endDate) {
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
    }, [startDate, endDate, selectedTeams, selectedProducts, selectedPages, selectedShops]);

    useEffect(() => {
        // Cancel previous request if it exists
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
        }

        const fetchChartData = async () => {
            try {
                setLoading(true);
                abortControllerRef.current = new AbortController();

                const response = await fetch(
                    `/workspaces/${workspace.slug}/chart-data?${queryString}`,
                    { signal: abortControllerRef.current.signal }
                );

                if (!response.ok) {
                    throw new Error('Failed to fetch chart data');
                }

                const data = await response.json();
                setChartData(data.chartData);
                setError(null);
            } catch (err) {
                // Ignore abort errors
                if (err instanceof Error && err.name === 'AbortError') {
                    return;
                }
                setError(err instanceof Error ? err.message : 'An error occurred');
                console.error('Error fetching chart data:', err);
            } finally {
                setLoading(false);
            }
        };

        fetchChartData();

        // Cleanup function
        return () => {
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        };
    }, [workspace.slug, queryString]);

    const clearFilters = useCallback(() => {
        setStartDate(undefined);
        setEndDate(undefined);
        setSelectedTeams([]);
        setSelectedProducts([]);
        setSelectedPages([]);
        setSelectedShops([]);
        router.get(url.split('?')[0], {}, { preserveState: true, preserveScroll: true });
    }, [url]);

    // Build params object - memoize to avoid recreating on every render
    const buildParams = useCallback(() => {
        const params: Record<string, string> = {};
        if (startDate) params.start_date = format(startDate, 'yyyy-MM-dd');
        if (endDate) params.end_date = format(endDate, 'yyyy-MM-dd');
        if (selectedTeams.length > 0) params.team_ids = selectedTeams.join(',');
        if (selectedProducts.length > 0) params.product_ids = selectedProducts.join(',');
        if (selectedPages.length > 0) params.page_ids = selectedPages.join(',');
        if (selectedShops.length > 0) params.shop_ids = selectedShops.join(',');
        return params;
    }, [startDate, endDate, selectedTeams, selectedProducts, selectedPages, selectedShops]);

    const handleDateRangeChange = useCallback((values: { range: { from?: Date; to?: Date } }) => {
        const start = values.range.from ?? undefined;
        const end = values.range.to ?? undefined;

        setStartDate(start);
        setEndDate(end);

        // Build params with new dates
        const params: Record<string, string> = {};
        if (start) params.start_date = format(start, 'yyyy-MM-dd');
        if (end) params.end_date = format(end, 'yyyy-MM-dd');
        if (selectedTeams.length > 0) params.team_ids = selectedTeams.join(',');
        if (selectedProducts.length > 0) params.product_ids = selectedProducts.join(',');
        if (selectedPages.length > 0) params.page_ids = selectedPages.join(',');
        if (selectedShops.length > 0) params.shop_ids = selectedShops.join(',');

        router.get(url.split('?')[0], params, { preserveState: true, preserveScroll: true });
    }, [url, selectedTeams, selectedProducts, selectedPages, selectedShops]);

    // Debounced effect to update URL when entity filters change
    useEffect(() => {
        // Skip on initial mount
        if (isInitialMount.current) {
            isInitialMount.current = false;
            return;
        }

        const timer = setTimeout(() => {
            const params = buildParams();
            router.get(url.split('?')[0], params, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);

        return () => clearTimeout(timer);
    }, [selectedTeams, selectedProducts, selectedPages, selectedShops, buildParams, url]);

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
                        {(startDate || endDate || selectedTeams.length > 0 || selectedProducts.length > 0 || selectedPages.length > 0 || selectedShops.length > 0) && (
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
                        <div>

                            <DateRangePicker
                                onUpdate={handleDateRangeChange}
                                align="end"
                                initialDateTo={endDate}
                                initialDateFrom={startDate}
                            />
                        </div>
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
                        footerDescription={chartFooterDescription}
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
                        footerDescription={chartFooterDescription}
                        dataKey="sales"
                        label="Total Sales"
                        color="var(--chart-1)"
                    />
                    <SingleLineChart<ChartDataPoint>
                        chartData={chartData}
                        loading={loading}
                        error={error}
                        title="Total Ad Spend"
                        description={chartDescription}
                        footerDescription={chartFooterDescription}
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
                        description={chartDescription}
                        footerDescription={chartFooterDescription}
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
                        description={chartDescription}
                        footerDescription={chartFooterDescription}
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
