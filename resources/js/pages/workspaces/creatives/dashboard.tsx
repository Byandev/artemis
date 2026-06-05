import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
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
import ActivityFeed from './components/dashboard/activity-feed';
import AdsStatusGrid from './components/dashboard/ads-status-grid';
import DashboardFiltersBar from './components/dashboard/dashboard-filters';
import KpiCards from './components/dashboard/kpi-cards';
import Leaderboard from './components/dashboard/leaderboard';
import Panel from './components/dashboard/panel';
import PipelineFunnel from './components/dashboard/pipeline-funnel';
import ThroughputChart from './components/dashboard/throughput-chart';
import { ApplyFilter, DashboardPageProps } from './components/dashboard/types';
import WorkList from './components/dashboard/work-list';

export default function CreativesDashboard({
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
    const base = `/workspaces/${workspace.slug}/creatives`;

    const applyFilter = useCallback<ApplyFilter>(
        (patch) => {
            router.get(
                `${base}/dashboard`,
                { ...filters, ...patch },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        },
        [base, filters],
    );

    const editUrl = useCallback((id: number) => `${base}/${id}/edit`, [base]);

    return (
        <AppLayout>
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
                        listUrl={base}
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
