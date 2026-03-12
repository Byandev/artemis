import LineChart from '@/components/charts/LineChart';
import { FilterValue } from '@/components/filters/Filters';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import moment from 'moment/moment';
import { useEffect, useState } from 'react';
import LineChartSkeleton from '@/components/charts/skeletons/LineChartSkeleton';

interface Props {
    workspace: Workspace;
    dateRange: string[];
    filter: FilterValue;
}

export function StatisticBreakdown({ workspace, dateRange, filter }: Props) {
    const [breakdown, setBreakdown] = useState<any[]>([]);
    const [option, setOption] = useState('totalSales');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [group, setGroup] = useState('daily');

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

    // Update group when available groups change
    useEffect(() => {
        if (!availableGroups.includes(group) && availableGroups.length > 0) {
            setGroup(availableGroups[0]);
        }
    }, [availableGroups, group]);

    const optionLabels: Record<string, string> = {
        totalSales: 'Sales',
        totalOrders: 'Orders',
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
                return `₱${value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
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

            try {
                const response = await axios.get(
                    `/api/v1/workspace/analytics/breakdown`,
                    {
                        headers: {
                            'X-Workspace-Id': workspace.id,
                        },
                        params: {
                            metric: option,
                            group: group,
                            'date_range[start_date]':
                                start.format('YYYY-MM-DD'),
                            'date_range[end_date]': end.format('YYYY-MM-DD'),
                            'filter[team_ids]': filter.teamIds.join(','),
                            'filter[shop_ids]': filter.shopIds.join(','),
                            'filter[page_ids]': filter.pageIds.join(','),
                            'filter[user_ids]': filter.userIds.join(','),
                            'filter[product_ids]': filter.productIds.join(','),
                        },
                    },
                );

                setBreakdown(response.data.data);
            } catch (error) {
                console.error('Failed to fetch breakdown:', error);
                setError('Failed to load data. Please try again.');
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, [workspace.id, option, group, dateRange, filter]);

    const capitalizeFirstLetter = (str: string) =>
        str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();

    return (
        <div className="space-y-4">
            <div className="flex justify-between">
                <h2 className="text-lg font-semibold">
                    {optionLabels[option]} Breakdown
                </h2>
                <div className="flex items-center justify-between gap-4">
                    <div className="flex rounded-lg bg-gray-100 p-1.5">
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

                    <div className="w-80">
                        <Select value={option} onValueChange={setOption}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select options" />
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
                </div>
            </div>

            {loading ? (
                <LineChartSkeleton />
            ) : error ? (
                <div className="h-60 flex flex-col items-center justify-center space-y-4 rounded-lg border border-gray-200 bg-gray-50">
                    <p className="text-red-500">{error}</p>
                    <Button
                        variant="outline"
                        onClick={() => window.location.reload()}
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
                <div className="h-60 flex flex-col items-center justify-center space-y-2 rounded-lg border border-gray-200 bg-gray-50">
                    <p className="text-gray-500">
                        No data available for the selected period
                    </p>
                    <p className="text-sm text-gray-400">
                        Try adjusting your filters or date range
                    </p>
                </div>
            ) : (
                <LineChart
                    categories={breakdown.map((a) => a.period)}
                    series={[
                        {
                            name: optionLabels[option],
                            data: breakdown.map((a) => a.value),
                        },
                    ]}
                    formatValue={formatValue}
                />
            )}
        </div>
    );
}
