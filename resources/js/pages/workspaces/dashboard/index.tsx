import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { Workspace } from '@/types/models/Workspace';
import { currencyFormatter, numberFormatter, percentageFormatter } from '@/lib/utils';

type Props = {
    workspace: Workspace;
    userWorkspaces: Workspace[];
    stats: {
        total_sales: number;
        total_orders: number;
        rts_rate_percentage: number;
        tracked_orders: number;
    }
}

export default function Index({ workspace, stats, userWorkspaces }: Props) {
    const analytics = useMemo(() => {
        return [
            { title: 'TOTAL SALES', value: currencyFormatter(stats.total_sales) },
            { title: 'TOTAL ORDERS', value: numberFormatter(stats.total_orders) },
            { title: 'RTS Rate', value: percentageFormatter(stats.rts_rate_percentage / 100) },
            { title: 'TRACKED ORDERS', value: numberFormatter(stats.tracked_orders) },
        ]
    }, [stats])

    return (
        <AppLayout workspaces={userWorkspaces} currentWorkspace={workspace}>
            <Head title="Index" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-4">
                    {
                        analytics.map((data, key) => {
                            return <div
                                className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {data.title}
                                </p>

                                <div className="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 className="text-2xl font-bold text-gray-800 dark:text-white/90">
                                            {data.value}
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            return <div key={key}
                                        className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                                {/*<PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />*/}
                                <h3 className="font-bold text-2xl">{data.value}</h3>
                                <h3>{data.title}</h3>
                            </div>
                        })
                    }
                </div>
                {/*<div className="relative min-h-[100vh] flex-1 space-y-6">*/}
                {/*    <SalesChart />*/}

                {/*    <RTSChart />*/}
                {/*</div>*/}
            </div>
        </AppLayout>
    );
}
