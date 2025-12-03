import AppLayout from '@/layouts/app-layout';
import RtsNavigation from '@/pages/workspaces/rts/partials/RtsNavigation';
import { Workspace } from '@/types/models/Workspace';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { useMemo } from 'react';
import { ChartConfig } from '@/components/ui/chart';
import AnalyticsView from './partials/AnalyticsView';
import { ColumnDef } from '@tanstack/react-table';

type BreakDownAnalytics = {
    total_orders: number;
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
        grouped_rts_stats_by_page: Array<PerPageBreakDownAnalytics>;
        grouped_rts_stats_by_users: Array<PerUserBreakDownAnalytics>;
        grouped_rts_stats_by_cities: Array<PerCityBreakDownAnalytics>;
    }
}

const Analytics = ({ workspace, data }: Props) => {
    const analytics = useMemo(() => {
        return [
            { title: 'RTS Rate', value: `${data.rts_rate_percentage}%` },
            { title: 'RTS Amount', value: data.returned_amount },
            { title: 'Tracked Orders', value: data.tracked_orders },
            { title: 'Parcel Updates Sent', value: data.sent_parcel_journey_notifications },
        ]
    }, [data])

    const chartConfig = {
        total: {
            label: "Total",
            color: "#3b82f6",
        },
        delivered: {
            label: "Delivered",
            color: "#10b981",
        },
        returned: {
            label: "Returned",
            color: "#ef4444",
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

                <div className="grid grid-cols-4 gap-4">
                    {analytics.map((data, key) => {
                        return <Card key={key}>
                            <CardHeader>
                                <CardTitle>{data.value}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p>{data.title}</p>
                            </CardContent>
                        </Card>
                    })}

                    <div className='col-span-4 mt-4 space-y-6'>
                        <AnalyticsView<PerPageBreakDownAnalytics>
                            columns={perPageColumns}
                            bars={[
                                { dataKey: 'total_orders', fill: chartConfig.total.color, name: chartConfig.total.label },
                                { dataKey: 'delivered_count', fill: chartConfig.delivered.color, name: chartConfig.delivered.label },
                                { dataKey: 'returned_count', fill: chartConfig.returned.color, name: chartConfig.returned.label },
                            ]}
                            xKey="page_name"
                            className="max-h-[400px] w-full"
                            data={data.grouped_rts_stats_by_page}
                            chartConfig={chartConfig}
                            title="Breakdown per Pages"
                        />

                        <AnalyticsView<PerUserBreakDownAnalytics>
                            columns={perUserColumns}
                            bars={[
                                { dataKey: 'total_orders', fill: chartConfig.total.color, name: chartConfig.total.label },
                                { dataKey: 'delivered_count', fill: chartConfig.delivered.color, name: chartConfig.delivered.label },
                                { dataKey: 'returned_count', fill: chartConfig.returned.color, name: chartConfig.returned.label },
                            ]}
                            xKey="user_name"
                            className="max-h-[400px] w-full"
                            data={data.grouped_rts_stats_by_users}
                            chartConfig={chartConfig}
                            title="Breakdown per Users"
                        />

                        <AnalyticsView<PerCityBreakDownAnalytics>
                            columns={perCityColumns}
                            bars={[
                                { dataKey: 'total_orders', fill: chartConfig.total.color, name: chartConfig.total.label },
                                { dataKey: 'delivered_count', fill: chartConfig.delivered.color, name: chartConfig.delivered.label },
                                { dataKey: 'returned_count', fill: chartConfig.returned.color, name: chartConfig.returned.label },
                            ]}
                            xKey="city_name"
                            className="max-h-[400px] w-full"
                            data={data.grouped_rts_stats_by_cities}
                            chartConfig={chartConfig}
                            title="Breakdown per Cities"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

export default Analytics;
