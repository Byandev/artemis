import { cn } from '@/lib/utils';
import { AlertTriangle, RotateCw } from 'lucide-react';
import { type ReactNode } from 'react';
import { type StatState } from './use-finance-stat';

interface Props<T> {
    title: string;
    icon?: ReactNode;
    /** Right-aligned header content (e.g. a total), placed before refresh. */
    action?: ReactNode;
    className?: string;
    state: StatState<T>;
    skeleton: ReactNode;
    /** True when the widget resolved but has nothing to show. */
    isEmpty?: (data: T) => boolean;
    emptyMessage?: string;
    children: (data: T) => ReactNode;
}

/**
 * Themed dashboard panel with a built-in refresh button and an error state that
 * offers a retry — so a failed widget never breaks the page and can be
 * recovered on its own. Mirrors the inventory/video-editor dashboards.
 */
export default function StatPanel<T>({
    title,
    icon,
    action,
    className,
    state,
    skeleton,
    isEmpty,
    emptyMessage = 'No data for this period.',
    children,
}: Props<T>) {
    const { data, loading, error, refetch } = state;

    return (
        <div
            className={cn(
                'flex h-full flex-col rounded-[14px] border border-black/6 bg-white transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10',
                className,
            )}
        >
            <div className="flex items-center gap-1.5 border-b border-black/5 px-5 py-3.5 dark:border-white/5">
                {icon}
                <h3 className="text-[13px] font-semibold tracking-tight text-gray-700 dark:text-gray-200">
                    {title}
                </h3>
                <div className="ml-auto flex items-center gap-2">
                    {action}
                    <button
                        type="button"
                        onClick={refetch}
                        disabled={loading}
                        aria-label={`Refresh ${title}`}
                        title="Refresh"
                        className="inline-flex h-7 w-7 items-center justify-center rounded-[8px] border border-black/8 bg-white text-gray-400 transition-colors hover:border-black/14 hover:text-gray-600 disabled:opacity-60 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-500 dark:hover:text-gray-300"
                    >
                        <RotateCw
                            className={cn(
                                'h-3.5 w-3.5',
                                loading && 'animate-spin',
                            )}
                        />
                    </button>
                </div>
            </div>
            <div className="flex-1 p-4 sm:p-5">
                {error ? (
                    <PanelError onRetry={refetch} />
                ) : loading || !data ? (
                    skeleton
                ) : isEmpty?.(data) ? (
                    <EmptyState message={emptyMessage} />
                ) : (
                    children(data)
                )}
            </div>
        </div>
    );
}

export function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex h-full min-h-[200px] items-center justify-center">
            <p className="font-mono text-[11px] text-gray-400 dark:text-gray-600">
                {message}
            </p>
        </div>
    );
}

function PanelError({ onRetry }: { onRetry: () => void }) {
    return (
        <div className="flex min-h-[200px] flex-col items-center justify-center gap-3 text-center">
            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-red-50 text-red-500 dark:bg-red-500/10 dark:text-red-400">
                <AlertTriangle className="h-5 w-5" />
            </div>
            <p className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                Couldn’t load this widget.
            </p>
            <button
                type="button"
                onClick={onRetry}
                className="inline-flex items-center gap-1.5 rounded-[8px] border border-black/8 bg-white px-3 py-1.5 font-mono text-[11px] font-medium text-gray-600 transition-colors hover:border-black/14 hover:text-gray-800 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300 dark:hover:text-gray-100"
            >
                <RotateCw className="h-3.5 w-3.5" />
                Retry
            </button>
        </div>
    );
}
