import LineChart from '@/components/charts/LineChart';
import LineChartSkeleton from '@/components/charts/skeletons/LineChartSkeleton';
import { FilterValue } from '@/components/filters/Filters';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import { Check, ChevronDown, RefreshCcw } from 'lucide-react';
import moment from 'moment/moment';
import { useEffect, useMemo, useState } from 'react';
import ComponentCard from '@/components/common/ComponentCard';
import { metricConfigs, MetricKey } from '@/types/metrics';

interface Props {
    metrics: MetricKey[],
    workspace: Workspace;
    dateRange: string[];
    filter: FilterValue;
}

interface Breakdown {
    period: string;
    value: number
}

export function StatisticBreakdown({ metrics, workspace, dateRange, filter }: Props) {
    const [primaryBreakdown, setPrimaryBreakdown] = useState<Breakdown[]>([]);
    const [secondaryBreakdown, setSecondaryBreakdown] = useState<Breakdown[]>([]);

    const [option, setOption] = useState<MetricKey>(metrics[0]);
    const [secondOption, setSecondOption] = useState<MetricKey>(metrics[1]);
    const [openOne, setOpenOne] = useState(false);
    const [openTwo, setOpenTwo] = useState(false);

    const metricOne = useMemo(
        () => metricConfigs.find((m) => m.key === option),
        [option],
    );

    const metricTwo = useMemo(
        () => metricConfigs.find((m) => m.key === secondOption),
        [secondOption],
    );

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [group, setGroup] = useState('daily');
    const [reload, setReload] = useState(false);

    const daysDiff = useMemo(
        () => moment(dateRange[1]).diff(moment(dateRange[0]), 'days'),
        [dateRange],
    );

    const availableGroups = useMemo<string[]>(() => {
        if (daysDiff <= 7) return ['daily'];
        if (daysDiff <= 30) return ['daily', 'weekly'];
        if (daysDiff <= 365) return ['weekly', 'monthly'];
        return ['weekly', 'monthly', 'yearly'];
    }, [daysDiff]);

    useEffect(() => {
        if (!availableGroups.includes(group) && availableGroups.length > 0) {
            setGroup(availableGroups[0]);
        }
    }, [availableGroups, group]);

    useEffect(() => {
        const fetchData = async () => {
            setLoading(true);
            setError(null);

            const commonParams = {
                group: group,
                'date_range[start_date]': dateRange[0],
                'date_range[end_date]': dateRange[1],
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
                    name: metricOne?.name,
                    data: allPeriods.map(
                        (period) => primaryMap.get(period) ?? 0,
                    ),
                },
                {
                    name: metricTwo?.name,
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
            <div className="item-center flex justify-between">
                <h2 className="font-semibold">
                    {metricOne?.name} vs {metricTwo?.name}
                </h2>

                <div className="flex items-center justify-between gap-4">
                    <div className="flex rounded-[10px] bg-stone-100 dark:bg-zinc-800 border border-black/6 dark:border-white/6 p-0.5">
                        {availableGroups.map((g) => (
                            <button
                                key={g}
                                className={`rounded-[8px] px-3 py-1 text-[12px] font-medium transition-all ${
                                    group === g
                                        ? 'bg-white dark:bg-zinc-700 text-gray-800 dark:text-gray-100 shadow-sm'
                                        : 'text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300'
                                }`}
                                onClick={() => setGroup(g)}
                            >
                                {capitalizeFirstLetter(g)}
                            </button>
                        ))}
                    </div>

                    <div className="flex items-center justify-between gap-2">
                        <Popover open={openOne} onOpenChange={setOpenOne}>
                            <PopoverTrigger asChild>
                                <button className={`flex h-8 items-center overflow-hidden rounded-[10px] border transition-all ${openOne ? 'border-emerald-500 ring-2 ring-emerald-500/15' : 'border-black/6 dark:border-white/6 hover:border-black/12 dark:hover:border-white/12'} bg-stone-100 dark:bg-zinc-800`}>
                                    <span className="flex h-full items-center justify-center border-r border-black/6 dark:border-white/6 px-2.5 text-gray-400 dark:text-gray-500">
                                        <ChevronDown className={`h-3.5 w-3.5 transition-transform ${openOne ? 'rotate-180 text-emerald-500' : ''}`} />
                                    </span>
                                    <span className="px-3 text-[13px] font-medium text-gray-700 dark:text-gray-200">
                                        {metricOne?.name ?? 'Select metric'}
                                    </span>
                                </button>
                            </PopoverTrigger>
                            <PopoverContent align="start" className="w-52 overflow-hidden rounded-[12px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900 p-1 shadow-lg dark:shadow-black/30">
                                {metricConfigs.filter((m) => metrics.includes(m.key)).map((item) => (
                                    <button
                                        key={item.key}
                                        className={`flex w-full items-center justify-between rounded-[8px] px-3 py-1.5 text-[13px] transition-colors ${option === item.key ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-medium' : 'text-gray-700 dark:text-gray-200 hover:bg-stone-50 dark:hover:bg-zinc-800'}`}
                                        onClick={() => { setOption(item.key as MetricKey); setOpenOne(false); }}
                                    >
                                        {item.name}
                                        {option === item.key && <Check className="h-3.5 w-3.5 text-emerald-500" />}
                                    </button>
                                ))}
                            </PopoverContent>
                        </Popover>

                        <Popover open={openTwo} onOpenChange={setOpenTwo}>
                            <PopoverTrigger asChild>
                                <button className={`flex h-8 items-center overflow-hidden rounded-[10px] border transition-all ${openTwo ? 'border-emerald-500 ring-2 ring-emerald-500/15' : 'border-black/6 dark:border-white/6 hover:border-black/12 dark:hover:border-white/12'} bg-stone-100 dark:bg-zinc-800`}>
                                    <span className="flex h-full items-center justify-center border-r border-black/6 dark:border-white/6 px-2.5 text-gray-400 dark:text-gray-500">
                                        <ChevronDown className={`h-3.5 w-3.5 transition-transform ${openTwo ? 'rotate-180 text-emerald-500' : ''}`} />
                                    </span>
                                    <span className="px-3 text-[13px] font-medium text-gray-700 dark:text-gray-200">
                                        {metricTwo?.name ?? 'Select metric'}
                                    </span>
                                </button>
                            </PopoverTrigger>
                            <PopoverContent align="start" className="w-52 overflow-hidden rounded-[12px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900 p-1 shadow-lg dark:shadow-black/30">
                                {metricConfigs.filter((m) => metrics.includes(m.key)).map((item) => (
                                    <button
                                        key={item.key}
                                        className={`flex w-full items-center justify-between rounded-[8px] px-3 py-1.5 text-[13px] transition-colors ${secondOption === item.key ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-medium' : 'text-gray-700 dark:text-gray-200 hover:bg-stone-50 dark:hover:bg-zinc-800'}`}
                                        onClick={() => { setSecondOption(item.key as MetricKey); setOpenTwo(false); }}
                                    >
                                        {item.name}
                                        {secondOption === item.key && <Check className="h-3.5 w-3.5 text-emerald-500" />}
                                    </button>
                                ))}
                            </PopoverContent>
                        </Popover>

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
                <ComponentCard>
                    <LineChart
                        categories={mergedChartData.categories}
                        series={mergedChartData.series}
                        leftAxisTitle={metricOne?.name ?? ''}
                        rightAxisTitle={metricTwo?.name ?? ''}
                        leftFormatter={(value) =>
                            metricOne?.formatter
                                ? metricOne.formatter(value)
                                : value.toString()
                        }
                        rightFormatter={(value) =>
                            metricTwo?.formatter
                                ? metricTwo.formatter(value)
                                : value.toString()
                        }
                    />
                </ComponentCard>
            )}
        </div>
    );
}
