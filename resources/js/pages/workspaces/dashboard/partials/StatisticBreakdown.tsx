import LineChart from '@/components/charts/LineChart';
import LineChartSkeleton from '@/components/charts/skeletons/LineChartSkeleton';
import { FilterValue } from '@/components/filters/Filters';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import { RefreshCcw } from 'lucide-react';
import moment from 'moment/moment';
import { useEffect, useMemo, useState } from 'react';

interface Props {
    workspace: Workspace;
    dateRange: string[];
    filter: FilterValue;
}

export function StatisticBreakdown({ workspace, dateRange, filter }: Props) {
    const [primaryBreakdown, setPrimaryBreakdown] = useState<any[]>([]);
    const [secondaryBreakdown, setSecondaryBreakdown] = useState<any[]>([]);

    const [option, setOption] = useState('totalSales');
    const [secondOption, setSecondOption] = useState('totalOrders');

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [group, setGroup] = useState('daily');
    const [reload, setReload] = useState(false);

    const start = moment(dateRange[0]);
    const end = moment(dateRange[1]);
    const daysDiff = end.diff(start, 'days');

    const availableGroups: string[] = [];
    if (daysDiff <= 7) {
        availableGroups.push('daily');
    } else if (daysDiff <= 30) {
        availableGroups.push('daily', 'weekly');
    } else if (daysDiff <= 365) {
        availableGroups.push('weekly', 'monthly');
    } else {
        availableGroups.push('weekly', 'monthly', 'yearly');
    }

    useEffect(() => {
        if (!availableGroups.includes(group) && availableGroups.length > 0) {
            setGroup(availableGroups[0]);
        }
    }, [availableGroups, group]);

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

    const formatValue = (value: number) => {
        switch (option) {
            case 'totalSales':
            case 'avgLifetimeValue':
                return `₱${value.toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                })}`;
            case 'rtsRate':
            case 'repeatOrderRatio':
                return `${(value * 100).toFixed(1)}%`;
            case 'timeToFirstOrder':
            case 'avgDeliveryDays':
            case 'avgShippedOutDays':
                return `${value.toFixed(1)} hrs`;
            case 'aov':
            case 'totalOrders':
            default:
                return value.toLocaleString();
        }
    };

    useEffect(() => {
        const fetchData = async () => {
            setLoading(true);
            setError(null);

            const commonParams = {
                group: group,
                'date_range[start_date]': start.format('YYYY-MM-DD'),
                'date_range[end_date]': end.format('YYYY-MM-DD'),
                'filter[team_ids]': filter.teamIds.join(','),
                'filter[shop_ids]': filter.shopIds.join(','),
                'filter[page_ids]': filter.pageIds.join(','),
                'filter[user_ids]': filter.userIds.join(','),
                'filter[product_ids]': filter.productIds.join(','),
            };

            try {
                const [primaryResponse, secondaryResponse] = await Promise.all([
                    axios.get(`/api/v1/workspace/analytics/breakdown`, {
                        headers: {
                            'X-Workspace-Id': workspace.id,
                        },
                        params: {
                            ...commonParams,
                            metric: option,
                        },
                    }),
                    axios.get(`/api/v1/workspace/analytics/breakdown`, {
                        headers: {
                            'X-Workspace-Id': workspace.id,
                        },
                        params: {
                            ...commonParams,
                            metric: secondOption,
                        },
                    }),
                ]);

                setPrimaryBreakdown(primaryResponse.data.data ?? []);
                setSecondaryBreakdown(secondaryResponse.data.data ?? []);
            } catch (error) {
                console.error('Failed to fetch breakdown:', error);
                setError('Failed to load data. Please try reload.');
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, [workspace.id, option, secondOption, group, dateRange, filter, reload]);

    const capitalizeFirstLetter = (str: string) =>
        str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();

    const mergedChartData = useMemo(() => {
        const allPeriods = Array.from(
            new Set([
                ...primaryBreakdown.map((item) => item.period),
                ...secondaryBreakdown.map((item) => item.period),
            ]),
        );

        const primaryMap = new Map(
            primaryBreakdown.map((item) => [item.period, item.value]),
        );

        const secondaryMap = new Map(
            secondaryBreakdown.map((item) => [item.period, item.value]),
        );

        return {
            categories: allPeriods,
            series: [
                {
                    name: optionLabels[option],
                    data: allPeriods.map(
                        (period) => primaryMap.get(period) ?? 0,
                    ),
                },
                {
                    name: optionLabels[secondOption],
                    data: allPeriods.map(
                        (period) => secondaryMap.get(period) ?? 0,
                    ),
                },
            ],
        };
    }, [primaryBreakdown, secondaryBreakdown, option, secondOption]);

    const hasData =
        mergedChartData.series[0].data.length > 0 ||
        mergedChartData.series[1].data.length > 0;

    return (
        <div className="space-y-4">
            <div className="flex justify-between">
                <h2 className="text-lg font-semibold">
                    {optionLabels[option]} vs {optionLabels[secondOption]}
                </h2>

                <div className="flex items-center justify-between gap-4">
                    <div className="flex rounded-lg bg-gray-100 p-1">
                        {availableGroups.map((g) => (
                            <button
                                key={g}
                                className={`rounded-md px-4 py-1 text-sm transition-all ${
                                    group === g
                                        ? 'bg-white text-gray-950 shadow-sm'
                                        : 'text-gray-400 hover:text-gray-600'
                                }`}
                                onClick={() => setGroup(g)}
                            >
                                {capitalizeFirstLetter(g)}
                            </button>
                        ))}
                    </div>

                    <div className="flex items-center justify-between gap-2">
                        <div className="w-60">
                            <Select value={option} onValueChange={setOption}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select first option" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(optionLabels).map(
                                        ([key, label]) => (
                                            <SelectItem key={key} value={key}>
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="w-60">
                            <Select
                                value={secondOption}
                                onValueChange={setSecondOption}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select second option" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(optionLabels).map(
                                        ([key, label]) => (
                                            <SelectItem key={key} value={key}>
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>

                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    className="rounded-md bg-gray-100 p-2 hover:text-white"
                                    onClick={() =>
                                        setReload((prevState) => !prevState)
                                    }
                                >
                                    <RefreshCcw className="h-5 w-5 text-gray-500" />
                                </Button>
                            </TooltipTrigger>

                            <TooltipContent>Refresh</TooltipContent>
                        </Tooltip>
                    </div>
                </div>
            </div>

            {loading ? (
                <LineChartSkeleton />
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
            ) : !hasData ? (
                <div className="flex h-60 flex-col items-center justify-center space-y-2 rounded-lg border border-gray-200 bg-gray-50">
                    <p className="text-gray-500">
                        No data available for the selected period
                    </p>
                    <p className="text-sm text-gray-400">
                        Try adjusting your filters or date range
                    </p>
                </div>
            ) : (
                <LineChart
                    categories={mergedChartData.categories}
                    series={mergedChartData.series}
                    formatValue={formatValue}
                />
            )}
        </div>
    );
}
