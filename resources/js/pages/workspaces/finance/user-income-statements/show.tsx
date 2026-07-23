import PageHeader from '@/components/common/PageHeader';
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
    Wallet,
} from 'lucide-react';
import moment from 'moment';
import { ComponentType, useState } from 'react';

interface ProductRow {
    product: string;
    revenue: number;
    orders: number;
    cogs: number;
    shipping: number;
    cod: number;
    vat: number;
    tagged_expense: number;
    gross_profit: number;
}

interface OpexRow {
    type_name: string;
    amount: number;
}

interface Statement {
    id: number | null;
    intern_id: number;
    intern_name: string;
    period_month: string; // YYYY-MM-DD
    delivered: number;
    orders: number;
    gross_profit: number;
    total_opex: number;
    advisory_rate: number;
    advisory_share: number;
    net_profit: number;
    cod_fee_rate: number;
    vat_rate: number;
    gencys_partner: boolean;
    generated_at?: string | null;
    products: ProductRow[];
    opex: OpexRow[];
}

interface Props {
    workspace: Workspace;
    mode: 'preview' | 'saved';
    statement: Statement;
}

const fmt = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const toPct = (fraction: number) => Number((fraction * 100).toFixed(4));
const pct = (v: number) => `${v < 0 ? '−' : ''}${Math.abs(v).toFixed(1)}%`;

const CARD =
    'rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900';
const BTN =
    'flex h-8 items-center gap-1.5 rounded-lg border border-black/6 bg-stone-50 px-3 font-mono text-[12px] text-gray-600 transition-all hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700';

export default function UserIncomeStatementShow({
    workspace,
    mode,
    statement,
}: Props) {
    const base = `/workspaces/${workspace.slug}/finance/user-income-statements`;
    const isPreview = mode === 'preview';
    const month = moment(statement.period_month).format('YYYY-MM');
    const monthLabel = moment(statement.period_month).format('MMMM YYYY');
    const [saving, setSaving] = useState(false);

    const codPct = toPct(statement.cod_fee_rate);
    const vatPct = toPct(statement.vat_rate);
    const advisoryPct = toPct(statement.advisory_rate);
    const netMargin =
        statement.delivered > 0
            ? (statement.net_profit / statement.delivered) * 100
            : 0;

    const save = () => {
        setSaving(true);
        router.post(
            base,
            { intern_id: statement.intern_id, month },
            { onFinish: () => setSaving(false) },
        );
    };

    const regenerate = () => {
        if (!statement.id) return;
        router.post(`${base}/${statement.id}/regenerate`, {}, { preserveScroll: true });
    };

    const Kpi = ({
        icon: Icon,
        label,
        value,
        sub,
        tone = 'default',
    }: {
        icon: ComponentType<{ className?: string }>;
        label: string;
        value: string;
        sub: string;
        tone?: 'default' | 'good' | 'bad';
    }) => (
        <div className={`${CARD} p-5`}>
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
            <div className="mt-2 text-[11px] text-gray-400">{sub}</div>
        </div>
    );

    const th = 'px-4 py-2.5 text-right text-[10px] font-semibold tracking-wider text-gray-400 uppercase';
    const td = 'px-4 py-2.5 text-right text-[12px] text-gray-700 tabular-nums dark:text-gray-200';

    return (
        <AppLayout>
            <Head
                title={`${workspace.name} - ${statement.intern_name} Income Statement`}
            />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 font-mono md:p-6">
                <PageHeader
                    title={`Income Statement — ${statement.intern_name}`}
                    description={
                        isPreview
                            ? `${monthLabel} · preview`
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
                        A statement already exists for {statement.intern_name} —{' '}
                        {monthLabel}. Saving will overwrite it.
                    </div>
                )}

                <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Kpi
                        icon={PackageCheck}
                        label="Total Delivered"
                        value={fmt(statement.delivered)}
                        sub={`${statement.orders.toLocaleString()} delivered orders`}
                    />
                    <Kpi
                        icon={Receipt}
                        label="Gross Profit"
                        value={fmt(statement.gross_profit)}
                        sub="sum of product gross"
                    />
                    <Kpi
                        icon={Wallet}
                        label="Net Profit"
                        value={fmt(statement.net_profit)}
                        sub={`${pct(netMargin)} net margin`}
                        tone={statement.net_profit < 0 ? 'bad' : 'good'}
                    />
                </div>

                {/* Product breakdown */}
                <div className={`${CARD} mb-6 overflow-hidden`}>
                    <div className="border-b border-black/6 px-5 py-4 dark:border-white/6">
                        <div className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                            Product Breakdown
                        </div>
                        <div className="mt-0.5 text-[11px] text-gray-400">
                            Revenue − COGS − Shipping − COD ({codPct}%) − VAT (
                            {vatPct}%) − tagged spend = gross
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px]">
                            <thead className="bg-stone-50 dark:bg-zinc-800/40">
                                <tr>
                                    <th className={`${th} text-left`}>Product</th>
                                    <th className={th}>Revenue</th>
                                    <th className={th}>Orders</th>
                                    <th className={th}>COGS</th>
                                    <th className={th}>Shipping</th>
                                    <th className={th}>COD</th>
                                    <th className={th}>VAT</th>
                                    <th className={th}>Tagged</th>
                                    <th className={th}>Gross</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-black/5 dark:divide-white/5">
                                {statement.products.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={9}
                                            className="px-4 py-8 text-center text-[12px] text-gray-400"
                                        >
                                            No delivered products for this month.
                                        </td>
                                    </tr>
                                )}
                                {statement.products.map((p) => (
                                    <tr
                                        key={p.product}
                                        className="hover:bg-stone-50 dark:hover:bg-zinc-800/40"
                                    >
                                        <td className="px-4 py-2.5 text-left text-[12px] text-gray-800 dark:text-gray-100">
                                            {p.product}
                                        </td>
                                        <td className={td}>{fmt(p.revenue)}</td>
                                        <td className={td}>{p.orders}</td>
                                        <td className={td}>({fmt(p.cogs)})</td>
                                        <td className={td}>
                                            ({fmt(p.shipping)})
                                        </td>
                                        <td className={td}>({fmt(p.cod)})</td>
                                        <td className={td}>({fmt(p.vat)})</td>
                                        <td className={td}>
                                            ({fmt(p.tagged_expense)})
                                        </td>
                                        <td
                                            className={`px-4 py-2.5 text-right text-[12px] font-semibold tabular-nums ${
                                                p.gross_profit < 0
                                                    ? 'text-rose-600 dark:text-rose-400'
                                                    : 'text-gray-800 dark:text-gray-100'
                                            }`}
                                        >
                                            {fmt(p.gross_profit)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t border-black/6 bg-stone-100 dark:border-white/6 dark:bg-zinc-800/60">
                                    <td className="px-4 py-3 text-left text-[12px] font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-200">
                                        Gross Profit
                                    </td>
                                    <td colSpan={7} />
                                    <td className="px-4 py-3 text-right text-[14px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                                        {fmt(statement.gross_profit)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {/* OPEX → Net */}
                <div className={`${CARD} overflow-hidden`}>
                    <div className="flex items-center justify-between border-b border-black/6 bg-stone-50 px-5 py-2.5 dark:border-white/6 dark:bg-zinc-800/40">
                        <span className="text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                            Less — Operating Expenses
                        </span>
                        <span className="text-[11px] text-gray-400 tabular-nums">
                            ({fmt(statement.total_opex)})
                        </span>
                    </div>
                    <div className="divide-y divide-black/5 dark:divide-white/5">
                        {statement.opex.length === 0 && (
                            <div className="px-5 py-6 text-center text-[12px] text-gray-400">
                                No untagged expenses charged to this intern.
                            </div>
                        )}
                        {statement.opex.map((o) => (
                            <div
                                key={o.type_name}
                                className="flex items-center justify-between px-5 py-2.5"
                            >
                                <span className="text-[13px] text-gray-800 dark:text-gray-100">
                                    {o.type_name}
                                </span>
                                <span className="text-[13px] text-gray-700 tabular-nums dark:text-gray-200">
                                    ({fmt(o.amount)})
                                </span>
                            </div>
                        ))}
                    </div>

                    {statement.gencys_partner && (
                        <div className="flex items-center justify-between border-t border-black/6 px-5 py-2.5 dark:border-white/6">
                            <div className="flex items-center gap-2">
                                <span className="text-[13px] text-gray-800 dark:text-gray-100">
                                    Advisory Share
                                </span>
                                <span className="rounded bg-indigo-50 px-1.5 py-0.5 text-[9px] font-semibold tracking-wider text-indigo-700 uppercase dark:bg-indigo-950/40 dark:text-indigo-300">
                                    {advisoryPct}% of gross profit
                                </span>
                            </div>
                            <span className="text-[13px] text-gray-700 tabular-nums dark:text-gray-200">
                                ({fmt(statement.advisory_share)})
                            </span>
                        </div>
                    )}

                    <div
                        className={`flex items-center justify-between border-t border-black/6 px-5 py-5 dark:border-white/6 ${
                            statement.net_profit < 0
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
                                statement.net_profit < 0
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : 'text-emerald-600 dark:text-emerald-400'
                            }`}
                        >
                            {fmt(statement.net_profit)}
                        </div>
                    </div>
                </div>

                <p className="mt-3 text-[11px] text-gray-400">
                    Each product's gross = revenue − COGS − shipping − COD − VAT
                    − transactions tagged to that product. Net Profit = Σ product
                    gross − OPEX (untagged spend charged to the intern) −
                    Advisory Share.
                </p>
            </div>
        </AppLayout>
    );
}
