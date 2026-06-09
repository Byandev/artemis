import type { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';

interface KpiCardProps {
    icon: LucideIcon;
    label: string;
    value: number;
    sub?: ReactNode;
    accentDot?: string;
}

/** Presentational KPI card used by the self-fetching KpiStatCard. */
export function KpiCard({
    icon: Icon,
    label,
    value,
    sub,
    accentDot,
}: KpiCardProps) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10">
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {label}
                </p>
                <div className="rounded-lg bg-stone-100 p-2 text-gray-700 dark:bg-zinc-800 dark:text-white/90">
                    <Icon className="h-5 w-5" />
                </div>
            </div>
            <div className="mt-3">
                <h4 className="font-mono text-[22px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                    {value}
                </h4>
                {sub && (
                    <p className="mt-1.5 flex items-center gap-1.5 text-[11px] text-gray-400 dark:text-gray-500">
                        {accentDot && (
                            <span
                                className={`h-1.5 w-1.5 shrink-0 rounded-full ${accentDot}`}
                            />
                        )}
                        {sub}
                    </p>
                )}
            </div>
        </div>
    );
}

/** Loading placeholder matching the KPI card layout. */
export function KpiCardSkeleton({
    label,
    icon: Icon,
}: {
    label: string;
    icon: LucideIcon;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {label}
                </p>
                <div className="rounded-lg bg-stone-100 p-2 dark:bg-zinc-800">
                    <Icon className="h-5 w-5 text-gray-300 dark:text-gray-600" />
                </div>
            </div>
            <div className="mt-4">
                <div className="h-7 w-16 animate-pulse rounded bg-gray-200 dark:bg-gray-800" />
                <div className="mt-3 h-3 w-24 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
            </div>
        </div>
    );
}
