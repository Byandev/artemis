import { perCall } from '@/components/csr/CsrAnalyticsStatCards';
import { Skeleton } from '@/components/ui/skeleton';
import { Trophy } from 'lucide-react';

/** A leader card's payload. `leader` is null when nobody qualified. */
export interface LeaderResponse<T> {
    leader: T | null;
}

export interface RtsLeader {
    name: string;
    /** Their RTS rate as a percentage — lower is the win here. */
    value: number;
    returned: number;
    delivered: number;
}

export interface RmoCalledLeader {
    name: string;
    /** The table's RMO % — called over confirmed. Higher is the win here. */
    value: number;
    called: number;
    confirmed: number;
}

export interface RmoDurationLeader {
    name: string;
    /** Total talk time in seconds. */
    value: number;
    calls: number;
    average_seconds: number | null;
}

export interface SalesLeader {
    name: string;
    value: number;
    orders: number;
    aov: number | null;
    /** Their slice of the workspace total, or null when there is no total. */
    share: number | null;
}

/** Seconds as `88h 32m` / `12m 05s` / `45s`, dropping units that read as zero. */
const duration = (seconds: number) => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;

    if (h > 0) return `${h.toLocaleString()}h ${String(m).padStart(2, '0')}m`;
    return m > 0 ? `${m}m ${String(s).padStart(2, '0')}s` : `${s}s`;
};

const peso = (n: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(Number(n) || 0);

/** First letters of the first two words — "Angeline Mercado" becomes "AM". */
function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();
}

/**
 * One "leader for the period" card: who came top, and by how much.
 *
 * Shaped differently from the stat cards on purpose. Those answer "how did we
 * do", so they carry a trend against the previous period; this answers "who",
 * and last period's leader may be a different person entirely — an arrow
 * comparing the two would be comparing strangers. The badge carries their share
 * of the total instead, which is the figure that says whether a big number is a
 * standout or just a big team.
 */
export function LeaderCard({
    title,
    badge,
    name,
    value,
    footnote,
    loading,
    empty,
}: {
    title: string;
    badge: string | null;
    name: string;
    value: string;
    footnote: string;
    loading: boolean;
    empty: boolean;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-3 flex items-center justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2">
                    <Trophy className="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
                    <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                        {title}
                    </span>
                </div>
                {loading ? (
                    <Skeleton className="h-[13px] w-16 shrink-0 rounded" />
                ) : (
                    badge && (
                        <span className="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                            {badge}
                        </span>
                    )
                )}
            </div>

            {loading ? (
                <>
                    <div className="mb-2 flex items-center gap-2.5">
                        <Skeleton className="h-8 w-8 shrink-0 rounded-full" />
                        <Skeleton className="h-[13px] w-28 rounded" />
                    </div>
                    <Skeleton className="my-[5px] block h-[22px] w-32 rounded" />
                    <Skeleton className="mt-2 block h-[13px] w-36 rounded" />
                </>
            ) : empty ? (
                <p className="py-3 text-[12px] text-gray-400 dark:text-gray-500">
                    Nobody to rank in this period
                </p>
            ) : (
                <>
                    <div className="mb-2 flex items-center gap-2.5">
                        <span className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-[11px] font-bold text-white">
                            {initials(name)}
                        </span>
                        <span className="truncate text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                            {name}
                        </span>
                    </div>
                    <span className="block font-mono text-[22px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                        {value}
                    </span>
                    <span className="mt-1.5 block text-[11px] text-gray-500 dark:text-gray-400">
                        {footnote}
                    </span>
                </>
            )}
        </div>
    );
}

export function HighestSalesLeaderCard({
    data,
    loading,
}: {
    data: LeaderResponse<SalesLeader> | null;
    loading: boolean;
}) {
    const leader = data?.leader ?? null;

    return (
        <LeaderCard
            title="Highest Sales"
            loading={loading || data === null}
            empty={leader === null}
            badge={
                leader?.share !== null && leader
                    ? `${leader.share}% of total`
                    : null
            }
            name={leader?.name ?? ''}
            value={leader ? peso(leader.value) : ''}
            footnote={
                leader
                    ? `${leader.orders.toLocaleString()} order${leader.orders === 1 ? '' : 's'}${
                          leader.aov === null
                              ? ''
                              : ` · AOV ${peso(leader.aov)}`
                      }`
                    : ''
            }
        />
    );
}

export function LowestRtsLeaderCard({
    data,
    loading,
}: {
    data: LeaderResponse<RtsLeader> | null;
    loading: boolean;
}) {
    const leader = data?.leader ?? null;

    return (
        <LeaderCard
            title="Lowest RTS"
            loading={loading || data === null}
            empty={leader === null}
            badge={leader ? 'Best delivery rate' : null}
            name={leader?.name ?? ''}
            value={leader ? `${leader.value.toFixed(1)}%` : ''}
            footnote={
                leader ? `${peso(leader.returned)} returned to sender` : ''
            }
        />
    );
}

export function HighestRmoCalledLeaderCard({
    data,
    loading,
}: {
    data: LeaderResponse<RmoCalledLeader> | null;
    loading: boolean;
}) {
    const leader = data?.leader ?? null;

    return (
        <LeaderCard
            title="Highest RMO Called"
            loading={loading || data === null}
            empty={leader === null}
            badge={leader ? 'Best coverage' : null}
            name={leader?.name ?? ''}
            value={leader ? `${leader.value.toFixed(1)}%` : ''}
            // "confirmed", not "assigned": the table's RMO % divides the
            // deliveries they called by the deliveries they confirmed, and the
            // footnote names the denominator it actually used.
            footnote={
                leader
                    ? `${leader.called.toLocaleString()} of ${leader.confirmed.toLocaleString()} confirmed`
                    : ''
            }
        />
    );
}

export function HighestRmoDurationLeaderCard({
    data,
    loading,
}: {
    data: LeaderResponse<RmoDurationLeader> | null;
    loading: boolean;
}) {
    const leader = data?.leader ?? null;

    return (
        <LeaderCard
            title="Highest RMO Duration"
            loading={loading || data === null}
            empty={leader === null}
            badge={leader ? 'Most time on calls' : null}
            name={leader?.name ?? ''}
            value={leader ? duration(leader.value) : ''}
            footnote={
                !leader || leader.average_seconds === null
                    ? 'No calls recorded'
                    : `avg ${perCall(leader.average_seconds)} per call`
            }
        />
    );
}
