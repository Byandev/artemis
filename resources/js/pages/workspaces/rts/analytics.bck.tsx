import { Button } from '@/components/ui/button';
import { SimpleDateRangePicker } from '@/components/ui/simple-date-range-picker';
import MetricsCard from '@/components/workspaces/MetricsCard';
import { useDateRange } from '@/hooks/use-date-range';
import AppLayout from '@/layouts/app-layout';
import workspaces from '@/routes/workspaces';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import AnalyticsFilters from './partials/AnalyticsFilters';
import BreakdownPerCities from './partials/BreakdownPerCities';
import BreakdownPerPages from './partials/BreakdownPerPages';
import BreakdownPerShops from './partials/BreakdownPerShops';
import BreakdownPerUsers from './partials/BreakdownPerUsers';
import { formatDate } from 'date-fns';
import { Bell, Package, RotateCcw, TrendingUp } from 'lucide-react';

type Props = {
    workspace: Workspace;
    filters: {
        page_ids?: number[];
        user_ids?: number[];
        shop_ids?: number[];
        start_date?: string;
        end_date?: string;
    };
    data: {
        rts_rate_percentage: number;
        returned_count: number;
        delivered_count: number;
        returned_amount: number;
        tracked_orders: number;
        sent_parcel_journey_notifications: number;
    };
};

const Analytics = ({ workspace, filters, data }: Props) => {
    // Filters
    const [selectedPagesFilter, setSelectedPagesFilter] = useState<number[]>(filters.page_ids ?? []);
    const [selectedUsersFilter, setSelectedUsersFilter] = useState<number[]>(filters.user_ids ?? []);
    const [selectedShopFilter, setSelectedShopFilter] = useState<number[]>(filters.shop_ids ?? []);

    // Use global date range state with automatic initialization from URL filters
    const { dateRange, setDateRange } = useDateRange({
        startDate: filters.start_date,
        endDate: filters.end_date
    });

    const dateRangeStr = useMemo(() => ({
        to: moment(dateRange?.to).format('YYYY-MM-DD'),
        from: moment(dateRange?.from).format('YYYY-MM-DD'),
    }), [dateRange])

    // Build query string
    const queryString = useMemo(() => {
        const params = new URLSearchParams();
        selectedPagesFilter.forEach((id) => params.append('page_ids[]', String(id)));
        selectedUsersFilter.forEach((id) => params.append('user_ids[]', String(id)));
        selectedShopFilter.forEach((id) => params.append('shop_ids[]', String(id)));
        if (dateRange?.from) params.append('start_date', dateRangeStr.from);
        if (dateRange?.to) params.append('end_date', dateRangeStr.to);
        return params.toString();
    }, [selectedPagesFilter, selectedUsersFilter, selectedShopFilter, dateRange, dateRangeStr]);

    // Update URL and refetch data when filters change
    useEffect(() => {
        router.get(
            workspaces.rts.analytics(workspace.slug),
            {
                page_ids: selectedPagesFilter.length > 0 ? selectedPagesFilter : undefined,
                user_ids: selectedUsersFilter.length > 0 ? selectedUsersFilter : undefined,
                shop_ids: selectedShopFilter.length > 0 ? selectedShopFilter : undefined,
                start_date: dateRange?.from ? dateRangeStr.from : undefined,
                end_date: dateRange?.to ? dateRangeStr.to : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['data'] // Only reload the data prop
            }
        );
    }, [workspace.slug, selectedPagesFilter, selectedUsersFilter, selectedShopFilter, dateRange, dateRangeStr]);

    // Analytics stat cards
    const analytics = useMemo(
        () => [
            {
                title: 'RTS Rate',
                value: `${data.rts_rate_percentage}%`,
                icon: <TrendingUp className="h-5 w-5" />,
                tooltip: 'Return to Sender rate percentage',
                color: 'purple' as const,
            },
            {
                title: 'RTS Amount',
                value: data.returned_amount,
                icon: <RotateCcw className="h-5 w-5" />,
                tooltip: 'Total value of returned items',
                color: 'orange' as const,
            },
            {
                title: 'Tracked Orders',
                value: data.tracked_orders,
                icon: <Package className="h-5 w-5" />,
                tooltip: 'Number of orders currently being tracked',
                color: 'blue' as const,
            },
            {
                title: 'Parcel Updates Sent',
                value: data.sent_parcel_journey_notifications,
                icon: <Bell className="h-5 w-5" />,
                tooltip: 'Total notifications sent for parcel journeys',
                color: 'green' as const,
            },
        ],
        [data],
    );
    const clearFilters = () => {
        setSelectedPagesFilter([]);
        setSelectedUsersFilter([]);
        setSelectedShopFilter([]);
        setDateRange({
            from: moment().startOf('month').toDate(),
            to: moment().toDate()
        });
        router.get(
            workspaces.rts.analytics(workspace.slug),
            {},
            { preserveState: true, preserveScroll: true }
        );
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Analytics`} />
            <div className="p-4 md:p-6 space-y-6">
                <div className="mb-6 flex flex-col items-start justify-between md:flex-row md:items-center">
                    <div className="flex flex-col">
                        <h1 className="text-2xl font-bold">RTS Analytics</h1>

                        <p className="text-sm font-light text-gray-500">
                            Performance overview from to{' '}
                        </p>
                    </div>
                    <div className="flex gap-4">
                        <SimpleDateRangePicker useGlobalState />

                        <AnalyticsFilters
                            workspace={workspace}
                            selectedPagesFilter={selectedPagesFilter}
                            setSelectedPagesFilter={setSelectedPagesFilter}
                            selectedUsersFilter={selectedUsersFilter}
                            setSelectedUsersFilter={setSelectedUsersFilter}
                            selectedShopFilter={selectedShopFilter}
                            setSelectedShopFilter={setSelectedShopFilter}
                        />
                    </div>

                    {(selectedPagesFilter.length > 0 ||
                        selectedUsersFilter.length > 0 ||
                        selectedShopFilter.length > 0 ||
                        dateRangeStr.from !==
                            moment().startOf('month').format('YYYY-MM-DD') ||
                        dateRangeStr.to !== moment().format('YYYY-MM-DD')) && (
                        <Button variant="outline" onClick={clearFilters}>
                            Clear Filters
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {analytics.map((item, key) => (
                        <MetricsCard
                            key={key}
                            title={item.title}
                            value={item.value}
                            icon={item.icon}
                            tooltip={item.tooltip}
                            color={item.color}
                        />
                    ))}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="col-span-1 md:col-span-2 ">
                        <BreakdownPerPages
                            workspace={workspace}
                            queryString={queryString}
                        />
                    </div>
                    <div className='h-full'>
                        <BreakdownPerCities
                            workspace={workspace}
                            queryString={queryString}
                        />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <BreakdownPerShops
                        workspace={workspace}
                        queryString={queryString}
                    />
                    <BreakdownPerUsers
                        workspace={workspace}
                        queryString={queryString}
                    />
                </div>
            </div>
        </AppLayout>
    );
};

export default Analytics;
