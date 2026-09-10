import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import {
    AlertTriangleIcon,
    ClockIcon,
    PackageCheckIcon,
    PhoneCallIcon,
    PhoneIcon,
    RotateCcwIcon,
    TargetIcon,
    TimerIcon,
    TruckIcon,
} from 'lucide-react';

interface StatCardProps {
    title: string;
    value: number;
    icon: React.ComponentType<{ className?: string }>;
    suffix?: string;
    /** Rendered in place of the formatted number — for values that aren't counts. */
    valueLabel?: string;
    /**
     * Swaps the figure for a placeholder bar while a new one is on the way.
     * The card keeps its height and its title, so changing a filter doesn't
     * reflow the page — and a stale number can't be read as the fresh one.
     */
    loading?: boolean;
}

export function StatCard({
    title,
    value,
    icon: Icon,
    suffix,
    valueLabel,
    loading = false,
}: StatCardProps) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-2 flex items-center gap-2">
                <Icon className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {title}
                </span>
            </div>
            {loading ? (
                <Skeleton className="my-[5px] block h-[22px] w-16 rounded" />
            ) : (
                <span className="font-mono text-[22px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                    {valueLabel ?? value.toLocaleString()}
                    {suffix}
                </span>
            )}
        </div>
    );
}

/** Seconds as `1h 04m 09s`, dropping the units that would read as zero. */
function formatCallDuration(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;

    if (h > 0) {
        return `${h}h ${String(m).padStart(2, '0')}m`;
    }

    return m > 0 ? `${m}m ${String(s).padStart(2, '0')}s` : `${s}s`;
}

interface RmoStatCardsProps {
    total_for_delivery_today: number;
    called_count: number;
    delivered_count: number;
    returning_count: number;
    problematic_count: number;
    /**
     * RMO calls only: the ones stamped as reaching the customer or the rider on
     * a delivery loaded for this day. Attempts, not orders, so it runs ahead of
     * "Called", and it is the sum of the two RMO tabs in the call-logs
     * breakdown — verification calls and numbers no delivery matched are out.
     * Only the public page reports the call-log figures; left out, the row is
     * the original five cards.
     */
    total_call_logs_count?: number;
    /** Combined talk time of those RMO calls, in seconds. */
    total_call_duration?: number;
    /** How many of those RMO calls lasted long enough to count as answered. */
    connected_call_logs_count?: number;
    /**
     * Talk time per connected RMO call, in seconds, and the share of RMO
     * attempts that connected. Both are arithmetic over the three figures
     * above, so they inherit the same customer/rider narrowing rather than
     * needing their own. Both are computed server-side — see RmoDailyStats — so
     * the page and the daily report can't disagree on what they mean, and both
     * are null rather than zero when nobody has called yet.
     *
     * These travel with the three counts above: a caller that supplies those
     * supplies these.
     */
    avg_call_duration?: number | null;
    hit_rate?: number | null;
    /** Show placeholder bars instead of the figures — a reload is in flight. */
    loading?: boolean;
}

export function RmoStatCards({
    total_for_delivery_today,
    called_count,
    delivered_count,
    returning_count,
    problematic_count,
    total_call_logs_count,
    total_call_duration,
    connected_call_logs_count,
    avg_call_duration = null,
    hit_rate = null,
    loading = false,
}: RmoStatCardsProps) {
    const showCallLogs = total_call_logs_count !== undefined;

    const totalCalls = total_call_logs_count ?? 0;
    const connectedCalls = connected_call_logs_count ?? 0;

    return (
        <div
            className={cn(
                'grid grid-cols-2 gap-3',
                showCallLogs
                    ? 'sm:grid-cols-3 lg:grid-cols-5'
                    : 'sm:grid-cols-5',
            )}
        >
            <StatCard
                title="Total For Delivery Today"
                value={total_for_delivery_today || 0}
                loading={loading}
                icon={TruckIcon}
            />
            <StatCard
                title="Called"
                value={called_count || 0}
                loading={loading}
                icon={PhoneIcon}
            />
            <StatCard
                title="Delivered"
                value={delivered_count || 0}
                loading={loading}
                icon={PackageCheckIcon}
            />
            <StatCard
                title="Returning"
                value={returning_count || 0}
                loading={loading}
                icon={RotateCcwIcon}
            />
            <StatCard
                title="Problematic"
                value={problematic_count || 0}
                loading={loading}
                icon={AlertTriangleIcon}
            />
            {showCallLogs && (
                <>
                    <StatCard
                        title="Total RMO Calls"
                        value={totalCalls}
                        loading={loading}
                        icon={PhoneCallIcon}
                    />
                    <StatCard
                        title="Total RMO Call Duration"
                        value={total_call_duration ?? 0}
                        valueLabel={formatCallDuration(
                            total_call_duration ?? 0,
                        )}
                        loading={loading}
                        icon={TimerIcon}
                    />
                    <StatCard
                        title="Connected RMO Calls (3s+)"
                        value={connectedCalls}
                        loading={loading}
                        icon={PhoneCallIcon}
                    />
                    <StatCard
                        title="Avg RMO Call Duration"
                        value={avg_call_duration ?? 0}
                        valueLabel={
                            avg_call_duration === null
                                ? '—'
                                : formatCallDuration(
                                      Math.round(avg_call_duration),
                                  )
                        }
                        loading={loading}
                        icon={ClockIcon}
                    />
                    <StatCard
                        title="RMO Hit Rate"
                        value={hit_rate ?? 0}
                        valueLabel={
                            hit_rate === null ? '—' : hit_rate.toFixed(1) + '%'
                        }
                        loading={loading}
                        icon={TargetIcon}
                    />
                </>
            )}
        </div>
    );
}
