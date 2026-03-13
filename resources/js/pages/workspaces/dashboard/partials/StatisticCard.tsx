import { FilterValue } from '@/components/filters/Filters';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import type { LucideIcon } from 'lucide-react';
import { CircleHelp } from 'lucide-react';
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

function CardSkeleton({ label }: { label: string }) {
    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                {label}
            </p>
            <div className="mt-3">
                <div className="h-8 w-2/3 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
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
    const [analytics, setAnalytics] = useState<AnalyticsResponse | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();

        setLoading(true);
        setError(null);

        axios
            .get(`/api/v1/workspace/analytics`, {
                signal: controller.signal,
                headers: { 'X-Workspace-Id': workspace.id },
                params: {
                    'metric[]': metric,
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
                        'Failed to load analytics.',
                );
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [workspace.id, dateRange, filter, metric]);

    if (loading) return <CardSkeleton label={label} />;

    return (
        <div className="dark:bg-white/3 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                        {label}
                    </p>

                    {tooltipLabel && (
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
                                <TooltipContent>
                                    <p>{tooltipLabel}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
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
                        {formatter(analytics?.[metric] ?? 0)}
                    </h4>

                    {error && (
                        <p className="mt-1 text-xs text-red-500">{error}</p>
                    )}
                </div>
            </div>
        </div>
    );
};

export default StatisticCard;
