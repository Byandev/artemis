import { clock } from '@/pages/workspaces/courses/lib/format';
import { Crosshair, Play } from 'lucide-react';

/** Seconds as the reviewer typed them: "1:05", "1:02:03" or plain "65". */
export function parseClock(value: string): number | null {
    const trimmed = value.trim();
    if (trimmed === '') return null;

    const parts = trimmed.split(':');
    if (parts.length > 3 || parts.some((p) => !/^\d+(\.\d+)?$/.test(p))) {
        return NaN;
    }

    return parts.reduce((total, part) => total * 60 + Number(part), 0);
}

export function formatClock(seconds: number | null): string {
    return seconds === null ? '' : clock(seconds);
}

/**
 * Converts the form's typed timestamp into what the API takes. An unparseable
 * entry is passed through as-is so the server rejects it with a field error.
 */
export function timestampPayload(value: string): number | string | null {
    const parsed = parseClock(value);
    return parsed !== null && Number.isNaN(parsed) ? value : parsed;
}

/** Text field for a review's timestamp, with a button to grab the player's time. */
export function TimestampInput({
    value,
    onChange,
    currentTime,
    error,
}: {
    value: string;
    onChange: (value: string) => void;
    /** Reads the player's position; absent when there's no player to read. */
    currentTime?: () => number;
    error?: string;
}) {
    return (
        <div>
            <div className="flex items-center gap-2">
                <input
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="At (m:ss) — optional"
                    className="h-8 min-w-0 flex-1 rounded-[8px] border border-black/8 bg-white px-2.5 font-mono! text-[12px]! text-gray-800 tabular-nums outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600"
                />
                {currentTime && (
                    <button
                        type="button"
                        onClick={() => onChange(clock(currentTime()))}
                        className="flex h-8 shrink-0 items-center gap-1 rounded-lg border border-black/8 bg-white px-2.5 font-mono! text-[11px]! font-medium text-gray-600 transition-colors hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        <Crosshair className="h-3 w-3" /> Current time
                    </button>
                )}
            </div>
            {error && (
                <p className="mt-1 font-mono text-[11px] text-red-500">
                    {error}
                </p>
            )}
        </div>
    );
}

/** The timestamp on a review. Seeks the player when there is one to seek. */
export function TimestampChip({
    seconds,
    onSeek,
}: {
    seconds: number;
    onSeek?: (seconds: number) => void;
}) {
    const label = clock(seconds);
    const cls =
        'inline-flex items-center gap-1 rounded-md bg-violet-50 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-violet-600 tabular-nums dark:bg-violet-500/[0.12] dark:text-violet-400';

    if (!onSeek) {
        return <span className={cls}>{label}</span>;
    }

    return (
        <button
            type="button"
            onClick={() => onSeek(seconds)}
            title={`Jump to ${label}`}
            className={`${cls} transition-colors hover:bg-violet-100 dark:hover:bg-violet-500/[0.2]`}
        >
            <Play className="h-2.5 w-2.5 fill-current" /> {label}
        </button>
    );
}
