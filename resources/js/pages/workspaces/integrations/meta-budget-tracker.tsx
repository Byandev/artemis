import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    CalendarDays,
    FileText,
    Minus,
    Package,
    TrendingDown,
    TrendingUp,
    Users,
    Wallet,
    type LucideIcon,
} from 'lucide-react';

interface BudgetRow {
    id: string;
    name: string;
    today: number;
    yesterday: number;
    difference: number;
}

interface Props {
    dates: { today: string; yesterday: string };
    perPage: BudgetRow[];
    perProduct: BudgetRow[];
    perUser: BudgetRow[];
}

/** Exact Philippine-peso formatting with thousands separators and 2 decimals. */
function peso(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

/** Percentage change of today vs yesterday, or null when there is no base. */
function pctChange(today: number, yesterday: number): number | null {
    if (yesterday <= 0) return null;
    return ((today - yesterday) / yesterday) * 100;
}

/** Format a YYYY-MM-DD string as e.g. "Jun 17" without a timezone shift. */
function formatDateLabel(date: string): string {
    const [y, m, d] = date.split('-').map(Number);
    if (!y || !m || !d) return date;
    const months = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
    ];
    return `${months[m - 1]} ${d}`;
}

function TrendPill({ value, pct }: { value: number; pct: number | null }) {
    const positive = value > 0;
    const negative = value < 0;
    const Icon = positive ? ArrowUpRight : negative ? ArrowDownRight : Minus;
    const cls = positive
        ? 'bg-brand-50 text-brand-700 ring-brand-600/15 dark:bg-brand-500/10 dark:text-brand-400 dark:ring-brand-500/20'
        : negative
          ? 'bg-red-50 text-red-600 ring-red-600/15 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20'
          : 'bg-gray-100 text-gray-500 ring-black/5 dark:bg-zinc-800 dark:text-gray-400 dark:ring-white/5';

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium tabular-nums ring-1 ring-inset',
                cls,
            )}
        >
            <Icon className="h-3 w-3" />
            {peso(Math.abs(value))}
            {pct !== null && value !== 0 && (
                <span className="opacity-70">
                    {pct > 0 ? '+' : ''}
                    {pct.toFixed(0)}%
                </span>
            )}
        </span>
    );
}

function InitialAvatar({ name }: { name: string }) {
    const letter = name?.trim()?.charAt(0)?.toUpperCase() || '?';
    return (
        <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-600 text-[11px] font-bold text-white shadow-sm shadow-brand-500/20">
            {letter}
        </span>
    );
}

function KpiCard({
    label,
    value,
    sub,
    icon: Icon,
    iconClass,
    valueClass = 'text-gray-900 dark:text-gray-100',
    glow = false,
    children,
}: {
    label: string;
    value: string;
    sub?: React.ReactNode;
    icon: LucideIcon;
    iconClass: string;
    valueClass?: string;
    glow?: boolean;
    children?: React.ReactNode;
}) {
    return (
        <div className="group relative overflow-hidden rounded-2xl border border-black/6 bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)] transition-all hover:-translate-y-0.5 hover:shadow-[0_10px_34px_rgba(0,0,0,0.07)] dark:border-white/6 dark:bg-zinc-900">
            {glow && (
                <div className="pointer-events-none absolute -top-8 -right-8 h-24 w-24 rounded-full bg-brand-500/10 blur-2xl" />
            )}
            <div className="relative flex items-start justify-between">
                <span className="text-[11px] font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                    {label}
                </span>
                <span
                    className={cn(
                        'flex h-9 w-9 items-center justify-center rounded-[10px] shadow-sm',
                        iconClass,
                    )}
                >
                    <Icon className="h-[18px] w-[18px]" />
                </span>
            </div>
            <div
                className={cn(
                    'relative mt-3.5 text-[26px] leading-none font-semibold tracking-tight tabular-nums',
                    valueClass,
                )}
            >
                {value}
            </div>
            {sub && (
                <div className="relative mt-2 text-[11px] text-gray-400 dark:text-gray-500">
                    {sub}
                </div>
            )}
            {children && <div className="relative mt-2.5">{children}</div>}
        </div>
    );
}

function BudgetTable({
    label,
    icon: Icon,
    rows,
    dates,
}: {
    label: string;
    icon: LucideIcon;
    rows: BudgetRow[];
    dates: { today: string; yesterday: string };
}) {
    const totals = rows.reduce(
        (acc, r) => ({
            today: acc.today + r.today,
            yesterday: acc.yesterday + r.yesterday,
            difference: acc.difference + r.difference,
        }),
        { today: 0, yesterday: 0, difference: 0 },
    );
    const maxToday = rows.reduce((m, r) => Math.max(m, r.today), 0);

    return (
        <div className="overflow-hidden rounded-2xl border border-black/6 bg-white shadow-[0_1px_3px_rgba(0,0,0,0.04)] dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-center justify-between border-b border-black/5 px-5 py-3.5 dark:border-white/5">
                <div className="flex items-center gap-2">
                    <Icon className="h-4 w-4 text-brand-600 dark:text-brand-400" />
                    <span className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                        {label}
                    </span>
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500 tabular-nums dark:bg-zinc-800 dark:text-gray-400">
                        {rows.length}
                    </span>
                </div>
                <span className="text-[11px] text-gray-400 dark:text-gray-500">
                    {formatDateLabel(dates.yesterday)} →{' '}
                    {formatDateLabel(dates.today)}
                </span>
            </div>

            <table className="w-full">
                <thead>
                    <tr className="text-left text-[10px] font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        <th className="py-2.5 pr-3 pl-5 font-semibold">
                            {label}
                        </th>
                        <th className="px-3 py-2.5 text-right font-semibold">
                            Today
                        </th>
                        <th className="px-3 py-2.5 text-right font-semibold">
                            Yesterday
                        </th>
                        <th className="py-2.5 pr-5 pl-3 text-right font-semibold">
                            Difference
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td colSpan={4} className="px-5 py-14">
                                <div className="flex flex-col items-center justify-center gap-2 text-center">
                                    <span className="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-zinc-800 dark:text-gray-500">
                                        <Wallet className="h-5 w-5" />
                                    </span>
                                    <span className="text-[13px] font-medium text-gray-500 dark:text-gray-400">
                                        No budget recorded
                                    </span>
                                    <span className="text-[11px] text-gray-400 dark:text-gray-500">
                                        Nothing for today or yesterday yet.
                                    </span>
                                </div>
                            </td>
                        </tr>
                    ) : (
                        rows.map((row, i) => (
                            <tr
                                key={row.id}
                                className="group border-t border-black/5 transition-colors hover:bg-brand-50/40 dark:border-white/5 dark:hover:bg-brand-500/5"
                            >
                                <td className="py-3 pr-3 pl-5">
                                    <div className="flex items-center gap-3">
                                        <span className="w-4 text-right text-[11px] font-medium text-gray-300 tabular-nums dark:text-gray-600">
                                            {i + 1}
                                        </span>
                                        <InitialAvatar name={row.name} />
                                        <span className="truncate text-[13px] font-medium text-gray-800 dark:text-gray-100">
                                            {row.name}
                                        </span>
                                    </div>
                                </td>
                                <td className="px-3 py-3">
                                    <div className="flex flex-col items-end gap-1.5">
                                        <span className="text-[13px] font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                                            {peso(row.today)}
                                        </span>
                                        <span className="block h-1 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-zinc-800">
                                            <span
                                                className="block h-full rounded-full bg-gradient-to-r from-brand-400 to-brand-600"
                                                style={{
                                                    width: `${maxToday > 0 ? (row.today / maxToday) * 100 : 0}%`,
                                                }}
                                            />
                                        </span>
                                    </div>
                                </td>
                                <td className="px-3 py-3 text-right text-[13px] text-gray-500 tabular-nums dark:text-gray-400">
                                    {peso(row.yesterday)}
                                </td>
                                <td className="py-3 pr-5 pl-3 text-right">
                                    <TrendPill
                                        value={row.difference}
                                        pct={pctChange(
                                            row.today,
                                            row.yesterday,
                                        )}
                                    />
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
                {rows.length > 0 && (
                    <tfoot>
                        <tr className="border-t border-black/10 bg-stone-50/70 dark:border-white/10 dark:bg-zinc-800/50">
                            <td className="py-3 pr-3 pl-5 text-[12px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                Total
                            </td>
                            <td className="px-3 py-3 text-right text-[13px] font-bold text-gray-900 tabular-nums dark:text-gray-100">
                                {peso(totals.today)}
                            </td>
                            <td className="px-3 py-3 text-right text-[13px] font-semibold text-gray-500 tabular-nums dark:text-gray-400">
                                {peso(totals.yesterday)}
                            </td>
                            <td className="py-3 pr-5 pl-3 text-right">
                                <TrendPill
                                    value={totals.difference}
                                    pct={pctChange(
                                        totals.today,
                                        totals.yesterday,
                                    )}
                                />
                            </td>
                        </tr>
                    </tfoot>
                )}
            </table>
        </div>
    );
}

export default function MetaBudgetTracker({
    dates,
    perPage,
    perProduct,
    perUser,
}: Props) {
    const totals = perPage.reduce(
        (acc, r) => ({
            today: acc.today + r.today,
            yesterday: acc.yesterday + r.yesterday,
        }),
        { today: 0, yesterday: 0 },
    );
    const net = totals.today - totals.yesterday;
    const netPct = pctChange(totals.today, totals.yesterday);
    const NetIcon = net > 0 ? TrendingUp : net < 0 ? TrendingDown : Minus;

    return (
        <AppLayout>
            <Head title="Meta Ads · Ad Spent Budget Tracker" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4 border-b border-black/6 pb-5 dark:border-white/6">
                    <div className="flex items-center gap-3.5">
                        <span className="flex h-11 w-11 items-center justify-center rounded-[13px] bg-gradient-to-br from-brand-500 to-brand-600 text-white shadow-lg shadow-brand-500/25">
                            <Wallet className="h-5 w-5" />
                        </span>
                        <div className="min-w-0">
                            <h1 className="text-[22px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                                Ad Spent Budget Tracker
                            </h1>
                            <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                                Page daily budget by page, product and user —
                                today vs yesterday.
                            </p>
                        </div>
                    </div>
                    <div className="hidden shrink-0 items-center gap-1.5 rounded-full border border-black/6 bg-white px-3.5 py-1.5 text-[11px] font-medium text-gray-500 shadow-sm sm:flex dark:border-white/6 dark:bg-zinc-900 dark:text-gray-400">
                        <CalendarDays className="h-3.5 w-3.5 text-brand-500" />
                        {formatDateLabel(dates.yesterday)} →{' '}
                        {formatDateLabel(dates.today)}
                    </div>
                </div>

                {/* KPI summary */}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <KpiCard
                        label="Today's Budget"
                        value={peso(totals.today)}
                        sub={`${formatDateLabel(dates.today)} · ${perPage.length} pages`}
                        icon={Wallet}
                        iconClass="bg-gradient-to-br from-brand-500 to-brand-600 text-white shadow-brand-500/20"
                    />
                    <KpiCard
                        label="Yesterday's Budget"
                        value={peso(totals.yesterday)}
                        sub={formatDateLabel(dates.yesterday)}
                        icon={CalendarDays}
                        iconClass="bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400"
                    />
                    <KpiCard
                        label="Net Change"
                        value={`${net >= 0 ? '+' : '−'}${peso(Math.abs(net))}`}
                        icon={NetIcon}
                        glow
                        valueClass={
                            net > 0
                                ? 'text-brand-600 dark:text-brand-400'
                                : net < 0
                                  ? 'text-red-600 dark:text-red-400'
                                  : 'text-gray-500 dark:text-gray-400'
                        }
                        iconClass={
                            net > 0
                                ? 'bg-gradient-to-br from-brand-500 to-brand-600 text-white shadow-brand-500/20'
                                : net < 0
                                  ? 'bg-gradient-to-br from-red-500 to-red-600 text-white shadow-red-500/20'
                                  : 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400'
                        }
                    >
                        <TrendPill value={net} pct={netPct} />
                    </KpiCard>
                </div>

                {/* Breakdown tabs */}
                <Tabs defaultValue="page" className="gap-4">
                    <TabsList className="h-10 p-1">
                        <TabsTrigger
                            value="page"
                            className="gap-1.5 px-3"
                        >
                            <FileText className="h-3.5 w-3.5" />
                            Per Page
                        </TabsTrigger>
                        <TabsTrigger
                            value="product"
                            className="gap-1.5 px-3"
                        >
                            <Package className="h-3.5 w-3.5" />
                            Per Product
                        </TabsTrigger>
                        <TabsTrigger
                            value="user"
                            className="gap-1.5 px-3"
                        >
                            <Users className="h-3.5 w-3.5" />
                            Per User
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="page">
                        <BudgetTable
                            label="Page"
                            icon={FileText}
                            rows={perPage}
                            dates={dates}
                        />
                    </TabsContent>
                    <TabsContent value="product">
                        <BudgetTable
                            label="Product"
                            icon={Package}
                            rows={perProduct}
                            dates={dates}
                        />
                    </TabsContent>
                    <TabsContent value="user">
                        <BudgetTable
                            label="User"
                            icon={Users}
                            rows={perUser}
                            dates={dates}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
