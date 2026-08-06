import { Skeleton } from '@/components/ui/skeleton';
import RefreshButton from '../refresh-button';
import { useInventoryStat } from '../use-inventory-stat';
import { EmptyState, num, panelClass, PanelHelp, STATE_STYLE } from './shared';
import type { BottleneckData, FlowOwner } from './types';

/**
 * The headline sentence, written from whichever owners are blocked.
 *
 * Kept in the client rather than the API: it is copy, and it changes far more
 * often than the scoring behind it.
 */
function verdict(data: BottleneckData): { headline: string; why: string } {
    const blocked = data.owners.filter((o) => o.state === 'blocked');
    const watch = data.owners.filter((o) => o.state === 'watch');

    if (blocked.length === 1) {
        const only = blocked[0];
        const why =
            only.key === 'operations'
                ? `Stock is being ordered, then held in your own approval and payment queues. ${num(data.internal_units)} units have not been sent to a supplier.`
                : only.key === 'supplier'
                  ? `Orders are leaving the office and then stalling. ${num(data.supplier_units)} units are with suppliers, and too much of it is past the delivery target.`
                  : `Goods are on the shelf with orders waiting on them — ${num(only.value)} units could have shipped days ago.`;

        return { headline: only.name, why };
    }

    if (blocked.length > 1) {
        return {
            headline: blocked.map((o) => o.name).join(' and '),
            why: `More than one step is holding stock. Clear ${blocked[0].name} first — it is holding the most.`,
        };
    }

    if (watch.length) {
        return {
            headline: `${watch.map((o) => o.name).join(' and ')} — worth watching`,
            why: 'Nothing is blocked, but this is where the slack is thinnest.',
        };
    }

    return {
        headline: 'Nothing is stuck',
        why: 'Every step is inside its target. No bottleneck right now.',
    };
}

/**
 * What each card is counting, and what would clear it.
 *
 * Copy lives here rather than in the API for the same reason the headline
 * sentence does — it changes far more often than the scoring behind it.
 */
const OWNER_HELP: Record<FlowOwner['key'], React.ReactNode> = {
    operations: (
        <>
            <b>Units on purchase orders that exist but have not been sent.</b>{' '}
            Raised, then sitting in approval or payment — no supplier has seen
            them, so nothing is on its way.
            <br />
            <br />
            <b>Past the target</b> is the part that has waited longer than a
            week. <b>Longest wait</b> and <b>orders held</b> count from when
            each order was raised.
            <br />
            <br />
            Goes red the moment any stock breaches the target, because an order
            nobody has actioned is pure delay — the work itself takes seconds.
            Clearing the approval and payment queues empties this card.
        </>
    ),
    supplier: (
        <>
            <b>
                Units on orders a supplier has and has not finished delivering.
            </b>
            <br />
            <br />
            <b>Past the target</b> is the part older than the delivery target.
            One caveat worth knowing: the clock runs from when the order was{' '}
            <i>raised</i>, not released — most orders carry no release timestamp
            yet, so this includes any time they spent in your own queues and
            reads slightly harsh on the supplier.
            <br />
            <br />
            Goes red only when more than 40% of in-transit stock is overdue. A
            couple of late orders is normal; most of the book being late is not.
        </>
    ),
    warehouse: (
        <>
            <b>Stock physically here with an unfulfilled order against it.</b>{' '}
            The headline counts only what has been sittable more than three days
            — <b>could ship today</b> is the full figure including the normal
            picking queue.
            <br />
            <br />
            <b>No stock to give</b> is the other half of unfulfilled demand:
            real orders with nothing behind them, which belong to whichever step
            above is blocked rather than to the warehouse.
            <br />
            <br />
            Goes red above 15% of unmet demand. One caveat: this is a snapshot,
            not a stopwatch — a high number can also mean the unfulfilled counts
            are stale rather than that nothing is being picked. Worth spot-
            checking a couple of SKUs before anyone is blamed.
        </>
    ),
};

/**
 * Opens the dashboard by answering the only question that matters first: is it
 * operations, the supplier, or the warehouse?
 *
 * Each owner is scored server-side from its own evidence, so the verdict
 * follows the data rather than the story anyone expects — clear the internal
 * queue and this panel will start blaming the supplier on its own.
 */
export default function BottleneckPanel({ slug }: { slug: string }) {
    const { data, loading, error, refetch } = useInventoryStat<BottleneckData>(
        slug,
        'po-flow/bottleneck',
    );

    if (loading) {
        return (
            <section className={panelClass}>
                <div className="p-[18px]">
                    <Skeleton className="h-3 w-24" />
                    <Skeleton className="mt-3 h-8 w-64" />
                    <Skeleton className="mt-3 h-3 w-full max-w-xl" />
                </div>
                <div className="grid gap-px border-t border-black/6 bg-black/6 md:grid-cols-3 dark:border-white/6 dark:bg-white/6">
                    {[0, 1, 2].map((i) => (
                        <div
                            key={i}
                            className="bg-white p-[18px] dark:bg-zinc-900"
                        >
                            <Skeleton className="h-3 w-20" />
                            <Skeleton className="mt-3 h-3 w-full" />
                            <Skeleton className="mt-4 h-6 w-24" />
                            <Skeleton className="mt-4 h-16 w-full" />
                        </div>
                    ))}
                </div>
            </section>
        );
    }

    if (error || !data) {
        return (
            <section className={panelClass}>
                <div className="p-[18px]">
                    <EmptyState message="Couldn't work out where the hold-up is." />
                    <div className="mt-3 flex justify-center">
                        <RefreshButton
                            onClick={refetch}
                            loading={loading}
                            error
                            label="the bottleneck"
                        />
                    </div>
                </div>
            </section>
        );
    }

    const { headline, why } = verdict(data);

    return (
        <section className={panelClass}>
            <div className="flex items-start justify-between gap-4 border-b border-black/6 p-[18px] pt-5 dark:border-white/6">
                <div>
                    <div className="flex items-center gap-1.5">
                        <p className="text-[10px] font-semibold tracking-[0.14em] text-gray-400 uppercase dark:text-gray-500">
                            The bottleneck is
                        </p>
                        <PanelHelp>
                            <b>
                                Three questions, answered from the data rather
                                than from anyone&rsquo;s opinion.
                            </b>
                            <br />
                            <br />
                            <b>Operations</b> — are we raising and processing
                            purchase orders on time? Blocked when any stock has
                            sat in an internal queue past the target.
                            <br />
                            <b>Supplier</b> — are suppliers delivering what we
                            have released to them? Blocked when more than 40% of
                            in-transit stock is past the delivery target.
                            <br />
                            <b>Warehouse</b> — is stock sitting here that an
                            unfulfilled order could already take? Blocked when
                            more than 15% of unmet demand has stock on the shelf
                            behind it.
                            <br />
                            <br />
                            Each is scored on its own evidence, so clearing one
                            will make this panel start naming the next.
                        </PanelHelp>
                    </div>
                    {/* Deliberately large: this is the one line someone reads if
                        they read nothing else on the page. */}
                    <h2 className="mt-1.5 text-[26px] leading-[1.1] font-semibold tracking-tight text-balance text-gray-900 sm:text-[30px] dark:text-gray-100">
                        {headline}
                    </h2>
                    <p className="mt-2.5 max-w-[74ch] text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        {why}
                    </p>
                </div>
                <RefreshButton
                    onClick={refetch}
                    loading={loading}
                    error={error}
                    label="the bottleneck"
                />
            </div>

            <div className="grid gap-px bg-black/6 md:grid-cols-3 dark:bg-white/6">
                {data.owners.map((owner) => (
                    <OwnerCard key={owner.key} owner={owner} />
                ))}
            </div>
        </section>
    );
}

function OwnerCard({ owner }: { owner: FlowOwner }) {
    const style = STATE_STYLE[owner.state];

    return (
        <div className="bg-white p-[18px] dark:bg-zinc-900">
            <div className="flex items-center gap-1.5">
                <p className="text-[11px] font-semibold tracking-[0.11em] text-gray-500 uppercase dark:text-gray-400">
                    {owner.name}
                </p>
                <PanelHelp>{OWNER_HELP[owner.key]}</PanelHelp>
            </div>
            {/* Fixed height so the three verdict chips line up across cards
                however long the questions are. */}
            <p className="mt-2 min-h-[3em] text-[11px] leading-snug text-gray-400 dark:text-gray-500">
                {owner.question}
            </p>

            <span
                className={`mt-3 inline-flex items-center gap-2 rounded-full py-1.5 pr-2.5 pl-2 text-[11px] font-bold tracking-wide uppercase ${style.chip}`}
            >
                <i className={`h-2 w-2 rounded-full ${style.dot}`} />
                {style.word}
            </span>

            <p
                className={`mt-3.5 font-mono text-[28px] leading-none font-semibold tracking-tight tabular-nums ${
                    owner.state === 'blocked'
                        ? style.text
                        : 'text-gray-900 dark:text-gray-100'
                }`}
            >
                {num(owner.value)}
            </p>
            <p className="mt-1.5 text-[11px] text-gray-400 dark:text-gray-500">
                {owner.unit}
            </p>

            <ul className="mt-3.5 flex flex-col gap-1.5 border-t border-black/5 pt-3 dark:border-white/5">
                {owner.facts.map(([label, value, unit]) => (
                    <li
                        key={label}
                        className="flex items-baseline gap-3 text-[11.5px] text-gray-500 dark:text-gray-400"
                    >
                        <span>{label}</span>
                        <span className="ml-auto text-right font-medium text-gray-900 dark:text-gray-100">
                            {value == null ? (
                                <span className="text-gray-400 dark:text-gray-500">
                                    none
                                </span>
                            ) : (
                                <>
                                    <span className="font-mono tabular-nums">
                                        {num(value)}
                                    </span>{' '}
                                    <span className="font-normal text-gray-400 dark:text-gray-500">
                                        {unit}
                                    </span>
                                </>
                            )}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
