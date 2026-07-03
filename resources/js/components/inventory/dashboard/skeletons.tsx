import { Skeleton } from '@/components/ui/skeleton';

/** Bar/area chart placeholder. */
export function ChartSkeleton({ height = 300 }: { height?: number }) {
    return (
        <div className="flex items-end gap-2" style={{ height }}>
            {Array.from({ length: 12 }).map((_, i) => (
                <Skeleton
                    key={i}
                    className="flex-1"
                    style={{ height: `${30 + ((i * 37) % 60)}%` }}
                />
            ))}
        </div>
    );
}

/** Donut placeholder. */
export function DonutSkeleton() {
    return (
        <div className="flex h-[300px] flex-col items-center justify-center gap-4">
            <Skeleton className="h-40 w-40 rounded-full" />
            <div className="flex gap-3">
                {Array.from({ length: 3 }).map((_, i) => (
                    <Skeleton key={i} className="h-3 w-16" />
                ))}
            </div>
        </div>
    );
}

/** Table placeholder with a configurable row count. */
export function TableSkeleton({ rows = 6 }: { rows?: number }) {
    return (
        <div className="space-y-2.5">
            {Array.from({ length: rows }).map((_, i) => (
                <div key={i} className="flex items-center gap-3">
                    <Skeleton className="h-8 flex-1" />
                </div>
            ))}
        </div>
    );
}

/** Alerts feed placeholder. */
export function AlertsSkeleton() {
    return (
        <div className="space-y-2.5">
            {Array.from({ length: 3 }).map((_, i) => (
                <div
                    key={i}
                    className="flex items-center gap-3 rounded-[12px] border border-black/5 p-3 dark:border-white/5"
                >
                    <Skeleton className="h-7 w-7 rounded-[9px]" />
                    <div className="flex-1 space-y-2">
                        <Skeleton className="h-3 w-1/3" />
                        <Skeleton className="h-2.5 w-2/3" />
                    </div>
                </div>
            ))}
        </div>
    );
}

/** KPI tile row placeholder. */
export function KpiRowSkeleton() {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {Array.from({ length: 8 }).map((_, i) => (
                <div
                    key={i}
                    className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900"
                >
                    <div className="flex items-start justify-between">
                        <Skeleton className="h-3 w-14" />
                        <Skeleton className="h-9 w-9 rounded-lg" />
                    </div>
                    <Skeleton className="mt-4 h-6 w-12" />
                </div>
            ))}
        </div>
    );
}
