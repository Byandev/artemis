import { Workspace } from '@/types/models/Workspace';
import { FilterValue } from '@/components/filters/Filters';
import { useEffect, useState } from 'react';
import axios from 'axios';
import moment from 'moment/moment';

export interface Props {
    label: string;
    metric: string;
    workspace: Workspace;
    filter: FilterValue;
    dateRange: string[];
    formatter: (value: number) => string
}


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

const StatisticCard = ({ metric, label, workspace, dateRange, filter, formatter}: Props) => {
    const [analytics, setAnalytics] = useState(null);
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
                // If request was cancelled, ignore
                if (axios.isCancel?.(err) || err?.name === 'CanceledError')
                    return;

                setError(
                    err?.response?.data?.message ??
                        err?.message ??
                        'Failed to load analytics.',
                );
            })
            .finally(() => {
                // If aborted, avoid flipping loading state after unmount/change
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [workspace.id, dateRange, filter, metric]);

    return loading ?
        <CardSkeleton label={label}/> :
        (
            <div
                className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3"
            >
                <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                    {label}
                </p>

                <div className="mt-3 flex items-end justify-between">
                    <div>
                        <h4 className="text-2xl font-bold text-gray-800 dark:text-white/90">
                            {formatter(analytics ? analytics[metric] : 0)}
                        </h4>
                    </div>
                </div>
            </div>
        );
}

export default StatisticCard
