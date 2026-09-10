import { Skeleton } from '@/components/ui/skeleton';
import {
    ArrowDown,
    ArrowUp,
    Headset,
    Hourglass,
    MessagesSquare,
    Minus,
    PhoneCall,
    PhoneIncoming,
    PhoneOutgoing,
    RotateCcw,
    ShieldAlert,
    ShieldCheck,
    Target,
    Timer,
    Wallet,
} from 'lucide-react';

/**
 * The customer return rate at or above which an order is flagged for a
 * verification call. Mirrors CSRController::VERIFICATION_RTS_THRESHOLD — shown
 * in the footnote so the card says what "needs verification" means.
 */
const RTS_THRESHOLD = 55;

/** Fields every analytics stat endpoint answers with. */
interface StatPayload {
    value: number | null;
    previous_value: number | null;
    change: number | null;
    previous_period: { from: string; to: string };
}

export interface SalesStat extends StatPayload {
    value: number;
    orders: number;
    previous_orders: number;
}

export interface RtsStat extends StatPayload {
    returning_amount: number;
}

export interface RmoCalledStat extends StatPayload {
    value: number;
}

export interface TotalRmoCalledStat extends StatPayload {
    value: number;
    seconds: number;
}

export interface RmoCallTimeStat extends StatPayload {
    value: number;
    calls: number;
    average_seconds: number | null;
}

export interface RmoRealConversationsStat extends StatPayload {
    value: number;
    /** RMO calls placed — what the conversations are read against. */
    calls: number;
    /** Conversations over calls, as a percentage; null with no calls. */
    rate: number | null;
}

export interface RmoHitRateStat extends StatPayload {
    /** Null when no RMO call was placed — no rate, rather than a rate of none. */
    value: number | null;
    conversations: number;
    calls: number;
}

export interface RmoTimeStat extends StatPayload {
    value: number;
    calls: number;
    average_seconds: number | null;
}

export interface CallsPlacedStat extends StatPayload {
    value: number;
}

export interface RealConversationsStat extends StatPayload {
    value: number;
    calls: number;
    average_seconds: number | null;
}

export interface ReachRateStat extends StatPayload {
    value: number;
    /** Orders whose customer number has no report behind it at all. */
    no_report: number;
    /** Orders whose customer is returning parcels at or above the threshold. */
    high_rts: number;
    orders: number;
}

export interface VerifiedOrdersStat extends StatPayload {
    /** Null when the range needed no verification at all. */
    value: number | null;
    /** Orders verified — the numerator, counted by order rather than by call. */
    orders: number;
    /** Calls it took to get through them — an order rung three times is three. */
    calls: number;
    /** The backlog it is read against — the Reach Rate card's figure. */
    needs_verification: number;
}

/** Seconds as `502h 32m` / `12m 05s` / `45s`, dropping units that read as zero. */
const duration = (seconds: number) => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;

    if (h > 0) return `${h.toLocaleString()}h ${String(m).padStart(2, '0')}m`;
    return m > 0 ? `${m}m ${String(s).padStart(2, '0')}s` : `${s}s`;
};

/**
 * A per-call average, in the unit that actually says something.
 *
 * Minutes to one decimal is the right read for a normal call, but useless below
 * a minute: a 4.3-second average lands on "0.1 min", which rounds a real figure
 * into noise. Under a minute it stays in seconds.
 */
export const perCall = (seconds: number) =>
    seconds < 60
        ? `${seconds.toFixed(seconds < 10 ? 1 : 0)}s`
        : `${(seconds / 60).toFixed(1)} min`;

const peso = (n: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(Number(n) || 0);

/**
 * Percent change against the period before this one, as an arrow and a figure.
 *
 * Sits top-right of the card, so the eye reads title, then trend, then the
 * figure itself. The period it compares against is not spelled out — it is
 * always the equally long stretch ending the day before the selected range, and
 * naming two more dates on a card whose range is already picked above it was
 * more to read than it was worth. It is on the hover title instead.
 *
 * `higherIsBetter` decides the colour, not the direction: sales climbing is
 * green, but a return rate climbing is red. Taking this from the card rather
 * than from the sign is the whole point — a worsening RTS drawn in green is
 * worse than no arrow at all.
 *
 * Flat and "nothing to compare against" stay distinct: a period following one
 * with no activity has no comparison, and 0% there would read as "unchanged"
 * rather than "nothing to measure against".
 */
function Trend({
    change,
    since,
    unit = '%',
    higherIsBetter = true,
}: {
    change: number | null;
    since: string;
    unit?: string;
    higherIsBetter?: boolean;
}) {
    if (change === null) {
        return (
            <span
                title={`Nothing to compare against in the previous period${since ? ` (${since})` : ''}`}
                className="text-[11px] text-gray-400 dark:text-gray-500"
            >
                —
            </span>
        );
    }

    const flat = Math.abs(change) < 0.05;
    const rising = change > 0;
    const good = higherIsBetter ? rising : !rising;

    const Icon = flat ? Minus : rising ? ArrowUp : ArrowDown;
    const tone = flat
        ? 'text-gray-400 dark:text-gray-500'
        : good
          ? 'text-emerald-600 dark:text-emerald-400'
          : 'text-red-600 dark:text-red-400';

    return (
        <span
            title={`Compared with ${since}`}
            className={`flex shrink-0 items-center gap-0.5 text-[11px] font-medium ${tone}`}
        >
            <Icon className="h-3 w-3 shrink-0" />
            {flat ? `0${unit}` : `${Math.abs(change).toFixed(1)}${unit}`}
        </span>
    );
}

/**
 * One card. Each has its own endpoint, so each carries its own loading state —
 * a slow metric shows a skeleton without holding up the card beside it.
 */
export function StatCard({
    title,
    icon: Icon,
    value,
    footnote,
    trend,
    loading,
}: {
    title: string;
    icon: React.ComponentType<{ className?: string }>;
    value: string;
    footnote: string;
    trend: React.ReactNode;
    loading: boolean;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-2 flex items-center justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2">
                    <Icon className="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
                    <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                        {title}
                    </span>
                </div>
                {loading ? (
                    <Skeleton className="h-[13px] w-12 shrink-0 rounded" />
                ) : (
                    trend
                )}
            </div>

            {loading ? (
                <>
                    <Skeleton className="my-[5px] block h-[22px] w-32 rounded" />
                    <Skeleton className="mt-2 block h-[13px] w-28 rounded" />
                </>
            ) : (
                <>
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

export function SalesStatCard({
    stat,
    loading,
}: {
    stat: SalesStat | null;
    loading: boolean;
}) {
    return (
        <StatCard
            title="Sales"
            icon={Wallet}
            loading={loading || stat === null}
            value={stat ? peso(stat.value) : ''}
            footnote={
                stat
                    ? `${stat.orders.toLocaleString()} order${stat.orders === 1 ? '' : 's'} handled`
                    : ''
            }
            trend={
                <Trend
                    change={stat?.change ?? null}
                    since={
                        stat
                            ? `${stat.previous_period.from} – ${stat.previous_period.to}`
                            : ''
                    }
                />
            }
        />
    );
}

export function RtsStatCard({
    stat,
    loading,
}: {
    stat: RtsStat | null;
    loading: boolean;
}) {
    return (
        <StatCard
            title="RTS Rate"
            icon={RotateCcw}
            loading={loading || stat === null}
            // Null when nothing was delivered or returned in the range — a dash,
            // not 0%, which would read as a flawless period.
            value={
                !stat || stat.value === null ? '—' : `${stat.value.toFixed(2)}%`
            }
            footnote={stat ? `${peso(stat.returning_amount)} returning` : ''}
            trend={
                <Trend
                    change={stat?.change ?? null}
                    since={
                        stat
                            ? `${stat.previous_period.from} – ${stat.previous_period.to}`
                            : ''
                    }
                    unit=" pts"
                    higherIsBetter={false}
                />
            }
        />
    );
}

export function RmoCalledStatCard({
    stat,
    loading,
}: {
    stat: RmoCalledStat | null;
    loading: boolean;
}) {
    return (
        <StatCard
            title="Total Called"
            icon={PhoneCall}
            loading={loading || stat === null}
            value={stat ? stat.value.toLocaleString() : ''}
            footnote={
                stat
                    ? `call${stat.value === 1 ? '' : 's'} placed in the range`
                    : ''
            }
            trend={
                <Trend
                    change={stat?.change ?? null}
                    since={
                        stat
                            ? `${stat.previous_period.from} – ${stat.previous_period.to}`
                            : ''
                    }
                />
            }
        />
    );
}

export function TotalRmoCalledStatCard({
    stat,
    loading,
}: {
    stat: TotalRmoCalledStat | null;
    loading: boolean;
}) {
    return (
        <StatCard
            title="RMO Called"
            icon={PhoneIncoming}
            loading={loading || stat === null}
            value={stat ? stat.value.toLocaleString() : ''}
            // The time behind these is the card beside this one, so the
            // footnote names the unit rather than restating that figure — the
            // same split the verification pair already makes.
            footnote={
                stat
                    ? `RMO call${stat.value === 1 ? '' : 's'} in the range`
                    : ''
            }
            trend={
                <Trend
                    change={stat?.change ?? null}
                    since={
                        stat
                            ? `${stat.previous_period.from} – ${stat.previous_period.to}`
                            : ''
                    }
                />
            }
        />
    );
}

export function RmoCallTimeStatCard({
    stat,
    loading,
}: {
    stat: RmoCallTimeStat | null;
    loading: boolean;
}) {
    return (
        <StatCard
            title="RMO Call Time"
            icon={Hourglass}
            loading={loading || stat === null}
            value={stat ? duration(stat.value) : ''}
            footnote={
                !stat || stat.average_seconds === null
                    ? 'No RMO calls in this period'
                    : `avg ${perCall(stat.average_seconds)} per call`
            }
            trend={
                <Trend
                    change={stat?.change ?? null}
                    since={
                        stat
                            ? `${stat.previous_period.from} – ${stat.previous_period.to}`
                            : ''
                    }
                />
            }
        />
    );
}

export function RmoRealConversationsStatCard({
    stat,
    loading,
}: {
    stat: RmoRealConversationsStat | null;
    loading: boolean;
}) {
    return (
        <StatCard
            title="RMO Real Conversation"
            icon={Headset}
            loading={loading || stat === null}
            value={stat ? stat.value.toLocaleString() : ''}
            // The pool it came out of. The share itself is the Hit Rate card
            // beside this one, so the footnote does not restate it.
            footnote={
                !stat || stat.rate === null
                    ? 'No RMO calls in this period'
                    : `of ${stat.calls.toLocaleString()} RMO call${stat.calls === 1 ? '' : 's'} placed`
            }
            trend={
                <Trend
                    change={stat?.change ?? null}
                    since={
                        stat
                            ? `${stat.previous_period.from} – ${stat.previous_period.to}`
                            : ''
                    }
                />
            }
        />
    );
}

export function RmoHitRateStatCard({
    stat,
    loading,
}: {
    stat: RmoHitRateStat | null;
    loading: boolean;
}) {
    return (
        <StatCard
            title="Hit Rate"
            icon={Target}
            loading={loading || stat === null}
            // A dash, not 0%, when nothing was placed — there is no rate to
            // report rather than a rate of nothing.
            value={
                !stat || stat.value === null ? '—' : `${stat.value.toFixed(1)}%`
            }
            // The division itself, so the figure can be read against its volume.
            footnote={
                !stat || stat.value === null
                    ? 'No RMO calls in this period'
                    : `${stat.conversations.toLocaleString()} conversations of ${stat.calls.toLocaleString()} calls`
            }
            trend={
                <Trend
                    change={stat?.change ?? null}
                    since={
                        stat
                            ? `${stat.previous_period.from} – ${stat.previous_period.to}`
                            : ''
                    }
                    unit=" pts"
                />
            }
        />
    );
}

export function RmoTimeStatCard({
    stat,
    loading,
}: {
    stat: RmoTimeStat | null;
    loading: boolean;
}) {
    return (
        <StatCard
            title="Total Called Time"
            icon={Timer}
            loading={loading || stat === null}
            value={stat ? duration(stat.value) : ''}
            footnote={
                !stat || stat.average_seconds === null
                    ? 'No calls in this period'
                    : `avg ${perCall(stat.average_seconds)} per call`
            }
            trend={
                <Trend
                    change={stat?.change ?? null}
                    since={
                        stat
                            ? `${stat.previous_period.from} – ${stat.previous_period.to}`
                            : ''
                    }
                />
            }
        />
    );
}

export function CallsPlacedStatCard({
    stat,
    loading,
}: {
    stat: CallsPlacedStat | null;
    loading: boolean;
}) {
    return (
        <StatCard
            title="Total Verification Called"
            icon={PhoneOutgoing}
            loading={loading || stat === null}
            value={stat ? stat.value.toLocaleString() : ''}
            // The time behind these is the card beside this one, so the
            // footnote names the unit rather than restating that figure.
            footnote={
                stat
                    ? `verification call${stat.value === 1 ? '' : 's'} in the range`
                    : ''
            }
            trend={
                <Trend
                    change={stat?.change ?? null}
                    since={
                        stat
                            ? `${stat.previous_period.from} – ${stat.previous_period.to}`
                            : ''
                    }
                />
            }
        />
    );
}

export function RealConversationsStatCard({
    stat,
    loading,
}: {
    stat: RealConversationsStat | null;
    loading: boolean;
}) {
    return (
        <StatCard
            title="Total Verification Call Time"
            icon={MessagesSquare}
            loading={loading || stat === null}
            value={stat ? duration(stat.value) : ''}
            footnote={
                !stat || stat.average_seconds === null
                    ? 'No verification calls in this period'
                    : `avg ${perCall(stat.average_seconds)} per call`
            }
            trend={
                <Trend
                    change={stat?.change ?? null}
                    since={
                        stat
                            ? `${stat.previous_period.from} – ${stat.previous_period.to}`
                            : ''
                    }
                />
            }
        />
    );
}

export function ReachRateStatCard({
    stat,
    loading,
}: {
    stat: ReachRateStat | null;
    loading: boolean;
}) {
    return (
        <StatCard
            title="Total Order needs Verification"
            icon={ShieldAlert}
            loading={loading || stat === null}
            value={stat ? stat.value.toLocaleString() : ''}
            // The two reasons split out: a card that only says "66" leaves you
            // unable to tell a batch of unknown numbers from a batch of known
            // bad ones, which are different problems.
            footnote={
                stat
                    ? `${stat.no_report.toLocaleString()} no report · ${stat.high_rts.toLocaleString()} at ${RTS_THRESHOLD}%+ RTS`
                    : ''
            }
            trend={
                <Trend
                    change={stat?.change ?? null}
                    since={
                        stat
                            ? `${stat.previous_period.from} – ${stat.previous_period.to}`
                            : ''
                    }
                />
            }
        />
    );
}

/**
 * How much of the range's verification backlog got verified.
 *
 * Orders verified over the "Total Order needs Verification" card beside it,
 * with both counts and the calls behind them in the footnote. Orders on both
 * sides of the division, not calls: three calls at one order cover one order,
 * and counting the calls read a two-order backlog rung three times as 150%.
 *
 * It can still pass 100% — a CSR ringing an order nothing flagged, or one
 * chased across two days, which the nightly rollup counts on each of them.
 * Real signal about the range rather than an error to clamp away.
 */
export function VerifiedOrdersStatCard({
    stat,
    loading,
}: {
    stat: VerifiedOrdersStat | null;
    loading: boolean;
}) {
    return (
        <StatCard
            title="Total Verified Orders"
            icon={ShieldCheck}
            loading={loading || stat === null}
            // A dash, not 0%, when nothing needed verifying — there is no rate
            // to report rather than a rate of nothing.
            value={
                !stat || stat.value === null ? '—' : `${stat.value.toFixed(1)}%`
            }
            // The division itself, and the calls it took: orders over orders,
            // not calls over orders — an order rung three times covers one, and
            // counting the calls put a two-order backlog at 150%.
            footnote={
                !stat || stat.value === null
                    ? 'Nothing needed verifying in this period'
                    : `${stat.orders.toLocaleString()} of ${stat.needs_verification.toLocaleString()} needing verification · ${stat.calls.toLocaleString()} calls`
            }
            trend={
                <Trend
                    change={stat?.change ?? null}
                    since={
                        stat
                            ? `${stat.previous_period.from} – ${stat.previous_period.to}`
                            : ''
                    }
                    unit=" pts"
                />
            }
        />
    );
}
