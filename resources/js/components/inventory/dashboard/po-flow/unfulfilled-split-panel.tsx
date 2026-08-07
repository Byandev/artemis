import { differenceInCalendarDays, format, parseISO } from 'date-fns';
import RefreshButton from '../refresh-button';
import { useInventoryStat } from '../use-inventory-stat';
import {
    cellClass,
    days,
    EmptyState,
    headClass,
    num,
    numCellClass,
    panelClass,
    PanelHead,
    pct,
    TableSkeleton,
} from './shared';
import type { UnfulfilledSplitData } from './types';

/**
 * Unmet demand, split by whether the stock is physically on the shelf.
 *
 * The two halves have different owners: anything shippable is the warehouse's
 * to clear today, and the rest is waiting on whichever step above is blocked.
 * Solid green is stock that exists; hatched is absence — drawn as a hole for
 * the same reason the pipeline's "not ordered" segment is, and because a second
 * saturated hue next to green fails colour-blind separation.
 */
/** A movement past this many days is worth noticing on a row holding stock. */
const STALE_DAYS = 7;

/**
 * The date of a ledger movement, with how long ago underneath.
 *
 * `stale` marks the despatch column amber on rows that have shippable stock:
 * an item sitting on stock that last went out a week ago is the warehouse
 * signal this panel exists to surface. The receipt column is never flagged —
 * not receiving is a supply story, told elsewhere.
 */
function MovementDate({ iso, stale }: { iso: string | null; stale?: boolean }) {
    if (!iso) {
        return <span className="text-gray-300 dark:text-gray-600">—</span>;
    }

    // parseISO rather than `new Date` so a date-only string lands on local
    // midnight instead of being read as UTC and slipping a day.
    const when = parseISO(iso);
    const ago = differenceInCalendarDays(new Date(), when);
    const flag = stale && ago > STALE_DAYS;

    return (
        <>
            <span
                className={
                    flag
                        ? 'text-amber-600 dark:text-amber-500'
                        : 'text-gray-500 dark:text-gray-400'
                }
            >
                {format(when, 'd MMM')}
            </span>
            <span className="mt-0.5 block text-[10px] text-gray-400 dark:text-gray-500">
                {ago === 0 ? 'today' : `${ago}d ago`}
            </span>
        </>
    );
}

export default function UnfulfilledSplitPanel({ slug }: { slug: string }) {
    const { data, loading, error, refetch } =
        useInventoryStat<UnfulfilledSplitData>(
            slug,
            'po-flow/unfulfilled-split',
        );

    const rows = data?.items ?? [];
    const max = Math.max(...rows.map((r) => r.unfulfilled), 1);

    return (
        <section className={panelClass}>
            <PanelHead
                title="Unfulfilled — is the stock actually here?"
                help={
                    <>
                        <b>Splits unmet demand by who can fix it.</b> Solid
                        green is demand you could satisfy today from stock
                        already in the building — that is a picking and despatch
                        job, not a buying one. Hatched is demand with nothing
                        behind it, which belongs to whichever step above is
                        blocked.
                        <br />
                        <br />
                        Compared per SKU, not per group: a customer ordered a
                        specific variant, so stock on its sibling cannot ship
                        it. A large green share means the warehouse is the
                        hold-up.
                    </>
                }
                action={
                    <RefreshButton
                        onClick={refetch}
                        loading={loading}
                        error={error}
                        label="the unfulfilled split"
                    />
                }
            >
                Unmet demand split by whether stock is on the shelf. Solid means
                it could ship today; hatched means there is nothing to give. The
                two dates show when stock last arrived and last went out.
            </PanelHead>

            {loading ? (
                <div className="px-[18px] pb-6">
                    <TableSkeleton rows={5} cols={4} />
                </div>
            ) : error ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="Couldn't load the unfulfilled split." />
                </div>
            ) : !data || rows.length === 0 ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="Nothing unfulfilled — every order is covered." />
                </div>
            ) : (
                <>
                    <div className="px-[18px] pb-4">
                        <div className="flex h-11 gap-0.5">
                            {data.here > 0 && (
                                <div
                                    style={{ flex: data.here }}
                                    className="flex min-w-0 flex-col justify-center rounded bg-emerald-600 px-2.5 text-white"
                                    title={`${num(data.here)} units could ship today from stock on hand`}
                                >
                                    <span className="font-mono text-sm leading-tight font-semibold tabular-nums">
                                        {num(data.here)}
                                    </span>
                                    <span className="mt-0.5 truncate text-[9px] tracking-[0.07em] uppercase opacity-90">
                                        Stock is here ·{' '}
                                        {pct(data.here, data.total)}%
                                    </span>
                                </div>
                            )}
                            {data.gone > 0 && (
                                <div
                                    style={{ flex: data.gone }}
                                    className="flex min-w-0 flex-col justify-center rounded border border-dashed border-gray-400 px-2.5 dark:border-gray-500"
                                    title={`${num(data.gone)} units of demand with no stock behind them`}
                                >
                                    <span className="font-mono text-sm leading-tight font-semibold text-gray-600 tabular-nums dark:text-gray-300">
                                        {num(data.gone)}
                                    </span>
                                    <span className="mt-0.5 truncate text-[9px] tracking-[0.07em] text-gray-400 uppercase dark:text-gray-500">
                                        No stock · {pct(data.gone, data.total)}%
                                    </span>
                                </div>
                            )}
                        </div>

                        <p className="mt-3.5 rounded-[10px] bg-stone-100 px-3.5 py-3 text-xs leading-relaxed text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                            <b className="font-semibold text-gray-900 dark:text-gray-100">
                                {num(data.here)} units
                            </b>{' '}
                            could ship today from stock already in the building
                            — that is the warehouse&rsquo;s to fix. The other{' '}
                            <b className="font-semibold text-gray-900 dark:text-gray-100">
                                {num(data.gone)}
                            </b>{' '}
                            have nothing behind them, so they belong to
                            whichever step above is blocked.
                            {data.sitting.units > 0 && (
                                <>
                                    {' '}
                                    Of the shippable part,{' '}
                                    <b className="font-semibold text-gray-900 dark:text-gray-100">
                                        {num(data.sitting.units)}
                                    </b>{' '}
                                    across {data.sitting.skus} SKU
                                    {data.sitting.skus === 1 ? '' : 's'} covers
                                    more than {data.picking_days} days of demand
                                    — past a normal picking queue.
                                </>
                            )}
                        </p>
                    </div>

                    <div className="max-h-[420px] overflow-auto border-t border-black/6 dark:border-white/6">
                        <table className="w-full border-collapse">
                            <thead className="sticky top-0 z-10 bg-zinc-50 dark:bg-zinc-900">
                                <tr className="border-b border-black/6 dark:border-white/6">
                                    <th className={headClass}>Item</th>
                                    <th className={`${headClass} text-right!`}>
                                        Unfulfilled
                                    </th>
                                    <th className={headClass}>
                                        Stock here / none
                                    </th>
                                    <th className={`${headClass} text-right!`}>
                                        Last In
                                    </th>
                                    <th className={`${headClass} text-right!`}>
                                        Last Out
                                    </th>
                                    <th className={`${headClass} text-right!`}>
                                        Can ship
                                    </th>
                                    <th className={`${headClass} text-right!`}>
                                        No stock
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-black/5 transition-colors last:border-0 hover:bg-zinc-50 dark:border-white/5 dark:hover:bg-zinc-800/50"
                                    >
                                        <td
                                            className={`${cellClass} text-xs font-medium text-gray-900 dark:text-gray-100`}
                                        >
                                            {row.item}
                                        </td>
                                        <td
                                            className={`${numCellClass} text-gray-500 dark:text-gray-400`}
                                        >
                                            {num(row.unfulfilled)}
                                        </td>
                                        <td
                                            className={`${cellClass} min-w-[150px]`}
                                        >
                                            <div
                                                className="flex h-4 gap-0.5"
                                                style={{
                                                    width: `${Math.max((row.unfulfilled / max) * 100, 8)}%`,
                                                }}
                                                title={`${row.item} — ${num(row.here)} shippable, ${num(row.gone)} with no stock`}
                                            >
                                                {row.here > 0 && (
                                                    <i
                                                        className="block rounded-[3px] bg-emerald-600"
                                                        style={{
                                                            flex: row.here,
                                                        }}
                                                    />
                                                )}
                                                {row.gone > 0 && (
                                                    <i
                                                        className="block rounded-[3px] border border-dashed border-gray-400 dark:border-gray-500"
                                                        style={{
                                                            flex: row.gone,
                                                        }}
                                                    />
                                                )}
                                            </div>
                                        </td>
                                        {/* Stock arriving and stock leaving. On
                                            a row with shippable stock, an old
                                            "out" date is the warehouse sitting
                                            on it; an old "in" is nothing turning
                                            up. */}
                                        <td className={`${numCellClass}`}>
                                            <MovementDate iso={row.last_in} />
                                        </td>
                                        <td className={`${numCellClass}`}>
                                            <MovementDate
                                                iso={row.last_out}
                                                stale={row.here > 0}
                                            />
                                        </td>
                                        <td
                                            className={`${numCellClass} ${
                                                row.here
                                                    ? 'font-semibold text-emerald-600 dark:text-emerald-400'
                                                    : 'text-gray-300 dark:text-gray-600'
                                            }`}
                                            title={
                                                row.here_days
                                                    ? `${days(row.here_days)} of demand sitting on the shelf`
                                                    : undefined
                                            }
                                        >
                                            {row.here ? num(row.here) : '—'}
                                        </td>
                                        <td
                                            className={`${numCellClass} text-gray-400 dark:text-gray-500`}
                                        >
                                            {row.gone ? num(row.gone) : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </section>
    );
}
