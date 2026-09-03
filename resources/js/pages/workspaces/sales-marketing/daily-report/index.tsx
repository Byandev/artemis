import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { ArrowDown, ArrowUp, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import DashboardCharts, { ChartsData, DashboardKpis } from './charts';

type Status = 'up' | 'down' | 'flat';

interface Row {
    id: number;
    name: string | null;
    orders: number;
    sales: number;
    yesterday_sales: number;
    change: number;
    status: Status;
    month_sales: number;
    rank: number;
    roas_yesterday: number | null;
    roas_today: number | null;
}

interface Subtotal {
    orders: number;
    sales: number;
    yesterday_sales: number;
    change: number;
    status: Status;
    month_sales: number;
    roas_yesterday: number | null;
    roas_today: number | null;
}

interface AdRtsRow {
    id: number;
    name: string | null;
    actual_ad_spent: number;
    target_ad_spent: number | null;
    avg_ad_spent: number;
    rts_rate: number | null;
    rts_amount: number | null;
}

interface AdRtsSubtotal {
    actual_ad_spent: number;
    target_ad_spent: number;
    avg_ad_spent: number;
    rts_rate: number | null;
    rts_amount: number | null;
}

interface View {
    date: string | null;
    date_label: string | null;
    prev_date: string | null;
    prev_date_label: string | null;
    rows: Row[];
    subtotal: Subtotal | null;
    ad_rts: { rows: AdRtsRow[]; subtotal: AdRtsSubtotal | null };
    charts: ChartsData;
}

interface Filters {
    date: string | null;
}

interface Props {
    workspace: Workspace;
    view: View;
    filters: Filters;
    // Base path for the page's data endpoint / URL sync. Defaults to the gencys
    // route; the S&M dashboard passes its own so this page can serve both.
    baseUrl?: string;
}

// ─── Formatters ────────────────────────────────────────────────────────────────
const peso = (v: number | null) =>
    v === null
        ? '—'
        : `${v < 0 ? '-' : ''}₱${Math.abs(v).toLocaleString('en-PH', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          })}`;

const int = (v: number) => v.toLocaleString('en-PH');

const pct = (v: number | null) => (v === null ? '—' : `${v.toFixed(2)}%`);

const roasText = (v: number | null) => (v === null ? '—' : v.toFixed(2));

const roasTone = (v: number | null) =>
    v === null
        ? 'text-gray-400 dark:text-gray-600'
        : v >= 3
          ? 'text-emerald-600 dark:text-emerald-400'
          : 'text-red-600 dark:text-red-400';

const changeTone = (v: number) =>
    v === 0
        ? 'text-gray-500 dark:text-gray-400'
        : v > 0
          ? 'bg-emerald-500/[0.06] text-emerald-600 dark:text-emerald-400'
          : 'bg-red-500/[0.06] text-red-600 dark:text-red-400';

// Shared cell chrome.
const cell =
    'border-b border-black/5 px-3 py-2 whitespace-nowrap dark:border-white/5';
const num = `${cell} text-right font-mono tabular-nums text-gray-700 dark:text-gray-300`;
const ctr = `${cell} text-center font-mono tabular-nums text-gray-700 dark:text-gray-300`;

// ─── Presentational bits ────────────────────────────────────────────────────────
function Remark({ status }: { status: Status }) {
    if (status === 'flat')
        return <span className="text-gray-300 dark:text-gray-600">—</span>;
    const up = status === 'up';
    return (
        <span
            className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold ${
                up
                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                    : 'bg-red-500/10 text-red-600 dark:text-red-400'
            }`}
        >
            {up ? (
                <ArrowUp className="h-3 w-3" strokeWidth={2.5} />
            ) : (
                <ArrowDown className="h-3 w-3" strokeWidth={2.5} />
            )}
            {up ? 'UP' : 'DOWN'}
        </span>
    );
}

function RankBadge({ rank }: { rank: number }) {
    const medal =
        rank === 1
            ? {
                  emoji: '🥇',
                  cls: 'bg-amber-400/15 text-amber-700 dark:text-amber-300',
              }
            : rank === 2
              ? {
                    emoji: '🥈',
                    cls: 'bg-zinc-400/15 text-zinc-600 dark:text-zinc-300',
                }
              : rank === 3
                ? {
                      emoji: '🥉',
                      cls: 'bg-amber-700/15 text-amber-800 dark:text-amber-500',
                  }
                : {
                      emoji: '',
                      cls: 'bg-black/5 text-gray-500 dark:bg-white/5 dark:text-gray-400',
                  };

    const suffix =
        rank % 10 === 1 && rank !== 11
            ? 'st'
            : rank % 10 === 2 && rank !== 12
              ? 'nd'
              : rank % 10 === 3 && rank !== 13
                ? 'rd'
                : 'th';

    return (
        <span
            className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-mono text-[11px] font-semibold ${medal.cls}`}
        >
            {rank}
            {suffix}
            {medal.emoji && <span className="not-italic">{medal.emoji}</span>}
        </span>
    );
}

function DataRow({ row }: { row: Row }) {
    return (
        <tr className="transition-colors hover:bg-black/[0.015] dark:hover:bg-white/[0.02]">
            <td
                className={`${cell} font-medium text-gray-800 uppercase dark:text-gray-200`}
            >
                {row.name ?? `#${row.id}`}
            </td>
            <td className={ctr}>{int(row.orders)}</td>
            <td className={num}>{peso(row.sales)}</td>
            <td className={`${num} text-gray-400 dark:text-gray-500`}>
                {peso(row.yesterday_sales)}
            </td>
            <td
                className={`${cell} text-right font-mono tabular-nums ${changeTone(row.change)}`}
            >
                {peso(row.change)}
            </td>
            <td className={`${cell} text-center`}>
                <Remark status={row.status} />
            </td>
            <td
                className={`${cell} bg-amber-500/[0.05] text-right font-mono font-medium text-gray-800 tabular-nums dark:text-gray-200`}
            >
                {peso(row.month_sales)}
            </td>
            <td className={`${cell} text-center`}>
                <RankBadge rank={row.rank} />
            </td>
            <td className={`${ctr} opacity-60 ${roasTone(row.roas_yesterday)}`}>
                {roasText(row.roas_yesterday)}
            </td>
            <td className={`${ctr} font-medium ${roasTone(row.roas_today)}`}>
                {roasText(row.roas_today)}
            </td>
        </tr>
    );
}

function SubtotalRow({ st }: { st: Subtotal }) {
    return (
        <tr className="bg-stone-50 dark:bg-white/2">
            <td
                className={`${cell} font-mono text-[11px] font-semibold text-gray-500 italic dark:text-gray-400`}
            >
                Sub-Total
            </td>
            <td className={`${ctr} font-semibold`}>{int(st.orders)}</td>
            <td className={`${num} font-semibold`}>{peso(st.sales)}</td>
            <td
                className={`${num} font-semibold text-gray-400 dark:text-gray-500`}
            >
                {peso(st.yesterday_sales)}
            </td>
            <td
                className={`${cell} text-right font-mono font-semibold tabular-nums ${changeTone(st.change)}`}
            >
                {peso(st.change)}
            </td>
            <td className={`${cell} text-center`}>
                <Remark status={st.status} />
            </td>
            <td
                className={`${cell} bg-amber-500/[0.08] text-right font-mono font-semibold text-gray-800 tabular-nums dark:text-gray-100`}
            >
                {peso(st.month_sales)}
            </td>
            <td className={cell} />
            <td
                className={`${ctr} font-semibold opacity-60 ${roasTone(st.roas_yesterday)}`}
            >
                {roasText(st.roas_yesterday)}
            </td>
            <td className={`${ctr} font-semibold ${roasTone(st.roas_today)}`}>
                {roasText(st.roas_today)}
            </td>
        </tr>
    );
}

// Budget verdict: actual vs target ad spend, within a ±2,000 tolerance band.
const BUDGET_TOLERANCE = 2000;
type BudgetVerdict = 'over' | 'under' | 'within';

function budgetVerdict(
    actual: number,
    target: number | null,
): BudgetVerdict | null {
    if (target == null) return null;
    const diff = actual - target;
    if (diff > BUDGET_TOLERANCE) return 'over';
    if (diff < -BUDGET_TOLERANCE) return 'under';
    return 'within';
}

const BUDGET_REMARK: Record<BudgetVerdict, { label: string; cls: string }> = {
    over: {
        label: 'Overspending',
        cls: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
    },
    under: {
        label: 'Underspending',
        cls: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    },
    within: {
        label: 'Within Budget',
        cls: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    },
};

function BudgetRemark({
    actual,
    target,
}: {
    actual: number;
    target: number | null;
}) {
    const v = budgetVerdict(actual, target);
    if (!v) return <span className="text-gray-300 dark:text-gray-600">—</span>;
    const c = BUDGET_REMARK[v];
    return (
        <span
            className={`inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap ${c.cls}`}
        >
            {c.label}
        </span>
    );
}

function AdRtsDataRow({ row }: { row: AdRtsRow }) {
    return (
        <tr className="transition-colors hover:bg-black/[0.015] dark:hover:bg-white/[0.02]">
            <td
                className={`${cell} font-medium text-gray-800 uppercase dark:text-gray-200`}
            >
                {row.name ?? `#${row.id}`}
            </td>
            <td className={num}>{peso(row.actual_ad_spent)}</td>
            <td className={num}>{peso(row.target_ad_spent)}</td>
            <td className={`${cell} text-center`}>
                <BudgetRemark
                    actual={row.actual_ad_spent}
                    target={row.target_ad_spent}
                />
            </td>
            <td className={num}>{peso(row.avg_ad_spent)}</td>
            <td className={ctr}>{pct(row.rts_rate)}</td>
            <td className={num}>{peso(row.rts_amount)}</td>
        </tr>
    );
}

function AdRtsSubtotalRow({ st }: { st: AdRtsSubtotal }) {
    return (
        <tr className="bg-stone-50 dark:bg-white/2">
            <td
                className={`${cell} font-mono text-[11px] font-semibold text-gray-500 italic dark:text-gray-400`}
            >
                Sub-Total
            </td>
            <td className={`${num} font-semibold`}>
                {peso(st.actual_ad_spent)}
            </td>
            <td className={`${num} font-semibold`}>
                {peso(st.target_ad_spent)}
            </td>
            <td className={ctr} />
            <td className={`${num} font-semibold`}>{peso(st.avg_ad_spent)}</td>
            <td className={ctr} />
            <td className={`${num} font-semibold`}>{peso(st.rts_amount)}</td>
        </tr>
    );
}

export default function InternDashboard({
    workspace,
    view: initialView,
    filters,
    baseUrl: baseUrlProp,
}: Props) {
    const [date, setDate] = useState<string>(filters.date ?? '');
    const [view, setView] = useState<View>(initialView);
    const [loading, setLoading] = useState(false);

    const baseUrl =
        baseUrlProp ?? `/workspaces/${workspace.slug}/gencys/intern-dashboard`;

    // Persist the selected date in the URL so a refresh restores it — the
    // controller reads filter.date on load. replaceState (not an Inertia visit)
    // keeps this axios-driven; we preserve the existing history state so
    // Inertia's page record isn't clobbered. The active tab lives in the route
    // path, so it's carried by the pathname automatically.
    const syncUrl = (dateVal: string | null) => {
        const params = new URLSearchParams();
        if (dateVal) params.set('filter[date]', dateVal);
        const qs = params.toString();
        window.history.replaceState(
            window.history.state,
            '',
            qs ? `${window.location.pathname}?${qs}` : window.location.pathname,
        );
    };

    // Fetch the table via the JSON endpoint (axios) whenever the date changes;
    // reflect the server-resolved date back into the picker so the default day
    // is visible.
    const applyDate = (value: string) => {
        setDate(value);
        setLoading(true);
        axios
            .get(`${baseUrl}/data`, { params: { filter: { date: value } } })
            .then((res) => {
                const data = res.data as { view: View; filters: Filters };
                setView(data.view);
                setDate(data.filters.date ?? '');
                syncUrl(data.filters.date ?? null);
            })
            .catch(() => toast.error('Failed to load dashboard data.'))
            .finally(() => setLoading(false));
    };

    const datePicker = (
        <DatePicker
            id="intern-dashboard-date"
            key={date}
            mode="single"
            placeholder="Report date"
            defaultDate={date || undefined}
            onChange={(_dates, dateStr) => {
                if (dateStr) applyDate(dateStr);
            }}
        />
    );

    const headCell =
        'border-b border-black/6 bg-stone-50 px-3 py-2.5 text-center align-middle font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:border-white/6 dark:bg-white/2 dark:text-gray-500';

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Intern Dashboard`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div>
                    {/* Page title on the left, report-date control on the right. */}
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <h1 className="my-0! text-[22px]! font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                            Daily Report
                        </h1>
                        {datePicker}
                    </div>

                    {/* Summary statistics on top — for the selected day only */}
                    <div className="mt-4">
                        <p className="mb-2 px-1 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            {view.date_label
                                ? `Totals for ${view.date_label}`
                                : 'Totals for the selected day'}
                        </p>
                        <DashboardKpis kpis={view.charts.kpis} />
                    </div>

                    <div className="relative mt-6 overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        {loading && (
                            <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/60 dark:bg-zinc-900/60">
                                <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
                            </div>
                        )}
                        <table className="w-full min-w-[1080px] border-collapse text-[12px] text-gray-800 dark:text-gray-200">
                            <thead>
                                <tr>
                                    <th className={`${headCell} text-left`}>
                                        Name
                                    </th>
                                    <th className={headCell}>Orders</th>
                                    <th className={headCell}>Sales</th>
                                    <th className={headCell}>
                                        Yesterday Sales
                                        {view.prev_date_label && (
                                            <span className="mt-0.5 block text-[9px] font-normal tracking-normal text-gray-300 normal-case dark:text-gray-600">
                                                {view.prev_date_label}
                                            </span>
                                        )}
                                    </th>
                                    <th className={headCell}>Change</th>
                                    <th className={headCell}>Remarks</th>
                                    <th className={headCell}>
                                        Day to Month Sales
                                    </th>
                                    <th className={headCell}>
                                        Top Sales Ranking
                                    </th>
                                    <th
                                        className={`${headCell} text-emerald-600/70 dark:text-emerald-400/60`}
                                    >
                                        ROAS Yesterday
                                    </th>
                                    <th
                                        className={`${headCell} text-emerald-600/70 dark:text-emerald-400/60`}
                                    >
                                        ROAS Today
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {view.rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={10}
                                            className="px-3 py-16 text-center text-[12px] text-gray-400 dark:text-gray-500"
                                        >
                                            No intern records for this date.
                                        </td>
                                    </tr>
                                )}

                                {view.rows.map((row) => (
                                    <DataRow key={row.id} row={row} />
                                ))}

                                {view.subtotal && view.rows.length > 0 && (
                                    <SubtotalRow st={view.subtotal} />
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Second table — ad spend & month-to-date RTS */}
                    <h2 className="mt-8 mb-2 px-1 font-mono text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                        Ad Spend &amp; RTS
                    </h2>
                    <div className="relative overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        {loading && (
                            <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/60 dark:bg-zinc-900/60">
                                <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
                            </div>
                        )}
                        <table className="w-full min-w-[720px] border-collapse text-[12px] text-gray-800 dark:text-gray-200">
                            <thead>
                                <tr>
                                    <th className={`${headCell} text-left`}>
                                        Intern
                                    </th>
                                    <th className={headCell}>
                                        Actual Ad Spent
                                    </th>
                                    <th className={headCell}>
                                        Target Ad Spent
                                    </th>
                                    <th className={headCell}>Remarks</th>
                                    <th className={headCell}>
                                        3 Days Average Ad Spent
                                    </th>
                                    <th className={headCell}>
                                        RTS to Date
                                        {view.date_label && (
                                            <span className="mt-0.5 block text-[9px] font-normal tracking-normal text-gray-300 normal-case dark:text-gray-600">
                                                as of {view.date_label}
                                            </span>
                                        )}
                                    </th>
                                    <th className={headCell}>RTS Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                {view.ad_rts.rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-3 py-16 text-center text-[12px] text-gray-400 dark:text-gray-500"
                                        >
                                            No intern records for this date.
                                        </td>
                                    </tr>
                                )}

                                {view.ad_rts.rows.map((row) => (
                                    <AdRtsDataRow key={row.id} row={row} />
                                ))}

                                {view.ad_rts.subtotal &&
                                    view.ad_rts.rows.length > 0 && (
                                        <AdRtsSubtotalRow
                                            st={view.ad_rts.subtotal}
                                        />
                                    )}
                            </tbody>
                        </table>
                    </div>

                    <p className="mt-3 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                        REMARKS: —
                    </p>

                    {/* Analytics — KPI tiles + trend / efficiency charts */}
                    <h2 className="mt-8 mb-3 px-1 font-mono text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                        Analytics
                    </h2>
                    <DashboardCharts data={view.charts} loading={loading} />
                </div>
            </div>
        </AppLayout>
    );
}
