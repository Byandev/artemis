import DatePicker from '@/components/ui/date-picker';
import {
    CsrScheduleList,
    DeliveryChart,
    KpiCard,
    MyPerformance,
    PendingOrdersTable,
    RtsTrendChart,
    SalesTrendChart,
    Section,
    StatusPieChart,
    TeamLeaderboard,
    formatCallTime,
    pesoCompact,
    pct,
} from '@/components/csr';
import type {
    CsrScheduleEntry,
    DailyTrendEntry,
    MonthlyPerf,
    PancakeAccount,
    PendingOrder,
    StatusBreakdownEntry,
    TodayStats,
    TopCsr,
} from '@/components/csr';
import CsrLayout from '@/layouts/csr-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import flatpickr from 'flatpickr';
import {
    CheckCircle2,
    Clock,
    Package,
    PhoneCall,
    RotateCcw,
    TrendingUp,
    Truck,
} from 'lucide-react';
import moment from 'moment';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    workspace: Workspace;
    authUserId: number;
    pancakeAccounts: PancakeAccount[];
    myTodayStats: TodayStats;
    pendingOrders: PendingOrder[];
    myMonthly: MonthlyPerf;
    topCsrs: TopCsr[];
    today: string;
    from: string;
    to: string;
    monthLabel: string;
    dailyTrend: DailyTrendEntry[];
    statusBreakdown: StatusBreakdownEntry[];
    teamAvg: MonthlyPerf;
    csrSchedules: CsrScheduleEntry[];
}

export default function CsrDashboard({
    workspace,
    authUserId,
    pancakeAccounts,
    myTodayStats,
    pendingOrders,
    myMonthly,
    topCsrs,
    today,
    from,
    to,
    monthLabel,
    dailyTrend,
    statusBreakdown,
    teamAvg,
    csrSchedules,
}: Props) {
    const slug = workspace.slug;
    const assigned = myTodayStats.assigned;

    const nowDate = new Date(today + 'T00:00:00');
    const defaultFrom = `${nowDate.getFullYear()}-${String(nowDate.getMonth() + 1).padStart(2, '0')}-01`;
    const isDefault = from === defaultFrom && to === today;

    const deliveryRate = assigned > 0 ? Math.round((myTodayStats.delivered / assigned) * 100) : 0;
    const callRate = assigned > 0 ? Math.round((myTodayStats.called / assigned) * 100) : 0;
    const totalStatusCount = statusBreakdown.reduce((s, e) => s + e.count, 0);
    const rtsRateColor = myMonthly.rts_rate > 15 ? 'red' : myMonthly.rts_rate > 10 ? 'amber' : 'green';

    const navigateToRange = (newFrom: string, newTo: string) => {
        router.get(
            `/workspaces/${slug}/csr/dashboard`,
            { from: newFrom, to: newTo },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <CsrLayout>
            <Head title={`${workspace.name} - CSR Dashboard`} />

            <div className="mx-auto w-full max-w-[1600px] space-y-6 px-5 py-5">

                {/* ── Page header ── */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-lg font-bold text-gray-900 dark:text-white">CSR Dashboard</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">{monthLabel}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <DatePicker
                            id="csr-dashboard-date-range"
                            mode="range"
                            defaultDate={[from, to] as never as DateOption}
                            onChange={(dates) => {
                                if (dates.length === 2) {
                                    navigateToRange(
                                        moment(dates[0]).format('YYYY-MM-DD'),
                                        moment(dates[1]).format('YYYY-MM-DD'),
                                    );
                                }
                            }}
                        />
                        {!isDefault && (
                            <button
                                onClick={() => navigateToRange(defaultFrom, today)}
                                className="h-9 rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-[12px] font-medium text-emerald-700 transition-colors hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400 dark:hover:bg-emerald-950/50"
                            >
                                This Month
                            </button>
                        )}
                    </div>
                </div>

                {/* ── Today's Activity ── */}
                <div>
                    <p className="mb-3 text-[11px] font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        Today's Activity
                    </p>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        <KpiCard
                            label="Assigned"
                            value={assigned}
                            sub="orders for delivery"
                            icon={Package}
                            iconBg="bg-blue-50 dark:bg-blue-950/40"
                            iconColor="text-blue-600 dark:text-blue-400"
                        />
                        <KpiCard
                            label="Pending"
                            value={myTodayStats.pending}
                            sub={`${pct(myTodayStats.pending, assigned)} remaining`}
                            icon={Clock}
                            iconBg="bg-amber-50 dark:bg-amber-950/40"
                            iconColor="text-amber-600 dark:text-amber-400"
                            badge={myTodayStats.pending > 0 ? { text: 'Action needed', color: 'amber' } : { text: 'All done', color: 'green' }}
                        />
                        <KpiCard
                            label="Called"
                            value={myTodayStats.called}
                            sub={`${pct(myTodayStats.called, assigned)} contact rate`}
                            icon={PhoneCall}
                            iconBg="bg-violet-50 dark:bg-violet-950/40"
                            iconColor="text-violet-600 dark:text-violet-400"
                            progress={callRate}
                        />
                        <KpiCard
                            label="Delivered"
                            value={myTodayStats.delivered}
                            sub={`${pct(myTodayStats.delivered, assigned)} delivery rate`}
                            icon={Truck}
                            iconBg="bg-emerald-50 dark:bg-emerald-950/40"
                            iconColor="text-emerald-600 dark:text-emerald-400"
                            progress={deliveryRate}
                        />
                        <KpiCard
                            label="Returning"
                            value={myTodayStats.returning}
                            sub={`${pct(myTodayStats.returning, assigned)} return rate`}
                            icon={RotateCcw}
                            iconBg="bg-orange-50 dark:bg-orange-950/40"
                            iconColor="text-orange-600 dark:text-orange-400"
                            badge={myTodayStats.returning > 3 ? { text: 'High', color: 'red' } : undefined}
                        />
                        <KpiCard
                            label="Completion"
                            value={assigned > 0 ? `${(((assigned - myTodayStats.pending) / assigned) * 100).toFixed(0)}%` : '—'}
                            sub={assigned > 0 ? `${assigned - myTodayStats.pending} of ${assigned} resolved` : 'No orders today'}
                            icon={CheckCircle2}
                            iconBg="bg-teal-50 dark:bg-teal-950/40"
                            iconColor="text-teal-600 dark:text-teal-400"
                            progress={assigned > 0 ? Math.round(((assigned - myTodayStats.pending) / assigned) * 100) : 0}
                        />
                    </div>
                </div>

                {/* ── Monthly Highlights ── */}
                <div>
                    <p className="mb-3 text-[11px] font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        Monthly Highlights
                    </p>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <KpiCard
                            label="Total Orders"
                            value={myMonthly.total_orders.toLocaleString()}
                            sub={`Team avg: ${teamAvg.total_orders.toLocaleString()}`}
                            icon={Package}
                            iconBg="bg-blue-50 dark:bg-blue-950/40"
                            iconColor="text-blue-600 dark:text-blue-400"
                            badge={myMonthly.total_orders >= teamAvg.total_orders ? { text: 'Above avg', color: 'green' } : { text: 'Below avg', color: 'amber' }}
                        />
                        <KpiCard
                            label="Total Sales"
                            value={pesoCompact(myMonthly.total_sales)}
                            sub={`Team avg: ${pesoCompact(teamAvg.total_sales)}`}
                            icon={TrendingUp}
                            iconBg="bg-emerald-50 dark:bg-emerald-950/40"
                            iconColor="text-emerald-600 dark:text-emerald-400"
                            badge={myMonthly.total_sales >= teamAvg.total_sales ? { text: 'Above avg', color: 'green' } : { text: 'Below avg', color: 'amber' }}
                        />
                        <KpiCard
                            label="RTS Rate"
                            value={`${myMonthly.rts_rate.toFixed(1)}%`}
                            sub={`Team avg: ${teamAvg.rts_rate.toFixed(1)}%`}
                            icon={RotateCcw}
                            iconBg={rtsRateColor === 'red' ? 'bg-red-50 dark:bg-red-950/40' : rtsRateColor === 'amber' ? 'bg-amber-50 dark:bg-amber-950/40' : 'bg-emerald-50 dark:bg-emerald-950/40'}
                            iconColor={rtsRateColor === 'red' ? 'text-red-600 dark:text-red-400' : rtsRateColor === 'amber' ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'}
                            badge={{ text: rtsRateColor === 'green' ? 'Good' : rtsRateColor === 'amber' ? 'Monitor' : 'High RTS', color: rtsRateColor }}
                        />
                        <KpiCard
                            label="Call Time"
                            value={formatCallTime(myMonthly.total_call_time)}
                            sub={`${myMonthly.total_called.toLocaleString()} calls made`}
                            icon={PhoneCall}
                            iconBg="bg-violet-50 dark:bg-violet-950/40"
                            iconColor="text-violet-600 dark:text-violet-400"
                        />
                    </div>
                </div>

                {/* ── Sales Trend + My Performance ── */}
                <div className="grid gap-4 lg:grid-cols-[1fr_360px]">
                    <Section title="Sales & Orders Trend" desc={monthLabel}>
                        <SalesTrendChart data={dailyTrend} />
                    </Section>
                    <Section
                        title="My Performance"
                        desc={`vs team average · ${monthLabel}`}
                        action={{ label: 'Full Analytics', href: `/workspaces/${slug}/csr/analytics` }}
                    >
                        <MyPerformance mine={myMonthly} avg={teamAvg} />
                    </Section>
                </div>

                {/* ── Delivery + RTS Trend + Status Breakdown ── */}
                <div className="grid gap-4 lg:grid-cols-3">
                    <Section title="Delivered vs Returning" desc={monthLabel}>
                        <DeliveryChart data={dailyTrend} />
                    </Section>
                    <Section title="RTS Rate Trend" desc={`${monthLabel} · lower is better`}>
                        <RtsTrendChart data={dailyTrend} />
                    </Section>
                    <Section title="Status Breakdown" desc={`${totalStatusCount.toLocaleString()} total orders`}>
                        <StatusPieChart data={statusBreakdown} />
                    </Section>
                </div>

                {/* ── Team Leaderboard ── */}
                <Section
                    title="Team Leaderboard"
                    desc={monthLabel}
                    action={{ label: 'View Analytics', href: `/workspaces/${slug}/csr/analytics` }}
                >
                    <TeamLeaderboard csrs={topCsrs} authUserId={authUserId} />
                </Section>

                {/* ── Pending Orders + Schedule ── */}
                <div className="grid gap-4 pb-6 lg:grid-cols-2">
                    <Section
                        title="My Pending Orders"
                        desc={`${pendingOrders.length} order${pendingOrders.length !== 1 ? 's' : ''} awaiting action`}
                        action={{ label: 'Open RMO', href: `/workspaces/${slug}/csr/rmo-management` }}
                    >
                        <PendingOrdersTable orders={pendingOrders} />
                    </Section>
                    <Section
                        title="CSR Schedule"
                        desc={csrSchedules.length > 0 ? `${csrSchedules.length} shift${csrSchedules.length !== 1 ? 's' : ''} in this period` : undefined}
                    >
                        <CsrScheduleList schedules={csrSchedules} pancakeAccounts={pancakeAccounts} />
                    </Section>
                </div>

            </div>
        </CsrLayout>
    );
}
