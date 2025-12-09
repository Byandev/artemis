import React, { useEffect, useMemo, useState, useCallback } from 'react';
import AppLayout from '@/layouts/app-layout';
import RtsNavigation from '@/pages/workspaces/rts/partials/RtsNavigation';
import { Workspace } from '@/types/models/Workspace';
import { ChartConfig } from '@/components/ui/chart';
import BreakdownAnalyticsView from './partials/BreakdownAnalyticsView';
import { ColumnDef } from '@tanstack/react-table';
import { getLatLng } from '@/lib/cities';
import { HeatPoint } from './partials/HeatmapMap';
import AnalyticsFilters from './partials/AnalyticsFilters';
import AnalyticsStatCard from './partials/AnalyticsStatCard';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { router } from '@inertiajs/react';
import workspaces from '@/routes/workspaces';
import { Button } from '@/components/ui/button';

type BreakDownAnalytics = {
    id: number;
    name: string;
    total_orders: number;
    rts_rate_percentage: number;
    returned_count: number;
    delivered_count: number;
};

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

    // Grouped analytics
    const [groupedByPage, setGroupedByPage] = useState<{
        data: BreakDownAnalytics[];
        filter_options: { id: number; name: string }[];
    }>({ data: [], filter_options: [] });
    const [groupedByShops, setGroupedByShops] = useState<{
        data: BreakDownAnalytics[];
        filter_options: { id: number; name: string }[];
    }>({ data: [], filter_options: [] });
    const [groupedByUsers, setGroupedByUsers] = useState<{
        data: BreakDownAnalytics[];
        filter_options: { id: number; name: string }[];
    }>({ data: [], filter_options: [] });
    const [groupedByCities, setGroupedByCities] = useState<{
        data: BreakDownAnalytics[];
        filter_options: { id: number; name: string }[];
    }>({ data: [], filter_options: [] });


    const [loadingGrouped, setLoadingGrouped] = useState<boolean>(true);

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

    // Refresh page with Inertia
    const refreshPage = useCallback(() => {
        router.get(workspaces.rts.analytics(workspace.slug), {
            page_ids: selectedPagesFilter,
            user_ids: selectedUsersFilter,
            shop_ids: selectedShopFilter,
            start_date: startDate ? startDate.toISOString().slice(0, 10) : undefined,
            end_date: endDate ? endDate.toISOString().slice(0, 10) : undefined,
        }, { preserveState: true, preserveScroll: true });
    }, [workspace.slug, selectedPagesFilter, selectedUsersFilter, selectedShopFilter, startDate, endDate]);

    // Fetch grouped analytics
    useEffect(() => {
        const base = `/workspaces/${workspace.slug}/rts/analytics/group-by`;

        const fetchJson = async (path: string) => {
            try {
                const res = await fetch(path, { credentials: 'same-origin' });
                if (!res.ok) return [];
                return res.json();
            } catch {
                return [];
            }
        };

        const fetchGroupedData = async () => {
            setLoadingGrouped(true);
            try {
                // Update Inertia page
                refreshPage();

                const [pages, shops, users, cities] = await Promise.all([
                    fetchJson(`${base}/pages${queryString ? `?${queryString}` : ''}`),
                    fetchJson(`${base}/shops${queryString ? `?${queryString}` : ''}`),
                    fetchJson(`${base}/users${queryString ? `?${queryString}` : ''}`),
                    fetchJson(`${base}/cities${queryString ? `?${queryString}` : ''}`),
                ]);

                setGroupedByPage(pages ?? { data: [], filter_options: [] });
                setGroupedByShops(shops ?? { data: [], filter_options: [] });
                setGroupedByUsers(users ?? { data: [], filter_options: [] });
                setGroupedByCities(cities ?? { data: [], filter_options: [] });
            } finally {
                setLoadingGrouped(false);
            }
        };

        fetchGroupedData();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [workspace.slug, queryString, refreshPage]);

    // Heatmap points
    const heatmapPoints: HeatPoint[] = useMemo(() => {
        return groupedByCities.data
            .map((city) => {
                const latLng = getLatLng(city.name);
                if (!latLng) return null;
                return { coordinates: latLng, value: city.rts_rate_percentage };
            })
            .filter((p): p is HeatPoint => p !== null);
    }, [groupedByCities]);

    // Analytics stat cards
    const analytics = useMemo(() => [
        { title: 'RTS Rate', value: `${data.rts_rate_percentage}%` },
        { title: 'RTS Amount', value: data.returned_amount },
        { title: 'Tracked Orders', value: data.tracked_orders },
        { title: 'Parcel Updates Sent', value: data.sent_parcel_journey_notifications },
    ], [data]);

    const chartConfig = useMemo(() => ({
        rts_rate_percentage: { label: 'RTS Rate %', color: 'hsl(var(--primary))' }
    } satisfies ChartConfig), []);

    // Table columns
    const buildColumns = useCallback((label: string): ColumnDef<BreakDownAnalytics>[] => [
        { accessorKey: 'name', header: label },
        { accessorKey: 'total_orders', header: 'Total Orders' },
        { accessorKey: 'returned_count', header: 'Returned' },
        { accessorKey: 'delivered_count', header: 'Delivered' },
        {
            accessorKey: 'rts_rate_percentage',
            header: 'RTS Rate',
            cell: ({ row }) => `${row.original.rts_rate_percentage}%`,
        },
    ], []);

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
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">RTS Management</h1>
                        <p className="text-muted-foreground mt-1">Manage RTS analytics and reports</p>
                    </div>
                </div>

                <RtsNavigation workspace={workspace} />

                <div className="rounded-xl border bg-card p-6 shadow-sm">
                    <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-8">
                        <div>
                            <h2 className="text-2xl font-bold tracking-tight">Analytics</h2>
                            <p className="text-sm text-muted-foreground mt-1">Track your RTS performance metrics</p>
                        </div>

                        <div className="flex items-center flex-wrap gap-2">

                            {!(loadingGrouped || (selectedPagesFilter.length === 0 && selectedUsersFilter.length === 0 && selectedShopFilter.length === 0 && !startDate && !endDate)) && (
                                <Button
                                    variant="outline"
                                    onClick={clearFilters}
                                >
                                    Clear Filters
                                </Button>
                            )}

                            <AnalyticsFilters
                                availablePages={groupedByPage.filter_options}
                                availableUsers={groupedByUsers.filter_options}
                                availableShops={groupedByShops.filter_options}
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

                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        {analytics.map((item, key) => (
                            <AnalyticsStatCard key={key} title={item.title} value={item.value} />
                        ))}

                        <div className="col-span-1 sm:col-span-2 md:col-span-4 mt-6 space-y-6">
                            <BreakdownAnalyticsView<BreakDownAnalytics>
                                columns={buildColumns('Page')}
                                bars={[{ dataKey: 'rts_rate_percentage', fill: chartConfig.rts_rate_percentage.color, name: chartConfig.rts_rate_percentage.label }]}
                                xKey="name"
                                className="w-full max-h-[400px]"
                                data={groupedByPage.data}
                                chartConfig={chartConfig}
                                title="Breakdown per Pages"
                                loading={loadingGrouped}
                            />

                            <BreakdownAnalyticsView<BreakDownAnalytics>
                                columns={buildColumns('Shop')}
                                bars={[{ dataKey: 'rts_rate_percentage', fill: chartConfig.rts_rate_percentage.color, name: chartConfig.rts_rate_percentage.label }]}
                                xKey="name"
                                className="w-full max-h-[400px]"
                                data={groupedByShops.data}
                                chartConfig={chartConfig}
                                title="Breakdown per Shops"
                                loading={loadingGrouped}
                            />

                            <BreakdownAnalyticsView<BreakDownAnalytics>
                                columns={buildColumns('User')}
                                bars={[{ dataKey: 'rts_rate_percentage', fill: chartConfig.rts_rate_percentage.color, name: chartConfig.rts_rate_percentage.label }]}
                                xKey="name"
                                className="w-full max-h-[400px]"
                                data={groupedByUsers.data}
                                chartConfig={chartConfig}
                                title="Breakdown per Users"
                                loading={loadingGrouped}
                            />

                            <BreakdownAnalyticsView<BreakDownAnalytics>
                                columns={buildColumns('City')}
                                availableViews={['heatmap', 'table']}
                                className="w-full max-h-[400px]"
                                data={groupedByCities.data}
                                title="Breakdown per Cities"
                                heatmapPoints={heatmapPoints}
                                loading={loadingGrouped}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
};

export default Analytics;
