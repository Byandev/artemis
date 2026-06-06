import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import ActivityFeed from '@/pages/workspaces/creatives/components/dashboard/activity-feed';
import AdsStatusGrid from '@/pages/workspaces/creatives/components/dashboard/ads-status-grid';
import DashboardFiltersBar from '@/pages/workspaces/creatives/components/dashboard/dashboard-filters';
import GranularityToggle from '@/pages/workspaces/creatives/components/dashboard/granularity-toggle';
import KpiCards from '@/pages/workspaces/creatives/components/dashboard/kpi-cards';
import Leaderboard from '@/pages/workspaces/creatives/components/dashboard/leaderboard';
import Panel from '@/pages/workspaces/creatives/components/dashboard/panel';
import PipelineFunnel from '@/pages/workspaces/creatives/components/dashboard/pipeline-funnel';
import ThroughputChart from '@/pages/workspaces/creatives/components/dashboard/throughput-chart';
import {
    ApplyFilter,
    DashboardPageProps,
} from '@/pages/workspaces/creatives/components/dashboard/types';
import WorkList from '@/pages/workspaces/creatives/components/dashboard/work-list';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    Clock,
    ListChecks,
    MessageSquare,
    Pencil,
    Rocket,
    TrendingUp,
    Trophy,
} from 'lucide-react';
import { useCallback } from 'react';

export default function VideoEditorDashboard({
    workspace,
    currentUserId,
    kpis,
    pipeline,
    revisionList,
    waitingList,
    throughput,
    leaderboard,
    recentActivity,
    products,
    filters,
}: DashboardPageProps) {
    // Filters reload this dashboard; edit/list links point at the creatives module.
    const dashboardBase = `/workspaces/${workspace.slug}/video-editor`;
    const creativesBase = `/workspaces/${workspace.slug}/creatives`;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Video Editor Dashboard',
            href: `${dashboardBase}/dashboard`,
        },
    ];

    const applyFilter = useCallback<ApplyFilter>(
        (patch) => {
            router.get(
                `${dashboardBase}/dashboard`,
                { ...filters, ...patch },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        },
        [dashboardBase, filters],
    );

    const editUrl = useCallback(
        (id: number) => `${creativesBase}/${id}/edit`,
        [creativesBase],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Work" />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="My Work"
                    description="Your creative workload, pipeline, and performance"
                    stackActionsOnMobile
                >
                    <DashboardFiltersBar
                        filters={filters}
                        products={products}
                        onChange={applyFilter}
                        listUrl={creativesBase}
                    />
                </PageHeader>

                <KpiCards kpis={kpis} />

                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
                    <Panel
                        title="Ads Status"
                        icon={<Rocket className="h-3.5 w-3.5 text-gray-400" />}
                    >
                        <AdsStatusGrid ads={kpis.ads} />
                    </Panel>

                    <div className="lg:col-span-2">
                        <Panel
                            title="Pipeline"
                            icon={
                                <ListChecks className="h-3.5 w-3.5 text-gray-400" />
                            }
                        >
                            <PipelineFunnel pipeline={pipeline} />
                        </Panel>
                    </div>
                </div>

                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <Panel
                        title="Needs Revision"
                        icon={<Pencil className="h-3.5 w-3.5 text-amber-500" />}
                        count={revisionList.length}
                    >
                        <WorkList
                            items={revisionList}
                            editUrl={editUrl}
                            emptyText="Nothing waiting on revisions. Nice."
                            showFeedback
                        />
                    </Panel>
                    <Panel
                        title="Waiting for Submission"
                        icon={<Clock className="h-3.5 w-3.5 text-gray-400" />}
                        count={waitingList.length}
                    >
                        <WorkList
                            items={waitingList}
                            editUrl={editUrl}
                            emptyText="No pending submissions."
                        />
                    </Panel>
                </div>

                <div className="mt-3">
                    <Panel
                        title="Output Over Time"
                        icon={
                            <TrendingUp className="h-3.5 w-3.5 text-gray-400" />
                        }
                        action={
                            <GranularityToggle
                                value={filters.group}
                                dateFrom={filters.date_from}
                                dateTo={filters.date_to}
                                onChange={(group) => applyFilter({ group })}
                            />
                        }
                    >
                        <ThroughputChart throughput={throughput} />
                    </Panel>
                </div>

                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <Panel
                        title="Editor Leaderboard"
                        icon={<Trophy className="h-3.5 w-3.5 text-amber-500" />}
                    >
                        <Leaderboard
                            rows={leaderboard}
                            currentUserId={currentUserId}
                        />
                    </Panel>
                    <Panel
                        title="Recent Activity"
                        icon={
                            <MessageSquare className="h-3.5 w-3.5 text-gray-400" />
                        }
                    >
                        <ActivityFeed rows={recentActivity} editUrl={editUrl} />
                    </Panel>
                </div>
            </div>
        </AppLayout>
    );
}
