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
    RmoCalledStatCard,
    RmoTimeStatCard,
    RtsStatCard,
    SalesStatCard,
    type CallsPlacedStat,
    type LongestCallStat,
    type ReachRateStat,
    type RealConversationsStat,
    type RmoCalledStat,
    type RmoTimeStat,
    type RtsStat,
    type SalesStat,
} from '@/components/csr/CsrAnalyticsStatCards';
import CsrComparisonPanel, {
    type ComparisonResponse,
} from '@/components/csr/CsrComparisonPanel';
import CsrDailyCallOutcomesTable, {
    type DailyCallOutcomesResponse,
} from '@/components/csr/CsrDailyCallOutcomesTable';
import CsrDailyEffortChart, {
    type DailyEffortResponse,
} from '@/components/csr/CsrDailyEffortChart';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { cn } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { format, parseISO, subDays } from 'date-fns';
import { omit } from 'lodash';
import { useEffect, useMemo, useRef, useState } from 'react';

interface CsrRecord {
    id: number;
    name: string;
    pancake_user_id: string;
    total_orders: number;
    total_sales: number;
    delivered: number;
    returning_count: number;
    rts_rate: number;
    total_called: number;
    total_call_time: number;
    total_rmo_call_attempts: number;
    total_confirmed: number;
    rmo_percentage: number;
    total_delivered: number;
    total_returning: number;
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
): [T | null, boolean] {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);

        const params = new URLSearchParams({ from, to });

        fetch(
            `/api/workspaces/${workspaceSlug}/csrs/stats/${stat}?${params.toString()}`,
            {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            },
        )
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
    }, [workspaceSlug, stat, from, to]);

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
    // Not keyed on the POS/ERP switch. Sales reads the POS rollup, the same
    // rows the leader and the comparison below it read, so the total and the
    // names under it always agree; the rest read the workspace's orders, which
    // have no POS/ERP side. That switch only picks the rollup the table is
    // built from.
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
                // Eleven columns of figures are wider than any screen, and a
                // row of numbers with the name scrolled off is unreadable — so
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
            {
                accessorKey: 'total_orders',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Orders" />
                ),
                cell: ({ row }) =>
                    Number(row.original.total_orders).toLocaleString(),
            },
            {
                accessorKey: 'total_sales',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Sales" />
                ),
                cell: ({ row }) => peso(row.original.total_sales),
            },
            {
                accessorKey: 'total_delivered',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Delivered" />
                ),
                cell: ({ row }) => peso(row.original.total_delivered),
            },
            {
                accessorKey: 'total_returning',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Returning" />
                ),
                cell: ({ row }) => peso(row.original.total_returning),
            },
            {
                accessorKey: 'rts_rate',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RTS Rate" />
                ),
                cell: ({ row }) =>
                    `${Number(row.original.rts_rate).toFixed(2)}%`,
            },
            {
                accessorKey: 'total_confirmed',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RMO Confirmed" />
                ),
                cell: ({ row }) =>
                    Number(row.original.total_confirmed).toLocaleString(),
            },
            {
                accessorKey: 'total_called',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RMO Assigned" />
                ),
                cell: ({ row }) =>
                    Number(row.original.total_called).toLocaleString(),
            },
            {
                accessorKey: 'total_rmo_call_attempts',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RMO Called" />
                ),
                cell: ({ row }) =>
                    Number(
                        row.original.total_rmo_call_attempts,
                    ).toLocaleString(),
            },
            {
                accessorKey: 'rmo_percentage',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RMO %" />
                ),
                cell: ({ row }) => {
                    // RMO % = RMO assigned / RMO confirmed (computed on the backend).
                    const confirmed = Number(row.original.total_confirmed) || 0;
                    if (confirmed === 0) return '—';
                    return `${Number(row.original.rmo_percentage).toFixed(2)}%`;
                },
            },
            {
                accessorKey: 'total_call_time',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RMO Call Time" />
                ),
                cell: ({ row }) => formatCallTime(row.original.total_call_time),
            },
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

                    <input
                        type="text"
                        placeholder="Search CSR..."
                        value={searchInput}
                        onChange={(e) => setSearchInput(e.target.value)}
                        className="h-9 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm! text-zinc-900 placeholder-zinc-400 focus:border-zinc-400 focus:outline-none sm:w-64 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 dark:focus:border-zinc-500"
                    />
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
