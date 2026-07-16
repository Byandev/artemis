import { useMemo } from 'react';

export interface RoasRow {
    date: string;
    orders: number;
    sales: number;
    ad_spent: number;
    roas: number | null;
}

/** Number with grouping and a fixed number of decimals (no currency symbol). */
const num = (v: number | null | undefined, dp = 2) =>
    Number(v ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: dp,
        maximumFractionDigits: dp,
    });

/** Peso-formatted amount (sales / ad spend). */
const peso = (v: number | null | undefined, dp = 2) => `₱${num(v, dp)}`;

const roas = (v: number | null) => (v == null ? '—' : num(v, 2));

const colHead =
    'border-b border-black/6 bg-stone-50 px-4 py-2.5 text-center font-mono text-[10px] font-semibold uppercase tracking-wider text-gray-400 whitespace-nowrap dark:border-white/6 dark:bg-zinc-800/40';
const cell =
    'border-b border-black/5 px-4 py-2.5 text-center font-mono text-[12px] tabular-nums text-gray-700 dark:border-white/5 dark:text-gray-300';

/**
 * ADSPENT ROAS SUMMARY table in the Artemis card style — orders, sales, amount
 * spent and ROAS per day. Totals and averages are derived here so the caller
 * only passes rows.
 */
export default function AdSpentRoasTable({ rows }: { rows: RoasRow[] }) {
    const totals = useMemo(() => {
        const n = rows.length || 1;
        const t = { orders: 0, sales: 0, spent: 0 };
        for (const r of rows) {
            t.orders += r.orders;
            t.sales += r.sales;
            t.spent += r.ad_spent;
        }
        return {
            ...t,
            roas: t.spent > 0 ? t.sales / t.spent : null,
            avg: {
                orders: t.orders / n,
                sales: t.sales / n,
                spent: t.spent / n,
            },
        };
    }, [rows]);

    return (
        <div className="overflow-x-auto rounded-xl border border-black/6 dark:border-white/6">
            <table className="w-full border-collapse">
                <thead>
                    <tr>
                        <th className={`${colHead} text-left`}>Date</th>
                        <th className={colHead}>Orders</th>
                        <th className={colHead}>Sales</th>
                        <th className={colHead}>Amount Spent</th>
                        <th className={colHead}>ROAS</th>
                    </tr>
                </thead>

                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td
                                colSpan={5}
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
                                <td className={cell}>{num(r.orders, 0)}</td>
                                <td className={cell}>{peso(r.sales)}</td>
                                <td className={cell}>{peso(r.ad_spent)}</td>
                                <td
                                    className={`${cell} font-semibold text-brand-600 dark:text-brand-400`}
                                >
                                    {roas(r.roas)}
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>

                <tfoot>
                    <tr className="bg-brand-50 font-semibold text-brand-800 dark:bg-brand-500/10 dark:text-brand-300">
                        <td
                            className={`${cell} text-left !text-brand-800 dark:!text-brand-300`}
                        >
                            Total
                        </td>
                        <td className={cell}>{num(totals.orders, 0)}</td>
                        <td className={cell}>{peso(totals.sales)}</td>
                        <td className={cell}>{peso(totals.spent)}</td>
                        <td className={cell}>{roas(totals.roas)}</td>
                    </tr>
                    <tr className="bg-stone-100 font-semibold text-gray-700 dark:bg-zinc-800 dark:text-gray-200">
                        <td className={`${cell} text-left`}>Average</td>
                        <td className={cell}>{num(totals.avg.orders)}</td>
                        <td className={cell}>{peso(totals.avg.sales)}</td>
                        <td className={cell}>{peso(totals.avg.spent)}</td>
                        <td className={cell} />
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}
