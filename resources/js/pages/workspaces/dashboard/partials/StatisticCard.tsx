import { FilterValue } from '@/components/filters/Filters';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import type { LucideIcon } from 'lucide-react';
import {
    CircleHelp,
    TrendingDown,
    TrendingUp,
    DollarSign,
    ShoppingCart,
    ReceiptText,
    Undo2,
    Repeat,
    Clock3,
    Wallet,
    Truck,
    PackageCheck
} from 'lucide-react';
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
    icon?: LucideIcon;
    tooltipLabel?: string;
    className?: string;
}

type AnalyticsResponse = {
    current: Record<string, number>;
    change?: Record<string, number | null>;
};

// Icon color mapping based on metric type
const iconColorConfig: Record<string, { bg: string; color: string }> = {
    // Revenue/Sales metrics - Green/Success
    totalSales: { bg: 'bg-emerald-50 dark:bg-emerald-950/30', color: 'text-emerald-600 dark:text-emerald-400' },
    aov: { bg: 'bg-emerald-50 dark:bg-emerald-950/30', color: 'text-emerald-600 dark:text-emerald-400' },
    avgLifetimeValue: { bg: 'bg-emerald-50 dark:bg-emerald-950/30', color: 'text-emerald-600 dark:text-emerald-400' },

    // Order metrics - Blue/Info
    totalOrders: { bg: 'bg-blue-50 dark:bg-blue-950/30', color: 'text-blue-600 dark:text-blue-400' },
    repeatOrderRatio: { bg: 'bg-blue-50 dark:bg-blue-950/30', color: 'text-blue-600 dark:text-blue-400' },

    // Return/Cancellation metrics - Rose/Warning
    rtsRate: { bg: 'bg-rose-50 dark:bg-rose-950/30', color: 'text-rose-600 dark:text-rose-400' },

    // Time metrics - Amber/Neutral
    timeToFirstOrder: { bg: 'bg-amber-50 dark:bg-amber-950/30', color: 'text-amber-600 dark:text-amber-400' },
    avgDeliveryDays: { bg: 'bg-amber-50 dark:bg-amber-950/30', color: 'text-amber-600 dark:text-amber-400' },
    avgShippedOutDays: { bg: 'bg-amber-50 dark:bg-amber-950/30', color: 'text-amber-600 dark:text-amber-400' },

    // Logistics metrics - Purple
    avgDeliveryDays: { bg: 'bg-purple-50 dark:bg-purple-950/30', color: 'text-purple-600 dark:text-purple-400' },

    // Customer metrics - Indigo
    avgLifetimeValue: { bg: 'bg-indigo-50 dark:bg-indigo-950/30', color: 'text-indigo-600 dark:text-indigo-400' },
};

// Default colors for metrics without specific mapping
const defaultIconColor = { bg: 'bg-gray-100 dark:bg-gray-800', color: 'text-gray-600 dark:text-gray-400' };

function CardSkeleton() {
    return (
        <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex items-center justify-between">
                <div className="h-4 w-20 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
                <div className="h-8 w-8 animate-pulse rounded-lg bg-gray-200 dark:bg-gray-800" />
            </div>
            <div className="mt-3">
                <div className="h-7 w-24 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
                <div className="mt-2 h-3 w-32 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
            </div>
        </div>
    );
}

function PercentageSkeleton() {
    return (
        <div className="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5">
            <div className="h-3 w-3 animate-pulse rounded-full bg-gray-200 dark:bg-gray-800" />
            <div className="h-3 w-8 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
        </div>
    );
}

function ComparisonTextSkeleton() {
    return (
        <div className="mt-1">
            <div className="h-3 w-24 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
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
                           className = '',
                       }: Props) => {
    const [analytics, setAnalytics] = useState<AnalyticsResponse | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [percentageLoading, setPercentageLoading] = useState(true);

    useEffect(() => {
        const controller = new AbortController();

        setLoading(true);
        setError(null);
        setPercentageLoading(true);

        axios
            .get(`/api/v1/workspace/analytics`, {
                signal: controller.signal,
                headers: { 'X-Workspace-Id': workspace.id },
                params: {
                    'metric[]': metric,
                    compare_previous: 1,
                    'date_range[start_date]': moment(dateRange[0]).format(
                        'YYYY-MM-DD',
                    ),
                    'date_range[end_date]': moment(dateRange[1]).format(
                        'YYYY-MM-DD',
                    ),
                    'filter[team_ids]': filter.teamIds.join(','),
                    'filter[shop_ids]': filter.shopIds.join(','),
                    'filter[page_ids]': filter.pageIds.join(','),
                    'filter[user_ids]': filter.userIds.join(','),
                    'filter[product_ids]': filter.productIds.join(','),
                },
            })
            .then((response) => {
                setAnalytics(response.data);
            })
            .catch((err) => {
                if (axios.isCancel?.(err) || err?.name === 'CanceledError')
                    return;

                setError(
                    err?.response?.data?.message ??
                    err?.message ??
                    'Failed to load analytics',
                );
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);

                    // Keep percentage loading true for a bit longer
                    setTimeout(() => {
                        setPercentageLoading(false);
                    }, 400); // 400ms delay after main loading completes
                }
            });

        return () => controller.abort();
    }, [workspace.id, dateRange, filter, metric]);

    const currentValue = analytics?.current?.[metric] ?? 0;
    const percentageChange = analytics?.change?.[metric];

    const totalDays = useMemo(
        () => moment(dateRange[1]).diff(moment(dateRange[0]), 'days') + 1,
        [dateRange],
    );

    const hasComparison = useMemo(
        () => Boolean(analytics?.change && metric in analytics.change),
        [analytics, metric],
    );

    const formatChange = (value: number | null | undefined) => {
        if (value === null || value === undefined) return '';
        const rounded = Math.round(value);
        return `${rounded > 0 ? '+' : ''}${rounded}%`;
    };

    const getChangeConfig = (change: number | null | undefined) => {
        if (change == null)
            return { color: 'text-gray-500', bg: 'bg-gray-100 dark:bg-gray-800' };
        if (change > 0)
            return { color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/30' };
        if (change < 0) return { color: 'text-rose-600 dark:text-rose-400', bg: 'bg-rose-50 dark:bg-rose-950/30' };
        return { color: 'text-gray-500 dark:text-gray-400', bg: 'bg-gray-100 dark:bg-gray-800' };
    };

    const changeConfig = getChangeConfig(percentageChange);

    // Get icon colors based on metric key
    const iconColors = iconColorConfig[metric] || defaultIconColor;

    if (loading) return <CardSkeleton />;

    return (
        <div
            className={`rounded-lg border border-gray-200 bg-white p-4 transition-all hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.03] ${className}`}
        >
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-1.5">
                    <span className="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {label}
                    </span>

                    {tooltipLabel && (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button
                                        type="button"
                                        className="text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <CircleHelp className="h-3.5 w-3.5" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="top"
                                    className="max-w-[200px] text-xs"
                                >
                                    <p>{tooltipLabel}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
                </div>

                {Icon && (
                    <div className={`rounded-lg p-1.5 transition-colors ${iconColors.bg}`}>
                        <Icon className={`h-4 w-4 ${iconColors.color}`} />
                    </div>
                )}
            </div>

            <div className="mt-2">
                <div className="flex items-baseline gap-2">
                    <span className="text-xl font-semibold text-gray-900 dark:text-white">
                        {formatter(currentValue)}
                    </span>

                    {hasComparison && (
                        <>
                            {percentageLoading ? (
                                <PercentageSkeleton />
                            ) : (
                                percentageChange !== null && (
                                    <div
                                        className={`inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs font-medium ${changeConfig.bg} ${changeConfig.color}`}
                                    >
                                        {percentageChange > 0 && (
                                            <TrendingUp className="h-3 w-3" />
                                        )}
                                        {percentageChange < 0 && (
                                            <TrendingDown className="h-3 w-3" />
                                        )}
                                        <span>{formatChange(percentageChange)}</span>
                                    </div>
                                )
                            )}
                        </>
                    )}
                </div>

                {hasComparison && (
                    <>
                        {percentageLoading ? (
                            <ComparisonTextSkeleton />
                        ) : (
                            percentageChange !== null && (
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    vs previous {totalDays} days
                                </p>
                            )
                        )}
                    </>
                )}

                {error && <p className="mt-1 text-xs text-rose-500">{error}</p>}
            </div>
        </div>
    );
};

export default StatisticCard;
