import BarChart from '@/components/charts/BarChart';
import { FilterValue } from '@/components/filters/Filters';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';

interface Props {
    workspace: Workspace;
    dateRange: string[];
    filter: FilterValue;
}

interface BreakdownItem {
    page_id: number;
    page_name: string;
    value: number | string;
}



export default function PageBreakdown({ workspace, dateRange, filter }: Props) {
    const [breakdown, setBreakdown] = useState<BreakdownItem[]>([]);
    const [loading, setLoading] = useState(true);
    const [option, setOption] = useState('totalSales');

    const startDate = moment(dateRange[0]).format('YYYY-MM-DD');
    const endDate = moment(dateRange[1]).format('YYYY-MM-DD');

    const teamIds = filter.teamIds.join(',');
    const shopIds = filter.shopIds.join(',');
    const pageIds = filter.pageIds.join(',');
    const userIds = filter.userIds.join(',');
    const productIds = filter.productIds.join(',');

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
        const controller = new AbortController();

        setLoading(true);

        axios
            .get('/api/v1/workspace/analytics/per-page', {
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
    ]);

    const categories = useMemo(() => {
        return breakdown.map((item) => item.page_name);
    }, [breakdown]);

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

    const series = useMemo(() => {
        return [
            {
                name: optionLabels[option] ?? 'Value',
                data: breakdown.map((item) => Number(item.value)),
            },
        ];
    }, [breakdown, option]);

    if (loading) {
        return <div>Loading...</div>;
    }

    return (
        <div>
            <div className="flex items-center justify-between gap-4">
                <h2 className="text-lg font-semibold">
                    {optionLabels[option]} Per Page
                </h2>

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
            <BarChart categories={categories} series={series}  formatValue={formatValue}/>
        </div>
    );
}
