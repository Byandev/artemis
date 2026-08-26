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
    Users,
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

interface ProductRow {
    product: string;
    orders: number;
    delivered: number;
    cogs: number;
    shipping: number;
    cod_fee: number;
    vat: number;
    adspent: number;
    cost_of_sales: number;
    gross_profit: number;
    advisory: number;
    net_profit: number;
    commission_rate: number;
    commission: number;
    product_id: number | null;
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
    // Per-product delivered revenue + cost of sales (present on a per-user view).
    products?: ProductRow[];
}

interface Props {
    workspace: Workspace;
    mode: 'preview' | 'saved';
    statement: Statement;
    // When rendered for a scoped statement (per-product / per-user), the route
    // prefix + the scope label and the extra params to send on save.
    base?: string;
    scope?: { label?: string; params?: Record<string, string | number> };
    // Live-computed view with no persistence — hides Save/Regenerate/Export.
    readonly?: boolean;
    // When set (per-user view), the endpoint to PUT a per-product commission
    // rate to, enabling the editable commission row on the product breakdown.
    commissionUrl?: string;
}

const AUTO = ['cogs', 'shipping_fee', 'cod_fee', 'vat'];

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
const round4 = (v: number) => Math.round(v * 10000) / 10000;
const int = (v: number) => Number(v).toLocaleString('en-PH');
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
    base: baseProp,
    scope,
    readonly = false,
    commissionUrl,
}: Props) {
    const base =
        baseProp ?? `/workspaces/${workspace.slug}/finance/income-statements`;
    const isPreview = mode === 'preview';
    const month = moment(statement.period_month).format('YYYY-MM');
    const monthLabel = moment(statement.period_month).format('MMMM YYYY');

    const [rows, setRows] = useState<ExpenseRow[]>(statement.expenses);
    const [saving, setSaving] = useState(false);
    // In-flight edits to a product's commission rate, keyed by product id.
    const [rateDrafts, setRateDrafts] = useState<Record<number, string>>({});
    const [savingRate, setSavingRate] = useState<number | null>(null);

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
        if (r.source === 'cogs')
            return 'Cost of Goods, split across interns by orders';
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

    // On a per-user breakdown (a scoped view) the advisory share is hidden for
    // now — it's shown per product instead — so Net Profit here is Gross − OPEX.
    const hideAdvisory = !!scope;
    const shownAdvisory = hideAdvisory ? 0 : advisoryShare;

    const opexTotal = isPreview
        ? sumIncluded(opexRows)
        : (statement.gross_profit ?? 0) -
          (statement.net_profit ?? 0) -
          advisoryShare;
    const netProfit = isPreview
        ? grossProfit - opexTotal - shownAdvisory
        : (statement.net_profit ?? 0) + (hideAdvisory ? advisoryShare : 0);

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
        ...(statement.gencys_partner && !hideAdvisory
            ? [
                  {
                      key: 'adv',
                      label: 'Advisory',
                      amount: shownAdvisory,
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
                ...(scope?.params ?? {}),
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

    // The per-product slice of this statement — only sent on the per-user view.
    // The service keeps the unresolved "Discrepancy" row (product_id null) last.
    const products = statement.products ?? [];
    const namedProducts = products.filter((p) => p.product_id !== null);

    const productTotal = namedProducts.reduce(
        (a, r) => ({
            orders: a.orders + r.orders,
            delivered: a.delivered + r.delivered,
            cogs: a.cogs + r.cogs,
            shipping: a.shipping + r.shipping,
            cod_fee: a.cod_fee + r.cod_fee,
            vat: a.vat + r.vat,
            adspent: a.adspent + r.adspent,
            cost_of_sales: a.cost_of_sales + r.cost_of_sales,
            gross_profit: a.gross_profit + r.gross_profit,
            advisory: a.advisory + r.advisory,
            net_profit: a.net_profit + r.net_profit,
            commission: a.commission + r.commission,
        }),
        {
            orders: 0,
            delivered: 0,
            cogs: 0,
            shipping: 0,
            cod_fee: 0,
            vat: 0,
            adspent: 0,
            cost_of_sales: 0,
            gross_profit: 0,
            advisory: 0,
            net_profit: 0,
            commission: 0,
        },
    );

    /**
     * Save a product's commission rate. Typed as a percentage, stored as a
     * fraction (the column holds 4 decimals); a blank input clears it back to 0.
     * A blur that didn't actually change the rate is dropped rather than
     * round-tripping the server.
     */
    const saveCommissionRate = (
        productId: number,
        draft: string,
        current: number,
    ) => {
        const clearDraft = () =>
            setRateDrafts((prev) => {
                const next = { ...prev };
                delete next[productId];
                return next;
            });

        const parsed = draft.trim() === '' ? 0 : Number(draft);
        const rate = round4(parsed / 100);

        if (
            !commissionUrl ||
            !Number.isFinite(parsed) ||
            parsed < 0 ||
            parsed > 100 ||
            rate === round4(current)
        ) {
            clearDraft();
            return;
        }

        setSavingRate(productId);
        router.put(
            commissionUrl,
            { product_id: productId, rate },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSavingRate(null);
                    clearDraft();
                },
            },
        );
    };

    // ── Per-product breakdown ───────────────────────────────────────────────
    // Laid out like the finance sheet: one column per product, one row per line
    // item, Total on the right. The label column is sticky so it stays put while
    // the product columns scroll.
    const PCELL =
        'border-b border-black/5 px-4 py-2.5 text-right text-[12px] whitespace-nowrap tabular-nums dark:border-white/5';
    const PLABEL =
        'sticky left-0 z-10 border-b border-black/5 py-2.5 pr-4 pl-5 text-left text-[12px] whitespace-nowrap dark:border-white/5';
    const PTOTAL = 'border-l border-black/6 dark:border-white/6';

    // Repeated on the sticky label cell so scrolling columns don't show through.
    const PBG = {
        plain: 'bg-white dark:bg-zinc-900',
        muted: 'bg-stone-50 dark:bg-zinc-800/40',
        band: 'bg-stone-100 dark:bg-zinc-800/60',
    };

    /**
     * One line item across every product column plus the Total.
     * `deduction` renders the figure in parentheses, ledger-style; `signed`
     * colours it by sign (for the Gross / Net bands).
     */
    const lineRow = (
        label: string,
        pick: (r: ProductRow) => number,
        total: number,
        opts: {
            deduction?: boolean;
            tone?: 'plain' | 'muted' | 'band';
            integer?: boolean;
            emphasis?: boolean;
            signed?: boolean;
            note?: string;
        } = {},
    ) => {
        const {
            deduction = false,
            tone = 'plain',
            integer = false,
            emphasis = false,
            signed = false,
            note,
        } = opts;

        const show = (v: number) => {
            const body = integer ? int(v) : fmt(v);
            return deduction ? `(${body})` : body;
        };

        const colorFor = (v: number) => {
            if (signed)
                return v < 0
                    ? 'text-rose-600 dark:text-rose-400'
                    : 'text-emerald-600 dark:text-emerald-400';
            if (deduction) return 'text-gray-500 dark:text-gray-400';
            return 'text-gray-800 dark:text-gray-100';
        };

        return (
            <tr key={label}>
                <th
                    scope="row"
                    className={`${PLABEL} ${PBG[tone]} ${
                        emphasis
                            ? 'font-semibold text-gray-800 dark:text-gray-100'
                            : 'font-normal text-gray-600 dark:text-gray-300'
                    }`}
                >
                    {label}
                    {note && (
                        <span className="ml-2 text-[10px] text-gray-400">
                            {note}
                        </span>
                    )}
                </th>
                {products.map((r) => (
                    <td
                        key={r.product_id ?? r.product}
                        className={`${PCELL} ${PBG[tone]} ${colorFor(pick(r))} ${
                            emphasis ? 'font-semibold' : ''
                        }`}
                    >
                        {show(pick(r))}
                    </td>
                ))}
                <td
                    className={`${PCELL} ${PTOTAL} ${PBG[tone]} pr-5 ${colorFor(total)} ${
                        emphasis ? 'font-semibold' : 'font-medium'
                    }`}
                >
                    {show(total)}
                </td>
            </tr>
        );
    };

    /** A "Less:"-style divider spanning the whole table. */
    const sectionRow = (label: string) => (
        <tr key={label}>
            <th
                scope="row"
                className={`${PLABEL} ${PBG.muted} text-[10px] font-semibold tracking-wider text-gray-400 uppercase`}
            >
                {label}
            </th>
            <td
                className={`${PBG.muted} border-b border-black/5 dark:border-white/5`}
                colSpan={products.length + 1}
            />
        </tr>
    );

    /** The editable per-product commission rate. */
    const rateRow = () => (
        <tr key="commission-rate">
            <th
                scope="row"
                className={`${PLABEL} ${PBG.plain} font-normal text-gray-600 dark:text-gray-300`}
            >
                Commission Rate
            </th>
            {products.map((r) => {
                const draft =
                    r.product_id !== null
                        ? rateDrafts[r.product_id]
                        : undefined;
                const value = draft ?? String(toPct(r.commission_rate));

                return (
                    <td
                        key={r.product_id ?? r.product}
                        className={`${PBG.plain} border-b border-black/5 px-4 py-2.5 text-right dark:border-white/5`}
                    >
                        {commissionUrl && r.product_id !== null ? (
                            <span className="flex items-center justify-end gap-1">
                                <input
                                    type="number"
                                    min={0}
                                    max={100}
                                    step={0.01}
                                    value={value}
                                    disabled={savingRate === r.product_id}
                                    aria-label={`Commission rate for ${r.product} (%)`}
                                    onChange={(e) =>
                                        setRateDrafts((prev) => ({
                                            ...prev,
                                            [r.product_id as number]:
                                                e.target.value,
                                        }))
                                    }
                                    onBlur={(e) =>
                                        saveCommissionRate(
                                            r.product_id as number,
                                            e.target.value,
                                            r.commission_rate,
                                        )
                                    }
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter')
                                            e.currentTarget.blur();
                                    }}
                                    className="h-7 w-16 rounded-md border border-black/8 bg-white px-2 text-right font-mono text-[12px] text-gray-800 tabular-nums outline-none focus:border-emerald-500 disabled:opacity-50 dark:border-white/10 dark:bg-zinc-800 dark:text-gray-100"
                                />
                                <span className="text-[11px] text-gray-400">
                                    %
                                </span>
                            </span>
                        ) : (
                            <span className="text-[12px] text-gray-400 tabular-nums">
                                {r.commission_rate > 0
                                    ? `${toPct(r.commission_rate)}%`
                                    : '—'}
                            </span>
                        )}
                    </td>
                );
            })}
            <td
                className={`${PCELL} ${PTOTAL} ${PBG.plain} pr-5 text-gray-400`}
            >
                —
            </td>
        </tr>
    );

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
                    title={
                        scope?.label
                            ? `Income Statement — ${scope.label}`
                            : 'Income Statement'
                    }
                    description={
                        readonly
                            ? `${monthLabel} · live breakdown`
                            : isPreview
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
                        {!scope && statement.gencys_partner && statement.id && (
                            <>
                                <Link
                                    href={`/workspaces/${workspace.slug}/finance/income-statements/${statement.id}/users`}
                                    className={BTN}
                                >
                                    <Users className="h-3.5 w-3.5" />
                                    Per-user breakdown
                                </Link>
                                <Link
                                    href={`/workspaces/${workspace.slug}/finance/income-statements/${statement.id}/products`}
                                    className={BTN}
                                >
                                    <ShoppingBag className="h-3.5 w-3.5" />
                                    Per-product breakdown
                                </Link>
                            </>
                        )}
                        {readonly ? null : isPreview ? (
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
                    {/* Gross Profit total — hidden on the per-user breakdown for now. */}
                    {!hideAdvisory && (
                        <div className="flex items-center justify-between border-y border-black/6 bg-stone-100 px-5 py-3 dark:border-white/6 dark:bg-zinc-800/60">
                            <span className="text-[12px] font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-200">
                                = Gross Profit
                            </span>
                            <span className="text-[15px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                                {fmt(grossProfit)}
                            </span>
                        </div>
                    )}

                    {/* Advisory share (gencys partners) — deducted from gross profit.
                        Hidden on the per-user breakdown (shown per product there). */}
                    {statement.gencys_partner && !hideAdvisory && (
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
                    Profit − Advisory Share − OPEX.
                    {isPreview
                        ? ' Cost-of-Sales types are set on the Transaction Types page; uncheck any line to exclude it.'
                        : ''}
                </p>

                {/* Per-product breakdown — where this user's gross profit came
                    from, product by product. Only rendered on the per-user view
                    (the workspace statement has no per-product slice). */}
                {products.length > 0 && (
                    <>
                        <div className={`${CARD} mt-6 overflow-hidden`}>
                            <div className="flex items-center justify-between border-b border-black/6 px-5 py-4 dark:border-white/6">
                                <div>
                                    <div className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                                        Per-product breakdown
                                    </div>
                                    <div className="mt-0.5 text-[11px] text-gray-400">
                                        {monthLabel} ·{' '}
                                        {int(namedProducts.length)}{' '}
                                        {namedProducts.length === 1
                                            ? 'product'
                                            : 'products'}
                                    </div>
                                </div>
                                <span className="rounded-full border border-black/6 px-2.5 py-0.5 text-[10px] tracking-wider text-gray-400 uppercase dark:border-white/6">
                                    Live
                                </span>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full border-separate border-spacing-0">
                                    <thead>
                                        <tr>
                                            <th
                                                scope="col"
                                                className={`${PLABEL} ${PBG.band} z-20 text-[10px] font-semibold tracking-wider text-gray-400 uppercase`}
                                            >
                                                Product
                                            </th>
                                            {products.map((r) => (
                                                <th
                                                    key={
                                                        r.product_id ??
                                                        r.product
                                                    }
                                                    scope="col"
                                                    className={`${PBG.band} min-w-[140px] border-b border-black/5 px-4 py-3 text-right text-[11px] font-semibold whitespace-nowrap dark:border-white/5 ${
                                                        r.product_id === null
                                                            ? 'text-amber-700 dark:text-amber-400'
                                                            : 'text-gray-800 dark:text-gray-100'
                                                    }`}
                                                >
                                                    {r.product}
                                                </th>
                                            ))}
                                            <th
                                                scope="col"
                                                className={`${PBG.band} ${PTOTAL} min-w-[140px] border-b border-black/5 px-4 py-3 pr-5 text-right text-[11px] font-semibold tracking-wider text-gray-700 uppercase dark:border-white/5 dark:text-gray-200`}
                                            >
                                                Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {lineRow(
                                            'Delivered',
                                            (r) => r.delivered,
                                            productTotal.delivered,
                                            { emphasis: true },
                                        )}
                                        {lineRow(
                                            'Total Parcels',
                                            (r) => r.orders,
                                            productTotal.orders,
                                            { integer: true },
                                        )}

                                        {sectionRow('Less — Cost of Sales')}
                                        {lineRow(
                                            'COGS',
                                            (r) => r.cogs,
                                            productTotal.cogs,
                                            {
                                                deduction: true,
                                                note: 'split by orders',
                                            },
                                        )}
                                        {lineRow(
                                            'Shipping Fee',
                                            (r) => r.shipping,
                                            productTotal.shipping,
                                            { deduction: true },
                                        )}
                                        {lineRow(
                                            'COD Fee',
                                            (r) => r.cod_fee,
                                            productTotal.cod_fee,
                                            {
                                                deduction: true,
                                                note: `${codPct}% of delivered`,
                                            },
                                        )}
                                        {lineRow(
                                            'VAT',
                                            (r) => r.vat,
                                            productTotal.vat,
                                            {
                                                deduction: true,
                                                note: `${vatPct}% of COD fee`,
                                            },
                                        )}
                                        {lineRow(
                                            'Ad Spent',
                                            (r) => r.adspent,
                                            productTotal.adspent,
                                            { deduction: true },
                                        )}
                                        {lineRow(
                                            'Total Cost of Sales',
                                            (r) => r.cost_of_sales,
                                            productTotal.cost_of_sales,
                                            {
                                                deduction: true,
                                                tone: 'muted',
                                                emphasis: true,
                                            },
                                        )}

                                        {lineRow(
                                            '= Gross Profit',
                                            (r) => r.gross_profit,
                                            productTotal.gross_profit,
                                            {
                                                tone: 'band',
                                                emphasis: true,
                                                signed: true,
                                            },
                                        )}

                                        {statement.gencys_partner &&
                                            sectionRow('Less')}
                                        {statement.gencys_partner &&
                                            lineRow(
                                                'Advisory Share',
                                                (r) => r.advisory,
                                                productTotal.advisory,
                                                {
                                                    deduction: true,
                                                    note: `${advisoryPct}% of positive gross`,
                                                },
                                            )}

                                        {lineRow(
                                            '= Net Profit',
                                            (r) => r.net_profit,
                                            productTotal.net_profit,
                                            {
                                                tone: 'band',
                                                emphasis: true,
                                                signed: true,
                                            },
                                        )}

                                        {rateRow()}
                                        {lineRow(
                                            'Commission',
                                            (r) => r.commission,
                                            productTotal.commission,
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <p className="mt-3 text-[11px] text-gray-400">
                            Per product: Gross = Delivered − (COGS + Shipping +
                            COD + VAT + Ad Spent).
                            {statement.gencys_partner
                                ? ' Advisory is a % of positive gross profit.'
                                : ''}{' '}
                            COGS is the product&rsquo;s bulk Cost of Goods split
                            across interns by delivered orders; Ad Spent is this
                            user&rsquo;s charged share. Commission is Rate × a
                            positive Net Profit &mdash; shown for information,
                            it does not change the statement.
                            {commissionUrl
                                ? ' Edit a rate to save it for this user and product.'
                                : ''}{' '}
                            OPEX is not split per product yet &mdash; it sits at
                            the user level in the statement above.
                        </p>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
