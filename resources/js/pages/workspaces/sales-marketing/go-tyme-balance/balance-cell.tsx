import { cn } from '@/lib/utils';
import { useEffect, useRef, useState } from 'react';
import { peso } from './types';

/**
 * The cell's box, shared by the resting text and the input. Sized identically
 * in both states so the column never shifts width when one opens.
 */
const CELL_BOX =
    'block h-7 w-full bg-transparent px-2 text-right font-mono text-[12px] tabular-nums outline-none';

interface Props {
    /** Null on the days before the wallet was opened. */
    value: number | null;
    /** The day before's figure, for the up/down colouring. */
    previous: number | null;
    isToday: boolean;
    canEdit: boolean;
    onSave: (value: number | null) => void;
}

/**
 * One day's balance: the wallet's position at the close of that date, walked
 * from its opening balance through its liquidation ledger. Days before the
 * wallet's history starts read as an em dash, not ₱0 — and are still typeable,
 * which is how a balance from before it was created gets recorded.
 *
 * Typing a figure does not store it — it posts a correction to the ledger for
 * the difference, so the grid stays derived and every day after this one
 * re-flows. Clearing the cell drops that correction.
 */
export default function BalanceCell({
    value,
    previous,
    isToday,
    canEdit,
    onSave,
}: Props) {
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (editing) {
            inputRef.current?.focus();
            inputRef.current?.select();
        }
    }, [editing]);

    const startEditing = () => {
        if (!canEdit) return;
        setDraft(value === null ? '' : String(value));
        setEditing(true);
    };

    const commit = () => {
        setEditing(false);

        const trimmed = draft.trim();
        const next = trimmed === '' ? null : Number(trimmed);

        // A non-numeric entry is treated as a slip, not a clear.
        if (next !== null && Number.isNaN(next)) return;
        if (next === value) return;

        onSave(next);
    };

    if (editing) {
        return (
            <input
                ref={inputRef}
                type="number"
                step="0.01"
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                onBlur={commit}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') commit();
                    if (e.key === 'Escape') setEditing(false);
                }}
                className={cn(
                    CELL_BOX,
                    'rounded-md bg-white ring-2 ring-emerald-500/30 dark:bg-zinc-800',
                    'text-gray-900 dark:text-gray-100',
                )}
            />
        );
    }

    // Green when the day gained on the one before, red when it lost. The
    // wallet's first day has nothing to compare against, and a flat day stays
    // neutral.
    const tone =
        value === null || previous === null || value === previous
            ? null
            : value > previous
              ? 'text-emerald-600 dark:text-emerald-400'
              : 'text-red-500 dark:text-red-400';

    return (
        <button
            type="button"
            onClick={startEditing}
            disabled={!canEdit}
            title={
                canEdit
                    ? 'Click to set this day’s balance — the difference is posted to the ledger as an adjustment.'
                    : undefined
            }
            className={cn(
                CELL_BOX,
                'rounded-md transition-colors',
                canEdit
                    ? 'cursor-text hover:bg-stone-100 dark:hover:bg-zinc-800'
                    : 'cursor-default',
                value === null
                    ? 'text-gray-300 dark:text-gray-600'
                    : isToday
                      ? 'font-semibold text-gray-900 dark:text-gray-100'
                      : (tone ?? 'text-gray-600 dark:text-gray-300'),
            )}
        >
            {value === null ? '–' : peso(value)}
        </button>
    );
}
