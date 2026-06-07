import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import DashboardFiltersBar from '@/pages/workspaces/creatives/components/dashboard/dashboard-filters';
import {
    AdsStatusSection,
    KpiCardsSection,
    LeaderboardSection,
    PipelineSection,
    RecentActivitySection,
    RevisionListSection,
    ThroughputSection,
    WaitingListSection,
} from '@/pages/workspaces/creatives/components/dashboard/sections';
import {
    ApplyFilter,
    DashboardPageProps,
} from '@/pages/workspaces/creatives/components/dashboard/types';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useCallback } from 'react';

export default function VideoEditorDashboard({
    workspace,
    currentUserId,
    products,
    filters,
}: DashboardPageProps) {
    // Filters reload the shell; edit/list links point at the creatives module.
    // Each statistic below fetches itself from its own API endpoint.
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

                <KpiCardsSection
                    workspaceSlug={workspace.slug}
                    filters={filters}
                />

                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
                    <AdsStatusSection
                        workspaceSlug={workspace.slug}
                        filters={filters}
                    />
                    <div className="lg:col-span-2">
                        <PipelineSection
                            workspaceSlug={workspace.slug}
                            filters={filters}
                        />
                    </div>
                </div>

                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <RevisionListSection
                        workspaceSlug={workspace.slug}
                        filters={filters}
                        editUrl={editUrl}
                    />
                    <WaitingListSection
                        workspaceSlug={workspace.slug}
                        filters={filters}
                        editUrl={editUrl}
                    />
                </div>

                <div className="mt-3">
                    <ThroughputSection
                        workspaceSlug={workspace.slug}
                        filters={filters}
                        onGroupChange={(group) => applyFilter({ group })}
                    />
                </div>

                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <LeaderboardSection
                        workspaceSlug={workspace.slug}
                        filters={filters}
                        currentUserId={currentUserId}
                    />
                    <RecentActivitySection
                        workspaceSlug={workspace.slug}
                        filters={filters}
                        editUrl={editUrl}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
