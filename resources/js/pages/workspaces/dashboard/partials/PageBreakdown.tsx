import BarChart from '@/components/charts/BarChart';
import { FilterValue } from '@/components/filters/Filters';
import { Button } from '@/components/ui/button';
import DropdownSelect from '@/components/common/DropdownSelect';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import BarChartSkeleton from '@/components/charts/skeletons/BarChartSkeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { RefreshCcw } from 'lucide-react';
import ComponentCard from '@/components/common/ComponentCard';

interface Props {
    workspace: Workspace;
    dateRange: string[];
    filter: FilterValue;
}

interface BreakdownItem {
    page_id: number;
    page_name: string;
    value: number | string;
}

export default function PageBreakdown({ workspace, dateRange, filter }: Props) {
    const [breakdown, setBreakdown] = useState<BreakdownItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [option, setOption] = useState('totalSales');
    const [reload, setReload] = useState(false);


    const startDate = moment(dateRange[0]).format('YYYY-MM-DD');
    const endDate = moment(dateRange[1]).format('YYYY-MM-DD');

    const teamIds = filter.teamIds.join(',');
    const shopIds = filter.shopIds.join(',');
    const pageIds = filter.pageIds.join(',');
    const userIds = filter.userIds.join(',');
    const productIds = filter.productIds.join(',');

    const formatValue = (value: number) => {
        switch (option) {
            case 'totalSales':
            case 'avgLifetimeValue':
                return `₱${value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            case 'rtsRate':
            case 'repeatOrderRatio':
                return `${(value * 100).toFixed(1)}%`;
            case 'timeToFirstOrder':
                return `${value.toFixed(1)} hrs`;
            case 'avgDeliveryDays':
            case 'avgShippedOutDays':
                return `${value.toFixed(1)} days`;
            case 'aov':
            case 'totalOrders':
            default:
                return value.toLocaleString();
        }
    };

    useEffect(() => {
        const controller = new AbortController();

        setLoading(true);
        setError(null);

        axios
            .get('/api/v1/workspace/analytics/per-page', {
                signal: controller.signal,
                headers: {
                    'X-Workspace-Id': workspace.id,
                },
                params: {
                    metric: option,
                    'date_range[start_date]': startDate,
                    'date_range[end_date]': endDate,
                    'filter[team_ids]': teamIds || undefined,
                    'filter[shop_ids]': shopIds || undefined,
                    'filter[page_ids]': pageIds || undefined,
                    'filter[user_ids]': userIds || undefined,
                    'filter[product_ids]': productIds || undefined,
                },
            })
            .then((response) => {
                setBreakdown(response.data.data ?? []);
            })
            .catch((error) => {
                if (error.code !== 'ERR_CANCELED') {
                    console.error(error?.response?.data || error);
                    setError('Failed to load data. Please try reload.');
                    setBreakdown([]);
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [
        workspace.id,
        option,
        startDate,
        endDate,
        teamIds,
        shopIds,
        pageIds,
        userIds,
        productIds,
        reload
    ]);

    const categories = useMemo(() => {
        return breakdown.map((item) => item.page_name);
    }, [breakdown]);

    const optionLabels: Record<string, string> = {
        totalSales: 'Total Sales',
        totalOrders: 'Total Orders',
        aov: 'AOV',
        rtsRate: 'RTS',
        repeatOrderRatio: 'ROR',
        timeToFirstOrder: 'Time to First Order',
        avgLifetimeValue: 'Average Lifetime Value',
        avgDeliveryDays: 'Average Delivery Days',
        avgShippedOutDays: 'Average Shipped Out Days',
    };

    const series = useMemo(() => {
        return [
            {
                name: optionLabels[option] ?? 'Value',
                data: breakdown.map((item) => Number(item.value)),
            },
        ];
    }, [breakdown, option]);

    return (

            <div className="space-y-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-[14px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                            {optionLabels[option]} <span className="text-gray-300 dark:text-gray-600">·</span> Per Page
                        </h2>
                    </div>

                    <div className="flex items-center gap-4">
                        <DropdownSelect
                            value={option}
                            onChange={setOption}
                            options={Object.entries(optionLabels).map(([key, label]) => ({ key, label }))}
                            label="Metric"
                            align="end"
                        />

                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 w-8 rounded-[10px] border border-black/6 dark:border-white/6 bg-stone-100 dark:bg-zinc-800 p-0 text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-stone-200 dark:hover:bg-zinc-700"
                                    onClick={() =>
                                        setReload((prevState) => !prevState)
                                    }
                                >
                                    <RefreshCcw className="h-3.5 w-3.5" />
                                </Button>
                            </TooltipTrigger>

                            <TooltipContent>Refresh</TooltipContent>
                        </Tooltip>
                    </div>
                </div>

                {loading ? (
                    <BarChartSkeleton />
                ) : error ? (
                    <div className="flex h-60 flex-col items-center justify-center space-y-4 rounded-lg border border-gray-200 bg-gray-50">
                        <p className="text-red-500">{error}</p>
                        <Button
                            variant="outline"
                            onClick={() => setReload((prevState) => !prevState)}
                            className="gap-2"
                        >
                            <svg
                                className="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                            Try Again
                        </Button>
                    </div>
                ) : !breakdown.length ? (
                    <div className="flex h-60 flex-col items-center justify-center space-y-2 rounded-lg border border-gray-200 bg-gray-50">
                        <p className="text-gray-500">
                            No data available for the selected period
                        </p>
                        <p className="text-sm text-gray-400">
                            Try adjusting your filters or date range
                        </p>
                    </div>
                ) : (
                    <BarChart
                        categories={categories}
                        series={series}
                        formatValue={formatValue}
                    />
                )}
            </div>

    );
}
