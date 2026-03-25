import { FilterValue } from '@/components/filters/Filters';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import type { LucideIcon } from 'lucide-react';
import { CircleHelp, Minus, TrendingDown, TrendingUp } from 'lucide-react';
import moment from 'moment/moment';
import { useEffect, useMemo, useState } from 'react';

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
    icon?: LucideIcon | null;
    tooltipLabel?: string;
    reverseTrend?: boolean;
}

type AnalyticsResponse = Record<string, number>;

function CardSkeleton({
                          label,
                          icon: Icon,
                      }: {
    label: string;
    icon?: LucideIcon | null;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900 p-[18px]">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <div>
                        <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                            {label}
                        </p>
                        <div className="mt-2 h-3 w-20 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
                    </div>
                </div>

                <div className="rounded-lg bg-stone-100 p-2 dark:bg-zinc-800">
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
                           reverseTrend = false,
                       }: Props) => {
    const [currentValue, setCurrentValue] = useState(0);
    const [previousValue, setPreviousValue] = useState(0);

    const [loadingCurrent, setLoadingCurrent] = useState(false);
    const [loadingComparison, setLoadingComparison] = useState(false);

    const period = useMemo(() => {
        const start = moment(dateRange[0]).startOf('day');
        const end = moment(dateRange[1]).startOf('day');
        const days = end.diff(start, 'days') + 1;

        const prevEnd = start.clone().subtract(1, 'day');
        const prevStart = prevEnd.clone().subtract(days - 1, 'days');

        return {
            start,
            end,
            days,
            prevStart,
            prevEnd,
        };
    }, [dateRange[0], dateRange[1]]);

    const teamIds = useMemo(() => filter.teamIds.join(','), [filter.teamIds]);
    const shopIds = useMemo(() => filter.shopIds.join(','), [filter.shopIds]);
    const pageIds = useMemo(() => filter.pageIds.join(','), [filter.pageIds]);
    const userIds = useMemo(() => filter.userIds.join(','), [filter.userIds]);
    const productIds = useMemo(
        () => filter.productIds.join(','),
        [filter.productIds],
    );

    const commonParams = useMemo(
        () => ({
            'metric[]': metric,
            'filter[team_ids]': teamIds,
            'filter[shop_ids]': shopIds,
            'filter[page_ids]': pageIds,
            'filter[user_ids]': userIds,
            'filter[product_ids]': productIds,
        }),
        [metric, teamIds, shopIds, pageIds, userIds, productIds],
    );

    useEffect(() => {
        const controller = new AbortController();

        const fetchData = async () => {
            try {
                setLoadingCurrent(true);
                setLoadingComparison(false);
                setCurrentValue(0);
                setPreviousValue(0);

                const currentRes = await axios.get<AnalyticsResponse>(
                    '/api/v1/workspace/analytics',
                    {
                        signal: controller.signal,
                        headers: { 'X-Workspace-Id': workspace.id },
                        params: {
                            ...commonParams,
                            'date_range[start_date]':
                                period.start.format('YYYY-MM-DD'),
                            'date_range[end_date]':
                                period.end.format('YYYY-MM-DD'),
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
                                period.prevStart.format('YYYY-MM-DD'),
                            'date_range[end_date]':
                                period.prevEnd.format('YYYY-MM-DD'),
                        },
                    },
                );

                if (controller.signal.aborted) return;

                setPreviousValue(Number(previousRes.data?.[metric] ?? 0));
            } catch (err: any) {
                if (axios.isCancel?.(err) || err?.name === 'CanceledError') {
                    return;
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoadingCurrent(false);
                    setLoadingComparison(false);
                }
            }
        };

        fetchData();

        return () => controller.abort();
    }, [workspace.id, metric, commonParams, period]);

    const previousPeriodText = useMemo(() => {
        if (period.days === 1) return 'yesterday';
        return `prev ${period.days} days`;
    }, [period.days]);

    const comparison = useMemo(() => {
        const hasPreviousData = previousValue > 0;
        const difference = currentValue - previousValue;
        const percent = hasPreviousData
            ? (difference / previousValue) * 100
            : 0;

        const isPositive = difference > 0;
        const isNegative = difference < 0;
        const isNeutral = difference === 0;

        const TrendIcon = isPositive
            ? TrendingUp
            : isNegative
                ? TrendingDown
                : Minus;

        const isGood = reverseTrend ? isNegative : isPositive;
        const isBad = reverseTrend ? isPositive : isNegative;

        const trendClasses = isGood
            ? 'text-green-600 bg-green-50 dark:text-green-400 dark:bg-green-950/30'
            : isBad
                ? 'text-red-600 bg-red-50 dark:text-red-400 dark:bg-red-950/30'
                : 'text-gray-500 bg-gray-50 dark:text-gray-400 dark:bg-gray-800';

        return {
            hasPreviousData,
            difference,
            percent,
            isPositive,
            isNegative,
            isNeutral,
            isGood,
            isBad,
            TrendIcon,
            trendClasses,
        };
    }, [currentValue, previousValue, reverseTrend]);

    const tooltipDescription = useMemo(() => {
        if (loadingComparison) {
            return `Comparing ${label} with the previous ${period.days}-day period...`;
        }

        if (comparison.hasPreviousData) {
            const status = comparison.isNeutral
                ? 'unchanged'
                : comparison.isGood
                    ? 'improving'
                    : 'worsening';

            return `${label} is ${status} by ${Math.abs(comparison.percent).toFixed(1)}% compared to the previous ${period.days}-day period. Current: ${formatter(currentValue)}. Previous: ${formatter(previousValue)}.`;
        }

        return `${label} is currently ${formatter(currentValue)}. No previous data available for comparison.`;
    }, [
        loadingComparison,
        label,
        period.days,
        comparison.hasPreviousData,
        comparison.isNeutral,
        comparison.isGood,
        comparison.percent,
        formatter,
        currentValue,
        previousValue,
    ]);

    if (loadingCurrent) {
        return <CardSkeleton label={label} icon={Icon} />;
    }

    return (
        <div className="rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900 p-[18px]">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
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
                    <div className="rounded-lg bg-stone-100 p-2 dark:bg-zinc-800">
                        <Icon className="h-5 w-5 text-gray-700 dark:text-white/90" />
                    </div>
                )}
            </div>

            <div className="mt-3 flex flex-row justify-between">
                <h4 className="text-[22px] font-semibold font-mono tracking-tight tabular-nums text-gray-900 dark:text-gray-100">
                    {formatter(currentValue)}
                </h4>

                <div>
                    {loadingComparison ? (
                        <div className="mt-2 flex items-center gap-2">
                            <div className="h-4 w-4 animate-pulse rounded-full bg-gray-200 dark:bg-gray-700" />
                            <div className="h-3 w-24 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                        </div>
                    ) : (
                        comparison.hasPreviousData && (
                            <div className="mt-2 flex items-center gap-2">
                                {/*<span className="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">*/}
                                {/*    {previousPeriodText}*/}
                                {/*</span>*/}

                                <p
                                    className={`inline-flex items-center gap-1 rounded-xl border-0 px-2 py-1 text-[10px] font-medium ${comparison.trendClasses}`}
                                >
                                    <comparison.TrendIcon className="h-3.5 w-3.5" />
                                    {comparison.isNeutral
                                        ? '0.0%'
                                        : `${comparison.isPositive ? '+' : ''}${comparison.percent.toFixed(1)}%`}
                                </p>
                            </div>
                        )
                    )}
                </div>
            </div>
        </div>
    );
};

export default StatisticCard;
