import { FilterValue } from '@/components/filters/Filters';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import { CircleHelp, Users } from 'lucide-react';
import moment from 'moment/moment';
import { useEffect, useMemo, useState } from 'react';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { percentageFormatter } from '@/lib/utils';

interface CohortData {
    cohort_size: number;
    rate_30d: number;
    rate_60d: number;
    rate_90d: number;
}

interface Props {
    workspace: Workspace;
    filter: FilterValue;
    dateRange: string[];
}

function CardSkeleton() {
    return (
        <div className="rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900 p-[18px]">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="h-3 w-44 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
                </div>
                <div className="rounded-lg bg-stone-100 p-2 dark:bg-zinc-800">
                    <Users className="h-5 w-5 text-gray-300 dark:text-gray-600" />
                </div>
            </div>
            <div className="mt-4 grid grid-cols-3 gap-3">
                {[0, 1, 2].map((i) => (
                    <div key={i} className="rounded-xl bg-gray-50 dark:bg-zinc-800 p-3">
                        <div className="h-2 w-8 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                        <div className="mt-2 h-6 w-14 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                    </div>
                ))}
            </div>
        </div>
    );
}

const windows = [
    { label: '30-day', key: 'rate_30d' as const, days: 30 },
    { label: '60-day', key: 'rate_60d' as const, days: 60 },
    { label: '90-day', key: 'rate_90d' as const, days: 90 },
];

const CohortRetentionCard = ({ workspace, filter, dateRange }: Props) => {
    const [data, setData] = useState<CohortData | null>(null);
    const [loading, setLoading] = useState(false);

    const teamIds    = useMemo(() => filter.teamIds.join(','), [filter.teamIds]);
    const shopIds    = useMemo(() => filter.shopIds.join(','), [filter.shopIds]);
    const pageIds    = useMemo(() => filter.pageIds.join(','), [filter.pageIds]);
    const userIds    = useMemo(() => filter.userIds.join(','), [filter.userIds]);
    const productIds = useMemo(() => filter.productIds.join(','), [filter.productIds]);

    useEffect(() => {
        const controller = new AbortController();

        const fetchData = async () => {
            try {
                setLoading(true);
                setData(null);

                const res = await axios.get<Record<string, CohortData>>(
                    '/api/v1/workspace/analytics',
                    {
                        signal: controller.signal,
                        headers: { 'X-Workspace-Id': workspace.id },
                        params: {
                            'metric[]': 'retentionRateCohort',
                            'date_range[start_date]': moment(dateRange[0]).format('YYYY-MM-DD'),
                            'date_range[end_date]':   moment(dateRange[1]).format('YYYY-MM-DD'),
                            'filter[team_ids]':    teamIds,
                            'filter[shop_ids]':    shopIds,
                            'filter[page_ids]':    pageIds,
                            'filter[user_ids]':    userIds,
                            'filter[product_ids]': productIds,
                        },
                    },
                );

                if (controller.signal.aborted) return;
                setData(res.data?.retentionRateCohort ?? null);
            } catch (err: any) {
                if (axios.isCancel?.(err) || err?.name === 'CanceledError') return;
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        };

        fetchData();

        return () => controller.abort();
    }, [workspace.id, dateRange, teamIds, shopIds, pageIds, userIds, productIds]);

    const period = useMemo(() => {
        const start = moment(dateRange[0]);
        const end   = moment(dateRange[1]);
        return end.diff(start, 'days') + 1;
    }, [dateRange]);

    if (loading) return <CardSkeleton />;

    const cohortSize = data?.cohort_size ?? 0;

    return (
        <div className="rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900 p-[18px]">
            {/* Header */}
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                        Retention Rate Cohort
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
                                    <p>
                                        Of the <strong>{cohortSize.toLocaleString()}</strong> new customers
                                        who placed their first order in the selected {period}-day window, this
                                        shows the percentage who returned within 30, 60, and 90 days.
                                    </p>
                                </div>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>

                <div className="rounded-lg bg-stone-100 p-2 dark:bg-zinc-800">
                    <Users className="h-5 w-5 text-gray-700 dark:text-white/90" />
                </div>
            </div>

            {/* Cohort size */}
            <div className="mt-3">
                <span className="text-[11px] text-gray-400 dark:text-gray-500">
                    {cohortSize.toLocaleString()} new customers in cohort
                </span>
            </div>

            {/* Rate panels */}
            <div className="mt-3 grid grid-cols-3 gap-2">
                {windows.map(({ label, key }) => {
                    const rate = data?.[key] ?? 0;
                    const pct  = rate * 100;

                    const colorClass =
                        pct >= 20
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : pct >= 10
                                ? 'text-amber-600 dark:text-amber-400'
                                : 'text-gray-500 dark:text-gray-400';

                    const bgClass =
                        pct >= 20
                            ? 'bg-emerald-50 dark:bg-emerald-950/30'
                            : pct >= 10
                                ? 'bg-amber-50 dark:bg-amber-950/30'
                                : 'bg-gray-50 dark:bg-zinc-800';

                    const barClass =
                        pct >= 20
                            ? 'bg-emerald-400 dark:bg-emerald-500'
                            : pct >= 10
                                ? 'bg-amber-400 dark:bg-amber-500'
                                : 'bg-gray-300 dark:bg-gray-600';

                    return (
                        <div key={key} className={`rounded-xl p-3 ${bgClass}`}>
                            <p className="text-[10px] font-medium text-gray-400 dark:text-gray-500">
                                {label}
                            </p>
                            <p className={`mt-1 text-[20px] font-semibold font-mono tabular-nums tracking-tight ${colorClass}`}>
                                {percentageFormatter(rate)}
                            </p>
                            {/* Progress bar */}
                            <div className="mt-2 h-1 w-full rounded-full bg-black/6 dark:bg-white/8 overflow-hidden">
                                <div
                                    className={`h-full rounded-full transition-all duration-500 ${barClass}`}
                                    style={{ width: `${Math.min(pct * 2, 100)}%` }}
                                />
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default CohortRetentionCard;
