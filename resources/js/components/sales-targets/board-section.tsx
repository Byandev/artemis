import { RotateCw } from 'lucide-react';

/**
 * The frame every board section shares while its own request is in flight or
 * has failed. A failure is scoped to the panel — the rest of the board keeps
 * whatever it already loaded, and this one offers to try again.
 */
export function BoardSectionState({
    loading,
    failed,
    onRetry,
    label,
    height = 'h-24',
}: {
    loading: boolean;
    failed: boolean;
    onRetry: () => void;
    label: string;
    height?: string;
}) {
    if (failed) {
        return (
            <div
                className={`flex ${height} flex-col items-center justify-center gap-2 rounded-[12px] border border-dashed border-black/8 bg-white dark:border-white/8 dark:bg-zinc-900`}
            >
                <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Couldn&apos;t load {label}.
                </p>
                <button
                    onClick={onRetry}
                    className="flex h-7 items-center gap-1.5 rounded-lg border border-black/8 px-2.5 text-[11px] font-medium text-gray-700 transition-all hover:bg-stone-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10"
                >
                    <RotateCw className="h-3 w-3" />
                    Retry
                </button>
            </div>
        );
    }

    if (loading) {
        return (
            <div
                className={`${height} animate-pulse rounded-[12px] border border-black/6 bg-white dark:border-white/8 dark:bg-zinc-900`}
                aria-label={`Loading ${label}`}
            />
        );
    }

    return null;
}
