import AppLayout from '@/layouts/app-layout';
import RtsNavigation from '@/pages/workspaces/rts/partials/RtsNavigation';
import { Workspace } from '@/types/models/Workspace';
import { useEffect, useMemo, useState } from 'react';
import { ChartConfig } from '@/components/ui/chart';
import BreakdownAnalyticsView from './partials/BreakdownAnalyticsView';
import { ColumnDef } from '@tanstack/react-table';
import { getLatLng } from '@/lib/cities';
import { HeatPoint } from './partials/HeatmapMap';
import AnalyticsFilters from './partials/AnalyticsFilters';
import DateFilter from './partials/DateFilter';
import AnalyticsStatCard from './partials/AnalyticsStatCard';
import { formatDate } from '@/lib/utils';

type BreakDownAnalytics = {
    id: number;
    name: string;
    total_orders: number;
    rts_rate_percentage: number;
    returned_count: number;
    delivered_count: number;
}

type Props = {
    workspace: Workspace;
    data: {
        rts_rate_percentage: number;
        returned_count: number;
        delivered_count: number;
        returned_amount: number;
        tracked_orders: number;
        sent_parcel_journey_notifications: number;
    }
}


const Analytics = ({ workspace, data }: Props) => {
    const [selectedPagesFilter, setSelectedPagesFilter] = useState<number[]>([]);
    const [selectedUsersFilter, setSelectedUsersFilter] = useState<number[]>([]);
    const [selectedShopFilter, setSelectedShopFilter] = useState<number[]>([]);

    const [allGroupedByPage, setAllGroupedByPage] = useState<BreakDownAnalytics[]>([]);
    const [allGroupedByShops, setAllGroupedByShops] = useState<BreakDownAnalytics[]>([]);
    const [allGroupedByUsers, setAllGroupedByUsers] = useState<BreakDownAnalytics[]>([]);

    const [groupedByPage, setGroupedByPage] = useState<BreakDownAnalytics[]>([]);
    const [groupedByShops, setGroupedByShops] = useState<BreakDownAnalytics[]>([]);
    const [groupedByUsers, setGroupedByUsers] = useState<BreakDownAnalytics[]>([]);
    const [groupedByCities, setGroupedByCities] = useState<BreakDownAnalytics[]>([]);
    const [loadingGrouped, setLoadingGrouped] = useState<boolean>(true);

    const [open, setOpen] = useState(false)
    const [date, setDate] = useState<Date | undefined>()
    const [month, setMonth] = useState<Date | undefined>(undefined)
    const [value, setValue] = useState(formatDate(undefined))

    useEffect(() => {
        const base = `/workspaces/${workspace.slug}/rts/analytics/group-by`;

        const fetchJson = async (path: string) => {
            const res = await fetch(path, { credentials: 'same-origin' });
            if (!res.ok) return [];
            return res.json();
        };

        const buildQuery = () => {
            const params = new URLSearchParams();
            selectedPagesFilter.forEach((id) => params.append('page_ids[]', String(id)));
            selectedUsersFilter.forEach((id) => params.append('user_ids[]', String(id)));
            selectedShopFilter.forEach((id) => params.append('shop_ids[]', String(id)));
            if (date) {
                // format YYYY-MM-DD
                const d = new Date(date);
                const iso = d.toISOString().slice(0, 10);
                params.append('date', iso);
            }
            return params.toString();
        };

        (async () => {
            setLoadingGrouped(true);
            try {
                const qs = buildQuery();
                const [pages, shops, users, cities] = await Promise.all([
                    fetchJson(`${base}/page${qs ? `?${qs}` : ''}`),
                    fetchJson(`${base}/shops${qs ? `?${qs}` : ''}`),
                    fetchJson(`${base}/users${qs ? `?${qs}` : ''}`),
                    fetchJson(`${base}/cities${qs ? `?${qs}` : ''}`),
                ]);

                if (allGroupedByPage.length === 0) {
                    setAllGroupedByPage(pages ?? []);
                }
                if (allGroupedByShops.length === 0) {
                    setAllGroupedByShops(shops ?? []);
                }
                if (allGroupedByUsers.length === 0) {
                    setAllGroupedByUsers(users ?? []);
                }

                setGroupedByPage(pages ?? []);
                setGroupedByShops(shops ?? []);
                setGroupedByUsers(users ?? []);
                setGroupedByCities(cities ?? []);
            } catch (e) {
                console.error('Failed to load grouped analytics', e);
            } finally {
                setLoadingGrouped(false);
            }
        })();
    }, [workspace.slug, selectedPagesFilter, selectedUsersFilter, selectedShopFilter, date]);

    const heatmapPoints: HeatPoint[] = useMemo(() => {
        return groupedByCities
            .map((city) => {
                const latLng = getLatLng(city.name);
                if (!latLng) return null;
                const coordinates = {
                    lat: latLng.lat,
                    lng: latLng.lng,
                };
                return {
                    coordinates,
                    value: city.rts_rate_percentage,
                };
            })
            .filter((p): p is HeatPoint => p !== null);
    }, [groupedByCities]);


    const analytics = useMemo(() => {
        return [
            { title: 'RTS Rate', value: `${data.rts_rate_percentage}%` },
            { title: 'RTS Amount', value: data.returned_amount },
            { title: 'Tracked Orders', value: data.tracked_orders },
            { title: 'Parcel Updates Sent', value: data.sent_parcel_journey_notifications },
        ]
    }, [data])

    const chartConfig = {
        rts_rate_percentage: {
            label: "Rts Rate %",
            color: "#1f2937",
        },
    } satisfies ChartConfig;

    const perPageColumns: ColumnDef<BreakDownAnalytics>[] = [
        {
            accessorKey: "name",
            header: "Page",
        },
        {
            accessorKey: "total_orders",
            header: "Total Orders",
        },
        {
            accessorKey: "returned_count",
            header: "Returned",
        },
        {
            accessorKey: "delivered_count",
            header: "Delivered",
        },
        {
            accessorKey: "rts_rate_percentage",
            header: "RTS Rate",
            cell: ({ row }) => {
                return `${row.original.rts_rate_percentage}%`
            }
        },
    ];

    const perUserColumns: ColumnDef<BreakDownAnalytics>[] = [
        {
            accessorKey: "name",
            header: "User",
        },
        {
            accessorKey: "total_orders",
            header: "Total Orders",
        },
        {
            accessorKey: "returned_count",
            header: "Returned",
        },
        {
            accessorKey: "delivered_count",
            header: "Delivered",
        },
        {
            accessorKey: "rts_rate_percentage",
            header: "RTS Rate",
            cell: ({ row }) => {
                return `${row.original.rts_rate_percentage}%`
            }
        },
    ];

    const perCityColumns: ColumnDef<BreakDownAnalytics>[] = [
        {
            accessorKey: "name",
            header: "City",
        },
        {
            accessorKey: "total_orders",
            header: "Total Orders",
        },
        {
            accessorKey: "returned_count",
            header: "Returned",
        },
        {
            accessorKey: "delivered_count",
            header: "Delivered",
        },
        {
            accessorKey: "rts_rate_percentage",
            header: "RTS Rate",
            cell: ({ row }) => {
                return `${row.original.rts_rate_percentage}%`
            }
        },
    ];

    const perShopColumns: ColumnDef<BreakDownAnalytics>[] = [
        {
            accessorKey: "name",
            header: "Shop",
        },
        {
            accessorKey: "total_orders",
            header: "Total Orders",
        },
        {
            accessorKey: "returned_count",
            header: "Returned",
        },
        {
            accessorKey: "delivered_count",
            header: "Delivered",
        },
        {
            accessorKey: "rts_rate_percentage",
            header: "RTS Rate",
            cell: ({ row }) => {
                return `${row.original.rts_rate_percentage}%`
            }
        },
    ];

    return (
        <AppLayout>
            <div className='px-4 py-6'>
                <RtsNavigation workspace={workspace} />

                <div className='border p-5 rounded-md'>
                    <div className='flex items-center justify-between mb-8'>
                        <h1 className="scroll-m-20 text-center text-3xl font-extrabold tracking-tight text-balance">
                            Analytics
                        </h1>
                        <div className='flex gap-2'>
                            <AnalyticsFilters
                                groupedByPage={allGroupedByPage}
                                groupedByUsers={allGroupedByUsers}
                                groupedByShops={allGroupedByShops}
                                selectedPagesFilter={selectedPagesFilter}
                                setSelectedPagesFilter={setSelectedPagesFilter}
                                selectedUsersFilter={selectedUsersFilter}
                                setSelectedUsersFilter={setSelectedUsersFilter}
                                selectedShopFilter={selectedShopFilter}
                                setSelectedShopFilter={setSelectedShopFilter}
                                loadingGrouped={loadingGrouped}
                            />

                            <DateFilter
                                open={open}
                                setOpen={setOpen}
                                date={date}
                                setDate={setDate}
                                month={month}
                                setMonth={setMonth}
                                value={value}
                                setValue={setValue}
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        {analytics.map((data, key) => (
                            <div className='col-span-1' key={key}>
                                <AnalyticsStatCard title={data.title} value={data.value} />
                            </div>
                        ))}

                        <div className="col-span-1 sm:col-span-2 md:col-span-4 mt-4 space-y-6">
                            <BreakdownAnalyticsView<BreakDownAnalytics>
                                columns={perPageColumns}
                                bars={[
                                    { dataKey: 'rts_rate_percentage', fill: chartConfig.rts_rate_percentage.color, name: chartConfig.rts_rate_percentage.label },
                                ]}
                                xKey="name"
                                className="w-full max-h-[400px]"
                                data={groupedByPage}
                                chartConfig={chartConfig}
                                title="Breakdown per Pages"
                                loading={loadingGrouped}
                            />

                            <BreakdownAnalyticsView<BreakDownAnalytics>
                                columns={perShopColumns}
                                bars={[
                                    { dataKey: 'rts_rate_percentage', fill: chartConfig.rts_rate_percentage.color, name: chartConfig.rts_rate_percentage.label },
                                ]}
                                xKey="name"
                                className="w-full max-h-[400px]"
                                data={groupedByShops}
                                chartConfig={chartConfig}
                                title="Breakdown per Shops"
                                loading={loadingGrouped}
                            />

                            <BreakdownAnalyticsView<BreakDownAnalytics>
                                columns={perUserColumns}
                                bars={[
                                    { dataKey: 'rts_rate_percentage', fill: chartConfig.rts_rate_percentage.color, name: chartConfig.rts_rate_percentage.label },
                                ]}
                                xKey="name"
                                className="w-full max-h-[400px]"
                                data={groupedByUsers}
                                chartConfig={chartConfig}
                                title="Breakdown per Users"
                                loading={loadingGrouped}
                            />

                            <BreakdownAnalyticsView<BreakDownAnalytics>
                                columns={perCityColumns}
                                availableViews={['heatmap', 'table']}
                                className="w-full max-h-[400px]"
                                data={groupedByCities}
                                title="Breakdown per Cities"
                                heatmapPoints={heatmapPoints}
                                loading={loadingGrouped}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout >
    );
}

export default Analytics;
