import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ChevronDown, ChevronUp, SlidersHorizontal, X } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

/** The multi-select filters managed by this popover (all arrays of id strings). */
export interface PageRoasFilterValue {
    pages: string[];
    shops: string[];
    users: string[];
}

export interface FilterOption {
    key: string;
    label: string;
}

interface Props {
    value: PageRoasFilterValue;
    pageOptions: FilterOption[];
    shopOptions: FilterOption[];
    userOptions: FilterOption[];
    onApply: (value: PageRoasFilterValue) => void;
}

// Multi-select collapsible group, mirroring the main dashboard's filter.
function MultiSelectGroup({
    name,
    options,
    selected,
    onToggle,
}: {
    name: string;
    options: FilterOption[];
    selected: string[];
    onToggle: (key: string) => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger className="flex w-full items-center justify-between rounded-lg px-2 py-2 transition-colors hover:bg-black/2 dark:hover:bg-white/3">
                <span className="flex items-center gap-2">
                    <span className="font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                        {name}
                    </span>
                    {selected.length > 0 && (
                        <span className="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-500/[0.10] px-1 text-[10px] font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                            {selected.length}
                        </span>
                    )}
                </span>
                {open ? (
                    <ChevronUp className="h-3.5 w-3.5 text-gray-300 dark:text-gray-600" />
                ) : (
                    <ChevronDown className="h-3.5 w-3.5 text-gray-300 dark:text-gray-600" />
                )}
            </CollapsibleTrigger>
            <CollapsibleContent className="mt-0.5">
                <div className="max-h-44 overflow-y-auto">
                    {options.length === 0 ? (
                        <p className="px-2 py-3 text-center text-[11px] text-gray-400 dark:text-gray-600">
                            No {name.toLowerCase()} available
                        </p>
                    ) : (
                        options.map((option) => {
                            const checked = selected.includes(option.key);
                            return (
                                <label
                                    key={option.key}
                                    className={[
                                        'flex cursor-pointer items-center gap-2.5 rounded-[8px] px-2 py-2 transition-colors',
                                        checked
                                            ? 'bg-emerald-500/[0.06] dark:bg-emerald-500/[0.08]'
                                            : 'hover:bg-black/[0.02] dark:hover:bg-white/[0.03]',
                                    ].join(' ')}
                                >
                                    <Checkbox
                                        checked={checked}
                                        onCheckedChange={() =>
                                            onToggle(option.key)
                                        }
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
                                        {option.label}
                                    </span>
                                </label>
                            );
                        })
                    )}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

/**
 * Combined Page / Shop / User filter for the ROAS tracker, styled to match the
 * Creative Tracker's Filters control — sliders trigger + grouped multi-select
 * sections + an explicit "Apply Changes" action and a "Clear all" reset.
 */
export default function PageRoasFilters({
    value,
    pageOptions,
    shopOptions,
    userOptions,
    onApply,
}: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [localValue, setLocalValue] = useState<PageRoasFilterValue>(value);
    const [hasChanges, setHasChanges] = useState(false);

    // Count groups that have any selection.
    const activeCount = useMemo(() => {
        let count = 0;
        if (value.pages.length > 0) count++;
        if (value.shops.length > 0) count++;
        if (value.users.length > 0) count++;
        return count;
    }, [value]);

    const toggle = useCallback((key: keyof PageRoasFilterValue, id: string) => {
        setLocalValue((prev) => {
            const current = prev[key];
            const next = current.includes(id)
                ? current.filter((x) => x !== id)
                : [...current, id];
            return { ...prev, [key]: next };
        });
        setHasChanges(true);
    }, []);

    const handleApply = useCallback(() => {
        onApply(localValue);
        setHasChanges(false);
        setIsOpen(false);
    }, [localValue, onApply]);

    const handleClearAll = useCallback(() => {
        setLocalValue({ pages: [], shops: [], users: [] });
        setHasChanges(true);
    }, []);

    const handleOpenChange = useCallback(
        (open: boolean) => {
            if (!open) {
                // Discard unapplied edits when closing without applying.
                setLocalValue(value);
                setHasChanges(false);
            }
            setIsOpen(open);
        },
        [value],
    );

    return (
        <Popover open={isOpen} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <button
                    className={[
                        'inline-flex h-9 min-w-max shrink-0 items-center overflow-hidden rounded-[10px] border transition-all duration-150',
                        'bg-white dark:bg-zinc-900',
                        'shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] dark:shadow-none',
                        isOpen
                            ? 'border-emerald-500/40 ring-2 ring-emerald-500/10 dark:border-emerald-500/30'
                            : activeCount > 0
                              ? 'border-emerald-500/30 hover:border-emerald-500/50 dark:border-emerald-500/20 dark:hover:border-emerald-500/30'
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
                            Filters
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
                align="end"
            >
                <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <span className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                        Filters
                    </span>
                    <button
                        onClick={handleClearAll}
                        className="inline-flex items-center gap-1 text-[11px] font-medium text-gray-400 transition-colors hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                    >
                        <X className="h-3 w-3" />
                        Clear all
                    </button>
                </div>

                <div className="max-h-72 space-y-1 overflow-y-auto p-2">
                    <MultiSelectGroup
                        name="Page"
                        options={pageOptions}
                        selected={localValue.pages}
                        onToggle={(id) => toggle('pages', id)}
                    />
                    <MultiSelectGroup
                        name="Shop"
                        options={shopOptions}
                        selected={localValue.shops}
                        onToggle={(id) => toggle('shops', id)}
                    />
                    <MultiSelectGroup
                        name="User"
                        options={userOptions}
                        selected={localValue.users}
                        onToggle={(id) => toggle('users', id)}
                    />
                </div>

                <div className="flex gap-2 border-t border-black/6 px-4 py-3 dark:border-white/6">
                    <button
                        onClick={handleApply}
                        disabled={!hasChanges}
                        className="flex-1 rounded-lg bg-emerald-600 py-2 text-xs font-medium text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-emerald-500 dark:hover:bg-emerald-400"
                    >
                        Apply Changes
                    </button>
                    <button
                        onClick={() => handleOpenChange(false)}
                        className="rounded-lg border border-black/6 bg-white px-4 py-2 text-xs font-medium text-gray-500 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-400 dark:hover:border-white/10"
                    >
                        Cancel
                    </button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
