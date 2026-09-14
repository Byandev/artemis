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
    ConfirmedRiskyOrdersStatCard,
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
    type ConfirmedRiskyOrdersStat,
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
import CsrDailyEffortChart, {
    type DailyEffortResponse,
} from '@/components/csr/CsrDailyEffortChart';
import CsrHourlyEffortChart, {
    type HourlyEffortResponse,
} from '@/components/csr/CsrHourlyEffortChart';
import {
    ROLLUP_COLUMN_OPTIONS,
    rollupColumns,
    type CsrRollupFigures,
} from '@/components/csr/csr-breakdown-columns';
import {
    ColumnsDropdown,
    useColumnVisibility,
    type ColumnOption,
} from '@/components/ui/columns-dropdown';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import { useCsrStat } from '@/hooks/use-csr-stat';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type PaginatedData } from '@/types';
import { Head } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { format, parseISO, subDays } from 'date-fns';
import { omit } from 'lodash';
import { useMemo, useState } from 'react';

/**
 * One of the CSR's own days, every figure of both nightly rollups.
 *
 * The analytics breakdown's row, at a per-day grain instead of per-CSR — same
 * columns, same aliases, so the two tables read alike.
 */
interface DayRecord extends CsrRollupFigures {
    /** `YYYY-MM-DD`. */
    date: string;
}

const COLUMN_OPTIONS: ColumnOption[] = [
    { id: 'date', label: 'Date', required: true, group: 'Day' },
    ...ROLLUP_COLUMN_OPTIONS,
];

const COLUMNS_STORAGE_KEY = 'csr-dashboard-breakdown-cols';

// Pinning the header is done from this page rather than inside the shared
// DataTable: the classes below are stamped onto this table's columns and its
// scroll container, so no other table's layout moves. The surface is repainted
// on the pinned cells (the body would otherwise show through) and the rule is
// drawn as an inset shadow — a collapsed table drops the border of a sticky
// cell once it starts to scroll.
const STICKY_HEADER_CELL =
    'sticky top-0 z-20 bg-white shadow-[inset_0_-1px_0_rgba(0,0,0,0.06)] dark:bg-zinc-900 dark:shadow-[inset_0_-1px_0_rgba(255,255,255,0.06)]';

// The body has to scroll for a header to stick to anything, so the container
// DataTable renders is capped here.
const SCROLL_BODY =
    '[&_.custom-scrollbar]:max-h-[32rem] [&_.custom-scrollbar]:overflow-y-auto';

/** "Thu, Aug 14" — the range is already named above, so the year would be noise. */
const dayLabel = (date: string) => format(parseISO(date), 'EEE, MMM d');

interface Props {
    workspace: {
        id: number;
        name: string;
        slug: string;
    };
}

export default function CsrDashboard({ workspace }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'CSR Dashboard',
            href: `/workspaces/${workspace.slug}/csr/dashboard`,
        },
    ];

    // The cards are fetched over XHR, so the range lives here rather than in
    // the URL — picking a date refetches the cards without revisiting the page.
    // Same default as the analytics page: the last seven days, today included.
    const today = new Date();
    const [range, setRange] = useState<{ from: Date; to: Date }>({
        from: subDays(today, 6),
        to: today,
    });

    const fromStr = format(range.from, 'yyyy-MM-dd');
    const toStr = format(range.to, 'yyyy-MM-dd');

    // The analytics page's cards, narrowed by the endpoints to the pancake
    // accounts linked to the signed-in user — so these are their own figures,
    // not the workspace's. One endpoint per card, as there: a slow metric shows
    // a skeleton without holding up the card beside it.
    const [salesStat, salesLoading] = useCsrStat<SalesStat>(
        workspace.slug,
        'dashboard-sales',
        fromStr,
        toStr,
    );
    const [rtsStat, rtsLoading] = useCsrStat<RtsStat>(
        workspace.slug,
        'dashboard-rts',
        fromStr,
        toStr,
    );
    const [rmoCalledStat, rmoCalledLoading] = useCsrStat<RmoCalledStat>(
        workspace.slug,
        'dashboard-rmo-called',
        fromStr,
        toStr,
    );
    const [rmoTimeStat, rmoTimeLoading] = useCsrStat<RmoTimeStat>(
        workspace.slug,
        'dashboard-rmo-time',
        fromStr,
        toStr,
    );
    const [callsPlacedStat, callsPlacedLoading] = useCsrStat<CallsPlacedStat>(
        workspace.slug,
        'dashboard-calls-placed',
        fromStr,
        toStr,
    );
    const [realConversationsStat, realConversationsLoading] =
        useCsrStat<RealConversationsStat>(
            workspace.slug,
            'dashboard-real-conversations',
            fromStr,
            toStr,
        );
    const [riskyOrdersStat, riskyOrdersLoading] =
        useCsrStat<ConfirmedRiskyOrdersStat>(
            workspace.slug,
            'dashboard-confirmed-risky-orders',
            fromStr,
            toStr,
        );
    const [verifiedOrdersStat, verifiedOrdersLoading] =
        useCsrStat<VerifiedOrdersStat>(
            workspace.slug,
            'dashboard-verified-orders',
            fromStr,
            toStr,
        );
    const [totalRmoCalledStat, totalRmoCalledLoading] =
        useCsrStat<TotalRmoCalledStat>(
            workspace.slug,
            'dashboard-total-rmo-called',
            fromStr,
            toStr,
        );
    const [rmoCallTimeStat, rmoCallTimeLoading] = useCsrStat<RmoCallTimeStat>(
        workspace.slug,
        'dashboard-rmo-call-time',
        fromStr,
        toStr,
    );
    const [rmoRealStat, rmoRealLoading] = useCsrStat<RmoRealConversationsStat>(
        workspace.slug,
        'dashboard-rmo-real-conversations',
        fromStr,
        toStr,
    );
    const [rmoHitRateStat, rmoHitRateLoading] = useCsrStat<RmoHitRateStat>(
        workspace.slug,
        'dashboard-rmo-hit-rate',
        fromStr,
        toStr,
    );

    // Who came top in the workspace — the one part of the page not narrowed to
    // the reader. A board ranking a roster of one would crown them on every
    // figure; this is the scale the cards above are read against.
    const [salesLeader, salesLeaderLoading] = useCsrStat<
        LeaderResponse<SalesLeader>
    >(workspace.slug, 'dashboard-leader-sales', fromStr, toStr);
    const [rtsLeader, rtsLeaderLoading] = useCsrStat<LeaderResponse<RtsLeader>>(
        workspace.slug,
        'dashboard-leader-rts',
        fromStr,
        toStr,
    );
    const [rmoCalledLeader, rmoCalledLeaderLoading] = useCsrStat<
        LeaderResponse<RmoCalledLeader>
    >(workspace.slug, 'dashboard-leader-rmo-called', fromStr, toStr);
    const [rmoDurationLeader, rmoDurationLeaderLoading] = useCsrStat<
        LeaderResponse<RmoDurationLeader>
    >(workspace.slug, 'dashboard-leader-rmo-duration', fromStr, toStr);

    // The two call cards' totals, spread across the days that made them and
    // again across the hours of the day — the CSR's own calls, so the shape is
    // their working week rather than the workspace's.
    const [dailyEffort, dailyEffortLoading] = useCsrStat<DailyEffortResponse>(
        workspace.slug,
        'dashboard-daily-effort',
        fromStr,
        toStr,
    );
    const [hourlyEffort, hourlyEffortLoading] =
        useCsrStat<HourlyEffortResponse>(
            workspace.slug,
            'dashboard-hourly-effort',
            fromStr,
            toStr,
        );

    // The breakdown pages and sorts over the same XHR the cards use, so the
    // table moves without the page reloading. Sorting is server-side, as on the
    // analytics table — the figures are summed there, so the order has to be
    // decided there too.
    const [sort, setSort] = useState('-date');
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(10);

    const [breakdown, breakdownLoading] = useCsrStat<PaginatedData<DayRecord>>(
        workspace.slug,
        'dashboard-breakdown',
        fromStr,
        toStr,
        { sort, page: String(page), per_page: String(perPage) },
    );

    const { visibility: columnVisibility, setVisibility: setColumnVisibility } =
        useColumnVisibility(COLUMN_OPTIONS, COLUMNS_STORAGE_KEY);

    const baseColumns = useMemo<ColumnDef<DayRecord>[]>(
        () => [
            {
                accessorKey: 'date',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Date" />
                ),
                cell: ({ row }) => dayLabel(row.original.date),
                size: 150,
                // Two dozen columns of figures are far wider than any screen,
                // and a row of numbers with the date scrolled off is
                // unreadable — so the date column is pinned and the figures
                // scroll past it.
                meta: {
                    headerClassName:
                        'sticky left-0 z-30 bg-white dark:bg-zinc-900 shadow-[inset_-1px_-1px_0_rgba(0,0,0,0.06)] dark:shadow-[inset_-1px_-1px_0_rgba(255,255,255,0.06)]',
                    cellClassName:
                        'sticky left-0 z-10 bg-white dark:bg-zinc-900 shadow-[inset_-1px_0_0_rgba(0,0,0,0.06)] dark:shadow-[inset_-1px_0_0_rgba(255,255,255,0.06)]',
                },
            },
            ...rollupColumns<DayRecord>(),
        ],
        [],
    );

    const columns = useMemo<ColumnDef<DayRecord>[]>(
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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="CSR Dashboard" />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 sm:p-6">
                <PageHeader
                    title="CSR Dashboard"
                    description="Your own performance in this workspace."
                    stackActionsOnMobile
                    divider={false}
                >
                    <DatePicker
                        id="csr-dashboard-date-range"
                        mode="range"
                        defaultDate={[range.from, range.to] as never}
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setRange({
                                    from: dates[0] as Date,
                                    to: dates[1] as Date,
                                });
                            } else if (dates.length === 0) {
                                setRange({
                                    from: subDays(new Date(), 6),
                                    to: new Date(),
                                });
                            }
                        }}
                    />
                </PageHeader>

                {/* Same order as the analytics page, so the two read alike. */}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
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
                    <ConfirmedRiskyOrdersStatCard
                        stat={riskyOrdersStat}
                        loading={riskyOrdersLoading}
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

                <h2 className="mt-6 mb-3 text-[11px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    Leaders for this period
                </h2>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
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

                <h2 className="mt-6 mb-3 text-[11px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    Effort against results
                </h2>

                <CsrDailyEffortChart
                    data={dailyEffort}
                    loading={dailyEffortLoading}
                />

                <CsrHourlyEffortChart
                    data={hourlyEffort}
                    loading={hourlyEffortLoading}
                />

                <div className="mt-6 mb-3 flex items-center justify-between gap-3">
                    <h2 className="text-[11px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        My breakdown
                    </h2>
                    <ColumnsDropdown
                        options={COLUMN_OPTIONS}
                        visibility={columnVisibility}
                        onChange={setColumnVisibility}
                    />
                </div>

                <div className={SCROLL_BODY}>
                    <DataTable
                        key={sort}
                        columns={columns}
                        data={breakdown?.data ?? []}
                        loading={breakdownLoading}
                        initialSorting={toFrontendSort(sort)}
                        meta={breakdown ? omit(breakdown, ['data']) : undefined}
                        columnVisibility={columnVisibility}
                        onColumnVisibilityChange={setColumnVisibility}
                        onFetch={(params) => {
                            // A new sort or page size starts the table again at
                            // page one; paging alone just moves.
                            if (params?.sort !== undefined) {
                                setSort(params.sort as string);
                                setPage(1);
                            }
                            if (params?.per_page !== undefined) {
                                setPerPage(params.per_page as number);
                                setPage(1);
                            }
                            if (params?.page !== undefined) {
                                setPage(params.page as number);
                            }
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
