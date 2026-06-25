import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { BarChart3, Plus } from 'lucide-react';
import { createReport, ReportsSidebar } from './components/ReportsSidebar';
import { type ReportListItem, reportsUrl } from './types';

interface Props {
    workspace: { id: number; name: string; slug: string };
    reports: ReportListItem[];
    archivedReports: ReportListItem[];
}

export default function ReportsIndex({
    workspace,
    reports,
    archivedReports,
}: Props) {
    const baseUrl = reportsUrl(workspace.slug);
    const backUrl = `/workspaces/${workspace.slug}/integrations/meta/ads-manager`;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Meta Ads', href: backUrl },
        { title: 'Reports', href: baseUrl },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />

            <div className="flex h-[calc(100dvh-5rem)] overflow-hidden">
                <ReportsSidebar
                    reports={reports}
                    archivedReports={archivedReports}
                    activeId={null}
                    baseUrl={baseUrl}
                />

                {/* Empty preview until the user picks a report from the sidebar. */}
                <div className="flex flex-1 items-center justify-center overflow-y-auto p-6">
                    <div className="flex max-w-sm flex-col items-center text-center">
                        <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <BarChart3 className="h-6 w-6" />
                        </div>
                        <p className="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {reports.length === 0
                                ? 'No reports yet'
                                : 'Select a report'}
                        </p>
                        <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                            {reports.length === 0
                                ? 'Create a report to explore your top-performing ads and creative with custom breakdowns and metrics.'
                                : 'Choose a report from the sidebar to preview it, or create a new one.'}
                        </p>
                        <Button
                            className="mt-5"
                            size="sm"
                            onClick={() => createReport(baseUrl)}
                        >
                            <Plus className="mr-1 h-4 w-4" />
                            {reports.length === 0
                                ? 'Create your first report'
                                : 'New report'}
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
