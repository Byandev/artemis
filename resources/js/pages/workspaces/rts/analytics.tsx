import AppLayout from '@/layouts/app-layout';
import RtsNavigation from '@/pages/workspaces/rts/partials/RtsNavigation';
import { Workspace } from '@/types/models/Workspace';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { useMemo } from 'react';
import { ChartConfig } from '@/components/ui/chart';
import AnalyticsView from './partials/AnalyticsView';
import { ColumnDef } from '@tanstack/react-table';

type RTSAnalyticsData = {
    page_id: number;
    page_name?: string | null;
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
        grouped_rts_stats_by_page: Array<RTSAnalyticsData>;
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

    const perPageChartConfig = {
        delivered: {
            label: "Delivered",
            color: "#10b981",
        },
        returned: {
            label: "Returned",
            color: "#ef4444",
        },
    } satisfies ChartConfig;

    const perPageColumns: ColumnDef<RTSAnalyticsData>[] = [
        {
            accessorKey: "page_name",
            header: "Page Name",
        },
        {
            accessorKey: "returned_count",
            header: "Returned Count",
        },
        {
            accessorKey: "delivered_count",
            header: "Delivered Count",
        },
        {
            accessorKey: "rts_rate_percentage",
            header: "RTS Rate Percentage",
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

                    <div className='col-span-4 mt-4 '>
                        <AnalyticsView<RTSAnalyticsData>
                            columns={perPageColumns}
                            bars={[
                                { dataKey: 'delivered_count', fill: perPageChartConfig.delivered.color, name: perPageChartConfig.delivered.label },
                                { dataKey: 'returned_count', fill: perPageChartConfig.returned.color, name: perPageChartConfig.returned.label },
                            ]}
                            xKey="page_name"
                            className="max-h-[400px] w-full"
                            data={data.grouped_rts_stats_by_page}
                            chartConfig={perPageChartConfig}
                            title="Breakdown per Pages"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

export default Analytics;
