import PageHeader from '@/components/common/PageHeader';
import {
    HighestRmoCalledLeaderCard,
    HighestRmoDurationLeaderCard,
    HighestSalesLeaderCard,
    LowestRtsLeaderCard,
    type LeaderResponse,
    type RmoCalledLeader,
    type RmoDurationLeader,
    type RtsLeader,
    type SalesLeader,
} from '@/components/csr/CsrAnalyticsLeaderCards';
import {
    CallsPlacedStatCard,
    LongestCallStatCard,
    ReachRateStatCard,
    RealConversationsStatCard,
    RmoCallTimeStatCard,
    RmoCalledStatCard,
    RmoHitRateStatCard,
    RmoRealConversationsStatCard,
    RmoTimeStatCard,
    RtsStatCard,
    SalesStatCard,
    TotalRmoCalledStatCard,
    type CallsPlacedStat,
    type LongestCallStat,
    type ReachRateStat,
    type RealConversationsStat,
    type RmoCallTimeStat,
    type RmoCalledStat,
    type RmoHitRateStat,
    type RmoRealConversationsStat,
    type RmoTimeStat,
    type RtsStat,
    type SalesStat,
    type TotalRmoCalledStat,
} from '@/components/csr/CsrAnalyticsStatCards';
import CsrCallMixChart, {
    type CallMixGranularity,
    type CallMixResponse,
} from '@/components/csr/CsrCallMixChart';
import CsrComparisonPanel, {
    type ComparisonResponse,
} from '@/components/csr/CsrComparisonPanel';
import CsrDailyCallOutcomesTable, {
    type DailyCallOutcomesResponse,
} from '@/components/csr/CsrDailyCallOutcomesTable';
import CsrDailyEffortChart, {
    type DailyEffortResponse,
} from '@/components/csr/CsrDailyEffortChart';
import {
    ColumnsDropdown,
    useColumnVisibility,
    type ColumnOption,
} from '@/components/ui/columns-dropdown';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { cn } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { eachDayOfInterval, format, parseISO, subDays } from 'date-fns';
import { omit } from 'lodash';
import { useEffect, useMemo, useRef, useState } from 'react';

/**
 * One CSR's row in the breakdown.
 *
 * Every metric on it is a column of `pancake_user_pos_daily_reports` or
 * `pancake_user_daily_call_reports`, summed over the range and carrying the
 * name the column itself has — `total_called` is the call report's
 * `total_called`, not RMO's share of it. `rts_rate` and `rmo_percentage` are
 * the two the server derives, so they can be sorted like any other.
 */
interface CsrRecord {
    id: number;
    name: string;
    pancake_user_id: string;

    // pancake_user_pos_daily_reports
    total_orders: number;
    total_sales: number;
    total_delivered: number;
    delivered_count: number;
    total_returning: number;
    returning_count: number;
    rts_rate: number;

    // pancake_user_daily_call_reports
    total_called: number;
    total_call_time: number;
    total_rmo_called: number;
    total_rmo_call_time: number;
    total_rmo_connected_called: number;
    total_rmo_real_called: number;
    longest_rmo_call_time: number;
    total_rmo_customer_called: number;
    total_rmo_customer_call_time: number;
    total_rmo_rider_called: number;
    total_rmo_rider_call_time: number;
    total_rmo_assigned_count: number;
    total_rmo_confirmed_count: number;
    total_verification_called: number;
    total_verification_call_time: number;

    rmo_percentage: number;
}

interface Props {
    workspace: Workspace;
    records: PaginatedData<CsrRecord>;
    query?: {
        sort?: string | null;
        from?: string | null;
        to?: string | null;
        page?: number | string;
        per_page?: number | string;
        type?: 'erp' | 'pos' | null;
        search?: string | null;
        /** Which CSR comparison tab to open on — one of the metric keys. */
        comparison?: string | null;
    };
}

const peso = (n: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(Number(n) || 0);

const formatCallTime = (seconds: number) => {
    const s = Math.max(0, Math.floor(Number(seconds) || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = (n: number) => n.toString().padStart(2, '0');
    return h > 0 ? `${h}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
};
/** Stable default, so the hook's query string does not change every render. */
const NO_EXTRA_PARAMS: Record<string, string> = {};

/**
 * One CSR analytics stat card's figures.
 *
 * Each card has its own endpoint, so each gets its own request and its own
 * loading flag. The AbortController is what keeps a fast sequence of date
 * changes from landing out of order — the last range picked is the one shown.
 */
function useAnalyticsStat<T>(
    workspaceSlug: string,
    stat: string,
    from: string,
    to: string,
    extraParams: Record<string, string> = NO_EXTRA_PARAMS,
): [T | null, boolean] {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);

    // A string, so a caller passing a fresh object literal each render does not
    // re-fire the request; the effect keys on what the query actually says.
    const search = new URLSearchParams({ from, to, ...extraParams }).toString();

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);

        fetch(`/api/workspaces/${workspaceSlug}/csrs/stats/${stat}?${search}`, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((response) => {
                if (!response.ok) throw new Error(String(response.status));
                return response.json();
            })
            .then((payload: T) => {
                setData(payload);
                setLoading(false);
            })
            .catch((error: Error) => {
                // An abort is this effect superseding itself — the newer request
                // owns the loading flag now.
                if (error.name === 'AbortError') return;
                setLoading(false);
            });

        return () => controller.abort();
    }, [workspaceSlug, stat, search]);

    return [data, loading];
}

// Pinning the header is done from this page rather than inside the shared
// DataTable: the classes below are stamped onto this table's columns and its
// scroll container, so no other table's layout moves. The surface is repainted
// on the pinned cells (the body would otherwise show through) and the rule is
// drawn as an inset shadow — a collapsed table drops the border of a sticky
// cell once it starts to scroll.
const STICKY_HEADER_CELL =
    'sticky top-0 z-20 bg-white shadow-[inset_0_-1px_0_rgba(0,0,0,0.06)] dark:bg-zinc-900 dark:shadow-[inset_0_-1px_0_rgba(255,255,255,0.06)]';

// The body has to scroll for a header to stick to anything, so the container
// DataTable renders is capped here. The page then stays put while a long table
// is read, instead of the columns scrolling out of sight.
/** How a metric column's raw figure is drawn. */
type CsrFormat = 'number' | 'peso' | 'percent' | 'duration' | 'rmoPercent';

interface CsrMetricColumn {
    /** The column's own name — also the sort key the server accepts. */
    id: Exclude<keyof CsrRecord, 'id' | 'name' | 'pancake_user_id'>;
    label: string;
    format: CsrFormat;
    /** Heading it sits under in the Columns menu. */
    group: string;
    /** Off until switched on. The rollups carry far more than fits a screen. */
    hidden?: boolean;
}

/**
 * Every metric the two rollups carry, in the order the table lays them out.
 *
 * The ones without `hidden` are the columns the table has always shown, so a
 * first visit looks exactly as it did before the rest became available. A
 * hidden column takes its declared place the moment it is switched on rather
 * than being appended at the end.
 */
const CSR_METRIC_COLUMNS: CsrMetricColumn[] = [
    // pancake_user_pos_daily_reports
    {
        id: 'total_orders',
        label: 'Orders',
        format: 'number',
        group: 'Sales & delivery',
    },
    {
        id: 'total_sales',
        label: 'Sales',
        format: 'peso',
        group: 'Sales & delivery',
    },
    {
        id: 'total_delivered',
        label: 'Delivered',
        format: 'peso',
        group: 'Sales & delivery',
    },
    {
        id: 'delivered_count',
        label: 'Delivered Parcels',
        format: 'number',
        group: 'Sales & delivery',
        hidden: true,
    },
    {
        id: 'total_returning',
        label: 'Returning',
        format: 'peso',
        group: 'Sales & delivery',
    },
    {
        id: 'returning_count',
        label: 'Returning Parcels',
        format: 'number',
        group: 'Sales & delivery',
        hidden: true,
    },
    {
        id: 'rts_rate',
        label: 'RTS Rate',
        format: 'percent',
        group: 'Sales & delivery',
    },

    // pancake_user_daily_call_reports — the RMO side
    {
        id: 'total_rmo_confirmed_count',
        label: 'RMO Confirmed',
        format: 'number',
        group: 'RMO',
    },
    {
        id: 'total_rmo_assigned_count',
        label: 'RMO Assigned',
        format: 'number',
        group: 'RMO',
    },
    {
        id: 'total_rmo_called',
        label: 'RMO Called',
        format: 'number',
        group: 'RMO',
    },
    {
        id: 'rmo_percentage',
        label: 'RMO %',
        format: 'rmoPercent',
        group: 'RMO',
    },
    {
        id: 'total_rmo_call_time',
        label: 'RMO Call Time',
        format: 'duration',
        group: 'RMO',
    },
    {
        id: 'total_rmo_connected_called',
        label: 'RMO Connected',
        format: 'number',
        group: 'RMO',
        hidden: true,
    },
    {
        id: 'total_rmo_real_called',
        label: 'RMO Real Conversations',
        format: 'number',
        group: 'RMO',
        hidden: true,
    },
    {
        id: 'longest_rmo_call_time',
        label: 'Longest RMO Call',
        format: 'duration',
        group: 'RMO',
        hidden: true,
    },
    {
        id: 'total_rmo_customer_called',
        label: 'RMO Customer Called',
        format: 'number',
        group: 'RMO',
        hidden: true,
    },
    {
        id: 'total_rmo_customer_call_time',
        label: 'RMO Customer Call Time',
        format: 'duration',
        group: 'RMO',
        hidden: true,
    },
    {
        id: 'total_rmo_rider_called',
        label: 'RMO Rider Called',
        format: 'number',
        group: 'RMO',
        hidden: true,
    },
    {
        id: 'total_rmo_rider_call_time',
        label: 'RMO Rider Call Time',
        format: 'duration',
        group: 'RMO',
        hidden: true,
    },

    // pancake_user_daily_call_reports — every call, and the verification half
    {
        id: 'total_called',
        label: 'Total Called',
        format: 'number',
        group: 'All calls',
        hidden: true,
    },
    {
        id: 'total_call_time',
        label: 'Total Call Time',
        format: 'duration',
        group: 'All calls',
        hidden: true,
    },
    {
        id: 'total_verification_called',
        label: 'Verification Called',
        format: 'number',
        group: 'All calls',
        hidden: true,
    },
    {
        id: 'total_verification_call_time',
        label: 'Verification Call Time',
        format: 'duration',
        group: 'All calls',
        hidden: true,
    },
];

const CSR_COLUMN_OPTIONS: ColumnOption[] = [
    // Pinned and always drawn: a row of figures with the name scrolled off is
    // unreadable, so it is not a column anyone may switch off.
    { id: 'name', label: 'CSR', group: 'CSR', required: true },
    ...CSR_METRIC_COLUMNS.map((column) => ({
        id: column.id,
        label: column.label,
        group: column.group,
        defaultVisible: !column.hidden,
    })),
];

const CSR_COLUMNS_STORAGE_KEY = 'csr-analytics-cols';

const SCROLL_BODY =
    '[&_.custom-scrollbar]:max-h-[32rem] [&_.custom-scrollbar]:overflow-y-auto';

export default function Analytics({ workspace, records, query }: Props) {
    const today = new Date();
    const currentType = query?.type === 'erp' ? 'erp' : 'pos';
    const currentSort = query?.sort ?? '-total_sales';
    const range = {
        from: query?.from ? parseISO(query.from) : subDays(today, 6),
        to: query?.to ? parseISO(query.to) : today,
    };
    const [searchInput, setSearchInput] = useState(query?.search ?? '');
    const { visibility: columnVisibility, setVisibility: setColumnVisibility } =
        useColumnVisibility(CSR_COLUMN_OPTIONS, CSR_COLUMNS_STORAGE_KEY);

    // Kept in component state rather than the URL: the chart's own reading, not
    // something the page's other blocks or a shared link need to agree with.
    const [callMixGranularity, setCallMixGranularity] =
        useState<CallMixGranularity>('daily');
    const [hiddenCallMix, setHiddenCallMix] = useState<
        ('customer' | 'rider' | 'verification')[]
    >([]);
    const [callMixDay, setCallMixDay] = useState<string | null>(null);
    const [comparisonTab, setComparisonTab] = useState(
        query?.comparison ?? 'sales',
    );

    const fromStr = format(range.from, 'yyyy-MM-dd');
    const toStr = format(range.to, 'yyyy-MM-dd');

    // Single entry point for every filter/sort/page change: re-request the
    // Inertia page (controller already returns `records`) with the merged
    // params and only swap the data props. No API access.
    const navigate = (overrides: Record<string, string | number | undefined>) =>
        router.get(
            `/workspaces/${workspace.slug}/csr/analytics`,
            {
                type: currentType,
                from: fromStr,
                to: toStr,
                sort: currentSort,
                'filter[search]': searchInput || undefined,
                page: query?.page ?? 1,
                per_page: query?.per_page ?? records.per_page,
                comparison: comparisonTab,
                ...overrides,
            },
            {
                only: ['records', 'query'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );

    // Picking a comparison tab is a read of data already in hand, so it writes
    // the URL in place instead of making a visit — a reload or a shared link
    // then opens on the same metric. Inertia's own history entry is stamped
    // with the new URL too, so coming back through the browser's history
    // restores the tab rather than the one the entry was created with.
    const selectComparisonTab = (key: string) => {
        setComparisonTab(key);

        const url = new URL(window.location.href);
        url.searchParams.set('comparison', key);

        const state = window.history.state;
        window.history.replaceState(
            state?.page
                ? {
                      ...state,
                      page: {
                          ...state.page,
                          url: url.pathname + url.search,
                      },
                  }
                : state,
            '',
            url,
        );
    };

    // One endpoint per card, the same shape the other CSR stat endpoints use.
    // Each fetches on its own, so a slow metric shows a skeleton without holding
    // up the card beside it, and the table's sorting, paging and search never
    // touch either.
    //
    // Not keyed on the POS/ERP switch. Sales and RTS read the POS rollup, the
    // same rows the leaders and the comparison below them read, so the totals
    // and the names under them always agree; the RMO cards read the call
    // report and the delivery rows, which have no POS/ERP side. That switch
    // only picks the rollup the table is built from.
    const [salesStat, salesLoading] = useAnalyticsStat<SalesStat>(
        workspace.slug,
        'analytics-sales',
        fromStr,
        toStr,
    );
    const [rtsStat, rtsLoading] = useAnalyticsStat<RtsStat>(
        workspace.slug,
        'analytics-rts',
        fromStr,
        toStr,
    );
    const [rmoCalledStat, rmoCalledLoading] = useAnalyticsStat<RmoCalledStat>(
        workspace.slug,
        'analytics-rmo-called',
        fromStr,
        toStr,
    );
    const [totalRmoCalledStat, totalRmoCalledLoading] =
        useAnalyticsStat<TotalRmoCalledStat>(
            workspace.slug,
            'analytics-total-rmo-called',
            fromStr,
            toStr,
        );
    const [rmoCallTimeStat, rmoCallTimeLoading] =
        useAnalyticsStat<RmoCallTimeStat>(
            workspace.slug,
            'analytics-rmo-call-time',
            fromStr,
            toStr,
        );
    const [rmoRealStat, rmoRealLoading] =
        useAnalyticsStat<RmoRealConversationsStat>(
            workspace.slug,
            'analytics-rmo-real-conversations',
            fromStr,
            toStr,
        );
    const [rmoHitRateStat, rmoHitRateLoading] =
        useAnalyticsStat<RmoHitRateStat>(
            workspace.slug,
            'analytics-rmo-hit-rate',
            fromStr,
            toStr,
        );
    const [rmoTimeStat, rmoTimeLoading] = useAnalyticsStat<RmoTimeStat>(
        workspace.slug,
        'analytics-rmo-time',
        fromStr,
        toStr,
    );
    const [callsPlacedStat, callsPlacedLoading] =
        useAnalyticsStat<CallsPlacedStat>(
            workspace.slug,
            'analytics-calls-placed',
            fromStr,
            toStr,
        );
    const [realConversationsStat, realConversationsLoading] =
        useAnalyticsStat<RealConversationsStat>(
            workspace.slug,
            'analytics-real-conversations',
            fromStr,
            toStr,
        );
    const [reachRateStat, reachRateLoading] = useAnalyticsStat<ReachRateStat>(
        workspace.slug,
        'analytics-reach-rate',
        fromStr,
        toStr,
    );
    const [longestCallStat, longestCallLoading] =
        useAnalyticsStat<LongestCallStat>(
            workspace.slug,
            'analytics-longest-call',
            fromStr,
            toStr,
        );

    const [salesLeader, salesLeaderLoading] = useAnalyticsStat<
        LeaderResponse<SalesLeader>
    >(workspace.slug, 'analytics-leader-sales', fromStr, toStr);
    const [rtsLeader, rtsLeaderLoading] = useAnalyticsStat<
        LeaderResponse<RtsLeader>
    >(workspace.slug, 'analytics-leader-rts', fromStr, toStr);
    const [rmoCalledLeader, rmoCalledLeaderLoading] = useAnalyticsStat<
        LeaderResponse<RmoCalledLeader>
    >(workspace.slug, 'analytics-leader-rmo-called', fromStr, toStr);
    const [rmoDurationLeader, rmoDurationLeaderLoading] = useAnalyticsStat<
        LeaderResponse<RmoDurationLeader>
    >(workspace.slug, 'analytics-leader-rmo-duration', fromStr, toStr);

    // The field behind the leaders. One request for all four metrics — they
    // come from the same two scans, so a call per tab would repeat the work.
    // The days the hourly tabs offer. Derived from the range rather than stored,
    // so moving the date picker cannot leave a tab pointing outside it.
    const callMixDays = useMemo(
        () =>
            eachDayOfInterval({
                start: parseISO(fromStr),
                end: parseISO(toStr),
            }).map((day) => format(day, 'yyyy-MM-dd')),
        [fromStr, toStr],
    );

    // The last day of the range unless one was picked and is still in it — the
    // most recent day is the one worth opening on.
    const callMixReadDay =
        callMixDay && callMixDays.includes(callMixDay)
            ? callMixDay
            : callMixDays[callMixDays.length - 1];

    const [callMix, callMixLoading] = useAnalyticsStat<CallMixResponse>(
        workspace.slug,
        'analytics-call-mix',
        fromStr,
        toStr,
        useMemo<Record<string, string>>(() => {
            const params: Record<string, string> = {
                granularity: callMixGranularity,
            };

            // Only sent for hourly: a day on a daily request would read as a
            // narrower range than the one the page is showing.
            if (callMixGranularity === 'hourly') {
                params.day = callMixReadDay;
            }

            return params;
        }, [callMixGranularity, callMixReadDay]),
    );

    const [comparison, comparisonLoading] =
        useAnalyticsStat<ComparisonResponse>(
            workspace.slug,
            'analytics-comparison',
            fromStr,
            toStr,
        );

    // The two call cards' totals, spread across the days that made them.
    const [dailyEffort, dailyEffortLoading] =
        useAnalyticsStat<DailyEffortResponse>(
            workspace.slug,
            'analytics-daily-effort',
            fromStr,
            toStr,
        );

    // The same days as numbers, under the chart. Its own request: the table
    // answers a different question and carries columns the chart never draws.
    const [callOutcomes, callOutcomesLoading] =
        useAnalyticsStat<DailyCallOutcomesResponse>(
            workspace.slug,
            'analytics-daily-call-outcomes',
            fromStr,
            toStr,
        );

    // Debounced search — skip the initial mount so we don't refetch on load.
    const isFirstRender = useRef(true);
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timer = setTimeout(() => {
            navigate({
                'filter[search]': searchInput || undefined,
                page: 1,
            });
        }, 400);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchInput]);

    const initialSorting = useMemo(
        () => toFrontendSort(currentSort),
        [currentSort],
    );

    const baseColumns = useMemo<ColumnDef<CsrRecord>[]>(
        () => [
            {
                accessorKey: 'name',
                header: ({ column }) => (
                    <SortableHeader column={column} title="CSR" />
                ),
                // Fall back to the pancake_user_id when the user has no synced
                // name (e.g. assignees not yet pulled into pancake_users).
                cell: ({ row }) => row.original.name,
                size: 220,
                // The figures run wider than any screen once more than a
                // handful are switched on, and a row of numbers with the name
                // scrolled off is unreadable — so
                // the name column is pinned and the figures scroll past it. It
                // repaints the surface (the body would otherwise show through)
                // and carries the edge as an inset shadow rather than a border,
                // which a collapsed table drops on a sticky cell.
                meta: {
                    headerClassName:
                        'sticky left-0 z-30 bg-white dark:bg-zinc-900 shadow-[inset_-1px_-1px_0_rgba(0,0,0,0.06)] dark:shadow-[inset_-1px_-1px_0_rgba(255,255,255,0.06)]',
                    cellClassName:
                        'sticky left-0 z-10 bg-white dark:bg-zinc-900 shadow-[inset_-1px_0_0_rgba(0,0,0,0.06)] dark:shadow-[inset_-1px_0_0_rgba(255,255,255,0.06)]',
                },
            },
            ...CSR_METRIC_COLUMNS.map<ColumnDef<CsrRecord>>((metric) => ({
                accessorKey: metric.id,
                header: ({ column }) => (
                    <SortableHeader column={column} title={metric.label} />
                ),
                cell: ({ row }) => {
                    const value = Number(row.original[metric.id]) || 0;

                    switch (metric.format) {
                        case 'peso':
                            return peso(value);
                        case 'percent':
                            return `${value.toFixed(2)}%`;
                        case 'duration':
                            return formatCallTime(value);
                        case 'rmoPercent':
                            // Assigned over confirmed. With nothing confirmed
                            // there is no rate — a dash, not a clean 0%.
                            return Number(
                                row.original.total_rmo_confirmed_count,
                            ) > 0
                                ? `${value.toFixed(2)}%`
                                : '—';
                        default:
                            return value.toLocaleString();
                    }
                },
            })),
        ],
        [],
    );

    // cn lets a column keep its own pinned styling — the name column's corner
    // shadow and higher stacking win over the shared header class.
    const columns = useMemo<ColumnDef<CsrRecord>[]>(
        () =>
            baseColumns.map((column) => ({
                ...column,
                meta: {
                    ...column.meta,
                    headerClassName: cn(
                        STICKY_HEADER_CELL,
                        column.meta?.headerClassName,
                    ),
                },
            })),
        [baseColumns],
    );

    return (
        <AppLayout>
            <Head title={`${workspace.name} - CSR Analytics`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="CSR Analytics"
                    description="Aggregated CSR performance from daily records"
                    stackActionsOnMobile
                    divider={false}
                >
                    {/* ERP/POS toggle hidden for now
                    <div className="flex items-center rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800">
                        {['erp', 'pos'].map((value) => {
                            const label = value === 'erp' ? 'ERP' : 'POS';
                            const isActive = currentType === value;
                            const isDisabled = value === 'erp';
                            return (
                                <button
                                    key={value}
                                    disabled={isDisabled}
                                    onClick={() =>
                                        navigate({ type: value, page: 1 })
                                    }
                                    className={`rounded-lg px-3 py-1.5 text-[12px]! font-medium transition-colors ${
                                        isActive
                                            ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-600 dark:text-white'
                                            : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300'
                                    }`}
                                >
                                    {label}
                                </button>
                            );
                        })}
                    </div>
                    */}
                    <DatePicker
                        id="csr-analytics-date-range"
                        mode="range"
                        defaultDate={[range.from, range.to] as never}
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                navigate({
                                    from: format(
                                        dates[0] as Date,
                                        'yyyy-MM-dd',
                                    ),
                                    to: format(dates[1] as Date, 'yyyy-MM-dd'),
                                    page: 1,
                                });
                            } else if (dates.length === 0) {
                                navigate({
                                    from: undefined,
                                    to: undefined,
                                    page: 1,
                                });
                            }
                        }}
                    />
                </PageHeader>

                <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <SalesStatCard stat={salesStat} loading={salesLoading} />
                    <RtsStatCard stat={rtsStat} loading={rtsLoading} />
                    <RmoCalledStatCard
                        stat={rmoCalledStat}
                        loading={rmoCalledLoading}
                    />
                    <RmoTimeStatCard
                        stat={rmoTimeStat}
                        loading={rmoTimeLoading}
                    />
                    <CallsPlacedStatCard
                        stat={callsPlacedStat}
                        loading={callsPlacedLoading}
                    />
                    <RealConversationsStatCard
                        stat={realConversationsStat}
                        loading={realConversationsLoading}
                    />
                    <ReachRateStatCard
                        stat={reachRateStat}
                        loading={reachRateLoading}
                    />
                    <LongestCallStatCard
                        stat={longestCallStat}
                        loading={longestCallLoading}
                    />
                    <TotalRmoCalledStatCard
                        stat={totalRmoCalledStat}
                        loading={totalRmoCalledLoading}
                    />
                    <RmoCallTimeStatCard
                        stat={rmoCallTimeStat}
                        loading={rmoCallTimeLoading}
                    />
                    <RmoRealConversationsStatCard
                        stat={rmoRealStat}
                        loading={rmoRealLoading}
                    />
                    <RmoHitRateStatCard
                        stat={rmoHitRateStat}
                        loading={rmoHitRateLoading}
                    />
                </div>

                <h2 className="mt-6 mb-3 font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                    Leaders for the period
                </h2>

                <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <HighestSalesLeaderCard
                        data={salesLeader}
                        loading={salesLeaderLoading}
                    />
                    <LowestRtsLeaderCard
                        data={rtsLeader}
                        loading={rtsLeaderLoading}
                    />
                    <HighestRmoCalledLeaderCard
                        data={rmoCalledLeader}
                        loading={rmoCalledLeaderLoading}
                    />
                    <HighestRmoDurationLeaderCard
                        data={rmoDurationLeader}
                        loading={rmoDurationLeaderLoading}
                    />
                </div>

                <CsrComparisonPanel
                    data={comparison}
                    loading={comparisonLoading}
                    metricKey={comparisonTab}
                    onMetricChange={selectComparisonTab}
                />

                <CsrDailyEffortChart
                    data={dailyEffort}
                    loading={dailyEffortLoading}
                />

                <CsrCallMixChart
                    data={callMix}
                    loading={callMixLoading}
                    granularity={callMixGranularity}
                    onGranularityChange={setCallMixGranularity}
                    days={callMixDays}
                    day={callMixReadDay}
                    onDayChange={setCallMixDay}
                    hidden={hiddenCallMix}
                    onToggleSeries={(id) =>
                        setHiddenCallMix((current) =>
                            current.includes(id)
                                ? current.filter((series) => series !== id)
                                : [...current, id],
                        )
                    }
                />

                <CsrDailyCallOutcomesTable
                    data={callOutcomes}
                    loading={callOutcomesLoading}
                />

                {/* The same header row the sections above use: the section's
                    name on the left, its one control on the right. */}
                <div className="mt-6 mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                        CSR breakdown
                    </h2>

                    <div className="flex items-center gap-2">
                        <input
                            type="text"
                            placeholder="Search CSR..."
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            className="h-9 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm! text-zinc-900 placeholder-zinc-400 focus:border-zinc-400 focus:outline-none sm:w-64 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 dark:focus:border-zinc-500"
                        />

                        <ColumnsDropdown
                            options={CSR_COLUMN_OPTIONS}
                            visibility={columnVisibility}
                            onChange={setColumnVisibility}
                        />
                    </div>
                </div>

                <div
                    className={cn(
                        'rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900',
                        SCROLL_BODY,
                    )}
                >
                    <DataTable
                        key={currentSort}
                        columns={columns}
                        data={records.data ?? []}
                        initialSorting={initialSorting}
                        meta={omit(records, ['data'])}
                        columnVisibility={columnVisibility}
                        onColumnVisibilityChange={setColumnVisibility}
                        onFetch={(params) => {
                            const overrides: Record<
                                string,
                                string | number | undefined
                            > = {};
                            if (params?.sort !== undefined) {
                                overrides.sort = params.sort as string;
                                overrides.page = 1;
                            }
                            if (params?.per_page !== undefined) {
                                overrides.per_page = params.per_page as number;
                                overrides.page = 1;
                            }
                            if (params?.page !== undefined) {
                                overrides.page = params.page as number;
                            }
                            navigate(overrides);
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
