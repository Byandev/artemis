import { Skeleton } from '@/components/ui/skeleton';

/** Skeleton for the Ads Status grid (2x2). */
export function AdsStatusSkeleton() {
    return (
        <div className="grid grid-cols-2 gap-3">
            {Array.from({ length: 4 }).map((_, i) => (
                <div
                    key={i}
                    className="rounded-xl border border-black/5 p-4 dark:border-white/5"
                >
                    <Skeleton className="h-3 w-16" />
                    <Skeleton className="mt-3 h-6 w-10" />
                </div>
            ))}
        </div>
    );
}

/** Skeleton for the pipeline funnel rows. */
export function PipelineSkeleton() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-3">
                    <Skeleton className="h-3 w-24 shrink-0" />
                    <Skeleton className="h-6 flex-1" />
                    <Skeleton className="h-3 w-8 shrink-0" />
                </div>
            ))}
        </div>
    );
}

/** Skeleton for a work list (revision / waiting). */
export function WorkListSkeleton() {
    return (
        <div className="space-y-2.5">
            {Array.from({ length: 4 }).map((_, i) => (
                <div
                    key={i}
                    className="flex items-center gap-3 rounded-lg border border-black/5 p-3 dark:border-white/5"
                >
                    <Skeleton className="h-9 w-9 rounded-md" />
                    <div className="flex-1 space-y-2">
                        <Skeleton className="h-3 w-1/2" />
                        <Skeleton className="h-2.5 w-1/3" />
                    </div>
                    <Skeleton className="h-3 w-12" />
                </div>
            ))}
        </div>
    );
}

/** Skeleton for the throughput chart. */
export function ThroughputSkeleton() {
    return (
        <div className="flex h-[260px] items-end gap-2">
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

/** Skeleton for the leaderboard rows. */
export function LeaderboardSkeleton() {
    return (
        <div className="space-y-2.5">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex items-center gap-3">
                    <Skeleton className="h-6 w-6 rounded-full" />
                    <Skeleton className="h-3 flex-1" />
                    <Skeleton className="h-3 w-10" />
                    <Skeleton className="h-3 w-10" />
                </div>
            ))}
        </div>
    );
}

/** Skeleton for the recent-activity feed. */
export function ActivitySkeleton() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex gap-3">
                    <Skeleton className="h-7 w-7 rounded-full" />
                    <div className="flex-1 space-y-2">
                        <Skeleton className="h-3 w-2/3" />
                        <Skeleton className="h-2.5 w-1/4" />
                    </div>
                </div>
            ))}
        </div>
    );
}
