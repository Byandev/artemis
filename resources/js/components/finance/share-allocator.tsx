import { Field } from '@/components/finance/account-form-dialog';
import { MultiSelect } from '@/components/ui/multi-select';
import React, { useEffect } from 'react';

/**
 * One allocation row: what is charged, and its share of the amount. A blank
 * share takes an even cut of whatever the explicit ones left over — the server
 * fills it in the same way (see Modules\Finance\...\Concerns\SplitsShares).
 */
export interface Share {
    /** The option's value: a user id, a product id, or a product name. */
    key: string;
    amount: string;
}

export interface ShareOption {
    value: string;
    label: string;
}

/**
 * `total` cut into `count` even shares, in cents so nothing is lost to rounding
 * — 100 across 3 gives 33.34 / 33.33 / 33.33 rather than three times 33.33.
 */
export function evenShares(total: number, count: number): string[] {
    if (count <= 0) return [];

    const cents = Math.round((Number.isFinite(total) ? total : 0) * 100);
    const each = Math.floor(cents / count);
    const odd = cents - each * count;

    return Array.from({ length: count }, (_, i) =>
        ((each + (i < odd ? 1 : 0)) / 100).toFixed(2),
    );
}

const money = (n: number) =>
    n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

interface Props {
    label: string;
    options: ShareOption[];
    rows: Share[];
    /** The amount the shares have to add up to. */
    total: number;
    onChange: (rows: Share[]) => void;
    placeholder?: string;
    hint?: string;
    error?: string;
    /**
     * Whether to keep splitting evenly as the total changes. Turned off the
     * moment a share is edited by hand, and started off when editing a record
     * whose shares were already saved.
     */
    autoSplit: boolean;
    onAutoSplitChange: (auto: boolean) => void;
}

/**
 * Picks what an amount is charged to and how it is divided: a multi-select plus,
 * once more than one thing is picked, a share per row with a running total. The
 * shares split evenly until someone types their own figure.
 */
export function ShareAllocator({
    label,
    options,
    rows,
    total,
    onChange,
    placeholder,
    hint,
    error,
    autoSplit,
    onAutoSplitChange,
}: Props) {
    const labels = React.useMemo(
        () => new Map(options.map((o) => [o.value, o.label])),
        [options],
    );

    useEffect(() => {
        if (!autoSplit) return;

        const shares = evenShares(total, rows.length);

        if (shares.some((s, i) => s !== rows[i]?.amount)) {
            onChange(rows.map((row, i) => ({ ...row, amount: shares[i] })));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [autoSplit, total, rows]);

    /** Keep the shares already entered, and start any newly picked row blank. */
    const setKeys = (keys: string[]) => {
        onChange(
            keys.map(
                (key) => rows.find((r) => r.key === key) ?? { key, amount: '' },
            ),
        );
    };

    const setShare = (key: string, amount: string) => {
        onAutoSplitChange(false);
        onChange(rows.map((r) => (r.key === key ? { ...r, amount } : r)));
    };

    const allocated = rows.reduce(
        (sum, r) => sum + (parseFloat(r.amount) || 0),
        0,
    );
    const balanced = Math.abs(allocated - total) < 0.01;

    return (
        <Field label={label} error={error}>
            <MultiSelect
                options={options}
                selected={rows.map((r) => r.key)}
                onChange={setKeys}
                placeholder={placeholder}
            />

            {rows.length > 1 && (
                <div className="mt-2 space-y-1.5 rounded-[10px] border border-black/8 bg-stone-50 p-2.5 dark:border-white/8 dark:bg-zinc-800/60">
                    {rows.map((row) => {
                        const name = labels.get(row.key) ?? row.key;

                        return (
                            <div
                                key={row.key}
                                className="flex items-center gap-2"
                            >
                                <span className="min-w-0 flex-1 truncate font-mono text-[11px] text-gray-600 dark:text-gray-300">
                                    {name}
                                </span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={row.amount}
                                    onChange={(e) =>
                                        setShare(row.key, e.target.value)
                                    }
                                    placeholder="0.00"
                                    aria-label={`Share for ${name}`}
                                    className="h-8 w-28 rounded-md border border-black/8 bg-white px-2 text-right font-mono! text-[12px]! text-gray-800 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-100"
                                />
                            </div>
                        );
                    })}

                    <div className="flex items-center justify-between gap-2 border-t border-black/6 pt-2 dark:border-white/6">
                        <span
                            className={`font-mono text-[10px] ${
                                balanced
                                    ? 'text-gray-400 dark:text-gray-500'
                                    : 'text-amber-600 dark:text-amber-500'
                            }`}
                        >
                            {money(allocated)} of {money(total)} allocated
                        </span>
                        <button
                            type="button"
                            onClick={() => onAutoSplitChange(true)}
                            className="font-mono text-[10px] text-emerald-600 hover:underline dark:text-emerald-500"
                        >
                            Split equally
                        </button>
                    </div>
                </div>
            )}

            {hint && (
                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                    {hint}
                </p>
            )}
        </Field>
    );
}
