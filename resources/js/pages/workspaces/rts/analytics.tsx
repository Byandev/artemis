import AppLayout from '@/layouts/app-layout';
import RtsNavigation from '@/pages/workspaces/rts/partials/RtsNavigation';
import { Workspace } from '@/types/models/Workspace';
import { Card, CardHeader, CardContent } from '@/components/ui/card';
import { useMemo } from 'react';
import { ChartConfig } from '@/components/ui/chart';
import BreakdownAnalyticsView from './partials/BreakdownAnalyticsView';
import { ColumnDef } from '@tanstack/react-table';
import { getLatLng } from '@/lib/cities';
import { HeatPoint } from './partials/HeatmapMap';

type BreakDownAnalytics = {
    rts_rate_percentage_orders: number;
    rts_rate_percentage: number;
    returned_count: number;
    delivered_count: number;
}

interface PerPageBreakDownAnalytics extends BreakDownAnalytics {
    page_name: string;
}

interface PerUserBreakDownAnalytics extends BreakDownAnalytics {
    user_name: string;
}

interface PerCityBreakDownAnalytics extends BreakDownAnalytics {
    city_name: string;
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
        grouped_rts_stats_by_page: PerPageBreakDownAnalytics[];
        grouped_rts_stats_by_users: PerUserBreakDownAnalytics[];
        grouped_rts_stats_by_cities: PerCityBreakDownAnalytics[];
    }
}

const Analytics = ({ workspace, data }: Props) => {
    const heatmapPoints: HeatPoint[] = useMemo(() => {
        return data.grouped_rts_stats_by_cities
            .map((city) => {
                const latLng = getLatLng(city.city_name);
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
    }, [data.grouped_rts_stats_by_cities]);


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

    const perPageColumns: ColumnDef<PerPageBreakDownAnalytics>[] = [
        {
            accessorKey: "page_name",
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

    const perUserColumns: ColumnDef<PerUserBreakDownAnalytics>[] = [
        {
            accessorKey: "user_name",
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

    const perCityColumns: ColumnDef<PerCityBreakDownAnalytics>[] = [
        {
            accessorKey: "city_name",
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

    return (
        <AppLayout>
            <div className='px-4 py-6'>
                <RtsNavigation workspace={workspace} />

                <div className="grid grid-cols-4 gap-2">
                    {analytics.map((data, key) => {
                        return <Card key={key} className="p-4 gap-5">
                            <CardHeader className="p-0">
                                <div>
                                    <span className="text-2xl md:text-3xl font-extrabold">
                                        {typeof data.value === 'number'
                                            ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(data.value)
                                            : data.value}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                <p className="text-md text-muted-foreground">{data.title}</p>
                            </CardContent>
                        </Card>
                    })}

                    <div className='col-span-4 mt-4 space-y-6'>
                        <BreakdownAnalyticsView<PerPageBreakDownAnalytics>
                            columns={perPageColumns}
                            bars={[
                                { dataKey: 'rts_rate_percentage', fill: chartConfig.rts_rate_percentage.color, name: chartConfig.rts_rate_percentage.label },
                            ]}
                            xKey="page_name"
                            className="max-h-[400px] w-full"
                            data={data.grouped_rts_stats_by_page}
                            chartConfig={chartConfig}
                            title="Breakdown per Pages"
                        />

                        <BreakdownAnalyticsView<PerUserBreakDownAnalytics>
                            columns={perUserColumns}
                            bars={[
                                { dataKey: 'rts_rate_percentage', fill: chartConfig.rts_rate_percentage.color, name: chartConfig.rts_rate_percentage.label },
                            ]}
                            xKey="user_name"
                            className="max-h-[400px] w-full"
                            data={data.grouped_rts_stats_by_users}
                            chartConfig={chartConfig}
                            title="Breakdown per Users"
                        />

                        <BreakdownAnalyticsView<PerCityBreakDownAnalytics>
                            columns={perCityColumns}
                            availableViews={['heatmap', 'table']}
                            className="max-h-[400px] w-full"
                            data={data.grouped_rts_stats_by_cities}
                            title="Breakdown per Cities"
                            heatmapPoints={heatmapPoints}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

export default Analytics;
