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
    VerifiedOrdersStatCard,
    type CallsPlacedStat,
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
    type VerifiedOrdersStat,
} from '@/components/csr/CsrAnalyticsStatCards';
import CsrComparisonPanel, {
    type ComparisonMetricOption,
    type ComparisonResponse,
} from '@/components/csr/CsrComparisonPanel';
import CsrDailyEffortChart, {
    type DailyEffortResponse,
} from '@/components/csr/CsrDailyEffortChart';
import CsrHourlyEffortChart, {
    type HourlyEffortResponse,
} from '@/components/csr/CsrHourlyEffortChart';
import CsrSyncButton from '@/components/csr/CsrSyncButton';
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
import { format, parseISO, subDays } from 'date-fns';
import { omit } from 'lodash';
import { useEffect, useMemo, useRef, useState } from 'react';

/**
 * One CSR's period, every column of both nightly rollups.
 *
 * The sales figures come from pancake_user_pos_daily_reports (or its ERP twin,
 * which carries no parcel counts and so reads zero on them); the call figures
 * from pancake_user_daily_call_reports. The names are the aliases the
 * controller selects, not the raw column names — `total_called` is the RMO
 * assignments and `total_all_called` is the report's own `total_called`.
 */
interface CsrRecord {
    id: number;
    name: string;
    pancake_user_id: string;
    // pancake_user_pos_daily_reports
    total_orders: number;
    total_sales: number;
    total_delivered: number;
    total_delivered_count: number;
    total_returning: number;
    total_returning_count: number;
    rts_rate: number;
    // pancake_user_daily_call_reports
    total_confirmed: number;
    total_called: number;
    total_rmo_call_attempts: number;
    rmo_percentage: number;
    total_call_time: number;
    total_rmo_connected_called: number;
    total_rmo_real_called: number;
    longest_rmo_call_time: number;
    total_rmo_customer_called: number;
    total_rmo_customer_call_time: number;
    total_rmo_rider_called: number;
    total_rmo_rider_call_time: number;
    total_verification_called: number;
    total_verification_call_time: number;
    total_verification_real_called: number;
    total_verified_orders: number;
    total_all_called: number;
    total_all_call_time: number;
}

interface Props {
    workspace: Workspace;
    records: PaginatedData<CsrRecord>;
    /**
     * Whether the manual rollup trigger is offered. False in production, where
     * the nightly schedule is the only thing that rebuilds these records.
     */
    canRunSync?: boolean;
    /** Everything the CSR comparison dropdown lists, in the order it lists it. */
    comparisonMetrics?: ComparisonMetricOption[];
    query?: {
        sort?: string | null;
        from?: string | null;
        to?: string | null;
        page?: number | string;
        per_page?: number | string;
        type?: 'erp' | 'pos' | null;
        search?: string | null;
        /** Which CSR comparison metric to open on — one of the metric keys. */
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
    /** Anything else the endpoint needs — the comparison's metric, say. */
    extra?: Record<string, string>,
): [T | null, boolean] {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);
    // The effect compares its dependencies by identity, and an object literal
    // is a new one on every render; the serialised form is what actually says
    // whether the request has changed.
    const extraKey = JSON.stringify(extra ?? {});

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);

        const params = new URLSearchParams({
            from,
            to,
            ...(JSON.parse(extraKey) as Record<string, string>),
        });

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
    }, [workspaceSlug, stat, from, to, extraKey]);

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

// The breakdown carries every column of both nightly rollups, and all but a
// handful are the same three shapes: a count, a peso figure, or a span of call
// time. Building those from one place keeps two dozen columns readable and
// stops a new one from being formatted differently by accident.
const countColumn = (
    accessorKey: keyof CsrRecord,
    title: string,
): ColumnDef<CsrRecord> => ({
    accessorKey,
    header: ({ column }) => <SortableHeader column={column} title={title} />,
    cell: ({ row }) => Number(row.original[accessorKey]).toLocaleString(),
});

const moneyColumn = (
    accessorKey: keyof CsrRecord,
    title: string,
): ColumnDef<CsrRecord> => ({
    accessorKey,
    header: ({ column }) => <SortableHeader column={column} title={title} />,
    cell: ({ row }) => peso(Number(row.original[accessorKey])),
});

const durationColumn = (
    accessorKey: keyof CsrRecord,
    title: string,
): ColumnDef<CsrRecord> => ({
    accessorKey,
    header: ({ column }) => <SortableHeader column={column} title={title} />,
    cell: ({ row }) => formatCallTime(Number(row.original[accessorKey])),
});

// Which of those columns the reader wants on screen. The ids are the column
// accessorKeys, which are also the sort keys the server takes, and the groups
// are the two rollups the figures come from. Everything starts visible — the
// table shows the whole report and the menu is how it's narrowed — and the
// choice is remembered per browser under COLUMNS_STORAGE_KEY.
const COLUMN_OPTIONS: ColumnOption[] = [
    { id: 'name', label: 'CSR', required: true, group: 'CSR' },

    { id: 'total_orders', label: 'Orders', group: 'Sales report' },
    { id: 'total_sales', label: 'Sales', group: 'Sales report' },
    { id: 'total_delivered', label: 'Delivered', group: 'Sales report' },
    {
        id: 'total_delivered_count',
        label: 'Delivered Parcels',
        group: 'Sales report',
    },
    { id: 'total_returning', label: 'Returning', group: 'Sales report' },
    {
        id: 'total_returning_count',
        label: 'Returning Parcels',
        group: 'Sales report',
    },
    { id: 'rts_rate', label: 'RTS Rate', group: 'Sales report' },

    { id: 'total_confirmed', label: 'RMO Confirmed', group: 'Call report' },
    { id: 'total_called', label: 'RMO Assigned', group: 'Call report' },
    {
        id: 'total_rmo_call_attempts',
        label: 'RMO Called',
        group: 'Call report',
    },
    { id: 'rmo_percentage', label: 'RMO %', group: 'Call report' },
    { id: 'total_call_time', label: 'RMO Call Time', group: 'Call report' },
    {
        id: 'total_rmo_connected_called',
        label: 'RMO Answered',
        group: 'Call report',
    },
    {
        id: 'total_rmo_real_called',
        label: 'RMO Real Conversations',
        group: 'Call report',
    },
    {
        id: 'longest_rmo_call_time',
        label: 'Longest RMO Call',
        group: 'Call report',
    },
    {
        id: 'total_rmo_customer_called',
        label: 'RMO Customer Called',
        group: 'Call report',
    },
    {
        id: 'total_rmo_customer_call_time',
        label: 'RMO Customer Call Time',
        group: 'Call report',
    },
    {
        id: 'total_rmo_rider_called',
        label: 'RMO Rider Called',
        group: 'Call report',
    },
    {
        id: 'total_rmo_rider_call_time',
        label: 'RMO Rider Call Time',
        group: 'Call report',
    },
    {
        id: 'total_verification_called',
        label: 'Verification Called',
        group: 'Call report',
    },
    {
        id: 'total_verification_call_time',
        label: 'Verification Call Time',
        group: 'Call report',
    },
    {
        id: 'total_verification_real_called',
        label: 'Verification Real Conversations',
        group: 'Call report',
    },
    {
        id: 'total_verified_orders',
        label: 'Total Verified Orders',
        group: 'Call report',
    },
    { id: 'total_all_called', label: 'Total Called', group: 'Call report' },
    {
        id: 'total_all_call_time',
        label: 'Total Call Time',
        group: 'Call report',
    },
];

const COLUMNS_STORAGE_KEY = 'csr-analytics-cols';

export default function Analytics({
    workspace,
    records,
    query,
    canRunSync = false,
    comparisonMetrics = [],
}: Props) {
    const today = new Date();
    const currentType = query?.type === 'erp' ? 'erp' : 'pos';
    const currentSort = query?.sort ?? '-total_sales';
    const range = {
        from: query?.from ? parseISO(query.from) : subDays(today, 6),
        to: query?.to ? parseISO(query.to) : today,
    };
    const [searchInput, setSearchInput] = useState(query?.search ?? '');
    // The controller resolves the URL's key (and the four the panel used to
    // tab between) against the metric catalogue, so this only stands in when
    // the page is rendered without it.
    const [comparisonTab, setComparisonTab] = useState(
        query?.comparison ?? 'total_sales',
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

    // Picking a comparison metric refetches that metric alone; the page itself
    // does not move, so it writes the URL in place instead of making a visit —
    // a reload or a shared link then opens on the same metric. Inertia's own
    // history entry is stamped with the new URL too, so coming back through the
    // browser's history restores the metric rather than the one the entry was
    // created with.
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
    const [verifiedOrdersStat, verifiedOrdersLoading] =
        useAnalyticsStat<VerifiedOrdersStat>(
            workspace.slug,
            'analytics-verified-orders',
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

    // The field behind the leaders, for the one metric being read. The metric
    // goes to the endpoint rather than the whole catalogue coming back, so the
    // scan is over that column alone — picking another fetches that one.
    const [comparison, comparisonLoading] =
        useAnalyticsStat<ComparisonResponse>(
            workspace.slug,
            'analytics-comparison',
            fromStr,
            toStr,
            { metric: comparisonTab },
        );

    // The two call cards' totals, spread across the days that made them.
    const [dailyEffort, dailyEffortLoading] =
        useAnalyticsStat<DailyEffortResponse>(
            workspace.slug,
            'analytics-daily-effort',
            fromStr,
            toStr,
        );

    // The same calls folded into one round of the clock. Its own request, and
    // its own source: the hour is on the call log, not on the nightly rollup.
    const [hourlyEffort, hourlyEffortLoading] =
        useAnalyticsStat<HourlyEffortResponse>(
            workspace.slug,
            'analytics-hourly-effort',
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

    const { visibility: columnVisibility, setVisibility: setColumnVisibility } =
        useColumnVisibility(COLUMN_OPTIONS, COLUMNS_STORAGE_KEY);

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
                // Two dozen columns of figures are far wider than any
                // screen, and a row of numbers with the name scrolled off is
                // unreadable — so the name column is pinned and the figures
                // scroll past it. It repaints the surface (the body would
                // otherwise show through) and carries the edge as an inset
                // shadow rather than a border, which a collapsed table drops
                // on a sticky cell.
                meta: {
                    headerClassName:
                        'sticky left-0 z-30 bg-white dark:bg-zinc-900 shadow-[inset_-1px_-1px_0_rgba(0,0,0,0.06)] dark:shadow-[inset_-1px_-1px_0_rgba(255,255,255,0.06)]',
                    cellClassName:
                        'sticky left-0 z-10 bg-white dark:bg-zinc-900 shadow-[inset_-1px_0_0_rgba(0,0,0,0.06)] dark:shadow-[inset_-1px_0_0_rgba(255,255,255,0.06)]',
                },
            },
            // pancake_user_pos_daily_reports — the sales side of the period.
            // Delivered and Returning are money; the two Parcels columns beside
            // them are the counts behind that money, which only the POS rollup
            // carries (ERP reads zero).
            countColumn('total_orders', 'Orders'),
            moneyColumn('total_sales', 'Sales'),
            moneyColumn('total_delivered', 'Delivered'),
            countColumn('total_delivered_count', 'Delivered Parcels'),
            moneyColumn('total_returning', 'Returning'),
            countColumn('total_returning_count', 'Returning Parcels'),
            {
                accessorKey: 'rts_rate',
                header: ({ column }) => (
                    <SortableHeader column={column} title="RTS Rate" />
                ),
                cell: ({ row }) =>
                    `${Number(row.original.rts_rate).toFixed(2)}%`,
            },
            // pancake_user_daily_call_reports — the calling side.
            countColumn('total_confirmed', 'RMO Confirmed'),
            countColumn('total_called', 'RMO Assigned'),
            countColumn('total_rmo_call_attempts', 'RMO Called'),
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
            durationColumn('total_call_time', 'RMO Call Time'),
            // How far the RMO calls got: one that joined at all, and one that
            // lasted past the shared five-second mark. Longest is a max over
            // the range, not a sum.
            countColumn('total_rmo_connected_called', 'RMO Answered'),
            countColumn('total_rmo_real_called', 'RMO Real Conversations'),
            durationColumn('longest_rmo_call_time', 'Longest RMO Call'),
            // The same RMO calls split by who was on the other end.
            countColumn('total_rmo_customer_called', 'RMO Customer Called'),
            durationColumn(
                'total_rmo_customer_call_time',
                'RMO Customer Call Time',
            ),
            countColumn('total_rmo_rider_called', 'RMO Rider Called'),
            durationColumn('total_rmo_rider_call_time', 'RMO Rider Call Time'),
            // Calls against an order with no delivery behind it — confirming
            // the order rather than chasing the parcel.
            countColumn('total_verification_called', 'Verification Called'),
            durationColumn(
                'total_verification_call_time',
                'Verification Call Time',
            ),
            countColumn(
                'total_verification_real_called',
                'Verification Real Conversations',
            ),
            // The same verification work counted by order rather than by call:
            // an order rung three times is three above and one here.
            countColumn('total_verified_orders', 'Total Verified Orders'),
            // The report's own totals: RMO work and verification added
            // together, which is every call the CSR placed.
            countColumn('total_all_called', 'Total Called'),
            durationColumn('total_all_call_time', 'Total Call Time'),
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
                    {canRunSync && (
                        <CsrSyncButton workspaceSlug={workspace.slug} />
                    )}
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
                    <VerifiedOrdersStatCard
                        stat={verifiedOrdersStat}
                        loading={verifiedOrdersLoading}
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
                    options={comparisonMetrics}
                    onMetricChange={selectComparisonTab}
                />

                <CsrDailyEffortChart
                    data={dailyEffort}
                    loading={dailyEffortLoading}
                />

                <CsrHourlyEffortChart
                    data={hourlyEffort}
                    loading={hourlyEffortLoading}
                />

                {/* The same header row the sections above use: the section's
                    name on the left, its controls on the right. The search
                    gives way when the row is narrow; the columns menu keeps its
                    width, since a wrapped trigger label reads as broken. */}
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
                            className="h-9 w-full min-w-0 rounded-lg border border-zinc-200 bg-white px-3 text-sm! text-zinc-900 placeholder-zinc-400 focus:border-zinc-400 focus:outline-none sm:w-64 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 dark:focus:border-zinc-500"
                        />

                        <div className="shrink-0">
                            <ColumnsDropdown
                                options={COLUMN_OPTIONS}
                                visibility={columnVisibility}
                                onChange={setColumnVisibility}
                            />
                        </div>
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
