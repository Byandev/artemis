import PageHeader from '@/components/common/PageHeader';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    PackageCheck,
    Receipt,
    RefreshCw,
    Save,
    ShoppingBag,
} from 'lucide-react';
import moment from 'moment';
import { ComponentType, useState } from 'react';

interface ExpenseRow {
    type_key: number;
    type_name: string;
    amount: number;
    source: string; // 'shipping_fee' | 'cod_fee' | 'vat' | 'transaction_type'
    section: string; // 'cost_of_sales' | 'opex'
    included: boolean;
}

interface Statement {
    id: number | null;
    period_month: string; // YYYY-MM-DD
    delivered: number;
    orders: number;
    cod_fee_rate: number; // fraction (0.02)
    vat_rate: number; // fraction (0.12)
    advisory_rate: number; // fraction (0.30)
    advisory_share?: number;
    gencys_partner: boolean;
    gross_profit?: number;
    total_expenses?: number;
    net_profit?: number;
    generated_at?: string | null;
    expenses: ExpenseRow[];
}

interface Props {
    workspace: Workspace;
    mode: 'preview' | 'saved';
    statement: Statement;
}

const AUTO = ['shipping_fee', 'cod_fee', 'vat'];

// Money-flow segment colors — fixed order, separated in hue AND lightness; every
// segment is direct-labelled in the legend, so identity is never colour-alone.
const FLOW = {
    cost_of_sales: '#64748b', // slate-500
    advisory: '#6366f1', // indigo-500
    opex: '#f59e0b', // amber-500
    net: '#10b981', // emerald-500
};

const fmt = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const round2 = (v: number) => Math.round(v * 100) / 100;
const toPct = (fraction: number) => Number((fraction * 100).toFixed(4));
const pct = (v: number) => `${v < 0 ? '−' : ''}${Math.abs(v).toFixed(1)}%`;
const clamp = (v: number) => Math.max(0, Math.min(100, v));

const CARD =
    'rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900';
const BTN =
    'flex h-8 items-center gap-1.5 rounded-lg border border-black/6 bg-stone-50 px-3 font-mono text-[12px] text-gray-600 transition-all hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700';

export default function IncomeStatementShow({
    workspace,
    mode,
    statement,
}: Props) {
    const base = `/workspaces/${workspace.slug}/finance/income-statements`;
    const isPreview = mode === 'preview';
    const month = moment(statement.period_month).format('YYYY-MM');
    const monthLabel = moment(statement.period_month).format('MMMM YYYY');

    const [rows, setRows] = useState<ExpenseRow[]>(statement.expenses);
    const [saving, setSaving] = useState(false);

    const codPct = toPct(statement.cod_fee_rate);
    const vatPct = toPct(statement.vat_rate);
    const codRate = codPct / 100;
    const vatRate = vatPct / 100;

    const codFeeAmount = round2(statement.delivered * codRate);
    const vatAmount = round2(codFeeAmount * vatRate);

    const toggle = (key: number, checked: boolean) =>
        setRows((prev) =>
            prev.map((r) =>
                r.type_key === key ? { ...r, included: checked } : r,
            ),
        );

    const effectiveAmount = (r: ExpenseRow) => {
        if (!isPreview) return r.amount;
        if (r.source === 'cod_fee') return codFeeAmount;
        if (r.source === 'vat') return vatAmount;
        return r.amount;
    };

    const derivation = (r: ExpenseRow): string | null => {
        if (r.source === 'shipping_fee') return 'Orders shipped out this month';
        if (r.source === 'cod_fee')
            return `${codPct}% of Total Delivered (${fmt(statement.delivered)})`;
        if (r.source === 'vat')
            return `${vatPct}% of COD Fee (${fmt(codFeeAmount)})`;
        return null;
    };

    const costOfSalesRows = rows.filter((r) => r.section === 'cost_of_sales');
    const opexRows = rows.filter((r) => r.section === 'opex');

    const sumIncluded = (list: ExpenseRow[]) =>
        list
            .filter((r) => r.included)
            .reduce((s, r) => s + effectiveAmount(r), 0);

    const costOfSalesTotal = isPreview
        ? sumIncluded(costOfSalesRows)
        : statement.delivered - (statement.gross_profit ?? 0);
    const grossProfit = isPreview
        ? statement.delivered - costOfSalesTotal
        : (statement.gross_profit ?? 0);

    const advisoryRate = statement.advisory_rate ?? 0;
    const advisoryPct = toPct(advisoryRate);
    const advisoryShare = isPreview
        ? statement.gencys_partner && grossProfit > 0
            ? round2(grossProfit * advisoryRate)
            : 0
        : (statement.advisory_share ?? 0);

    const opexTotal = isPreview
        ? sumIncluded(opexRows)
        : (statement.gross_profit ?? 0) -
          (statement.net_profit ?? 0) -
          advisoryShare;
    const netProfit = isPreview
        ? grossProfit - opexTotal - advisoryShare
        : (statement.net_profit ?? 0);

    const d = statement.delivered || 0;
    const netMargin = d > 0 ? (netProfit / d) * 100 : 0;

    // Where every peso of delivered revenue goes (part-to-whole).
    const flowSegments = [
        {
            key: 'cos',
            label: 'Cost of Sales',
            amount: costOfSalesTotal,
            color: FLOW.cost_of_sales,
        },
        ...(statement.gencys_partner
            ? [
                  {
                      key: 'adv',
                      label: 'Advisory',
                      amount: advisoryShare,
                      color: FLOW.advisory,
                  },
              ]
            : []),
        { key: 'opex', label: 'OPEX', amount: opexTotal, color: FLOW.opex },
        {
            key: 'net',
            label: 'Net Profit',
            amount: netProfit,
            color: FLOW.net,
        },
    ]
        .filter((s) => s.amount > 0)
        .map((s) => ({ ...s, width: d > 0 ? (s.amount / d) * 100 : 0 }));

    const showFlow = d > 0 && netProfit >= 0;

    const save = () => {
        setSaving(true);
        router.post(
            base,
            {
                month,
                cod_rate: codRate,
                vat_rate: vatRate,
                included_keys: rows
                    .filter((r) => r.included)
                    .map((r) => r.type_key),
            },
            { onFinish: () => setSaving(false) },
        );
    };

    const regenerate = () => {
        if (!statement.id) return;
        router.post(
            `${base}/${statement.id}/regenerate`,
            {},
            { preserveScroll: true },
        );
    };

    const Kpi = ({
        icon: Icon,
        label,
        value,
        sub,
        tone = 'default',
        meter,
    }: {
        icon: ComponentType<{ className?: string }>;
        label: string;
        value: string;
        sub: string;
        tone?: 'default' | 'good' | 'bad';
        meter?: number;
    }) => (
        <div
            className={`${CARD} p-5 ${
                tone === 'good'
                    ? 'ring-1 ring-emerald-500/15'
                    : tone === 'bad'
                      ? 'ring-1 ring-rose-500/15'
                      : ''
            }`}
        >
            <div className="flex items-center gap-2 text-[10px] font-medium tracking-wider text-gray-400 uppercase">
                <Icon className="h-3.5 w-3.5" />
                {label}
            </div>
            <div
                className={`mt-2.5 text-[26px] leading-none font-semibold tabular-nums ${
                    tone === 'good'
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : tone === 'bad'
                          ? 'text-rose-600 dark:text-rose-400'
                          : 'text-gray-800 dark:text-gray-100'
                }`}
            >
                {value}
            </div>
            {meter !== undefined ? (
                <div className="mt-3">
                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-stone-100 dark:bg-zinc-800">
                        <div
                            className="h-full rounded-full"
                            style={{
                                width: `${clamp(meter)}%`,
                                backgroundColor:
                                    tone === 'bad' ? '#f43f5e' : '#10b981',
                            }}
                        />
                    </div>
                    <div className="mt-1.5 text-[11px] text-gray-400">
                        {sub}
                    </div>
                </div>
            ) : (
                <div className="mt-2 text-[11px] text-gray-400">{sub}</div>
            )}
        </div>
    );

    const sectionHeader = (label: string, total: number) => (
        <div className="flex items-center justify-between border-y border-black/6 bg-stone-50 px-5 py-2.5 dark:border-white/6 dark:bg-zinc-800/40">
            <span className="text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                {label}
            </span>
            <span className="text-[11px] text-gray-400 tabular-nums">
                ({fmt(total)})
            </span>
        </div>
    );

    const renderRow = (r: ExpenseRow) => (
        <div
            key={r.type_key}
            className={`flex items-center gap-3 px-5 py-2.5 transition-colors hover:bg-stone-50 dark:hover:bg-zinc-800/40 ${
                isPreview && !r.included ? 'opacity-40' : ''
            }`}
        >
            {isPreview && (
                <Checkbox
                    checked={r.included}
                    onCheckedChange={(v) => toggle(r.type_key, Boolean(v))}
                />
            )}
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <span className="truncate text-[13px] text-gray-800 dark:text-gray-100">
                        {r.type_name}
                    </span>
                    {AUTO.includes(r.source) && (
                        <span className="rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] font-semibold tracking-wider text-emerald-700 uppercase dark:bg-emerald-950/40 dark:text-emerald-300">
                            auto
                        </span>
                    )}
                </div>
                {derivation(r) && (
                    <div className="mt-0.5 text-[10px] text-gray-400">
                        {derivation(r)}
                    </div>
                )}
            </div>
            <div className="text-[13px] text-gray-700 tabular-nums dark:text-gray-200">
                ({fmt(effectiveAmount(r))})
            </div>
        </div>
    );

    return (
        <AppLayout>
            <Head
                title={`${workspace.name} - Income Statement ${monthLabel}`}
            />
            <div className="w-full p-4 font-mono md:p-6">
                <PageHeader
                    title="Income Statement"
                    description={
                        isPreview
                            ? `${monthLabel} · preview — choose deductions, then save.`
                            : statement.generated_at
                              ? `${monthLabel} · saved ${moment(statement.generated_at).format('MMM D, YYYY h:mm A')}`
                              : `${monthLabel} · saved snapshot`
                    }
                >
                    <div className="flex items-center gap-2">
                        <Link href={base} className={BTN}>
                            <ArrowLeft className="h-3.5 w-3.5" />
                            Back
                        </Link>
                        {isPreview ? (
                            <button
                                onClick={save}
                                disabled={saving}
                                className="flex h-8 items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 text-[12px] font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-60"
                            >
                                <Save className="h-3.5 w-3.5" />
                                {statement.id ? 'Save (overwrite)' : 'Save'}
                            </button>
                        ) : (
                            <>
                                <button onClick={regenerate} className={BTN}>
                                    <RefreshCw className="h-3.5 w-3.5" />
                                    Regenerate
                                </button>
                                <a
                                    href={`${base}/${statement.id}/export`}
                                    className={BTN}
                                >
                                    <Download className="h-3.5 w-3.5" />
                                    Export
                                </a>
                            </>
                        )}
                    </div>
                </PageHeader>

                {isPreview && statement.id && (
                    <div className="mb-6 rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 text-[12px] text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-200">
                        A statement already exists for {monthLabel}. Saving will
                        overwrite it.
                    </div>
                )}

                {/* Summary metrics */}
                <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Kpi
                        icon={PackageCheck}
                        label="Total Delivered"
                        value={fmt(statement.delivered)}
                        sub="delivered revenue"
                    />
                    <Kpi
                        icon={ShoppingBag}
                        label="Total Orders"
                        value={statement.orders.toLocaleString()}
                        sub="delivered parcels"
                    />
                    <Kpi
                        icon={Receipt}
                        label="Total OPEX"
                        value={fmt(opexTotal)}
                        sub="operating expenses"
                    />
                </div>

                {/* Money flow */}
                {showFlow && (
                    <div className={`${CARD} mb-6 p-5`}>
                        <div className="mb-3 flex items-center justify-between">
                            <span className="text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                                Where the revenue goes
                            </span>
                            <span className="text-[10px] text-gray-400">
                                share of delivered
                            </span>
                        </div>
                        <div className="flex h-3 w-full overflow-hidden rounded-full bg-stone-100 dark:bg-zinc-800">
                            {flowSegments.map((s, i) => (
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
                        <div className="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4">
                            {flowSegments.map((s) => (
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

                {/* Statement ledger */}
                <div className={`${CARD} overflow-hidden`}>
                    <div className="flex items-center justify-between border-b border-black/6 px-5 py-4 dark:border-white/6">
                        <div>
                            <div className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                                Income Statement
                            </div>
                            <div className="mt-0.5 text-[11px] text-gray-400">
                                {monthLabel} ·{' '}
                                {statement.orders.toLocaleString()} delivered
                                orders
                            </div>
                        </div>
                        <span className="rounded-full border border-black/6 px-2.5 py-0.5 text-[10px] tracking-wider text-gray-400 uppercase dark:border-white/6">
                            {isPreview ? 'Preview' : 'Saved'}
                        </span>
                    </div>

                    {/* Revenue */}
                    <div className="flex items-center justify-between px-5 py-3">
                        <span className="text-[13px] font-medium text-gray-800 dark:text-gray-100">
                            Total Delivered
                        </span>
                        <span className="text-[14px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                            {fmt(statement.delivered)}
                        </span>
                    </div>

                    {/* Cost of sales → Gross Profit */}
                    {sectionHeader('Less — Cost of Sales', costOfSalesTotal)}
                    <div className="divide-y divide-black/5 dark:divide-white/5">
                        {costOfSalesRows.map(renderRow)}
                    </div>
                    <div className="flex items-center justify-between border-y border-black/6 bg-stone-100 px-5 py-3 dark:border-white/6 dark:bg-zinc-800/60">
                        <span className="text-[12px] font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-200">
                            = Gross Profit
                        </span>
                        <span className="text-[15px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                            {fmt(grossProfit)}
                        </span>
                    </div>

                    {/* Advisory share (gencys partners) — deducted from gross profit */}
                    {statement.gencys_partner && (
                        <div className="flex items-center justify-between px-5 py-2.5">
                            <div className="flex items-center gap-2">
                                <span className="text-[13px] text-gray-800 dark:text-gray-100">
                                    Advisory Share
                                </span>
                                <span className="rounded bg-indigo-50 px-1.5 py-0.5 text-[9px] font-semibold tracking-wider text-indigo-700 uppercase dark:bg-indigo-950/40 dark:text-indigo-300">
                                    {advisoryPct}% of gross profit
                                </span>
                            </div>
                            <span className="text-[13px] text-gray-700 tabular-nums dark:text-gray-200">
                                ({fmt(advisoryShare)})
                            </span>
                        </div>
                    )}

                    {/* OPEX → Net Profit */}
                    {sectionHeader('Less — Operating Expenses', opexTotal)}
                    <div className="divide-y divide-black/5 dark:divide-white/5">
                        {opexRows.length === 0 && (
                            <div className="px-5 py-6 text-center text-[12px] text-gray-400">
                                No operating expenses for this month.
                            </div>
                        )}
                        {opexRows.map(renderRow)}
                    </div>

                    <div
                        className={`flex items-center justify-between border-t border-black/6 px-5 py-5 dark:border-white/6 ${
                            netProfit < 0
                                ? 'bg-gradient-to-br from-rose-50 to-transparent dark:from-rose-950/25'
                                : 'bg-gradient-to-br from-emerald-50 to-transparent dark:from-emerald-950/25'
                        }`}
                    >
                        <div>
                            <div className="text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                                = Net Profit
                            </div>
                            <div className="mt-1 text-[11px] text-gray-400">
                                {pct(netMargin)} net margin
                            </div>
                        </div>
                        <div
                            className={`text-[30px] leading-none font-bold tabular-nums ${
                                netProfit < 0
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : 'text-emerald-600 dark:text-emerald-400'
                            }`}
                        >
                            {fmt(netProfit)}
                        </div>
                    </div>
                </div>

                <p className="mt-3 text-[11px] text-gray-400">
                    Gross Profit = Delivered − Cost of Sales. Net Profit = Gross
                    Profit − Advisory Share − OPEX. Cost-of-Sales types are set
                    on the Transaction Types page; uncheck any line to exclude
                    it.
                </p>
            </div>
        </AppLayout>
    );
}
