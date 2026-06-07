import axios from 'axios';
import {
    Clapperboard,
    Clock,
    FileImage,
    LayoutGrid,
    ListChecks,
    type LucideIcon,
    MessageSquare,
    Pencil,
    Rocket,
    TrendingUp,
    Trophy,
} from 'lucide-react';
import { ReactNode, useEffect, useState } from 'react';
import ActivityFeed from './activity-feed';
import AdsStatusGrid from './ads-status-grid';
import GranularityToggle from './granularity-toggle';
import { KpiCard, KpiCardSkeleton } from './kpi-card';
import Leaderboard from './leaderboard';
import Panel from './panel';
import PipelineFunnel from './pipeline-funnel';
import {
    ActivitySkeleton,
    AdsStatusSkeleton,
    LeaderboardSkeleton,
    PipelineSkeleton,
    ThroughputSkeleton,
    WorkListSkeleton,
} from './section-skeletons';
import ThroughputChart from './throughput-chart';
import {
    ActivityRow,
    AdsBreakdown,
    ApprovedStat,
    CountStat,
    DashboardFilters,
    EditUrl,
    LeaderRow,
    Pipeline,
    Throughput,
    TotalCreativesStat,
    WorkItem,
} from './types';
import WorkList from './work-list';

/**
 * Self-contained dashboard sections. Each one fetches its own statistic from
 * its API endpoint and renders its own skeleton while loading — mirroring the
 * main dashboard's StatisticCard / *Breakdown components. The parent page just
 * places them; it owns no fetch state.
 */

interface SectionProps {
    workspaceSlug: string;
    filters: DashboardFilters;
}

// ─── KPI cards (one endpoint per card) ───────────────────────────────────────

/**
 * Generic self-fetching KPI card, reused for each metric the same way the main
 * dashboard reuses StatisticCard. Hits its own endpoint and shows a skeleton
 * until the value arrives.
 */
function KpiStatCard<T extends CountStat>({
    workspaceSlug,
    filters,
    endpoint,
    label,
    icon,
    accentDot,
    renderSub,
}: SectionProps & {
    endpoint: string;
    label: string;
    icon: LucideIcon;
    accentDot?: string;
    renderSub?: (data: T) => ReactNode;
}) {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);
    const { date_from, date_to, product_id, format, group } = filters;

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);

        axios
            .get<T>(
                `/api/workspaces/${workspaceSlug}/video-editor/${endpoint}`,
                {
                    params: { date_from, date_to, product_id, format, group },
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [
        workspaceSlug,
        endpoint,
        date_from,
        date_to,
        product_id,
        format,
        group,
    ]);

    if (loading || !data) return <KpiCardSkeleton label={label} icon={icon} />;

    return (
        <KpiCard
            icon={icon}
            label={label}
            value={data.value}
            accentDot={accentDot}
            sub={renderSub?.(data)}
        />
    );
}

/** Top KPI card row — each card fetches its own endpoint independently. */
export function KpiCardsSection({ workspaceSlug, filters }: SectionProps) {
    return (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <KpiStatCard<TotalCreativesStat>
                workspaceSlug={workspaceSlug}
                filters={filters}
                endpoint="kpi/total"
                label="Total Creatives"
                icon={LayoutGrid}
                renderSub={(d) => (
                    <span className="flex items-center gap-3">
                        <span className="flex items-center gap-1">
                            <Clapperboard className="h-3 w-3" /> {d.video}
                        </span>
                        <span className="flex items-center gap-1">
                            <FileImage className="h-3 w-3" /> {d.image}
                        </span>
                    </span>
                )}
            />
            <KpiStatCard
                workspaceSlug={workspaceSlug}
                filters={filters}
                endpoint="kpi/awaiting-review"
                label="Awaiting Review"
                icon={Clock}
                accentDot="bg-blue-500"
                renderSub={() => 'for approval / re-approval'}
            />
            <KpiStatCard
                workspaceSlug={workspaceSlug}
                filters={filters}
                endpoint="kpi/needs-revision"
                label="Needs Revision"
                icon={Pencil}
                accentDot="bg-amber-500"
                renderSub={() => 'your action queue'}
            />
            <KpiStatCard<ApprovedStat>
                workspaceSlug={workspaceSlug}
                filters={filters}
                endpoint="kpi/approved"
                label="Approved"
                icon={TrendingUp}
                accentDot="bg-emerald-500"
                renderSub={(d) => `${d.approval_rate}% approval rate`}
            />
        </div>
    );
}

// ─── Panels ──────────────────────────────────────────────────────────────────

/** Ads status grid. */
export function AdsStatusSection({ workspaceSlug, filters }: SectionProps) {
    const [data, setData] = useState<AdsBreakdown | null>(null);
    const [loading, setLoading] = useState(true);
    const { date_from, date_to, product_id, format, group } = filters;

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<AdsBreakdown>(
                `/api/workspaces/${workspaceSlug}/video-editor/ads`,
                {
                    params: { date_from, date_to, product_id, format, group },
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
    }, [workspaceSlug, date_from, date_to, product_id, format, group]);

    return (
        <Panel
            title="Ads Status"
            icon={<Rocket className="h-3.5 w-3.5 text-gray-400" />}
        >
            {loading || !data ? (
                <AdsStatusSkeleton />
            ) : (
                <AdsStatusGrid ads={data} />
            )}
        </Panel>
    );
}

/** Pipeline funnel. */
export function PipelineSection({ workspaceSlug, filters }: SectionProps) {
    const [data, setData] = useState<Pipeline | null>(null);
    const [loading, setLoading] = useState(true);
    const { date_from, date_to, product_id, format, group } = filters;

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<Pipeline>(
                `/api/workspaces/${workspaceSlug}/video-editor/pipeline`,
                {
                    params: { date_from, date_to, product_id, format, group },
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
    }, [workspaceSlug, date_from, date_to, product_id, format, group]);

    return (
        <Panel
            title="Pipeline"
            icon={<ListChecks className="h-3.5 w-3.5 text-gray-400" />}
        >
            {loading || !data ? (
                <PipelineSkeleton />
            ) : (
                <PipelineFunnel pipeline={data} />
            )}
        </Panel>
    );
}

/** Needs-revision work list. */
export function RevisionListSection({
    workspaceSlug,
    filters,
    editUrl,
}: SectionProps & { editUrl: EditUrl }) {
    const [data, setData] = useState<WorkItem[] | null>(null);
    const [loading, setLoading] = useState(true);
    const { date_from, date_to, product_id, format, group } = filters;

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<WorkItem[]>(
                `/api/workspaces/${workspaceSlug}/video-editor/revision-list`,
                {
                    params: { date_from, date_to, product_id, format, group },
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
    }, [workspaceSlug, date_from, date_to, product_id, format, group]);

    return (
        <Panel
            title="Needs Revision"
            icon={<Pencil className="h-3.5 w-3.5 text-amber-500" />}
            count={data?.length}
        >
            {loading || !data ? (
                <WorkListSkeleton />
            ) : (
                <WorkList
                    items={data}
                    editUrl={editUrl}
                    emptyText="Nothing waiting on revisions. Nice."
                    showFeedback
                />
            )}
        </Panel>
    );
}

/** Waiting-for-submission work list. */
export function WaitingListSection({
    workspaceSlug,
    filters,
    editUrl,
}: SectionProps & { editUrl: EditUrl }) {
    const [data, setData] = useState<WorkItem[] | null>(null);
    const [loading, setLoading] = useState(true);
    const { date_from, date_to, product_id, format, group } = filters;

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<WorkItem[]>(
                `/api/workspaces/${workspaceSlug}/video-editor/waiting-list`,
                {
                    params: { date_from, date_to, product_id, format, group },
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
    }, [workspaceSlug, date_from, date_to, product_id, format, group]);

    return (
        <Panel
            title="Waiting for Submission"
            icon={<Clock className="h-3.5 w-3.5 text-gray-400" />}
            count={data?.length}
        >
            {loading || !data ? (
                <WorkListSkeleton />
            ) : (
                <WorkList
                    items={data}
                    editUrl={editUrl}
                    emptyText="No pending submissions."
                />
            )}
        </Panel>
    );
}

/** Output-over-time throughput chart with a granularity toggle. */
export function ThroughputSection({
    workspaceSlug,
    filters,
    onGroupChange,
}: SectionProps & { onGroupChange: (group: string) => void }) {
    const [data, setData] = useState<Throughput | null>(null);
    const [loading, setLoading] = useState(true);
    const { date_from, date_to, product_id, format, group } = filters;

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<Throughput>(
                `/api/workspaces/${workspaceSlug}/video-editor/throughput`,
                {
                    params: { date_from, date_to, product_id, format, group },
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
    }, [workspaceSlug, date_from, date_to, product_id, format, group]);

    return (
        <Panel
            title="Output Over Time"
            icon={<TrendingUp className="h-3.5 w-3.5 text-gray-400" />}
            action={
                <GranularityToggle
                    value={filters.group}
                    dateFrom={filters.date_from}
                    dateTo={filters.date_to}
                    onChange={onGroupChange}
                />
            }
        >
            {loading || !data ? (
                <ThroughputSkeleton />
            ) : (
                <ThroughputChart throughput={data} />
            )}
        </Panel>
    );
}

/** Editor leaderboard (ignores the editor scope). */
export function LeaderboardSection({
    workspaceSlug,
    filters,
    currentUserId,
}: SectionProps & { currentUserId: number }) {
    const [data, setData] = useState<LeaderRow[] | null>(null);
    const [loading, setLoading] = useState(true);
    const { date_from, date_to, product_id, format, group } = filters;

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<LeaderRow[]>(
                `/api/workspaces/${workspaceSlug}/video-editor/leaderboard`,
                {
                    params: { date_from, date_to, product_id, format, group },
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
    }, [workspaceSlug, date_from, date_to, product_id, format, group]);

    return (
        <Panel
            title="Editor Leaderboard"
            icon={<Trophy className="h-3.5 w-3.5 text-amber-500" />}
        >
            {loading || !data ? (
                <LeaderboardSkeleton />
            ) : (
                <Leaderboard rows={data} currentUserId={currentUserId} />
            )}
        </Panel>
    );
}

/** Recent review activity feed. */
export function RecentActivitySection({
    workspaceSlug,
    filters,
    editUrl,
}: SectionProps & { editUrl: EditUrl }) {
    const [data, setData] = useState<ActivityRow[] | null>(null);
    const [loading, setLoading] = useState(true);
    const { date_from, date_to, product_id, format, group } = filters;

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<ActivityRow[]>(
                `/api/workspaces/${workspaceSlug}/video-editor/recent-activity`,
                {
                    params: { date_from, date_to, product_id, format, group },
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
    }, [workspaceSlug, date_from, date_to, product_id, format, group]);

    return (
        <Panel
            title="Recent Activity"
            icon={<MessageSquare className="h-3.5 w-3.5 text-gray-400" />}
        >
            {loading || !data ? (
                <ActivitySkeleton />
            ) : (
                <ActivityFeed rows={data} editUrl={editUrl} />
            )}
        </Panel>
    );
}
