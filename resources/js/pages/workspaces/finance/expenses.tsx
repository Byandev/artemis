import CashflowChart from '@/components/charts/CashflowChart';
import PageHeader from '@/components/common/PageHeader';
import { SUB_CATEGORY_LABEL, SubCategory } from '@/components/finance/sub-category';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import flatpickr from 'flatpickr';
import { ArrowDownRight, ArrowUpRight, Flame, Hourglass, Minus, Receipt, Wallet } from 'lucide-react';
import moment from 'moment';
import { useMemo } from 'react';
import DateOption = flatpickr.Options.DateOption;

interface BreakdownRow {
    sub_category: SubCategory | 'unassigned';
    total: number;
    count: number;
}

interface CashflowPoint { bucket: string; label: string; in: number; out: number }

interface MoMRow {
    sub_category: SubCategory | 'unassigned';
    current: number;
    previous: number;
    delta: number;
    delta_pct: number | null;
}

interface Props {
    workspace: Workspace;
    range: { from: string; to: string };
    breakdown: BreakdownRow[];
    totalExpenses: number;
    totalCount: number;
    cashflowSeries: CashflowPoint[];
    expenseMoM: MoMRow[];
    totalBalance: number;
    burnRate: number;
    rangeOut: number;
    runwayMonths: number | null;
}

const fmt = (v: number) =>
    Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const fmtCompact = (v: number) => {
    const abs = Math.abs(v);
    if (abs >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M';
    if (abs >= 1_000) return (v / 1_000).toFixed(1) + 'K';
    return v.toFixed(0);
};

const labelFor = (key: BreakdownRow['sub_category']) =>
    key === 'unassigned' ? 'Unassigned' : SUB_CATEGORY_LABEL[key];

const SWATCHES = [
    'bg-rose-500', 'bg-amber-500', 'bg-emerald-500', 'bg-sky-500',
    'bg-violet-500', 'bg-fuchsia-500', 'bg-orange-500', 'bg-teal-500',
    'bg-indigo-500', 'bg-slate-500',
];

export default function FinanceExpenses({
    workspace, range, breakdown, totalExpenses, totalCount,
    cashflowSeries, expenseMoM, totalBalance, burnRate, rangeOut, runwayMonths,
}: Props) {
    const defaultDate = useMemo(() => [range.from, range.to], [range.from, range.to]);

    const sorted = useMemo(
        () => [...breakdown].sort((a, b) => b.total - a.total),
        [breakdown],
    );

    const sortedMoM = useMemo(
        () => [...expenseMoM].sort((a, b) => b.current - a.current),
        [expenseMoM],
    );

    const handleDateChange = (dates: Date[]) => {
        if (dates.length !== 2) return;
        const from = moment(dates[0]).format('YYYY-MM-DD');
        const to = moment(dates[1]).format('YYYY-MM-DD');
        if (from === range.from && to === range.to) return;
        router.get(
            `/workspaces/${workspace.slug}/finance/expenses`,
            { from, to },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const cashflowCategories = cashflowSeries.map((p) => p.label);
    const cashflowIn = cashflowSeries.map((p) => p.in);
    const cashflowOut = cashflowSeries.map((p) => p.out);
    const totalIn = cashflowIn.reduce((s, n) => s + n, 0);
    const totalOut = cashflowOut.reduce((s, n) => s + n, 0);

    const rangeLabel = `${moment(range.from).format('MMM D, YYYY')} – ${moment(range.to).format('MMM D, YYYY')}`;

    const hasCashflow = cashflowSeries.some((p) => p.in > 0 || p.out > 0);
    const hasBurn = rangeOut > 0;
    const hasExpenses = totalExpenses > 0;
    const hasMoM = expenseMoM.some((r) => r.current > 0 || r.previous > 0);

    const showAnything = hasCashflow || hasBurn || hasExpenses || hasMoM;

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Finance Dashboard`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Dashboard" description="Finance overview — cashflow, burn, and expense trends.">
                    <DatePicker
                        id="finance-expenses-date-range"
                        mode="range"
                        onChange={handleDateChange}
                        defaultDate={defaultDate as never as DateOption}
                    />
                </PageHeader>

                {/* Balance + Burn/Runway (Burn/Runway only when there is OUT activity in range) */}
                <div className={`grid grid-cols-1 gap-3 ${hasBurn ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-1'}`}>
                    <StatCard
                        icon={<Wallet className="h-4 w-4" />}
                        label="Balance at End of Range"
                        value={fmt(totalBalance)}
                        hint={`As of ${moment(range.to).format('MMM D, YYYY')}`}
                    />
                    {hasBurn && (
                        <>
                            <StatCard
                                icon={<Flame className="h-4 w-4 text-orange-500" />}
                                label="Burn Rate (avg / mo)"
                                value={fmt(burnRate)}
                                hint={`Based on ${rangeLabel}`}
                            />
                            <StatCard
                                icon={<Hourglass className="h-4 w-4 text-indigo-500" />}
                                label="Runway"
                                value={runwayMonths == null ? '—' : `${runwayMonths.toFixed(1)} mo`}
                                hint={runwayMonths == null ? 'No burn recorded' : 'At range burn rate'}
                            />
                        </>
                    )}
                </div>

                {/* Cashflow chart */}
                {hasCashflow && (
                    <div className="mt-6 rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
                        <div className="mb-3 flex items-center justify-between">
                            <div>
                                <div className="text-[11px] font-mono font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Cashflow
                                </div>
                                <div className="text-[13px] text-gray-600 dark:text-gray-300">IN vs OUT over time</div>
                            </div>
                            <div className="flex items-center gap-4 text-[12px]">
                                <TotalPill label="IN" value={fmt(totalIn)} color="text-emerald-600" dot="bg-emerald-500" />
                                <TotalPill label="OUT" value={fmt(totalOut)} color="text-rose-600" dot="bg-rose-500" />
                            </div>
                        </div>
                        <CashflowChart
                            categories={cashflowCategories}
                            inSeries={cashflowIn}
                            outSeries={cashflowOut}
                            formatValue={fmtCompact}
                        />
                    </div>
                )}

                {/* Expenses breakdown + MoM */}
                {(hasExpenses || hasMoM) && (
                    <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                        {hasExpenses && (
                            <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                                <div className="flex items-center justify-between border-b border-black/6 px-5 py-3 dark:border-white/6">
                                    <div className="text-[11px] font-mono font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                        Expenses per Sub-Category
                                    </div>
                                    <div className="flex items-center gap-3 text-[12px] text-gray-500 dark:text-gray-400">
                                        <div className="flex items-center gap-1.5">
                                            <Receipt className="h-3.5 w-3.5 text-gray-400" />
                                            <span className="font-mono font-semibold text-gray-800 dark:text-gray-100">{fmt(totalExpenses)}</span>
                                        </div>
                                        <span className="text-[11px] text-gray-400">{totalCount} txn{totalCount === 1 ? '' : 's'}</span>
                                    </div>
                                </div>
                                <div className="divide-y divide-black/6 dark:divide-white/6">
                                    {sorted
                                        .filter((row) => row.total > 0)
                                        .map((row, idx) => {
                                            const pct = totalExpenses > 0 ? (row.total / totalExpenses) * 100 : 0;
                                            const swatch = SWATCHES[idx % SWATCHES.length];
                                            return (
                                                <div key={row.sub_category} className="px-5 py-3">
                                                    <div className="mb-1.5 flex items-center justify-between">
                                                        <div className="flex items-center gap-2">
                                                            <span className={`inline-block h-2 w-2 rounded-full ${swatch}`} />
                                                            <span className="text-[13px] font-medium text-gray-800 dark:text-gray-100">
                                                                {labelFor(row.sub_category)}
                                                            </span>
                                                            <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400">
                                                                {row.count} txn{row.count === 1 ? '' : 's'}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center gap-3">
                                                            <span className="font-mono text-[11px] text-gray-400">{pct.toFixed(1)}%</span>
                                                            <span className="font-mono text-[13px] font-medium text-gray-700 dark:text-gray-200">
                                                                {fmt(row.total)}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                                                        <div className={`${swatch} h-full rounded-full transition-all`} style={{ width: `${pct}%` }} />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                </div>
                            </div>
                        )}

                        {hasMoM && (
                            <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                                <div className="border-b border-black/6 px-5 py-3 dark:border-white/6">
                                    <div className="text-[11px] font-mono font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                        Expense Change vs Prior Period
                                    </div>
                                    <div className="text-[11px] text-gray-400">
                                        Compared against the equivalent window immediately before the selected range.
                                    </div>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-[12px]">
                                        <thead>
                                            <tr className="text-left text-[10px] font-mono uppercase tracking-wider text-gray-400">
                                                <th className="px-5 py-2 font-medium">Sub-Category</th>
                                                <th className="px-3 py-2 text-right font-medium">Previous</th>
                                                <th className="px-3 py-2 text-right font-medium">Current</th>
                                                <th className="px-5 py-2 text-right font-medium">Δ</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-black/6 dark:divide-white/6">
                                            {sortedMoM
                                                .filter((row) => row.current > 0 || row.previous > 0)
                                                .map((row) => (
                                                    <tr key={row.sub_category}>
                                                        <td className="px-5 py-2 text-gray-800 dark:text-gray-100">{labelFor(row.sub_category)}</td>
                                                        <td className="px-3 py-2 text-right font-mono text-gray-500">{fmt(row.previous)}</td>
                                                        <td className="px-3 py-2 text-right font-mono text-gray-800 dark:text-gray-100">{fmt(row.current)}</td>
                                                        <td className="px-5 py-2 text-right">
                                                            <DeltaBadge delta={row.delta} deltaPct={row.delta_pct} />
                                                        </td>
                                                    </tr>
                                                ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {!showAnything && (
                    <div className="mt-6 rounded-[14px] border border-dashed border-black/10 bg-white px-6 py-10 text-center dark:border-white/10 dark:bg-zinc-900">
                        <div className="text-[13px] text-gray-500 dark:text-gray-400">
                            No finance activity in the selected range.
                        </div>
                        <div className="mt-1 text-[11px] text-gray-400">
                            Try a different date range or record transactions.
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function StatCard({
    icon, label, value, hint,
}: { icon: React.ReactNode; label: string; value: string; hint?: string }) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-center gap-2 text-[10px] font-mono uppercase tracking-wider text-gray-400 dark:text-gray-500">
                {icon}
                <span>{label}</span>
            </div>
            <div className="mt-2 font-mono text-[20px] font-semibold text-gray-800 dark:text-gray-100">{value}</div>
            {hint && <div className="mt-1 text-[10px] text-gray-400">{hint}</div>}
        </div>
    );
}

function TotalPill({ label, value, color, dot }: { label: string; value: string; color: string; dot: string }) {
    return (
        <div className="flex items-center gap-1.5">
            <span className={`inline-block h-2 w-2 rounded-full ${dot}`} />
            <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400">{label}</span>
            <span className={`font-mono font-semibold ${color}`}>{value}</span>
        </div>
    );
}

function DeltaBadge({ delta, deltaPct }: { delta: number; deltaPct: number | null }) {
    if (delta === 0 && (deltaPct === 0 || deltaPct == null)) {
        return (
            <span className="inline-flex items-center gap-1 font-mono text-[11px] text-gray-400">
                <Minus className="h-3 w-3" /> 0
            </span>
        );
    }
    const positive = delta > 0;
    const color = positive ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400';
    const Icon = positive ? ArrowUpRight : ArrowDownRight;
    const pctLabel = deltaPct == null ? 'new' : `${Math.abs(deltaPct).toFixed(1)}%`;
    return (
        <span className={`inline-flex items-center gap-1 font-mono text-[11px] ${color}`}>
            <Icon className="h-3 w-3" />
            {pctLabel}
        </span>
    );
}
