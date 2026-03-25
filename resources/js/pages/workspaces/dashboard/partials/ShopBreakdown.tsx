import BarChart from '@/components/charts/BarChart';
import { FilterValue } from '@/components/filters/Filters';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import BarChartSkeleton from '@/components/charts/skeletons/BarChartSkeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Check, ChevronDown, RefreshCcw } from 'lucide-react';
import ComponentCard from '@/components/common/ComponentCard';

interface Props {
    workspace: Workspace;
    dateRange: string[];
    filter: FilterValue;
}

interface BreakdownItem {
    shop_id: number | string;
    shop_name: string;
    value: number | string;
}

export default function ShopBreakdown({
                                          workspace,
                                          dateRange,
                                          filter,
                                      }: Props) {
    const [breakdown, setBreakdown] = useState<BreakdownItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [option, setOption] = useState('totalSales');
    const [reload, setReload] = useState(false);
    const [openDropdown, setOpenDropdown] = useState(false);

    const startDate = moment(dateRange[0]).format('YYYY-MM-DD');
    const endDate = moment(dateRange[1]).format('YYYY-MM-DD');

    const teamIds = filter.teamIds.join(',');
    const shopIds = filter.shopIds.join(',');
    const pageIds = filter.pageIds.join(',');
    const userIds = filter.userIds.join(',');
    const productIds = filter.productIds.join(',');

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
            case 'aov':
            case 'avgLifetimeValue':
                return `₱${value.toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                })}`;

            case 'rtsRate':
            case 'repeatOrderRatio':
                return `${(value * 100).toFixed(1)}%`;

            case 'timeToFirstOrder':
                return `${value.toFixed(1)} hrs`;

            case 'avgDeliveryDays':
            case 'avgShippedOutDays':
                return `${value.toFixed(1)} days`;

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
            .get('/api/v1/workspace/analytics/per-shop', {
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
        reload,
    ]);

    const categories = useMemo(() => {
        return breakdown.map((item) => item.shop_name);
    }, [breakdown]);

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
                <h2 className="text-lg font-semibold">
                    {optionLabels[option]} Per Shop
                </h2>
                <div className="flex items-center gap-4">
                    <Popover open={openDropdown} onOpenChange={setOpenDropdown}>
                        <PopoverTrigger asChild>
                            <button className={`flex h-8 items-center overflow-hidden rounded-[10px] border transition-all ${openDropdown ? 'border-emerald-500 ring-2 ring-emerald-500/15' : 'border-black/6 dark:border-white/6 hover:border-black/12 dark:hover:border-white/12'} bg-stone-100 dark:bg-zinc-800`}>
                                <span className="flex h-full items-center justify-center border-r border-black/6 dark:border-white/6 px-2.5 text-gray-400 dark:text-gray-500">
                                    <ChevronDown className={`h-3.5 w-3.5 transition-transform ${openDropdown ? 'rotate-180 text-emerald-500' : ''}`} />
                                </span>
                                <span className="px-3 text-[13px] font-medium text-gray-700 dark:text-gray-200">
                                    {optionLabels[option]}
                                </span>
                            </button>
                        </PopoverTrigger>
                        <PopoverContent align="end" className="w-56 overflow-hidden rounded-[12px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900 p-1 shadow-lg dark:shadow-black/30">
                            {Object.entries(optionLabels).map(([key, label]) => (
                                <button
                                    key={key}
                                    className={`flex w-full items-center justify-between rounded-[8px] px-3 py-1.5 text-[13px] transition-colors ${option === key ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-medium' : 'text-gray-700 dark:text-gray-200 hover:bg-stone-50 dark:hover:bg-zinc-800'}`}
                                    onClick={() => { setOption(key); setOpenDropdown(false); }}
                                >
                                    {label}
                                    {option === key && <Check className="h-3.5 w-3.5 text-emerald-500" />}
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

            {loading ? (
                <BarChartSkeleton />
            ) : error ? (
                <div className="flex h-60 flex-col items-center justify-center space-y-4 rounded-lg border border-gray-200 bg-gray-50">
                    <p className="text-red-500">{error}</p>
                    <Button
                        variant="outline"
                        onClick={() => setReload(true)}
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
                <ComponentCard>
                    <BarChart
                        categories={categories}
                        series={series}
                        formatValue={formatValue}
                    />
                </ComponentCard>
            )}
        </div>
    );
}
