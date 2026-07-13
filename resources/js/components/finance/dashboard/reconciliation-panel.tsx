import { cn } from '@/lib/utils';
import { format, parseISO } from 'date-fns';
import { type Reconciliation } from './types';
import { money } from './utils';

const fmtDate = (d: string) => {
    try {
        return format(parseISO(d), 'd MMM yyyy');
    } catch {
        return d;
    }
};

// Older buckets read as more urgent.
const bucketAccent = (index: number) =>
    ['text-gray-500', 'text-amber-500', 'text-red-500'][index] ??
    'text-gray-500';

/**
 * Unreconciled remittances: aging buckets up top, then the oldest outstanding
 * SOAs. Links out to the full remittance list for action.
 */
export default function ReconciliationPanel({
    data,
    remittancesUrl,
}: {
    data: Reconciliation;
    remittancesUrl: string;
}) {
    return (
        <div className="space-y-4">
            <div className="grid grid-cols-3 gap-3">
                {data.buckets.map((b, i) => (
                    <div
                        key={b.label}
                        className="rounded-[10px] border border-black/6 bg-stone-50 p-3 dark:border-white/6 dark:bg-zinc-800"
                    >
                        <div className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                            {b.label}
                        </div>
                        <div
                            className={cn(
                                'mt-1 font-mono text-[16px] font-semibold',
                                bucketAccent(i),
                            )}
                        >
                            {b.count}
                        </div>
                        <div className="font-mono text-[10px] text-gray-400">
                            ₱{money(b.amount)}
                        </div>
                    </div>
                ))}
            </div>

            {data.items.length === 0 ? (
                <p className="py-4 text-center font-mono text-[11px] text-gray-400 dark:text-gray-600">
                    Nothing outstanding — all remittances reconciled.
                </p>
            ) : (
                <div className="divide-y divide-black/5 dark:divide-white/5">
                    {data.items.map((it) => (
                        <div
                            key={it.id}
                            className="flex items-center justify-between gap-3 py-2.5"
                        >
                            <div className="min-w-0">
                                <div className="truncate text-[12px] font-medium text-gray-700 dark:text-gray-200">
                                    {it.soa_number}
                                </div>
                                <div className="font-mono text-[10px] text-gray-400">
                                    {it.courier} · due{' '}
                                    {fmtDate(it.billing_date_to)}
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">
                                    ₱{money(it.net_amount)}
                                </div>
                                <div className="font-mono text-[10px] text-gray-400">
                                    {it.days_outstanding}d outstanding
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <a
                href={remittancesUrl}
                className="inline-flex font-mono text-[11px] font-medium text-indigo-500 transition-colors hover:text-indigo-600"
            >
                View all remittances →
            </a>
        </div>
    );
}
