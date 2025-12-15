import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { Workspace } from '@/types/models/Workspace';
import { currencyFormatter, numberFormatter, percentageFormatter } from '@/lib/utils';
import MetricsCard from '@/components/workspaces/MetricsCard';

type Props = {
    workspace: Workspace;
    stats: {
        total_sales: number;
        total_ad_spend: number;
        total_orders: number;
        roas: number;
        rts_rate_percentage: number;
        delivered_orders: number;
        sms_sent: number;
        chat_msg_sent: number;
    }
}

export default function Index({ workspace, stats }: Props) {
    const analytics = useMemo(() => {
        return [
            { title: 'Total Sales', value: currencyFormatter(stats.total_sales) },
            { title: 'Total Add Spend', value: currencyFormatter(stats.total_ad_spend) },
            { title: 'Total Orders', value: numberFormatter(stats.total_orders) },
            { title: 'ROAS', value: stats.roas },
            { title: 'RTS Rate', value: percentageFormatter(stats.rts_rate_percentage / 100) },
            { title: 'Delivered Orders', value: numberFormatter(stats.delivered_orders) },
            { title: 'SMS Sent', value: numberFormatter(stats.sms_sent) },
            { title: 'Chat Messages Sent', value: numberFormatter(stats.chat_msg_sent) },
        ]
    }, [stats])

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Dashboard</h1>
                    <p className="text-muted-foreground mt-1">Welcome back! Here's your workspace overview.</p>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-4">
                    {analytics.map((data, key) => (
                        <MetricsCard key={key} title={data.title} value={data.value} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
