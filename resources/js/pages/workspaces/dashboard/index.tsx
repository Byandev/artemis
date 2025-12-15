import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { Workspace } from '@/types/models/Workspace';
import { currencyFormatter, numberFormatter, percentageFormatter } from '@/lib/utils';

type Props = {
    workspace: Workspace;
    stats: {
        total_sales: number;
        total_orders: number;
        rts_rate_percentage: number;
        tracked_orders: number;
    }
}

export default function Index({ workspace, stats }: Props) {
    const analytics = useMemo(() => {
        return [
            { title: 'Total Sales', value: currencyFormatter(stats.total_sales) },
            { title: 'Total Orders', value: numberFormatter(stats.total_orders) },
            { title: 'RTS Rate', value: percentageFormatter(stats.rts_rate_percentage / 100) },
            { title: 'Tracked Orders', value: numberFormatter(stats.tracked_orders) },
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
                        <div
                            key={key}
                            className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                            <div className="flex flex-col gap-3">
                                <p className="text-sm font-medium text-muted-foreground uppercase tracking-wide">
                                    {data.title}
                                </p>
                                <h4 className="text-3xl font-bold tracking-tight break-words">
                                    {data.value}
                                </h4>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
