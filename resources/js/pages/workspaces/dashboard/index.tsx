import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { Workspace } from '@/types/models/Workspace';
import SalesChart from '@/pages/workspaces/dashboard/partials/SalesChart';
import RTSChart from '@/pages/workspaces/dashboard/partials/RTSChart';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Index',
        href: dashboard().url,
    },
];

type Props = {
    workspace: Workspace;
    stats: {
        total_sales: number;
        total_orders: number;
        rts_rate_percentage: number;
        tracked_orders: number;
    }
}

export default function Index({ stats }: Props) {
    const analytics = useMemo(() => {
        return [
            { title: 'TOTAL SALES', value: stats.total_sales },
            { title: 'TOTAL ORDERS', value: stats.total_orders },
            { title: 'RTS Rate', value: stats.rts_rate_percentage },
            { title: 'TRACKED ORDERS', value: stats.tracked_orders },
        ]
    }, [stats])

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Index" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-4">
                    {
                        analytics.map((data, key) => {
                            return <div key={key} className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                {/*<PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />*/}
                                <h3>{data.value}</h3>
                                <h3>{data.title}</h3>
                            </div>
                        })
                    }
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <SalesChart/>

                    {/*<RTSChart/>*/}
                </div>
            </div>
        </AppLayout>
    );
}
