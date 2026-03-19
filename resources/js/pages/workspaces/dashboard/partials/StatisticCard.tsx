import { FilterValue } from '@/components/filters/Filters';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import type { LucideIcon } from 'lucide-react';
import { CircleHelp, Minus, TrendingDown, TrendingUp, Calendar } from 'lucide-react';
import moment from 'moment/moment';
import { useEffect, useState } from 'react';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

export interface Props {
    label: string;
    metric: string;
    workspace: Workspace;
    filter: FilterValue;
    dateRange: string[];
    formatter: (value: number) => string;
    icon?: LucideIcon;
    tooltipLabel?: string;
}

type AnalyticsResponse = Record<string, number>;

function CardSkeleton({
                          label,
                          icon: Icon,
                      }: {
    label: string;
    icon?: LucideIcon;
}) {
    return (
        <div className="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <div>
                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                            {label}
                        </p>
                        <div className="mt-2 h-3 w-20 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
                    </div>
                </div>

                <div className="rounded-lg bg-gray-100 p-2 dark:bg-gray-800">
                    {Icon ? (
                        <Icon className="h-5 w-5 text-gray-300 dark:text-gray-600" />
                    ) : (
                        <div className="h-5 w-5 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                    )}
                </div>
            </div>

            <div className="mt-4">
                <div className="h-8 w-32 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
                <div className="mt-3 h-4 w-24 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
            </div>
        </div>
    );
}

const StatisticCard = ({
                           metric,
                           label,
                           workspace,
                           dateRange,
                           filter,
                           formatter,
                           icon: Icon,
                           tooltipLabel,
                       }: Props) => {
    const [currentValue, setCurrentValue] = useState(0);
    const [previousValue, setPreviousValue] = useState(0);

    const [loadingCurrent, setLoadingCurrent] = useState(false);
    const [loadingComparison, setLoadingComparison] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();

        const fetchData = async () => {
            try {
                setLoadingCurrent(true);
                setLoadingComparison(false);
                setError(null);
                setCurrentValue(0);
                setPreviousValue(0);

                const start = moment(dateRange[0]).startOf('day');
                const end = moment(dateRange[1]).startOf('day');

                const days = end.diff(start, 'days') + 1;

                const prevEnd = start.clone().subtract(1, 'day');
                const prevStart = prevEnd.clone().subtract(days - 1, 'days');

                const commonParams = {
                    'metric[]': metric,
                    'filter[team_ids]': filter.teamIds.join(','),
                    'filter[shop_ids]': filter.shopIds.join(','),
                    'filter[page_ids]': filter.pageIds.join(','),
                    'filter[user_ids]': filter.userIds.join(','),
                    'filter[product_ids]': filter.productIds.join(','),
                };

                const currentRes = await axios.get<AnalyticsResponse>(
                    '/api/v1/workspace/analytics',
                    {
                        signal: controller.signal,
                        headers: { 'X-Workspace-Id': workspace.id },
                        params: {
                            ...commonParams,
                            'date_range[start_date]':
                                start.format('YYYY-MM-DD'),
                            'date_range[end_date]': end.format('YYYY-MM-DD'),
                        },
                    },
                );

                if (controller.signal.aborted) return;

                const current = Number(currentRes.data?.[metric] ?? 0);
                setCurrentValue(current);
                setLoadingCurrent(false);
                setLoadingComparison(true);

                const previousRes = await axios.get<AnalyticsResponse>(
                    '/api/v1/workspace/analytics',
                    {
                        signal: controller.signal,
                        headers: { 'X-Workspace-Id': workspace.id },
                        params: {
                            ...commonParams,
                            'date_range[start_date]':
                                prevStart.format('YYYY-MM-DD'),
                            'date_range[end_date]':
                                prevEnd.format('YYYY-MM-DD'),
                        },
                    },
                );

                if (controller.signal.aborted) return;

                setPreviousValue(Number(previousRes.data?.[metric] ?? 0));
            } catch (err: any) {
                if (axios.isCancel?.(err) || err?.name === 'CanceledError') {
                    return;
                }

                setError(
                    err?.response?.data?.message ??
                    err?.message ??
                    'Failed to load analytics.',
                );
            } finally {
                if (!controller.signal.aborted) {
                    setLoadingCurrent(false);
                    setLoadingComparison(false);
                }
            }
        };

        fetchData();

        return () => controller.abort();
    }, [workspace.id, dateRange, filter, metric]);

    const days =
        moment(dateRange[1])
            .startOf('day')
            .diff(moment(dateRange[0]).startOf('day'), 'days') + 1;

    // Get previous period description
    const getPreviousPeriodText = () => {
        if (days === 1) return 'yesterday';
        if (days === 7) return 'previous 7 days';
        if (days === 30) return 'previous 30 days';
        return `previous ${days} days`;
    };

    const hasPreviousData = previousValue > 0;
    const difference = currentValue - previousValue;

    const percent = hasPreviousData ? (difference / previousValue) * 100 : 0;

    const isPositive = difference > 0;
    const isNegative = difference < 0;
    const isNeutral = difference === 0;

    const TrendIcon = isPositive
        ? TrendingUp
        : isNegative
            ? TrendingDown
            : Minus;

    const trendTextClass = isPositive
        ? 'text-green-600'
        : isNegative
            ? 'text-red-600'
            : 'text-gray-500 dark:text-gray-400';

    const tooltipDescription = loadingComparison
        ? `Comparing ${label} with the previous ${days}-day period...`
        : hasPreviousData
            ? `${label} is ${isPositive ? 'up' : isNegative ? 'down' : 'unchanged'} ${Math.abs(percent).toFixed(1)}% compared to the previous ${days}-day period. Current: ${formatter(currentValue)}. Previous: ${formatter(previousValue)}.`
            : `${label} is currently ${formatter(currentValue)}. No previous data available for comparison.`;

    if (loadingCurrent) {
        return <CardSkeleton label={label} icon={Icon} />;
    }

    return (
        <div className="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                        {label}
                    </p>

                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    type="button"
                                    className="text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200"
                                >
                                    <CircleHelp className="h-4 w-4" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent className="max-w-xs">
                                <div className="space-y-1.5">
                                    {tooltipLabel && <p>{tooltipLabel}</p>}
                                    <p>{tooltipDescription}</p>
                                </div>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>

                {Icon && (
                    <div className="rounded-lg bg-gray-100 p-2 dark:bg-gray-800">
                        <Icon className="h-5 w-5 text-gray-700 dark:text-white/90" />
                    </div>
                )}
            </div>

            <div className="mt-3 flex items-end justify-between">
                <div>
                    <h4 className="text-2xl font-bold text-gray-800 dark:text-white/90">
                        {formatter(currentValue)}
                    </h4>

                    {loadingComparison ? (
                        <div className="mt-2 flex items-center gap-2">
                            <div className="h-4 w-4 animate-pulse rounded-full bg-gray-200 dark:bg-gray-700" />
                            <div className="h-3 w-24 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                        </div>
                    ) : hasPreviousData ? (
                        <div className="mt-2 flex items-center gap-2">
                            <p
                                className={`inline-flex items-center gap-1 text-xs font-medium ${trendTextClass}`}
                            >
                                <TrendIcon className="h-3.5 w-3.5" />
                                {isNeutral
                                    ? '0.0%'
                                    : `${isPositive ? '+' : ''}${percent.toFixed(1)}%`}
                            </p>
                            {/* Previous period indicator */}
                            <span className="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <Calendar className="h-3 w-3" />
                                from {getPreviousPeriodText()}
                            </span>
                        </div>
                    ) : (
                        <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            No comparison data
                        </p>
                    )}

                    {error && (
                        <p className="mt-1 text-xs text-red-500">{error}</p>
                    )}
                </div>
            </div>
        </div>
    );
};

export default StatisticCard;
