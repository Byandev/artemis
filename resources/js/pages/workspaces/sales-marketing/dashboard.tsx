import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';

interface Props {
    workspace: {
        id: number;
        name: string;
        slug: string;
    };
}

export default function SalesMarketingDashboard({ workspace }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'S&M Dashboard',
            href: `/workspaces/${workspace.slug}/sales-marketing/dashboard`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="S&M Dashboard" />

            <div className="p-4 sm:p-6">
                <PageHeader
                    title="S&M Dashboard"
                    description="Sales and marketing performance for this workspace."
                />

                <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-black/10 py-20 text-center dark:border-white/10">
                    <Megaphone className="mb-3 h-8 w-8 text-gray-300 dark:text-gray-600" />
                    <p className="text-sm font-medium text-gray-600 dark:text-gray-300">
                        Nothing here yet
                    </p>
                    <p className="mt-1 max-w-sm text-xs text-gray-400 dark:text-gray-500">
                        This dashboard is set up and ready. Metrics and charts
                        will be added soon.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
