import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Check, ChevronRight } from 'lucide-react';

interface StatementContext {
    id: number;
    period_month: string; // YYYY-MM-DD
    month: string; // YYYY-MM
    label: string; // "July 2026"
}

interface PnlRow {
    user_id: number | null;
    name: string;
    orders: number;
    delivered: number;
    cost_of_sales: number;
    gross_profit: number;
    advisory: number;
    opex: number;
    net_profit: number;
}

interface Props {
    workspace: Workspace;
    incomeStatement: StatementContext;
    users: PnlRow[];
    total: PnlRow;
    discrepancy: PnlRow;
}

const fmt = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const int = (v: number) => Number(v).toLocaleString('en-PH');
const pct = (v: number) => `${v < 0 ? '−' : ''}${Math.abs(v).toFixed(1)}%`;
const clamp = (v: number) => Math.max(0, Math.min(100, v));
const margin = (r: PnlRow) =>
    r.delivered > 0 ? (r.net_profit / r.delivered) * 100 : 0;

const CARD =
    'rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900';

// Row/segment identity colors — distinct hues, tied between the bar and table.
const PALETTE = [
    '#6366f1',
    '#10b981',
    '#f59e0b',
    '#ec4899',
    '#0ea5e9',
    '#8b5cf6',
    '#14b8a6',
    '#f43f5e',
];

export default function UserIncomeStatementsIndex({
    workspace,
    incomeStatement,
    users,
    total,
    discrepancy,
}: Props) {
    const finance = `/workspaces/${workspace.slug}/finance`;
    const base = `${finance}/income-statements/${incomeStatement.id}/users`;

    const totalDelivered = total.delivered || 0;

    // Delivered and orders should reconcile to the overall statement; anything
    // left over is revenue no user is credited with — something to resolve.
    const hasDiscrepancy =
        discrepancy.orders !== 0 || Math.abs(discrepancy.delivered) >= 0.01;

    const colorFor = (i: number) => PALETTE[i % PALETTE.length];

    const segments = users
        .map((u, i) => ({
            key: String(u.user_id),
            label: u.name,
            amount: u.delivered,
            color: colorFor(i),
        }))
        .filter((s) => s.amount > 0)
        .map((s) => ({
            ...s,
            width: totalDelivered > 0 ? (s.amount / totalDelivered) * 100 : 0,
        }));

    const COL = 'px-4 py-3 text-right tabular-nums';
    const HEAD =
        'px-4 py-3 text-right text-[10px] font-semibold tracking-wider text-gray-400 uppercase';

    const dataCols = (r: PnlRow) => (
        <>
            <td className={`${COL} text-gray-400`}>{int(r.orders)}</td>
            <td className={`${COL} text-gray-800 dark:text-gray-100`}>
                {fmt(r.delivered)}
            </td>
            <td className={`${COL} text-gray-500 dark:text-gray-400`}>
                {fmt(r.cost_of_sales)}
            </td>
            <td className={`${COL} text-gray-800 dark:text-gray-100`}>
                {fmt(r.gross_profit)}
            </td>
            <td className={`${COL} text-gray-500 dark:text-gray-400`}>
                {fmt(r.advisory)}
            </td>
            <td className={`${COL} text-gray-500 dark:text-gray-400`}>
                {fmt(r.opex)}
            </td>
            <td className="px-4 py-3 text-right">
                <div
                    className={`text-[13px] font-semibold tabular-nums ${
                        r.net_profit < 0
                            ? 'text-rose-600 dark:text-rose-400'
                            : 'text-emerald-600 dark:text-emerald-400'
                    }`}
                >
                    {fmt(r.net_profit)}
                </div>
                <div className="mt-1 ml-auto flex w-20 items-center gap-1.5">
                    <div className="h-1 flex-1 overflow-hidden rounded-full bg-stone-100 dark:bg-zinc-800">
                        <div
                            className="h-full rounded-full"
                            style={{
                                width: `${clamp(margin(r))}%`,
                                backgroundColor:
                                    r.net_profit < 0 ? '#f43f5e' : '#10b981',
                            }}
                        />
                    </div>
                    <span className="text-[10px] text-gray-400 tabular-nums">
                        {pct(margin(r))}
                    </span>
                </div>
            </td>
        </>
    );

    return (
        <AppLayout>
            <Head
                title={`${workspace.name} - Per User · ${incomeStatement.label}`}
            />
            <div className="w-full p-4 font-mono md:p-6">
                <PageHeader
                    title="Income Statement — Per User"
                    description={`${incomeStatement.label} · each user's profit & loss`}
                >
                    <Link
                        href={`${finance}/income-statements/${incomeStatement.id}`}
                        className="flex h-8 items-center gap-1.5 rounded-lg border border-black/6 bg-stone-50 px-3 text-[12px] text-gray-600 transition-all hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back to statement
                    </Link>
                </PageHeader>

                {/* Delivered revenue by user */}
                {segments.length > 0 && (
                    <div className={`${CARD} mb-6 p-5`}>
                        <div className="mb-3 flex items-center justify-between">
                            <span className="text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                                Delivered revenue by user
                            </span>
                            <span className="text-[10px] text-gray-400">
                                share of delivered
                            </span>
                        </div>
                        <div className="flex h-3 w-full overflow-hidden rounded-full bg-stone-100 dark:bg-zinc-800">
                            {segments.map((s, i) => (
                                <div
                                    key={s.key}
                                    className={
                                        i > 0
                                            ? 'border-l-2 border-white dark:border-zinc-900'
                                            : ''
                                    }
                                    style={{
                                        width: `${s.width}%`,
                                        backgroundColor: s.color,
                                    }}
                                />
                            ))}
                        </div>
                        <div className="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 lg:grid-cols-4">
                            {segments.map((s) => (
                                <div
                                    key={s.key}
                                    className="flex items-start gap-2"
                                >
                                    <span
                                        className="mt-0.5 h-2.5 w-2.5 shrink-0 rounded-[3px]"
                                        style={{ backgroundColor: s.color }}
                                    />
                                    <div className="min-w-0">
                                        <div className="truncate text-[11px] text-gray-500 dark:text-gray-400">
                                            {s.label}
                                        </div>
                                        <div className="text-[12px] text-gray-800 tabular-nums dark:text-gray-100">
                                            {fmt(s.amount)}
                                            <span className="ml-1 text-gray-400">
                                                · {s.width.toFixed(1)}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* P&L table */}
                <div className={`${CARD} overflow-hidden`}>
                    <div className="flex items-center justify-between border-b border-black/6 px-5 py-4 dark:border-white/6">
                        <div>
                            <div className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                                Per-user P&L
                            </div>
                            <div className="mt-0.5 text-[11px] text-gray-400">
                                {incomeStatement.label} · {int(users.length)}{' '}
                                {users.length === 1 ? 'user' : 'users'}
                            </div>
                        </div>
                        <span className="rounded-full border border-black/6 px-2.5 py-0.5 text-[10px] tracking-wider text-gray-400 uppercase dark:border-white/6">
                            Live
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-black/6 dark:border-white/6">
                                    <th className="px-5 py-3 text-left text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                                        User
                                    </th>
                                    <th className={HEAD}>Orders</th>
                                    <th className={HEAD}>Delivered</th>
                                    <th className={HEAD}>Cost of Sales</th>
                                    <th className={HEAD}>Gross</th>
                                    <th className={HEAD}>Advisory</th>
                                    <th className={HEAD}>OPEX</th>
                                    <th className={`${HEAD} pr-5`}>
                                        Net Profit
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-black/5 dark:divide-white/5">
                                {users.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-5 py-12 text-center text-[12px] text-gray-400"
                                        >
                                            No delivered revenue for{' '}
                                            {incomeStatement.label}.
                                        </td>
                                    </tr>
                                )}

                                {users.map((r, i) => (
                                    <tr
                                        key={r.user_id ?? r.name}
                                        className="group transition-colors hover:bg-stone-50 dark:hover:bg-zinc-800/40"
                                    >
                                        <td className="py-3 pr-4 pl-5">
                                            <Link
                                                href={`${base}/${r.user_id}`}
                                                className="flex items-center gap-3"
                                            >
                                                <span
                                                    className="h-7 w-1 shrink-0 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            colorFor(i),
                                                    }}
                                                />
                                                <span className="min-w-0">
                                                    <span className="block truncate text-[13px] font-medium text-gray-800 group-hover:text-primary dark:text-gray-100">
                                                        {r.name}
                                                    </span>
                                                    <span className="text-[10px] text-gray-400">
                                                        {int(r.orders)} orders
                                                    </span>
                                                </span>
                                                <ChevronRight className="ml-auto h-3.5 w-3.5 shrink-0 text-gray-300 opacity-0 transition-opacity group-hover:opacity-100 dark:text-gray-600" />
                                            </Link>
                                        </td>
                                        {dataCols(r)}
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t border-black/6 bg-stone-100 dark:border-white/6 dark:bg-zinc-800/60">
                                    <td className="py-3.5 pr-4 pl-5 text-[12px] font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-200">
                                        Total
                                    </td>
                                    <td className={`${COL} text-gray-500`}>
                                        {int(total.orders)}
                                    </td>
                                    <td
                                        className={`${COL} font-semibold text-gray-800 dark:text-gray-100`}
                                    >
                                        {fmt(total.delivered)}
                                    </td>
                                    <td
                                        className={`${COL} font-semibold text-gray-600 dark:text-gray-300`}
                                    >
                                        {fmt(total.cost_of_sales)}
                                    </td>
                                    <td
                                        className={`${COL} font-semibold text-gray-800 dark:text-gray-100`}
                                    >
                                        {fmt(total.gross_profit)}
                                    </td>
                                    <td
                                        className={`${COL} font-semibold text-gray-600 dark:text-gray-300`}
                                    >
                                        {fmt(total.advisory)}
                                    </td>
                                    <td
                                        className={`${COL} font-semibold text-gray-600 dark:text-gray-300`}
                                    >
                                        {fmt(total.opex)}
                                    </td>
                                    <td
                                        className={`px-4 py-3.5 pr-5 text-right text-[14px] font-bold tabular-nums ${
                                            total.net_profit < 0
                                                ? 'text-rose-600 dark:text-rose-400'
                                                : 'text-emerald-600 dark:text-emerald-400'
                                        }`}
                                    >
                                        {fmt(total.net_profit)}
                                    </td>
                                </tr>

                                {/* Delivered/orders not credited to any user. Zero
                                    means everything reconciles to the overall
                                    statement; non-zero is something to resolve. */}
                                <tr
                                    className={
                                        hasDiscrepancy
                                            ? 'border-t border-amber-200 bg-amber-50/70 dark:border-amber-500/20 dark:bg-amber-500/10'
                                            : 'border-t border-black/6 dark:border-white/6'
                                    }
                                >
                                    <td className="py-3 pr-4 pl-5">
                                        <div className="flex items-center gap-2">
                                            {hasDiscrepancy ? (
                                                <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-amber-500" />
                                            ) : (
                                                <Check className="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                                            )}
                                            <span>
                                                <span
                                                    className={`block text-[12px] font-semibold tracking-wide uppercase ${
                                                        hasDiscrepancy
                                                            ? 'text-amber-700 dark:text-amber-400'
                                                            : 'text-gray-500 dark:text-gray-400'
                                                    }`}
                                                >
                                                    Discrepancy
                                                </span>
                                                <span className="text-[10px] text-gray-400">
                                                    {hasDiscrepancy
                                                        ? 'revenue not attributed — resolve'
                                                        : 'all revenue attributed'}
                                                </span>
                                            </span>
                                        </div>
                                    </td>
                                    <td
                                        className={`${COL} font-semibold ${
                                            hasDiscrepancy
                                                ? 'text-amber-700 dark:text-amber-400'
                                                : 'text-gray-400'
                                        }`}
                                    >
                                        {int(discrepancy.orders)}
                                    </td>
                                    <td
                                        className={`${COL} font-semibold ${
                                            hasDiscrepancy
                                                ? 'text-amber-700 dark:text-amber-400'
                                                : 'text-gray-400'
                                        }`}
                                    >
                                        {fmt(discrepancy.delivered)}
                                    </td>
                                    <td className={`${COL} text-gray-300 dark:text-gray-600`}>
                                        —
                                    </td>
                                    <td className={`${COL} text-gray-300 dark:text-gray-600`}>
                                        —
                                    </td>
                                    <td className={`${COL} text-gray-300 dark:text-gray-600`}>
                                        —
                                    </td>
                                    <td className={`${COL} text-gray-300 dark:text-gray-600`}>
                                        —
                                    </td>
                                    <td className="px-4 py-3 pr-5 text-right text-gray-300 dark:text-gray-600">
                                        —
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <p className="mt-3 text-[11px] text-gray-400">
                    Delivered and Orders reconcile to the overall statement — the
                    Discrepancy row is what isn’t credited to any user; a non-zero
                    figure means an intern isn’t linked to a user, so resolve it.
                    Cost of Sales includes per-order COGS, so Gross and Net
                    intentionally differ from the workspace statement.
                </p>
            </div>
        </AppLayout>
    );
}
