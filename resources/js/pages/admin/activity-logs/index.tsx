import ActivityLogView from '@/components/activity-logs/activity-log-view';
import PageHeader from '@/components/common/PageHeader';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { PaginatedData } from '@/types';
import {
    ActivityLog,
    ActivityLogSummary,
} from '@/types/models/ActivityLog';
import { Head } from '@inertiajs/react';

interface Props {
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

export default function AdminActivityLogsIndex({
    logs,
    summary,
    options,
    filters,
    query,
}: Props) {
    return (
        <AdminSidebarLayout>
            <Head title="Admin | Activity Logs" />
            <div className="p-4 md:p-6">
                <PageHeader
                    title="Activity Logs"
                    description="Unified audit trail and system events across all workspaces."
                />
                <div className="mt-6">
                    <ActivityLogView
                        logs={logs}
                        summary={summary}
                        options={options}
                        filters={filters}
                        query={query}
                        baseUrl="/admin/activity-logs"
                        showWorkspace
                        showMetadata
                    />
                </div>
            </div>
        </AdminSidebarLayout>
    );
}
