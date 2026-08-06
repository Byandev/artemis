import { format, parseISO } from 'date-fns';
import RefreshButton from '../refresh-button';
import { useInventoryStat } from '../use-inventory-stat';
import {
    cellClass,
    days,
    EmptyState,
    headClass,
    KIND_COLOR,
    num,
    numCellClass,
    panelClass,
    PanelHead,
    TableSkeleton,
} from './shared';
import type { SupplierData, SupplierLine, SupplierTally } from './types';

/** At or above this fill, an order is nearly done and not worth a phone call. */
const NEARLY_DONE_PCT = 90;

const shortDate = (iso: string | null) =>
    iso ? format(parseISO(iso), 'd MMM yyyy') : 'none';

/**
 * Orders a supplier already has and has not finished delivering.
 *
 * Ranked by wait, and split three ways in the header because the three need
 * different conversations: nothing arrived at all, started then stalled, and
 * simply past the quoted lead time. Rows that are all but complete fade — ten
 * of your open lines are 90%+ filled with a handful of units outstanding, and
 * chasing those wastes the call.
 */
export default function SupplierDeliveriesPanel({ slug }: { slug: string }) {
    const { data, loading, error, refetch } = useInventoryStat<SupplierData>(
        slug,
        'po-flow/supplier-deliveries',
    );

    const lines = data?.lines ?? [];

    return (
        <section className={panelClass}>
            <PanelHead
                title="Chase these deliveries"
                action={
                    <RefreshButton
                        onClick={refetch}
                        loading={loading}
                        error={error}
                        label="supplier deliveries"
                    />
                }
            >
                Orders a supplier already has and has not finished delivering,
                longest wait first. Rows almost complete are faded — a handful
                of units outstanding is not worth a phone call.
            </PanelHead>

            {loading ? (
                <div className="px-[18px] pb-6">
                    <TableSkeleton rows={5} cols={6} />
                </div>
            ) : error ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="Couldn't load supplier deliveries." />
                </div>
            ) : lines.length === 0 ? (
                <div className="px-[18px] pb-6">
                    <EmptyState message="Nothing outstanding with a supplier — every released order has landed in full." />
                </div>
            ) : (
                <>
                    <div className="grid gap-px border-t border-black/6 bg-black/6 sm:grid-cols-3 dark:border-white/6 dark:bg-white/6">
                        <Tally
                            label="Nothing arrived at all"
                            tally={data!.nothing_arrived}
                            note="not one unit received"
                            tone="bad"
                        />
                        <Tally
                            label="Part-delivered, stalled"
                            tally={data!.part_delivered}
                            note="supplier started, then stopped"
                        />
                        <Tally
                            label={`Past the ${data!.quoted_days}-day target`}
                            tally={data!.past_quote}
                            note="overdue against the delivery target"
                            tone={
                                data!.past_quote.units > 0 ? 'bad' : undefined
                            }
                        />
                    </div>

                    <div className="max-h-[420px] overflow-auto border-t border-black/6 dark:border-white/6">
                        <table className="w-full border-collapse">
                            <thead className="sticky top-0 z-10 bg-zinc-50 dark:bg-zinc-900">
                                <tr className="border-b border-black/6 dark:border-white/6">
                                    <th className={headClass}>PO</th>
                                    <th className={headClass}>Waiting</th>
                                    <th className={headClass}>Item</th>
                                    <th className={headClass}>Delivered</th>
                                    <th className={`${headClass} text-right!`}>
                                        Still owed
                                    </th>
                                    <th className={`${headClass} text-right!`}>
                                        Last delivery
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((line) => (
                                    <Row
                                        key={line.id}
                                        line={line}
                                        quoted={data!.quoted_days}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </section>
    );
}

function Tally({
    label,
    tally,
    note,
    tone,
}: {
    label: string;
    tally: SupplierTally;
    note: string;
    tone?: 'bad';
}) {
    return (
        <div className="bg-white p-[18px] pb-4 dark:bg-zinc-900">
            <div className="text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {label}
            </div>
            <div
                className={`mt-1.5 font-mono text-[19px] font-semibold tracking-tight tabular-nums ${
                    tone === 'bad'
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-gray-900 dark:text-gray-100'
                }`}
            >
                {num(tally.units)}
            </div>
            <div className="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                {/* Orders, not lines: one PO with three late lines is one call. */}
                {tally.orders} order{tally.orders === 1 ? '' : 's'} · {note}
            </div>
        </div>
    );
}

function Row({ line, quoted }: { line: SupplierLine; quoted: number }) {
    const nearlyDone = line.fill_pct >= NEARLY_DONE_PCT;
    const untouched = line.delivered === 0;

    return (
        <tr
            className={`border-b border-black/5 transition-colors last:border-0 hover:bg-zinc-50 dark:border-white/5 dark:hover:bg-zinc-800/50 ${
                nearlyDone ? 'opacity-55' : ''
            }`}
        >
            <td
                className={`${cellClass} font-mono text-xs whitespace-nowrap text-gray-400 dark:text-gray-500`}
            >
                {line.po}
            </td>
            <td
                className={`${cellClass} font-mono text-xs whitespace-nowrap tabular-nums ${
                    line.age > quoted
                        ? 'font-semibold text-red-600 dark:text-red-400'
                        : 'text-gray-400 dark:text-gray-500'
                }`}
            >
                {days(line.age)}
            </td>
            <td className={`${cellClass} text-xs`}>
                <div className="font-medium text-gray-900 dark:text-gray-100">
                    {line.item}
                </div>
                {line.supplier && (
                    <div className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                        {line.supplier}
                    </div>
                )}
            </td>
            <td className={`${cellClass} min-w-[150px]`}>
                <div className="flex items-center gap-2">
                    <div
                        className="h-4 flex-1 overflow-hidden rounded bg-black/5 dark:bg-white/8"
                        title={`${num(line.delivered)} of ${num(line.ordered)} delivered`}
                    >
                        <div
                            className="h-full rounded"
                            style={{
                                width: `${line.fill_pct}%`,
                                // A supplier that has sent nothing is a
                                // different conversation from one mid-delivery.
                                background: untouched
                                    ? 'var(--color-red-600)'
                                    : nearlyDone
                                      ? 'var(--color-gray-400)'
                                      : KIND_COLOR.supplier,
                            }}
                        />
                    </div>
                    <span className="w-8 text-right font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                        {line.fill_pct}%
                    </span>
                </div>
            </td>
            <td
                className={`${numCellClass} font-semibold text-gray-900 dark:text-gray-100`}
            >
                {num(line.balance)}
            </td>
            <td className={`${numCellClass} text-gray-400 dark:text-gray-500`}>
                {shortDate(line.last_delivery)}
            </td>
        </tr>
    );
}
