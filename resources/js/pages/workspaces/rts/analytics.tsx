import { useMemo, useState, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import RTSManagementLayout from './partials/Layout';
import { Workspace } from '@/types/models/Workspace';
import AnalyticsFilters from './partials/AnalyticsFilters';
import MetricsCard from '@/components/workspaces/MetricsCard';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Head, router } from '@inertiajs/react';
import workspaces from '@/routes/workspaces';
import { Button } from '@/components/ui/button';
import BreakdownPerPages from './partials/BreakdownPerPages';
import BreakdownPerShops from './partials/BreakdownPerShops';
import BreakdownPerUsers from './partials/BreakdownPerUsers';
import BreakdownPerCities from './partials/BreakdownPerCities';
import ComponentCard from '@/components/common/ComponentCard';

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
    const [startDate, setStartDate] = useState<Date | undefined>(
        filters.start_date ? new Date(filters.start_date) : undefined
    );
    const [endDate, setEndDate] = useState<Date | undefined>(
        filters.end_date ? new Date(filters.end_date) : undefined
    );

    // Build query string
    const queryString = useMemo(() => {
        const params = new URLSearchParams();
        selectedPagesFilter.forEach((id) => params.append('page_ids[]', String(id)));
        selectedUsersFilter.forEach((id) => params.append('user_ids[]', String(id)));
        selectedShopFilter.forEach((id) => params.append('shop_ids[]', String(id)));
        if (startDate) params.append('start_date', startDate.toISOString().slice(0, 10));
        if (endDate) params.append('end_date', endDate.toISOString().slice(0, 10));
        return params.toString();
    }, [selectedPagesFilter, selectedUsersFilter, selectedShopFilter, startDate, endDate]);

    // Update URL and refetch data when filters change
    useEffect(() => {
        router.get(
            workspaces.rts.analytics(workspace.slug),
            {
                page_ids: selectedPagesFilter.length > 0 ? selectedPagesFilter : undefined,
                user_ids: selectedUsersFilter.length > 0 ? selectedUsersFilter : undefined,
                shop_ids: selectedShopFilter.length > 0 ? selectedShopFilter : undefined,
                start_date: startDate ? startDate.toISOString().slice(0, 10) : undefined,
                end_date: endDate ? endDate.toISOString().slice(0, 10) : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['data'] // Only reload the data prop
            }
        );
    }, [workspace.slug, selectedPagesFilter, selectedUsersFilter, selectedShopFilter, startDate, endDate]);

    // Analytics stat cards
    const analytics = useMemo(() => [
        { title: 'RTS Rate', value: `${data.rts_rate_percentage}%` },
        { title: 'RTS Amount', value: data.returned_amount },
        { title: 'Tracked Orders', value: data.tracked_orders },
        { title: 'Parcel Updates Sent', value: data.sent_parcel_journey_notifications },
    ], [data]);

    const clearFilters = () => {
        setSelectedPagesFilter([]);
        setSelectedUsersFilter([]);
        setSelectedShopFilter([]);
        setStartDate(undefined);
        setEndDate(undefined);
        router.get(
            workspaces.rts.analytics(workspace.slug),
            {},
            { preserveState: true, preserveScroll: true }
        );
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Analytics`} />
            <RTSManagementLayout workspace={workspace}>
                <ComponentCard title="Track your RTS performance metrics">
                    <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-8">
                        <div className="flex items-center justify-end w-full flex-wrap gap-2">

                            {(selectedPagesFilter.length > 0 || selectedUsersFilter.length > 0 || selectedShopFilter.length > 0 || startDate || endDate) && (
                                <Button
                                    variant="outline"
                                    onClick={clearFilters}
                                >
                                    Clear Filters
                                </Button>
                            )}

                            <AnalyticsFilters
                                workspace={workspace}
                                selectedPagesFilter={selectedPagesFilter}
                                setSelectedPagesFilter={setSelectedPagesFilter}
                                selectedUsersFilter={selectedUsersFilter}
                                setSelectedUsersFilter={setSelectedUsersFilter}
                                selectedShopFilter={selectedShopFilter}
                                setSelectedShopFilter={setSelectedShopFilter}
                            />

                            <DateRangePicker
                                onUpdate={(values) => {
                                    setStartDate(values.range.from ?? undefined);
                                    setEndDate(values.range.to ?? undefined);
                                }}
                                align="start"
                                initialDateTo={endDate}
                                initialDateFrom={startDate}
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        {analytics.map((item, key) => (
                            <MetricsCard key={key} title={item.title} value={item.value} className='col-span-2 md:col-span-1' />
                        ))}

                        <div className='col-span-2 flex flex-col gap-5'>
                            <BreakdownPerPages workspace={workspace} queryString={queryString} />

                            <BreakdownPerShops workspace={workspace} queryString={queryString} />

                            <BreakdownPerUsers workspace={workspace} queryString={queryString} />
                        </div>

                        <div className='col-span-2'>
                            <BreakdownPerCities workspace={workspace} queryString={queryString} />
                        </div>
                    </div>
                </ComponentCard>
            </RTSManagementLayout>
        </AppLayout>
    );
};

export default Analytics;
