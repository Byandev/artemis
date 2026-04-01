import { useState, useCallback, useMemo } from 'react';
import { SlidersHorizontal, X } from 'lucide-react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Workspace } from '@/types/models/Workspace';
import ShopFilter from '@/components/filters/ShopFilter';
import PageFilter from '@/components/filters/PageFilter';
import UserFilter from '@/components/filters/UserFilter';

export interface FilterValue {
    teamIds: (string | number)[];
    productIds: (string | number)[];
    shopIds: (string | number)[];
    pageIds: (string | number)[];
    userIds: (string | number)[];
}

interface Props {
    workspace: Workspace;
    onChange: (value: FilterValue) => void;
    initialValue?: FilterValue;
    onClear?: () => void;
}

const INITIAL_FILTER_VALUE: FilterValue = {
    teamIds: [],
    productIds: [],
    shopIds: [],
    pageIds: [],
    userIds: [],
};

const Filters = ({
    workspace,
    onChange,
    initialValue = INITIAL_FILTER_VALUE,
    onClear,
}: Props) => {
    const [isOpen, setIsOpen] = useState(false);
    const [localValue, setLocalValue] = useState<FilterValue>(initialValue);
    const [hasChanges, setHasChanges] = useState(false);

    const hasActiveFilters = useMemo(() =>
        Object.values(localValue).some((arr) => arr.length > 0),
        [localValue],
    );

    const handleFilterChange = useCallback(
        (type: keyof FilterValue, id: string | number) => {
            setLocalValue((prev) => {
                const currentArray = prev[type];
                const newArray = currentArray.includes(id)
                    ? currentArray.filter((item) => item !== id)
                    : [...currentArray, id];
                setHasChanges(true);
                return { ...prev, [type]: newArray };
            });
        },
        [],
    );

    const handleApply = useCallback(() => {
        onChange(localValue);
        setHasChanges(false);
        setIsOpen(false);
    }, [localValue, onChange]);

    const handleClearAll = useCallback(() => {
        setLocalValue(INITIAL_FILTER_VALUE);
        setHasChanges(true);
        onClear?.();
    }, [onClear]);

    const handleOpenChange = useCallback(
        (open: boolean) => {
            if (!open && hasChanges) {
                const shouldDiscard = window.confirm('Discard changes?');
                if (shouldDiscard) {
                    setLocalValue(initialValue);
                    setHasChanges(false);
                } else {
                    setIsOpen(true);
                    return;
                }
            }
            setIsOpen(open);
        },
        [hasChanges, initialValue],
    );

    const activeFilterCount = useMemo(() =>
        Object.values(localValue).reduce((acc, arr) => acc + arr.length, 0),
        [localValue],
    );

    return (
        <Popover open={isOpen} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <button
                    className={[
                        'inline-flex h-9 items-center overflow-hidden rounded-[10px] border transition-all duration-150',
                        'bg-white dark:bg-zinc-900',
                        'shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] dark:shadow-none',
                        isOpen
                            ? 'border-emerald-500/40 ring-2 ring-emerald-500/10 dark:border-emerald-500/30'
                            : activeFilterCount > 0
                              ? 'border-emerald-500/30 hover:border-emerald-500/50 dark:border-emerald-500/20 dark:hover:border-emerald-500/30'
                              : 'border-black/8 hover:border-black/14 dark:border-white/8 dark:hover:border-white/14',
                    ].join(' ')}
                >
                    {/* Icon cell */}
                    <span
                        className={[
                            'flex h-full w-9 shrink-0 items-center justify-center rounded-l-[10px] border-r transition-colors duration-150',
                            activeFilterCount > 0
                                ? 'border-emerald-500/20 bg-emerald-500/[0.07] dark:border-emerald-500/15 dark:bg-emerald-500/10'
                                : 'border-black/6 bg-stone-50 dark:border-white/6 dark:bg-white/3',
                        ].join(' ')}
                    >
                        <SlidersHorizontal
                            className={[
                                'h-3.5 w-3.5 transition-colors duration-150',
                                activeFilterCount > 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-gray-400 dark:text-gray-500',
                            ].join(' ')}
                        />
                    </span>

                    {/* Label + badge */}
                    <span className="flex items-center gap-2 px-3">
                        <span
                            className={[
                                'text-xs font-medium transition-colors duration-150',
                                activeFilterCount > 0
                                    ? 'text-gray-700 dark:text-gray-200'
                                    : 'text-gray-500 dark:text-gray-400',
                            ].join(' ')}
                        >
                            Filters
                        </span>
                        {activeFilterCount > 0 && (
                            <span className="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-500/[0.10] px-1 text-[10px] font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                {activeFilterCount}
                            </span>
                        )}
                    </span>
                </button>
            </PopoverTrigger>

            <PopoverContent
                className="w-[calc(100vw-2rem)] overflow-hidden rounded-[14px] border border-black/6 bg-white p-0 shadow-[0_8px_30px_rgba(0,0,0,0.08)] sm:w-72 dark:border-white/6 dark:bg-zinc-900 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
                align="start"
            >
                {/* Header */}
                <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <span className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                        Filters
                    </span>
                    {hasActiveFilters && (
                        <button
                            onClick={handleClearAll}
                            className="inline-flex items-center gap-1 text-[11px] font-medium text-gray-400 transition-colors hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                        >
                            <X className="h-3 w-3" />
                            Clear all
                        </button>
                    )}
                </div>

                {/* Filter groups */}
                <div className="max-h-72 space-y-1 overflow-y-auto p-2">
                    <PageFilter
                        workspace={workspace}
                        selected={localValue.pageIds}
                        onSelect={(id) => handleFilterChange('pageIds', id)}
                    />
                    <ShopFilter
                        workspace={workspace}
                        selected={localValue.shopIds}
                        onSelect={(id) => handleFilterChange('shopIds', id)}
                    />
                    <UserFilter
                        workspace={workspace}
                        selected={localValue.userIds}
                        onSelect={(id) => handleFilterChange('userIds', id)}
                    />
                </div>

                {/* Footer */}
                <div className="flex gap-2 border-t border-black/6 px-4 py-3 dark:border-white/6">
                    <button
                        onClick={handleApply}
                        disabled={!hasChanges}
                        className="flex-1 rounded-lg bg-emerald-600 py-2 text-xs font-medium text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-emerald-500 dark:hover:bg-emerald-400"
                    >
                        Apply
                    </button>
                    <button
                        onClick={() => {
                            setLocalValue(initialValue);
                            setHasChanges(false);
                            setIsOpen(false);
                        }}
                        className="rounded-lg border border-black/6 bg-white px-4 py-2 text-xs font-medium text-gray-500 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-400 dark:hover:border-white/10"
                    >
                        Cancel
                    </button>
                </div>
            </PopoverContent>
        </Popover>
    );
};

export default Filters;
