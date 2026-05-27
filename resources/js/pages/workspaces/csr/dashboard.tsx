import PageHeader from '@/components/common/PageHeader';
import {
    ChartConfig,
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import DatePicker from '@/components/ui/date-picker';
import CsrLayout from '@/layouts/csr-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import type flatpickr from 'flatpickr';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowDown,
    ArrowRight,
    ArrowUp,
    Clock,
    Minus,
    Package,
    PhoneCall,
    RotateCcw,
    Truck,
} from 'lucide-react';
import moment from 'moment';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Line,
    Pie,
    PieChart,
    XAxis,
    YAxis,
} from 'recharts';
import DateOption = flatpickr.Options.DateOption;

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface TopCsr {
    pancake_user_id: string;
    csr_name: string;
    total_sales: number;
    total_orders: number;
    delivered: number;
    returning_count: number;
    rts_rate: number;
}

interface PendingOrder {
    id: number;
    order_id: number;
    rider_name: string | null;
    order: {
        id: number;
        order_number: string;
        tracking_code: string | null;
        final_amount: number;
        shipping_address?: { full_name: string } | null;
    };
}

interface DailyTrendEntry {
    date: string;
    orders: number;
    sales: number;
    delivered: number;
    returning: number;
    rts_rate: number;
    called: number;
    call_time: number;
}

interface StatusBreakdownEntry {
    status: string;
    count: number;
}

interface CsrScheduleEntry {
    id: number;
    pancake_user_id: string;
    name: string;
    date: string;
    shift_start: string;
    shift_end: string;
    notes: string | null;
}

interface MonthlyPerf {
    total_orders: number;
    total_sales: number;
    delivered: number;
    returning_count: number;
    rts_rate: number;
    total_called: number;
    total_call_time: number;
}

interface Props {
    workspace: Workspace;
    pancakeAccounts: { id: string; name: string }[];
    myTodayStats: {
        assigned: number;
        called: number;
        delivered: number;
        returning: number;
        pending: number;
    };
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

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const peso = (n: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(n) || 0);

const pesoCompact = (n: number) => {
    const v = Number(n) || 0;
    if (v >= 1_000_000) return `₱${(v / 1_000_000).toFixed(1)}M`;
    if (v >= 1_000) return `₱${(v / 1_000).toFixed(1)}K`;
    return `₱${v.toFixed(0)}`;
};

const formatCallTime = (seconds: number) => {
    const s = Math.max(0, Math.floor(Number(seconds) || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = (n: number) => n.toString().padStart(2, '0');
    return h > 0 ? `${h}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
};

const shortDate = (dateStr: string) =>
    new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });

const fullDate = (dateStr: string) =>
    new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });

const STATUS_COLORS: Record<string, string> = {
    PENDING: '#eab308',
    DELIVERED: '#10b981',
    'RIDER OTW': '#3b82f6',
    RETURNING: '#f97316',
    RESCHEDULED: '#a855f7',
    'CX CBR': '#ef4444',
    'RIDER CBR': '#dc2626',
    CANCELLED: '#9ca3af',
    'WRONG SEGMENT CODE': '#f43f5e',
    'CX RINGING': '#f59e0b',
    'RIDER RINGING': '#d97706',
    'IN TRANSIT': '#06b6d4',
    'INCORRECT NUMBER': '#ec4899',
    'AUTO DROP CX': '#be185d',
    'AUTO DROP RIDER': '#6366f1',
};

const fallbackColor = '#71717a';

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function StatCard({
    title,
    value,
    icon: Icon,
    subtext,
    accent,
}: {
    title: string;
    value: string | number;
    icon: LucideIcon;
    subtext?: string;
    accent?: string;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {title}
                </p>
                <div
                    className={`rounded-lg p-2 ${accent ?? 'bg-stone-100 dark:bg-zinc-800'}`}
                >
                    <Icon className="h-5 w-5 text-gray-700 dark:text-white/90" />
                </div>
            </div>
            <h4 className="mt-3 font-mono text-[22px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                {value}
            </h4>
            {subtext && (
                <p className="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                    {subtext}
                </p>
            )}
        </div>
    );
}

function SectionCard({
    title,
    desc,
    action,
    children,
    className,
}: {
    title: string;
    desc?: string;
    action?: { label: string; href: string };
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div
            className={`overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900 ${className ?? ''}`}
        >
            <div className="flex items-center justify-between px-6 py-5">
                <div>
                    <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                        {title}
                    </h3>
                    {desc && (
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {desc}
                        </p>
                    )}
                </div>
                {action && (
                    <Link
                        href={action.href}
                        className="flex items-center gap-1 text-[11px] font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300"
                    >
                        {action.label}
                        <ArrowRight className="h-3 w-3" />
                    </Link>
                )}
            </div>
            {children}
        </div>
    );
}

function ComparisonBadge({
    mine,
    avg,
    invert = false,
}: {
    mine: number;
    avg: number;
    invert?: boolean;
}) {
    if (avg === 0) return null;
    const diff = ((mine - avg) / avg) * 100;
    const isUp = diff > 0;
    const isBetter = invert ? !isUp : isUp;
    const Icon = diff === 0 ? Minus : isUp ? ArrowUp : ArrowDown;

    return (
        <span
            className={`inline-flex items-center gap-0.5 rounded-xl px-2 py-0.5 text-[10px] font-medium ${
                isBetter
                    ? 'bg-green-50 text-green-600 dark:bg-green-950/30 dark:text-green-400'
                    : diff === 0
                      ? 'bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                      : 'bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-400'
            }`}
        >
            <Icon className="h-3 w-3" />
            {Math.abs(diff).toFixed(0)}%
        </span>
    );
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex h-52 items-center justify-center px-6 py-10">
            <p className="text-sm text-gray-400 dark:text-gray-500">
                {message}
            </p>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Chart configs
// ---------------------------------------------------------------------------

const salesTrendConfig: ChartConfig = {
    sales: {
        label: 'Sales (₱)',
        theme: { light: '#10d3a1', dark: '#10d3a1' },
    },
    orders: {
        label: 'Orders',
        theme: { light: '#0ba5ec', dark: '#36bffa' },
    },
};

const deliveryTrendConfig: ChartConfig = {
    delivered: {
        label: 'Delivered',
        theme: { light: '#10b981', dark: '#34d399' },
    },
    returning: {
        label: 'Returning',
        theme: { light: '#f97316', dark: '#fb923c' },
    },
};

const rtsTrendConfig: ChartConfig = {
    rts_rate: {
        label: 'RTS Rate %',
        theme: { light: '#ef4444', dark: '#f87171' },
    },
};

const callTrendConfig: ChartConfig = {
    called: {
        label: 'Calls Made',
        theme: { light: '#8b5cf6', dark: '#a78bfa' },
    },
};

const statusBreakdownConfig: ChartConfig = Object.fromEntries(
    Object.entries(STATUS_COLORS).map(([status, color]) => [
        status,
        { label: status, color },
    ]),
);

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function CsrDashboard({
    workspace,
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
    const hasAccount = pancakeAccounts.length > 0;
    const totalToday = myTodayStats.assigned;

    const navigateToRange = (newFrom: string, newTo: string) => {
        router.get(
            `/workspaces/${slug}/csr/dashboard`,
            { from: newFrom, to: newTo },
            { preserveState: true, preserveScroll: true },
        );
    };

    const todayStr = today;
    const nowDate = new Date(todayStr + 'T00:00:00');
    const defaultFrom = `${nowDate.getFullYear()}-${String(nowDate.getMonth() + 1).padStart(2, '0')}-01`;
    const isDefault = from === defaultFrom && to === todayStr;

    return (
        <CsrLayout>
            <Head title={`${workspace.name} - CSR Dashboard`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Dashboard"
                    description={
                        hasAccount
                            ? `Welcome back, ${pancakeAccounts[0].name}`
                            : 'CSR Dashboard'
                    }
                    stackActionsOnMobile
                >
                    <div className="flex items-center gap-1.5">
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
                                onClick={() =>
                                    navigateToRange(defaultFrom, todayStr)
                                }
                                className="h-9 rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400 dark:hover:bg-emerald-950/50"
                            >
                                This Month
                            </button>
                        )}
                    </div>
                </PageHeader>

                {/* ── Row 1: Stats + Schedule side by side ──────────────── */}
                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="grid grid-cols-2 gap-2 md:gap-3 lg:col-span-2 lg:grid-cols-3">
                        <StatCard
                            title="Assigned"
                            value={totalToday}
                            icon={Package}
                            subtext="Total for delivery"
                            accent="bg-blue-50 dark:bg-blue-950/30"
                        />
                        <StatCard
                            title="Pending"
                            value={myTodayStats.pending}
                            icon={Clock}
                            accent="bg-amber-50 dark:bg-amber-950/30"
                            subtext={
                                totalToday > 0
                                    ? `${((myTodayStats.pending / totalToday) * 100).toFixed(0)}% remaining`
                                    : undefined
                            }
                        />
                        <StatCard
                            title="Called"
                            value={myTodayStats.called}
                            icon={PhoneCall}
                            accent="bg-violet-50 dark:bg-violet-950/30"
                            subtext={
                                totalToday > 0
                                    ? `${((myTodayStats.called / totalToday) * 100).toFixed(0)}% contacted`
                                    : undefined
                            }
                        />
                        <StatCard
                            title="Delivered"
                            value={myTodayStats.delivered}
                            icon={Truck}
                            accent="bg-emerald-50 dark:bg-emerald-950/30"
                        />
                        <StatCard
                            title="Returning"
                            value={myTodayStats.returning}
                            icon={RotateCcw}
                            accent="bg-orange-50 dark:bg-orange-950/30"
                        />
                        <StatCard
                            title="Progress"
                            value={
                                totalToday > 0
                                    ? `${(((totalToday - myTodayStats.pending) / totalToday) * 100).toFixed(0)}%`
                                    : '—'
                            }
                            icon={Truck}
                            subtext={
                                totalToday > 0
                                    ? `${totalToday - myTodayStats.pending} of ${totalToday} done`
                                    : undefined
                            }
                            accent="bg-teal-50 dark:bg-teal-950/30"
                        />
                    </div>

                    {/* CSR Schedule */}
                    <SectionCard
                        title="CSR Schedule"
                        desc={`${csrSchedules.length} shift${csrSchedules.length !== 1 ? 's' : ''}`}
                    >
                        {csrSchedules.length === 0 ? (
                            <EmptyState message="No schedules for this period." />
                        ) : (
                            <div className="max-h-[280px] overflow-y-auto">
                                <table className="w-full">
                                    <tbody>
                                        {csrSchedules.map((s) => {
                                            const isMine =
                                                hasAccount &&
                                                pancakeAccounts.some(
                                                    (a) =>
                                                        a.id ===
                                                        s.pancake_user_id,
                                                );
                                            return (
                                                <tr
                                                    key={s.id}
                                                    className={`border-t border-black/4 dark:border-white/4 ${isMine ? 'bg-emerald-50/50 dark:bg-emerald-500/5' : ''}`}
                                                >
                                                    <td className="px-5 py-2.5">
                                                        <p className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                                                            {s.name}
                                                            {isMine && (
                                                                <span className="ml-1 text-[10px] text-emerald-600 dark:text-emerald-400">
                                                                    (You)
                                                                </span>
                                                            )}
                                                        </p>
                                                        {s.notes && (
                                                            <p className="text-[10px] text-gray-400 dark:text-gray-500">
                                                                {s.notes}
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-2.5 text-right text-[11px] text-gray-500 dark:text-gray-400">
                                                        {shortDate(s.date)}
                                                    </td>
                                                    <td className="px-5 py-2.5 text-right font-mono text-[11px] text-gray-500 tabular-nums dark:text-gray-400">
                                                        {s.shift_start} –{' '}
                                                        {s.shift_end}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                </div>

                {/* ── Row 2: Sales & Orders Trend + Status Breakdown ───── */}
                <div className="mt-6 grid gap-4 lg:grid-cols-3">
                    <SectionCard
                        title="Sales & Orders"
                        desc={monthLabel}
                        className="lg:col-span-2"
                    >
                        {dailyTrend.length === 0 ? (
                            <EmptyState message="No trend data available." />
                        ) : (
                            <div className="p-4 sm:p-6">
                                <ChartContainer
                                    id="csr-sales-trend"
                                    config={salesTrendConfig}
                                    className="aspect-auto h-[280px] w-full"
                                >
                                    <AreaChart data={dailyTrend}>
                                        <defs>
                                            <linearGradient
                                                id="salesGrad"
                                                x1="0"
                                                y1="0"
                                                x2="0"
                                                y2="1"
                                            >
                                                <stop
                                                    offset="0%"
                                                    stopColor="var(--color-sales)"
                                                    stopOpacity={0.2}
                                                />
                                                <stop
                                                    offset="95%"
                                                    stopColor="var(--color-sales)"
                                                    stopOpacity={0}
                                                />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid
                                            strokeDasharray="3 3"
                                            vertical={false}
                                        />
                                        <XAxis
                                            dataKey="date"
                                            tickLine={false}
                                            axisLine={false}
                                            tickMargin={12}
                                            tickFormatter={shortDate}
                                        />
                                        <YAxis
                                            yAxisId="sales"
                                            orientation="left"
                                            tickLine={false}
                                            axisLine={false}
                                            tickMargin={8}
                                            tickFormatter={pesoCompact}
                                            width={54}
                                        />
                                        <YAxis
                                            yAxisId="orders"
                                            orientation="right"
                                            tickLine={false}
                                            axisLine={false}
                                            tickMargin={8}
                                            width={32}
                                        />
                                        <ChartTooltip
                                            content={
                                                <ChartTooltipContent
                                                    labelFormatter={fullDate}
                                                    formatter={(
                                                        value,
                                                        name,
                                                    ) => {
                                                        if (name === 'sales')
                                                            return [
                                                                peso(
                                                                    Number(
                                                                        value,
                                                                    ),
                                                                ),
                                                                'Sales',
                                                            ];
                                                        return [
                                                            Number(
                                                                value,
                                                            ).toLocaleString(),
                                                            'Orders',
                                                        ];
                                                    }}
                                                />
                                            }
                                        />
                                        <Area
                                            yAxisId="sales"
                                            type="monotone"
                                            dataKey="sales"
                                            stroke="var(--color-sales)"
                                            strokeWidth={2}
                                            fill="url(#salesGrad)"
                                            dot={false}
                                            activeDot={{ r: 4, strokeWidth: 2 }}
                                        />
                                        <Line
                                            yAxisId="orders"
                                            type="monotone"
                                            dataKey="orders"
                                            stroke="var(--color-orders)"
                                            strokeWidth={2}
                                            dot={false}
                                            activeDot={{ r: 4, strokeWidth: 2 }}
                                        />
                                        <ChartLegend
                                            content={<ChartLegendContent />}
                                        />
                                    </AreaChart>
                                </ChartContainer>
                            </div>
                        )}
                    </SectionCard>

                    <SectionCard title="Status Breakdown" desc="Order statuses">
                        {statusBreakdown.length === 0 ? (
                            <EmptyState message="No orders for this date." />
                        ) : (
                            <div className="p-4 sm:p-6">
                                <ChartContainer
                                    id="csr-status-breakdown"
                                    config={statusBreakdownConfig}
                                    className="mx-auto aspect-square h-[200px]"
                                >
                                    <PieChart>
                                        <Pie
                                            data={statusBreakdown}
                                            dataKey="count"
                                            nameKey="status"
                                            cx="50%"
                                            cy="50%"
                                            innerRadius={55}
                                            outerRadius={85}
                                            paddingAngle={3}
                                            strokeWidth={0}
                                        >
                                            {statusBreakdown.map((entry) => (
                                                <Cell
                                                    key={entry.status}
                                                    fill={
                                                        STATUS_COLORS[
                                                            entry.status
                                                        ] ?? fallbackColor
                                                    }
                                                />
                                            ))}
                                        </Pie>
                                        <ChartTooltip
                                            content={
                                                <ChartTooltipContent
                                                    hideLabel
                                                />
                                            }
                                        />
                                    </PieChart>
                                </ChartContainer>
                                <div className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2">
                                    {statusBreakdown.map((entry) => (
                                        <div
                                            key={entry.status}
                                            className="flex items-center justify-between gap-2"
                                        >
                                            <div className="flex min-w-0 items-center gap-1.5">
                                                <span
                                                    className="inline-block h-2.5 w-2.5 shrink-0 rounded-sm"
                                                    style={{
                                                        backgroundColor:
                                                            STATUS_COLORS[
                                                                entry.status
                                                            ] ?? fallbackColor,
                                                    }}
                                                />
                                                <span className="truncate text-[11px] text-gray-500 dark:text-gray-400">
                                                    {entry.status}
                                                </span>
                                            </div>
                                            <span className="font-mono text-[11px] font-semibold text-gray-800 tabular-nums dark:text-gray-200">
                                                {entry.count}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </SectionCard>
                </div>

                {/* ── Row 3: Delivery Trend + RTS Rate ─────────────────── */}
                <div className="mt-6 grid gap-4 lg:grid-cols-2">
                    <SectionCard
                        title="Delivered vs Returning"
                        desc={monthLabel}
                    >
                        {dailyTrend.length === 0 ? (
                            <EmptyState message="No data available." />
                        ) : (
                            <div className="p-4 sm:p-6">
                                <ChartContainer
                                    id="csr-delivery-trend"
                                    config={deliveryTrendConfig}
                                    className="aspect-auto h-[240px] w-full"
                                >
                                    <BarChart
                                        data={dailyTrend}
                                        barGap={2}
                                        barCategoryGap="20%"
                                    >
                                        <CartesianGrid
                                            strokeDasharray="3 3"
                                            vertical={false}
                                        />
                                        <XAxis
                                            dataKey="date"
                                            tickLine={false}
                                            axisLine={false}
                                            tickMargin={12}
                                            tickFormatter={shortDate}
                                        />
                                        <YAxis
                                            tickLine={false}
                                            axisLine={false}
                                            tickMargin={8}
                                            width={32}
                                        />
                                        <ChartTooltip
                                            content={
                                                <ChartTooltipContent
                                                    labelFormatter={fullDate}
                                                />
                                            }
                                        />
                                        <Bar
                                            dataKey="delivered"
                                            fill="var(--color-delivered)"
                                            radius={[5, 5, 0, 0]}
                                        />
                                        <Bar
                                            dataKey="returning"
                                            fill="var(--color-returning)"
                                            radius={[5, 5, 0, 0]}
                                        />
                                        <ChartLegend
                                            content={<ChartLegendContent />}
                                        />
                                    </BarChart>
                                </ChartContainer>
                            </div>
                        )}
                    </SectionCard>

                    <SectionCard title="RTS Rate" desc="Last 14 days trend">
                        {dailyTrend.length === 0 ? (
                            <EmptyState message="No data available." />
                        ) : (
                            <div className="p-4 sm:p-6">
                                <ChartContainer
                                    id="csr-rts-trend"
                                    config={rtsTrendConfig}
                                    className="aspect-auto h-[240px] w-full"
                                >
                                    <AreaChart data={dailyTrend}>
                                        <defs>
                                            <linearGradient
                                                id="rtsGrad"
                                                x1="0"
                                                y1="0"
                                                x2="0"
                                                y2="1"
                                            >
                                                <stop
                                                    offset="0%"
                                                    stopColor="var(--color-rts_rate)"
                                                    stopOpacity={0.15}
                                                />
                                                <stop
                                                    offset="95%"
                                                    stopColor="var(--color-rts_rate)"
                                                    stopOpacity={0}
                                                />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid
                                            strokeDasharray="3 3"
                                            vertical={false}
                                        />
                                        <XAxis
                                            dataKey="date"
                                            tickLine={false}
                                            axisLine={false}
                                            tickMargin={12}
                                            tickFormatter={shortDate}
                                        />
                                        <YAxis
                                            tickLine={false}
                                            axisLine={false}
                                            tickMargin={8}
                                            tickFormatter={(v) => `${v}%`}
                                            domain={[0, 'auto']}
                                            width={40}
                                        />
                                        <ChartTooltip
                                            content={
                                                <ChartTooltipContent
                                                    labelFormatter={fullDate}
                                                    formatter={(value) => [
                                                        `${Number(value).toFixed(1)}%`,
                                                        'RTS Rate',
                                                    ]}
                                                />
                                            }
                                        />
                                        <Area
                                            type="monotone"
                                            dataKey="rts_rate"
                                            stroke="var(--color-rts_rate)"
                                            strokeWidth={2}
                                            fill="url(#rtsGrad)"
                                            dot={false}
                                            activeDot={{ r: 4, strokeWidth: 2 }}
                                        />
                                    </AreaChart>
                                </ChartContainer>
                            </div>
                        )}
                    </SectionCard>
                </div>

                {/* ── Row 4: Performance vs Team + Call Activity ────────── */}
                <div className="mt-6 grid gap-4 lg:grid-cols-2">
                    <SectionCard
                        title={`My Performance — ${monthLabel}`}
                        desc="Compared to team average"
                        action={{
                            label: 'Full Analytics',
                            href: `/workspaces/${slug}/csr/analytics`,
                        }}
                    >
                        <div className="grid grid-cols-2 gap-px border-t border-black/4 bg-black/4 dark:border-white/4 dark:bg-white/4">
                            {(
                                [
                                    {
                                        label: 'Orders',
                                        mine: myMonthly.total_orders,
                                        avg: teamAvg.total_orders,
                                        format: (v: number) =>
                                            v.toLocaleString(),
                                    },
                                    {
                                        label: 'Sales',
                                        mine: myMonthly.total_sales,
                                        avg: teamAvg.total_sales,
                                        format: peso,
                                    },
                                    {
                                        label: 'Delivered',
                                        mine: myMonthly.delivered,
                                        avg: teamAvg.delivered,
                                        format: (v: number) =>
                                            v.toLocaleString(),
                                    },
                                    {
                                        label: 'Returning',
                                        mine: myMonthly.returning_count,
                                        avg: teamAvg.returning_count,
                                        format: (v: number) =>
                                            v.toLocaleString(),
                                        invert: true,
                                    },
                                    {
                                        label: 'RTS Rate',
                                        mine: myMonthly.rts_rate,
                                        avg: teamAvg.rts_rate,
                                        format: (v: number) =>
                                            `${v.toFixed(2)}%`,
                                        invert: true,
                                    },
                                    {
                                        label: 'RMO Called',
                                        mine: myMonthly.total_called,
                                        avg: teamAvg.total_called,
                                        format: (v: number) =>
                                            v.toLocaleString(),
                                    },
                                    {
                                        label: 'Call Time',
                                        mine: myMonthly.total_call_time,
                                        avg: teamAvg.total_call_time,
                                        format: formatCallTime,
                                    },
                                ] as const
                            ).map((item) => (
                                <div
                                    key={item.label}
                                    className="bg-white px-5 py-4 dark:bg-zinc-900"
                                >
                                    <div className="flex items-center gap-2">
                                        <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                                            {item.label}
                                        </p>
                                        <ComparisonBadge
                                            mine={item.mine}
                                            avg={item.avg}
                                            invert={
                                                'invert' in item
                                                    ? item.invert
                                                    : false
                                            }
                                        />
                                    </div>
                                    <h4 className="mt-1.5 font-mono text-lg font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                                        {item.format(item.mine)}
                                    </h4>
                                    <p className="mt-1 text-[10px] text-gray-400 dark:text-gray-500">
                                        Team avg: {item.format(item.avg)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </SectionCard>

                    <SectionCard title="Call Activity" desc={monthLabel}>
                        {dailyTrend.length === 0 ? (
                            <EmptyState message="No call data available." />
                        ) : (
                            <div className="p-4 sm:p-6">
                                <ChartContainer
                                    id="csr-call-trend"
                                    config={callTrendConfig}
                                    className="aspect-auto h-[240px] w-full"
                                >
                                    <BarChart
                                        data={dailyTrend}
                                        barCategoryGap="25%"
                                    >
                                        <CartesianGrid
                                            strokeDasharray="3 3"
                                            vertical={false}
                                        />
                                        <XAxis
                                            dataKey="date"
                                            tickLine={false}
                                            axisLine={false}
                                            tickMargin={12}
                                            tickFormatter={shortDate}
                                        />
                                        <YAxis
                                            tickLine={false}
                                            axisLine={false}
                                            tickMargin={8}
                                            width={32}
                                        />
                                        <ChartTooltip
                                            content={
                                                <ChartTooltipContent
                                                    labelFormatter={fullDate}
                                                />
                                            }
                                        />
                                        <Bar
                                            dataKey="called"
                                            fill="var(--color-called)"
                                            radius={[5, 5, 0, 0]}
                                        />
                                    </BarChart>
                                </ChartContainer>
                            </div>
                        )}
                    </SectionCard>
                </div>

                {/* ── Row 5: Pending Orders + Team Leaderboard ───────────── */}
                <div className="mt-6 mb-6 grid gap-4 lg:grid-cols-2">
                    <SectionCard
                        title="My Pending Orders"
                        desc="Orders awaiting action"
                        action={{
                            label: 'Open RMO',
                            href: `/workspaces/${slug}/csr/rmo-management`,
                        }}
                    >
                        {pendingOrders.length === 0 ? (
                            <EmptyState message="No pending orders assigned to you." />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-t border-black/4 text-left text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:border-white/4 dark:text-gray-500">
                                            <th className="px-6 py-2.5">
                                                Order
                                            </th>
                                            <th className="px-6 py-2.5">
                                                Customer
                                            </th>
                                            <th className="px-6 py-2.5">
                                                Rider
                                            </th>
                                            <th className="px-6 py-2.5 text-right">
                                                SRP
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {pendingOrders.map((o) => (
                                            <tr
                                                key={o.id}
                                                className="border-t border-black/4 dark:border-white/4"
                                            >
                                                <td className="px-6 py-2.5">
                                                    <p className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                                                        {o.order.order_number}
                                                    </p>
                                                    {o.order.tracking_code && (
                                                        <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                                            {
                                                                o.order
                                                                    .tracking_code
                                                            }
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-6 py-2.5 text-[12px] text-gray-600 dark:text-gray-400">
                                                    {o.order.shipping_address
                                                        ?.full_name ?? '—'}
                                                </td>
                                                <td className="px-6 py-2.5 text-[12px] text-gray-600 dark:text-gray-400">
                                                    {o.rider_name ?? '—'}
                                                </td>
                                                <td className="px-6 py-2.5 text-right font-mono text-[12px] text-gray-600 tabular-nums dark:text-gray-400">
                                                    {peso(o.order.final_amount)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>

                    <SectionCard
                        title={`Team Leaderboard — ${monthLabel}`}
                        desc="Top performing CSRs"
                        action={{
                            label: 'View Analytics',
                            href: `/workspaces/${slug}/csr/analytics`,
                        }}
                    >
                        {topCsrs.length === 0 ? (
                            <EmptyState message="No CSR data available for this period." />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-t border-black/4 text-left text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:border-white/4 dark:text-gray-500">
                                            <th className="w-12 px-6 py-2.5">
                                                #
                                            </th>
                                            <th className="px-6 py-2.5">CSR</th>
                                            <th className="px-6 py-2.5 text-right">
                                                Orders
                                            </th>
                                            <th className="px-6 py-2.5 text-right">
                                                Sales
                                            </th>
                                            <th className="px-6 py-2.5 text-right">
                                                RTS
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {topCsrs.map((csr, i) => {
                                            const isMine =
                                                hasAccount &&
                                                pancakeAccounts.some(
                                                    (a) =>
                                                        a.id ===
                                                        csr.pancake_user_id,
                                                );
                                            return (
                                                <tr
                                                    key={csr.pancake_user_id}
                                                    className={`border-t border-black/4 dark:border-white/4 ${isMine ? 'bg-emerald-50/50 dark:bg-emerald-500/5' : ''}`}
                                                >
                                                    <td className="px-6 py-3">
                                                        <span
                                                            className={`inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold ${
                                                                i === 0
                                                                    ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'
                                                                    : i === 1
                                                                      ? 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300'
                                                                      : i === 2
                                                                        ? 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-400'
                                                                        : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400'
                                                            }`}
                                                        >
                                                            {i + 1}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-3 text-[12px] font-medium text-gray-700 dark:text-gray-300">
                                                        {csr.csr_name}
                                                        {isMine && (
                                                            <span className="ml-1.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400">
                                                                (You)
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-3 text-right text-[12px] text-gray-600 tabular-nums dark:text-gray-400">
                                                        {Number(
                                                            csr.total_orders,
                                                        ).toLocaleString()}
                                                    </td>
                                                    <td className="px-6 py-3 text-right text-[12px] text-gray-600 tabular-nums dark:text-gray-400">
                                                        {peso(
                                                            Number(
                                                                csr.total_sales,
                                                            ),
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-3 text-right text-[12px] font-medium tabular-nums">
                                                        <span
                                                            className={
                                                                Number(
                                                                    csr.rts_rate,
                                                                ) > 15
                                                                    ? 'text-red-600 dark:text-red-400'
                                                                    : Number(
                                                                            csr.rts_rate,
                                                                        ) > 10
                                                                      ? 'text-amber-600 dark:text-amber-400'
                                                                      : 'text-emerald-600 dark:text-emerald-400'
                                                            }
                                                        >
                                                            {Number(
                                                                csr.rts_rate,
                                                            ).toFixed(1)}
                                                            %
                                                        </span>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                </div>
            </div>
        </CsrLayout>
    );
}
