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
}

export function StatCard({
    title,
    value,
    icon: Icon,
    suffix,
    valueLabel,
}: StatCardProps) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-2 flex items-center gap-2">
                <Icon className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {title}
                </span>
            </div>
            <span className="font-mono text-[22px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                {valueLabel ?? value.toLocaleString()}
                {suffix}
            </span>
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
     * Every call placed to a customer or rider on these orders — attempts, not
     * orders, so it runs ahead of "Called". Only the public page reports the
     * call-log figures; left out, the row is the original five cards.
     */
    total_call_logs_count?: number;
    /** Combined talk time of those calls, in seconds. */
    total_call_duration?: number;
    /** How many of them lasted long enough to count as answered. */
    connected_call_logs_count?: number;
    /** Talk time of those answered calls alone, in seconds. */
    connected_call_duration?: number;
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
    connected_call_duration,
}: RmoStatCardsProps) {
    const showCallLogs = total_call_logs_count !== undefined;

    const totalCalls = total_call_logs_count ?? 0;
    const connectedCalls = connected_call_logs_count ?? 0;
    // No calls means no rate to report — 0% would read as "everyone hung up".
    const hitRate = totalCalls > 0 ? (connectedCalls / totalCalls) * 100 : null;

    // Answered talk time over answered calls — how long a call that actually
    // connected ran for. Dividing the *total* duration here instead would drag
    // the unanswered attempts into the numerator, and with a single answered
    // call it just reprints the Total Call Duration card.
    const avgCallDuration =
        connectedCalls > 0
            ? (connected_call_duration ?? 0) / connectedCalls
            : null;

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
                icon={TruckIcon}
            />
            <StatCard
                title="Called"
                value={called_count || 0}
                icon={PhoneIcon}
            />
            <StatCard
                title="Delivered"
                value={delivered_count || 0}
                icon={PackageCheckIcon}
            />
            <StatCard
                title="Returning"
                value={returning_count || 0}
                icon={RotateCcwIcon}
            />
            <StatCard
                title="Problematic"
                value={problematic_count || 0}
                icon={AlertTriangleIcon}
            />
            {showCallLogs && (
                <>
                    <StatCard
                        title="Total Call Logs Synced"
                        value={totalCalls}
                        icon={PhoneCallIcon}
                    />
                    <StatCard
                        title="Total Call Duration"
                        value={total_call_duration ?? 0}
                        valueLabel={formatCallDuration(
                            total_call_duration ?? 0,
                        )}
                        icon={TimerIcon}
                    />
                    <StatCard
                        title="Connected Calls (5s+)"
                        value={connectedCalls}
                        icon={PhoneCallIcon}
                    />
                    <StatCard
                        title="Avg Call Duration"
                        value={avgCallDuration ?? 0}
                        valueLabel={
                            avgCallDuration === null
                                ? '—'
                                : formatCallDuration(
                                      Math.round(avgCallDuration),
                                  )
                        }
                        icon={ClockIcon}
                    />
                    <StatCard
                        title="Hit Rate"
                        value={hitRate ?? 0}
                        valueLabel={
                            hitRate === null ? '—' : hitRate.toFixed(1) + '%'
                        }
                        icon={TargetIcon}
                    />
                </>
            )}
        </div>
    );
}
