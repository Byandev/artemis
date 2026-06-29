import axios from 'axios';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
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
import moment from 'moment';
import { ReactNode, useEffect, useState } from 'react';
import ActivityFeed from './activity-feed';
import AdsStatusGrid from './ads-status-grid';
import CreativesCalendar from './creatives-calendar';
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
    CalendarData,
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

/** Query params sent to every section endpoint (arrays for multi-select). */
function sectionParams(filters: DashboardFilters) {
    const { date_from, date_to, product_ids, user_ids, formats, group } =
        filters;
    return { date_from, date_to, product_ids, user_ids, formats, group };
}

/**
 * Refetch key for data sections. Excludes `group` (chart granularity) so that
 * toggling the throughput chart's daily/weekly view does NOT refetch every
 * other section — only the chart itself depends on `group`.
 */
function sectionKey(filters: DashboardFilters) {
    const { date_from, date_to, product_ids, user_ids, formats } = filters;
    return JSON.stringify({
        date_from,
        date_to,
        product_ids,
        user_ids,
        formats,
    });
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

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);

        axios
            .get<T>(
                `/api/workspaces/${workspaceSlug}/video-editor/${endpoint}`,
                {
                    params: sectionParams(filters),
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [workspaceSlug, endpoint, sectionKey(filters)]);

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

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<AdsBreakdown>(
                `/api/workspaces/${workspaceSlug}/video-editor/ads`,
                {
                    params: sectionParams(filters),
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [workspaceSlug, sectionKey(filters)]);

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

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<Pipeline>(
                `/api/workspaces/${workspaceSlug}/video-editor/pipeline`,
                {
                    params: sectionParams(filters),
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [workspaceSlug, sectionKey(filters)]);

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

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<WorkItem[]>(
                `/api/workspaces/${workspaceSlug}/video-editor/revision-list`,
                {
                    params: sectionParams(filters),
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [workspaceSlug, sectionKey(filters)]);

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

/** Output-over-time throughput chart with a granularity toggle. */
export function ThroughputSection({
    workspaceSlug,
    filters,
    onGroupChange,
}: SectionProps & { onGroupChange: (group: string) => void }) {
    const [data, setData] = useState<Throughput | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<Throughput>(
                `/api/workspaces/${workspaceSlug}/video-editor/throughput`,
                {
                    params: sectionParams(filters),
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [workspaceSlug, sectionKey(filters), filters.group]);

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

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<LeaderRow[]>(
                `/api/workspaces/${workspaceSlug}/video-editor/leaderboard`,
                {
                    params: sectionParams(filters),
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [workspaceSlug, sectionKey(filters)]);

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

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<ActivityRow[]>(
                `/api/workspaces/${workspaceSlug}/video-editor/recent-activity`,
                {
                    params: sectionParams(filters),
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [workspaceSlug, sectionKey(filters)]);

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

/**
 * Calendar of creative-creation activity: who created creatives each day and
 * how many. Has its own month navigation (independent of the dashboard date
 * range); the product / format / user filters still apply.
 */
export function CreativesCalendarSection({
    workspaceSlug,
    filters,
}: SectionProps) {
    const currentMonth = moment().format('YYYY-MM');
    const [month, setMonth] = useState(currentMonth);
    const [data, setData] = useState<CalendarData | null>(null);
    const [loading, setLoading] = useState(true);

    const monthStart = moment(`${month}-01`)
        .startOf('month')
        .format('YYYY-MM-DD');
    const monthEnd = moment(`${month}-01`).endOf('month').format('YYYY-MM-DD');

    // Refetch when the month or the non-date filters change.
    const filterKey = JSON.stringify({
        product_ids: filters.product_ids,
        user_ids: filters.user_ids,
        formats: filters.formats,
    });

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        axios
            .get<CalendarData>(
                `/api/workspaces/${workspaceSlug}/video-editor/calendar`,
                {
                    params: {
                        ...sectionParams(filters),
                        date_from: monthStart,
                        date_to: monthEnd,
                    },
                    signal: controller.signal,
                },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (!axios.isCancel(err)) console.error(err);
            })
            .finally(() => setLoading(false));
        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [workspaceSlug, monthStart, monthEnd, filterKey]);

    const shiftMonth = (delta: number) =>
        setMonth(moment(`${month}-01`).add(delta, 'month').format('YYYY-MM'));

    return (
        <Panel
            title="Creatives Calendar"
            icon={<CalendarDays className="h-3.5 w-3.5 text-gray-400" />}
            action={
                <div className="flex items-center gap-1">
                    <button
                        type="button"
                        onClick={() => shiftMonth(-1)}
                        aria-label="Previous month"
                        className="inline-flex h-7 w-7 items-center justify-center rounded-[8px] border border-black/8 bg-white text-gray-400 transition-colors hover:border-black/14 hover:text-gray-600 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-500 dark:hover:text-gray-300"
                    >
                        <ChevronLeft className="h-3.5 w-3.5" />
                    </button>
                    <span className="min-w-28 text-center text-[12px] font-medium text-gray-700 dark:text-gray-200">
                        {moment(`${month}-01`).format('MMMM YYYY')}
                    </span>
                    <button
                        type="button"
                        onClick={() => shiftMonth(1)}
                        aria-label="Next month"
                        className="inline-flex h-7 w-7 items-center justify-center rounded-[8px] border border-black/8 bg-white text-gray-400 transition-colors hover:border-black/14 hover:text-gray-600 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-500 dark:hover:text-gray-300"
                    >
                        <ChevronRight className="h-3.5 w-3.5" />
                    </button>
                    {month !== currentMonth && (
                        <button
                            type="button"
                            onClick={() => setMonth(currentMonth)}
                            className="ml-1 rounded-[8px] border border-black/8 px-2 py-1 text-[11px] font-medium text-gray-500 transition-colors hover:border-black/14 hover:text-gray-700 dark:border-white/8 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            Today
                        </button>
                    )}
                </div>
            }
        >
            {loading || !data ? (
                <div className="py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    Loading calendar…
                </div>
            ) : (
                <CreativesCalendar data={data} />
            )}
        </Panel>
    );
}
