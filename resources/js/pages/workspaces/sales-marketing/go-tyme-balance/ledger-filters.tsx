import DatePicker from '@/components/ui/date-picker';
import { Search, X } from 'lucide-react';
import { useMemo } from 'react';
import { EntryType, LiquidationEntry } from './types';

export interface LedgerFilterValue {
    search: string;
    /** '' = any. */
    type: EntryType | '';
    transactionType: string;
    department: string;
    from: string;
    to: string;
}

export const EMPTY_FILTERS: LedgerFilterValue = {
    search: '',
    type: '',
    transactionType: '',
    department: '',
    from: '',
    to: '',
};

export const hasActiveFilters = (v: LedgerFilterValue) =>
    Object.values(v).some((x) => x !== '');

const selectCls =
    'h-9 rounded-[10px] border border-black/6 bg-stone-100 px-2.5 font-mono! text-[12px]! text-gray-700 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200';

const ymd = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/**
 * Everything here narrows the rows already in memory — no request goes out, so
 * the table reacts as fast as you can type.
 */
export function applyFilters(
    entries: LiquidationEntry[],
    f: LedgerFilterValue,
): LiquidationEntry[] {
    const needle = f.search.trim().toLowerCase();

    return entries.filter((e) => {
        if (f.type && e.type !== f.type) return false;
        if (
            f.transactionType &&
            String(e.transaction_type_id) !== f.transactionType
        ) {
            return false;
        }
        if (f.department && (e.department ?? '') !== f.department) return false;
        if (f.from && e.date < f.from) return false;
        if (f.to && e.date > f.to) return false;

        if (needle) {
            const haystack = [
                e.transaction_type_name,
                e.description,
                e.department,
                e.notes,
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            if (!haystack.includes(needle)) return false;
        }

        return true;
    });
}

interface Props {
    entries: LiquidationEntry[];
    value: LedgerFilterValue;
    onChange: (next: LedgerFilterValue) => void;
}

export default function LedgerFilters({ entries, value, onChange }: Props) {
    const set = (patch: Partial<LedgerFilterValue>) =>
        onChange({ ...value, ...patch });

    // Offer only what the ledger actually contains, so no option returns zero
    // rows.
    const typeOptions = useMemo(() => {
        const seen = new Map<string, string>();
        entries.forEach((e) => {
            if (e.transaction_type_name) {
                seen.set(
                    String(e.transaction_type_id),
                    e.transaction_type_name,
                );
            }
        });

        return [...seen.entries()].sort((a, b) => a[1].localeCompare(b[1]));
    }, [entries]);

    const departmentOptions = useMemo(
        () =>
            [...new Set(entries.map((e) => e.department).filter(Boolean))].sort(
                (a, b) => (a as string).localeCompare(b as string),
            ) as string[],
        [entries],
    );

    return (
        <div className="mb-3 flex flex-wrap items-center gap-2">
            <div className="relative w-full max-w-xs">
                <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                <input
                    className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100"
                    placeholder="Search entries..."
                    value={value.search}
                    onChange={(e) => set({ search: e.target.value })}
                />
            </div>

            <select
                className={selectCls}
                value={value.type}
                onChange={(e) =>
                    set({ type: e.target.value as EntryType | '' })
                }
            >
                <option value="">All entries</option>
                <option value="in">Credit only</option>
                <option value="out">Debit only</option>
            </select>

            <select
                className={selectCls}
                value={value.transactionType}
                onChange={(e) => set({ transactionType: e.target.value })}
            >
                <option value="">All transactions</option>
                {typeOptions.map(([id, name]) => (
                    <option key={id} value={id}>
                        {name}
                    </option>
                ))}
            </select>

            <select
                className={selectCls}
                value={value.department}
                onChange={(e) => set({ department: e.target.value })}
            >
                <option value="">All departments</option>
                {departmentOptions.map((name) => (
                    <option key={name} value={name}>
                        {name}
                    </option>
                ))}
            </select>

            <DatePicker
                id={`ledger-range-${value.from}-${value.to}`}
                mode="range"
                placeholder="Any date"
                defaultDate={
                    value.from
                        ? [value.from, value.to || value.from]
                        : undefined
                }
                onChange={(picked) => {
                    if (picked.length === 0) set({ from: '', to: '' });
                    if (picked.length === 2) {
                        set({ from: ymd(picked[0]), to: ymd(picked[1]) });
                    }
                }}
            />

            {hasActiveFilters(value) && (
                <button
                    onClick={() => onChange(EMPTY_FILTERS)}
                    className="flex h-9 items-center gap-1 rounded-[10px] px-2.5 font-mono text-[11px] text-gray-400 transition-colors hover:text-gray-700 dark:hover:text-gray-200"
                >
                    <X className="h-3 w-3" />
                    Clear
                </button>
            )}
        </div>
    );
}
