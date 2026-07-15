import { Fragment, useMemo } from 'react';

export interface ViewStat {
    orders: number;
    amount: number;
    roas: number | null;
}

export interface RoasRow {
    date: string;
    total_orders: number;
    total_orders_amount: number;
    total_ad_spent: number;
    roas: number | null;
    views: Record<string, ViewStat>;
}

export interface ViewColumn {
    key: string;
    label: string;
}

/** Number with grouping and a fixed number of decimals (no currency symbol). */
const num = (v: number | null | undefined, dp = 2) =>
    Number(v ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: dp,
        maximumFractionDigits: dp,
    });

const roas = (v: number | null) => (v == null ? '—' : num(v, 2));

const groupHead =
    'border-b border-black/6 bg-stone-100 px-4 py-2 text-center text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:border-white/6 dark:bg-zinc-800/60 dark:text-gray-400';
const colHead =
    'border-b border-black/6 bg-stone-50 px-4 py-2.5 text-center text-[10px] font-semibold uppercase tracking-wider text-gray-400 whitespace-nowrap dark:border-white/6 dark:bg-zinc-800/40';
const cell =
    'border-b border-black/5 px-4 py-2.5 text-center text-[12px] tabular-nums text-gray-700 dark:border-white/5 dark:text-gray-300';

/**
 * ADSPENT ROAS SUMMARY table in the Artemis card style. Always shows the TOTAL
 * group; each entry in `views` adds an Orders/Amount/ROAS group. Totals and
 * averages are derived here so the caller only passes rows.
 */
export default function AdSpentRoasTable({
    rows,
    views = [],
}: {
    rows: RoasRow[];
    views?: ViewColumn[];
}) {
    const totals = useMemo(() => {
        const n = rows.length || 1;
        const t = {
            orders: 0,
            amount: 0,
            spent: 0,
            views: {} as Record<string, { orders: number; amount: number }>,
        };
        for (const r of rows) {
            t.orders += r.total_orders;
            t.amount += r.total_orders_amount;
            t.spent += r.total_ad_spent;
            for (const v of views) {
                t.views[v.key] ??= { orders: 0, amount: 0 };
                t.views[v.key].orders += r.views[v.key]?.orders ?? 0;
                t.views[v.key].amount += r.views[v.key]?.amount ?? 0;
            }
        }
        return {
            ...t,
            roas: t.spent > 0 ? t.amount / t.spent : null,
            avg: { orders: t.orders / n, amount: t.amount / n, spent: t.spent / n },
        };
    }, [rows, views]);

    return (
        <div className="overflow-x-auto rounded-xl border border-black/6 dark:border-white/6">
            <table className="w-full border-collapse">
                <thead>
                    {/* Group header row */}
                    <tr>
                        <th className={groupHead} />
                        <th colSpan={4} className={groupHead}>
                            Total
                        </th>
                        {views.map((v) => (
                            <th key={v.key} colSpan={3} className={groupHead}>
                                {v.label}
                            </th>
                        ))}
                    </tr>
                    {/* Column header row */}
                    <tr>
                        <th className={`${colHead} text-left`}>Date</th>
                        <th className={colHead}>Total Orders</th>
                        <th className={colHead}>Total Orders Amount</th>
                        <th className={colHead}>Total Ad Spent</th>
                        <th className={colHead}>ROAS</th>
                        {views.map((v) => (
                            <Fragment key={v.key}>
                                <th className={colHead}>Orders</th>
                                <th className={colHead}>Amount</th>
                                <th className={colHead}>ROAS</th>
                            </Fragment>
                        ))}
                    </tr>
                </thead>

                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td
                                colSpan={5 + views.length * 3}
                                className="px-4 py-10 text-center text-[13px] text-gray-400 dark:text-gray-500"
                            >
                                No data for this range.
                            </td>
                        </tr>
                    ) : (
                        rows.map((r, i) => (
                            <tr
                                key={r.date}
                                className={
                                    i % 2 === 1
                                        ? 'bg-stone-50/60 dark:bg-zinc-800/20'
                                        : ''
                                }
                            >
                                <td
                                    className={`${cell} text-left font-medium text-gray-800 dark:text-gray-100`}
                                >
                                    {r.date}
                                </td>
                                <td className={cell}>{num(r.total_orders, 0)}</td>
                                <td className={cell}>
                                    {num(r.total_orders_amount)}
                                </td>
                                <td className={cell}>{num(r.total_ad_spent)}</td>
                                <td
                                    className={`${cell} font-semibold text-brand-600 dark:text-brand-400`}
                                >
                                    {roas(r.roas)}
                                </td>
                                {views.map((v) => {
                                    const s = r.views[v.key];
                                    return (
                                        <Fragment key={v.key}>
                                            <td className={cell}>
                                                {num(s?.orders ?? 0, 0)}
                                            </td>
                                            <td className={cell}>
                                                {num(s?.amount ?? 0)}
                                            </td>
                                            <td
                                                className={`${cell} font-medium text-brand-600 dark:text-brand-400`}
                                            >
                                                {roas(s?.roas ?? null)}
                                            </td>
                                        </Fragment>
                                    );
                                })}
                            </tr>
                        ))
                    )}
                </tbody>

                <tfoot>
                    {/* TOTAL AMOUNT */}
                    <tr className="bg-brand-50 font-semibold text-brand-800 dark:bg-brand-500/10 dark:text-brand-300">
                        <td className={`${cell} text-left !text-brand-800 dark:!text-brand-300`}>
                            Total
                        </td>
                        <td className={cell}>{num(totals.orders)}</td>
                        <td className={cell}>{num(totals.amount)}</td>
                        <td className={cell}>{num(totals.spent)}</td>
                        <td className={cell}>{roas(totals.roas)}</td>
                        {views.map((v) => (
                            <Fragment key={v.key}>
                                <td className={cell}>
                                    {num(totals.views[v.key]?.orders ?? 0)}
                                </td>
                                <td className={cell}>
                                    {num(totals.views[v.key]?.amount ?? 0)}
                                </td>
                                <td className={cell}>
                                    {roas(
                                        totals.spent > 0
                                            ? (totals.views[v.key]?.amount ??
                                                  0) / totals.spent
                                            : null,
                                    )}
                                </td>
                            </Fragment>
                        ))}
                    </tr>
                    {/* AVERAGE */}
                    <tr className="bg-stone-100 font-semibold text-gray-700 dark:bg-zinc-800 dark:text-gray-200">
                        <td className={`${cell} text-left`}>Average</td>
                        <td className={cell}>{num(totals.avg.orders)}</td>
                        <td className={cell}>{num(totals.avg.amount)}</td>
                        <td className={cell}>{num(totals.avg.spent)}</td>
                        <td className={cell} />
                        {views.map((v) => (
                            <td key={v.key} colSpan={3} className={cell} />
                        ))}
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}
