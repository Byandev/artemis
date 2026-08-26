import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft } from 'lucide-react';

interface StatementContext {
    id: number;
    period_month: string; // YYYY-MM-DD
    month: string; // YYYY-MM
    label: string; // "July 2026"
}

/**
 * A saved product row. Goods bought this month are tracked apart from the cost
 * of the goods that actually shipped — in any one month the two rarely match.
 */
interface ProductRow {
    product_id: number | null;
    product: string;
    delivered_count: number;
    delivered_amount: number;
    total_bought_cogs: number;
    total_bought_cogs_delivery_fee: number;
    total_delivered_cogs: number;
}

interface MissingUnitCode {
    unit_code: string;
    orders: number;
}

interface Props {
    workspace: Workspace;
    incomeStatement: StatementContext;
    products: ProductRow[];
    total: ProductRow;
    missingUnitCodes: MissingUnitCode[];
}

const fmt = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const int = (v: number) => Number(v).toLocaleString('en-PH');

const CARD =
    'rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900';
const BTN =
    'flex h-8 items-center gap-1.5 rounded-lg border border-black/6 bg-stone-50 px-3 font-mono text-[12px] text-gray-600 transition-all hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700';
const COL = 'px-4 py-3 text-right text-[12px] whitespace-nowrap tabular-nums';
const HEAD =
    'px-4 py-3 text-right text-[10px] font-semibold tracking-wider text-gray-400 uppercase whitespace-nowrap';

export default function ProductIncomeStatements({
    workspace,
    incomeStatement,
    products,
    total,
    missingUnitCodes,
}: Props) {
    const finance = `/workspaces/${workspace.slug}/finance`;
    const named = products.filter((p) => p.product_id !== null);

    // The cost of what shipped against what was bought — the gap is inventory
    // moving in or out of stock, not profit.
    const boughtAll =
        total.total_bought_cogs + total.total_bought_cogs_delivery_fee;

    const cells = (r: ProductRow, muted = false) => {
        const tone = muted
            ? 'text-amber-700 dark:text-amber-400'
            : 'text-gray-800 dark:text-gray-100';
        return (
            <>
                <td className={`${COL} text-gray-500 dark:text-gray-400`}>
                    {int(r.delivered_count)}
                </td>
                <td className={`${COL} font-medium ${tone}`}>
                    {fmt(r.delivered_amount)}
                </td>
                <td className={`${COL} text-gray-500 dark:text-gray-400`}>
                    {fmt(r.total_bought_cogs)}
                </td>
                <td className={`${COL} text-gray-500 dark:text-gray-400`}>
                    {fmt(r.total_bought_cogs_delivery_fee)}
                </td>
                <td className={`${COL} pr-5 font-medium ${tone}`}>
                    {fmt(r.total_delivered_cogs)}
                </td>
            </>
        );
    };

    return (
        <AppLayout>
            <Head
                title={`${workspace.name} - Product Statement ${incomeStatement.label}`}
            />
            <div className="w-full p-4 font-mono md:p-6">
                <PageHeader
                    title="Product Statement"
                    description={`${incomeStatement.label} · every product, across all interns`}
                >
                    <Link
                        href={`${finance}/income-statements/${incomeStatement.id}`}
                        className={BTN}
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back to statement
                    </Link>
                </PageHeader>

                {/* Unit codes with no product behind them — the orders using
                    them can't be attributed, so they land in Unresolved. */}
                {missingUnitCodes.length > 0 && (
                    <div className="mb-6 rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 dark:border-amber-800/60 dark:bg-amber-950/40">
                        <div className="flex items-center gap-2 text-[12px] text-amber-800 dark:text-amber-200">
                            <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                            {int(missingUnitCodes.length)} unit code
                            {missingUnitCodes.length === 1 ? '' : 's'} on this
                            month&rsquo;s delivered orders map to no product.{' '}
                            <Link
                                href={`/workspaces/${workspace.slug}/gencys/unit-codes`}
                                className="underline underline-offset-2"
                            >
                                Map them
                            </Link>{' '}
                            to clear the Unresolved row.
                        </div>
                    </div>
                )}

                <div className={`${CARD} overflow-hidden`}>
                    <div className="flex items-center justify-between border-b border-black/6 px-5 py-4 dark:border-white/6">
                        <div>
                            <div className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                                Per-product figures
                            </div>
                            <div className="mt-0.5 text-[11px] text-gray-400">
                                {incomeStatement.label} · {int(named.length)}{' '}
                                {named.length === 1 ? 'product' : 'products'}
                            </div>
                        </div>
                        <span className="rounded-full border border-black/6 px-2.5 py-0.5 text-[10px] tracking-wider text-gray-400 uppercase dark:border-white/6">
                            Saved
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-black/6 dark:border-white/6">
                                    <th className="px-5 py-3 text-left text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                                        Product
                                    </th>
                                    <th className={HEAD}>Delivered Count</th>
                                    <th className={HEAD}>Delivered Amount</th>
                                    <th className={HEAD}>Bought COGS</th>
                                    <th className={HEAD}>
                                        Bought COGS Delivery Fee
                                    </th>
                                    <th className={`${HEAD} pr-5`}>
                                        Delivered COGS
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-black/5 dark:divide-white/5">
                                {products.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-5 py-12 text-center text-[12px] text-gray-400"
                                        >
                                            Nothing delivered in{' '}
                                            {incomeStatement.label}.
                                        </td>
                                    </tr>
                                )}

                                {products.map((r) => {
                                    const unresolved = r.product_id === null;
                                    return (
                                        <tr
                                            key={r.product_id ?? 'unresolved'}
                                            className={
                                                unresolved
                                                    ? 'bg-amber-50/70 dark:bg-amber-500/10'
                                                    : 'transition-colors hover:bg-stone-50 dark:hover:bg-zinc-800/40'
                                            }
                                        >
                                            <td className="py-3 pr-4 pl-5">
                                                <div className="flex items-center gap-2">
                                                    {unresolved && (
                                                        <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-amber-500" />
                                                    )}
                                                    <span className="min-w-0">
                                                        <span
                                                            className={`block truncate text-[13px] font-medium ${
                                                                unresolved
                                                                    ? 'text-amber-700 dark:text-amber-400'
                                                                    : 'text-gray-800 dark:text-gray-100'
                                                            }`}
                                                        >
                                                            {r.product}
                                                        </span>
                                                        {unresolved && (
                                                            <span className="text-[10px] text-gray-400">
                                                                orders that
                                                                resolve to no
                                                                product
                                                            </span>
                                                        )}
                                                    </span>
                                                </div>
                                            </td>
                                            {cells(r, unresolved)}
                                        </tr>
                                    );
                                })}
                            </tbody>
                            <tfoot>
                                <tr className="border-t border-black/6 bg-stone-100 dark:border-white/6 dark:bg-zinc-800/60">
                                    <td className="py-3.5 pr-4 pl-5 text-[12px] font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-200">
                                        Total
                                    </td>
                                    <td
                                        className={`${COL} font-semibold text-gray-600 dark:text-gray-300`}
                                    >
                                        {int(total.delivered_count)}
                                    </td>
                                    <td
                                        className={`${COL} font-semibold text-gray-800 dark:text-gray-100`}
                                    >
                                        {fmt(total.delivered_amount)}
                                    </td>
                                    <td
                                        className={`${COL} font-semibold text-gray-600 dark:text-gray-300`}
                                    >
                                        {fmt(total.total_bought_cogs)}
                                    </td>
                                    <td
                                        className={`${COL} font-semibold text-gray-600 dark:text-gray-300`}
                                    >
                                        {fmt(
                                            total.total_bought_cogs_delivery_fee,
                                        )}
                                    </td>
                                    <td
                                        className={`${COL} pr-5 font-semibold text-gray-800 dark:text-gray-100`}
                                    >
                                        {fmt(total.total_delivered_cogs)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <p className="mt-3 text-[11px] text-gray-400">
                    Every figure is the product&rsquo;s own, across all interns.
                    Bought COGS is what was purchased this month (its freight
                    kept separate); Delivered COGS is the cost of the goods that
                    actually shipped, summed off the orders. Bought{' '}
                    {fmt(boughtAll)} against {fmt(total.total_delivered_cogs)}{' '}
                    delivered — the gap is stock moving in or out, not profit.
                    The Total excludes the Unresolved row.
                </p>
            </div>
        </AppLayout>
    );
}
