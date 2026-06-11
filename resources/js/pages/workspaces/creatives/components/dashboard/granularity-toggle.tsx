import moment from 'moment';
import { useMemo } from 'react';

interface Props {
    /** Current grouping value (daily | weekly | monthly | yearly). */
    value: string;
    dateFrom: string;
    dateTo: string;
    onChange: (group: string) => void;
}

/** Options that make sense for a range — mirrors the main dashboard breakdown. */
export function availableGroupsFor(dateFrom: string, dateTo: string): string[] {
    const days = moment(dateTo).diff(moment(dateFrom), 'days');
    if (days <= 7) return ['daily'];
    if (days <= 30) return ['daily', 'weekly'];
    if (days <= 365) return ['weekly', 'monthly'];
    return ['weekly', 'monthly', 'yearly'];
}

const capitalize = (s: string) =>
    s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();

/** Segmented control to switch the throughput chart's bucket size. */
export default function GranularityToggle({
    value,
    dateFrom,
    dateTo,
    onChange,
}: Props) {
    const groups = useMemo(
        () => availableGroupsFor(dateFrom, dateTo),
        [dateFrom, dateTo],
    );

    if (groups.length <= 1) {
        return null;
    }

    return (
        <div className="flex h-8 shrink-0 items-center gap-0.5 rounded-[10px] border border-black/6 bg-stone-100 p-0.5 dark:border-white/6 dark:bg-zinc-800">
            {groups.map((g) => (
                <button
                    key={g}
                    type="button"
                    onClick={() => onChange(g)}
                    className={`h-full rounded-lg px-3 text-[12px]! font-semibold tracking-tight transition-all ${
                        value === g
                            ? 'bg-white text-gray-800 shadow-sm dark:bg-zinc-700 dark:text-gray-100'
                            : 'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300'
                    }`}
                >
                    {capitalize(g)}
                </button>
            ))}
        </div>
    );
}
