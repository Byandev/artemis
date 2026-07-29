import ActivityLogView from '@/components/activity-logs/activity-log-view';
import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { PaginatedData, type BreadcrumbItem } from '@/types';
import { ActivityLog, ActivityLogSummary } from '@/types/models/ActivityLog';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';

interface Props {
    workspace: Workspace;
    logs: PaginatedData<ActivityLog>;
    summary: ActivityLogSummary;
    options: {
        log_types: string[];
        statuses: string[];
        categories: string[];
        trigger_types: string[];
    };
    filters?: Record<string, string | null>;
    query?: {
        sort?: string | null;
        per_page?: number | string;
        page?: number | string;
    };
}

export default function WorkspaceActivityLogsIndex({
    workspace,
    logs,
    summary,
    options,
    filters,
    query,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/activity-logs`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Activity logs', href: baseUrl },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${workspace.name} - Activity Logs`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Activity Logs"
                    description="Audit trail of actions and system events in this workspace."
                />
                <div className="mt-6">
                    <ActivityLogView
                        logs={logs}
                        summary={summary}
                        options={options}
                        filters={filters}
                        query={query}
                        baseUrl={baseUrl}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
