import {
    index as logsIndex,
    summary as logsSummary,
} from '@/actions/App/Http/Controllers/API/Admin/ActivityLogController';
import ActivityLogView from '@/components/activity-logs/activity-log-view';
import PageHeader from '@/components/common/PageHeader';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { Head } from '@inertiajs/react';

interface Props {
    options: {
        log_types: string[];
        statuses: string[];
        categories: string[];
        trigger_types: string[];
    };
    filters?: Record<string, string | null>;
    query?: {
        sort?: string | null;
        per_page?: number | string | null;
        page?: number | string;
    };
}

export default function AdminActivityLogsIndex({
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
                        options={options}
                        filters={filters}
                        query={query}
                        logsUrl={logsIndex.url()}
                        summaryUrl={logsSummary.url()}
                        showWorkspace
                        showMetadata
                    />
                </div>
            </div>
        </AdminSidebarLayout>
    );
}
