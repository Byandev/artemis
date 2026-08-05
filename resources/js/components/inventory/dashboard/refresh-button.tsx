import { RotateCcw } from 'lucide-react';

/**
 * Manual refetch for a dashboard panel. Always available rather than
 * error-only, so a stale panel can be pulled fresh after a delivery or
 * adjustment lands. Doubles as the retry affordance when the fetch failed —
 * one control, two states.
 */
export default function RefreshButton({
    onClick,
    loading,
    error,
    label,
}: {
    onClick: () => void;
    loading: boolean;
    error: boolean;
    /** What is being refreshed, e.g. "movement". Used for the a11y label. */
    label: string;
}) {
    const title = error ? `Retry loading ${label}` : `Refresh ${label}`;

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={loading}
            title={title}
            aria-label={title}
            className={
                error
                    ? 'flex items-center justify-center rounded-md p-1.5 text-red-500 hover:bg-red-50 disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-950/40'
                    : 'flex items-center justify-center rounded-md p-1.5 text-gray-400 hover:bg-zinc-100 hover:text-gray-700 disabled:opacity-50 dark:text-gray-500 dark:hover:bg-zinc-800 dark:hover:text-gray-200'
            }
        >
            <RotateCcw
                className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`}
            />
        </button>
    );
}
