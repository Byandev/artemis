import { Checkbox } from '@/components/ui/checkbox';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Search, SlidersHorizontal, X } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

export interface CourseFilterValue {
    categories: string[];
}

interface Props {
    value: CourseFilterValue;
    categoryOptions: string[];
    onChange: (value: CourseFilterValue) => void;
}

const EMPTY: CourseFilterValue = { categories: [] };

/**
 * Same chrome as the dashboard's Filters popover — trigger with a slider icon
 * and a count badge, multi-select checkboxes, apply / clear — so the two read
 * as one control rather than two takes on the same idea.
 *
 * Category is the only axis worth filtering courses on, so the trigger names it
 * directly. The options sit flat in the popover rather than inside a
 * collapsible group: with a single group, the group heading would just repeat
 * the popover's own title and hide the list behind an extra click.
 */
export default function CourseFilters({
    value,
    categoryOptions,
    onChange,
}: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [local, setLocal] = useState<CourseFilterValue>(value);
    const [dirty, setDirty] = useState(false);
    const [search, setSearch] = useState('');

    // Worth searching once the list is long enough to scroll.
    const searchable = categoryOptions.length > 8;

    const visible = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return categoryOptions;
        return categoryOptions.filter((c) => c.toLowerCase().includes(q));
    }, [categoryOptions, search]);

    const activeCount = local.categories.length;

    const toggle = useCallback((id: string | number) => {
        const asString = String(id);
        setLocal((prev) => ({
            categories: prev.categories.includes(asString)
                ? prev.categories.filter((item) => item !== asString)
                : [...prev.categories, asString],
        }));
        setDirty(true);
    }, []);

    function apply() {
        onChange(local);
        setDirty(false);
        setIsOpen(false);
    }

    function clearAll() {
        setSearch('');
        setLocal(EMPTY);
        onChange(EMPTY);
        setDirty(false);
        setIsOpen(false);
    }

    return (
        <Popover
            open={isOpen}
            onOpenChange={(open) => {
                // Reopening after abandoning edits should show what is applied,
                // not the half-made selection from last time.
                if (!open && dirty) {
                    setLocal(value);
                    setDirty(false);
                }
                setIsOpen(open);
            }}
        >
            <PopoverTrigger asChild>
                <button
                    className={[
                        'inline-flex h-9 min-w-max shrink-0 items-center overflow-hidden rounded-[10px] border transition-all duration-150',
                        'bg-white dark:bg-zinc-900',
                        isOpen
                            ? 'border-emerald-500/40 ring-2 ring-emerald-500/10 dark:border-emerald-500/30'
                            : activeCount > 0
                              ? 'border-emerald-500/30 hover:border-emerald-500/50 dark:border-emerald-500/20'
                              : 'border-black/8 hover:border-black/14 dark:border-white/8 dark:hover:border-white/14',
                    ].join(' ')}
                >
                    <span
                        className={[
                            'flex h-full w-9 shrink-0 items-center justify-center rounded-l-[10px] border-r transition-colors duration-150',
                            activeCount > 0
                                ? 'border-emerald-500/20 bg-emerald-500/[0.07] dark:border-emerald-500/15 dark:bg-emerald-500/10'
                                : 'border-black/6 bg-stone-50 dark:border-white/6 dark:bg-white/3',
                        ].join(' ')}
                    >
                        <SlidersHorizontal
                            className={[
                                'h-3.5 w-3.5 transition-colors duration-150',
                                activeCount > 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-gray-400 dark:text-gray-500',
                            ].join(' ')}
                        />
                    </span>

                    <span className="flex items-center gap-2 px-3">
                        <span
                            className={[
                                'text-xs font-medium transition-colors duration-150',
                                activeCount > 0
                                    ? 'text-gray-700 dark:text-gray-200'
                                    : 'text-gray-500 dark:text-gray-400',
                            ].join(' ')}
                        >
                            Category
                        </span>
                        {activeCount > 0 && (
                            <span className="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-500/[0.10] px-1 text-[10px] font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                {activeCount}
                            </span>
                        )}
                    </span>
                </button>
            </PopoverTrigger>

            <PopoverContent
                className="w-[calc(100vw-2rem)] overflow-hidden rounded-[14px] border border-black/6 bg-white p-0 shadow-[0_8px_30px_rgba(0,0,0,0.08)] sm:w-72 dark:border-white/6 dark:bg-zinc-900 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
                align="start"
            >
                <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <span className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                        Category
                    </span>
                    {activeCount > 0 && (
                        <button
                            onClick={clearAll}
                            className="inline-flex items-center gap-1 text-[11px] font-medium text-gray-400 transition-colors hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                        >
                            <X className="h-3 w-3" />
                            Clear all
                        </button>
                    )}
                </div>

                {searchable && (
                    <div className="relative border-b border-black/6 px-3 py-2 dark:border-white/6">
                        <Search className="pointer-events-none absolute top-1/2 left-5.5 h-3 w-3 -translate-y-1/2 text-gray-300 dark:text-gray-600" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search categories…"
                            className="w-full rounded-[7px] border border-black/8 bg-stone-50 py-1.5 pr-2.5 pl-7 text-[12px]! text-gray-700 placeholder-gray-300 transition-colors outline-none focus:border-black/20 dark:border-white/8 dark:bg-white/3 dark:text-gray-300 dark:placeholder-gray-600 dark:focus:border-white/20"
                        />
                    </div>
                )}

                <div className="max-h-72 overflow-y-auto p-2">
                    {visible.length === 0 ? (
                        <p className="px-2 py-6 text-center text-[11px] text-gray-400 dark:text-gray-600">
                            {categoryOptions.length === 0
                                ? 'No categories yet'
                                : `No results for "${search}"`}
                        </p>
                    ) : (
                        visible.map((category) => {
                            const checked = local.categories.includes(category);

                            return (
                                <label
                                    key={category}
                                    className={[
                                        'flex cursor-pointer items-center gap-2.5 rounded-[8px] px-2 py-2 transition-colors',
                                        checked
                                            ? 'bg-emerald-500/[0.06] dark:bg-emerald-500/[0.08]'
                                            : 'hover:bg-black/[0.02] dark:hover:bg-white/[0.03]',
                                    ].join(' ')}
                                >
                                    <Checkbox
                                        checked={checked}
                                        onCheckedChange={() => toggle(category)}
                                        className="border-black/20 data-[state=checked]:border-emerald-600 data-[state=checked]:bg-emerald-600 dark:border-white/20 dark:data-[state=checked]:border-emerald-500 dark:data-[state=checked]:bg-emerald-500"
                                    />
                                    <span
                                        className={[
                                            'truncate text-[13px] select-none',
                                            checked
                                                ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                                : 'text-gray-600 dark:text-gray-400',
                                        ].join(' ')}
                                    >
                                        {category}
                                    </span>
                                </label>
                            );
                        })
                    )}
                </div>

                <div className="border-t border-black/6 px-4 py-3 dark:border-white/6">
                    <button
                        onClick={apply}
                        className="flex h-9 w-full items-center justify-center rounded-lg bg-emerald-600 text-[13px] font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        Apply
                    </button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
