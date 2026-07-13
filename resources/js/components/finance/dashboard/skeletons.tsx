import { Skeleton } from '@/components/ui/skeleton';

/** Loading placeholder for the KPI tile row — matches the 5-tile grid. */
export function KpiRowSkeleton() {
    return (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
            {Array.from({ length: 5 }).map((_, i) => (
                <div
                    key={i}
                    className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900"
                >
                    <Skeleton className="h-3 w-20" />
                    <Skeleton className="mt-3 h-6 w-24" />
                    <Skeleton className="mt-2 h-2.5 w-16" />
                </div>
            ))}
        </div>
    );
}

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
                <Skeleton key={i} className="h-8 w-full" />
            ))}
        </div>
    );
}

/** Reconciliation placeholder — aging buckets row + a short list. */
export function ReconciliationSkeleton() {
    return (
        <div className="space-y-4">
            <div className="grid grid-cols-3 gap-3">
                {Array.from({ length: 3 }).map((_, i) => (
                    <Skeleton key={i} className="h-16" />
                ))}
            </div>
            <div className="space-y-2.5">
                {Array.from({ length: 4 }).map((_, i) => (
                    <Skeleton key={i} className="h-9 w-full" />
                ))}
            </div>
        </div>
    );
}
