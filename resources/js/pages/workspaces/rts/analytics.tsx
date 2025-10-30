import AppLayout from '@/layouts/app-layout';
import RtsNavigation from '@/pages/workspaces/rts/partials/RtsNavigation';
import { Workspace } from '@/types/models/Workspace';
import workspace from '@/routes/workspace';
import { Card, CardDescription, CardHeader, CardTitle, CardContent, CardFooter } from '@/components/ui/card';
import { useMemo } from 'react';

type Props = {
    workspace: Workspace;
    data: {
        rts_rate_percentage: number;
        returned_count: number;
        delivered_count: number;
        returned_amount: number;
        tracked_orders: number;
        sent_parcel_journey_notifications: number
    }
}

const Analytics = ({ workspace, data } : Props) => {
    const analytics = useMemo(() => {
        return [
            { title: 'RTS Rate', value: `${data.rts_rate_percentage}%` },
            { title: 'RTS Amount', value: data.returned_amount },
            { title: 'Tracked Orders', value: data.tracked_orders },
            { title: 'Parcel Updates Sent', value: data.sent_parcel_journey_notifications },
        ]
    }, [data])

    return (
        <AppLayout>
            <div className='px-4 py-6'>
                <RtsNavigation workspace={workspace}/>

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
                </div>
            </div>
        </AppLayout>
    );
}

export default Analytics;
