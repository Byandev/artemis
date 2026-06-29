import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import DashboardFiltersBar from '@/pages/workspaces/creatives/components/dashboard/dashboard-filters';
import {
    AdsStatusSection,
    CreativesCalendarSection,
    KpiCardsSection,
    LeaderboardSection,
    PipelineSection,
    RecentActivitySection,
    RevisionListSection,
    ThroughputSection,
} from '@/pages/workspaces/creatives/components/dashboard/sections';
import {
    ApplyFilter,
    DashboardFilters,
    DashboardPageProps,
} from '@/pages/workspaces/creatives/components/dashboard/types';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

export default function VideoEditorDashboard({
    workspace,
    currentUserId,
    products,
    editors,
    filters: initialFilters,
}: DashboardPageProps) {
    // Edit/list links point at the creatives module.
    const dashboardBase = `/workspaces/${workspace.slug}/video-editor`;
    const creativesBase = `/workspaces/${workspace.slug}/creatives`;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Video Editor Dashboard',
            href: `${dashboardBase}/dashboard`,
        },
    ];

    // Filters live client-side: each section fetches itself from its own API
    // endpoint, so changing a filter (or the chart granularity) updates only
    // the affected sections — no full page reload. Persisted per workspace so
    // they survive a browser refresh.
    const STORAGE_KEY = `video-editor-dashboard-filters:${workspace.slug}`;

    const [filters, setFilters] = useState<DashboardFilters>(() => {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                return { ...initialFilters, ...JSON.parse(saved) };
            }
        } catch {
            // Ignore unavailable / malformed storage.
        }
        return initialFilters;
    });

    useEffect(() => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(filters));
        } catch {
            // Ignore storage errors (quota / private mode).
        }
    }, [STORAGE_KEY, filters]);

    const applyFilter = useCallback<ApplyFilter>((patch) => {
        setFilters((prev) => ({ ...prev, ...patch }));
    }, []);

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
                        editors={editors}
                        currentUserId={currentUserId}
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
                    <div className="lg:col-span-2 lg:h-full">
                        <PipelineSection
                            workspaceSlug={workspace.slug}
                            filters={filters}
                        />
                    </div>
                </div>

                <div className="mt-3">
                    <RevisionListSection
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

                <div className="mt-3">
                    <CreativesCalendarSection
                        workspaceSlug={workspace.slug}
                        filters={filters}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
