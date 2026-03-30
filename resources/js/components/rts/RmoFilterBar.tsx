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
import { ORDER_STATUSES } from '@/types/models/Pancake/OrderForDelivery';
import {
    ChevronDown,
    ChevronUp,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { orderStatusConfig, ParcelStatusEntry } from './rmo-config';

export interface UniquePage {
    id: string;
    name: string;
}

interface Props {
    searchValue: string;
    onSearchChange: (value: string) => void;

    uniquePages: UniquePage[];
    selectedPageIds: string[];
    onPageChange: (ids: string[]) => void;

    parcelStatusConfig: Record<string, ParcelStatusEntry>;
    parcelStatusOptions: string[];
    selectedParcelStatuses: string[];
    onParcelStatusChange: (statuses: string[]) => void;

    selectedStatuses: string[];
    onStatusChange: (statuses: string[]) => void;
}

function MultiSelectGroup({
    name,
    selected,
    onChange,
    options,
}: {
    name: string;
    selected: string[];
    onChange: (v: string[]) => void;
    options: { id: string; label: string; dot?: string }[];
}) {
    const [open, setOpen] = useState(false);
    const activeCount = selected.length;

    const toggle = (id: string) => {
        if (selected.includes(id)) {
            onChange(selected.filter((s) => s !== id));
        } else {
            onChange([...selected, id]);
        }
    };

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger
                className={[
                    'flex w-full items-center justify-between rounded-[8px] px-2 py-2 transition-colors',
                    'hover:bg-black/[0.02] dark:hover:bg-white/[0.03]',
                ].join(' ')}
            >
                <span className="flex items-center gap-2">
                    <span className="font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                        {name}
                    </span>
                    {activeCount > 0 && (
                        <span className="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-500/[0.10] px-1 text-[10px] font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                            {activeCount}
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
                <div>
                    {options.map((opt) => {
                        const checked = selected.includes(opt.id);
                        return (
                            <label
                                key={opt.id}
                                htmlFor={`rmo-filter-${name}-${opt.id}`}
                                className={[
                                    'flex cursor-pointer items-center gap-2.5 rounded-[8px] px-2 py-2 transition-colors',
                                    checked
                                        ? 'bg-emerald-500/[0.06] dark:bg-emerald-500/[0.08]'
                                        : 'hover:bg-black/[0.02] dark:hover:bg-white/[0.03]',
                                ].join(' ')}
                            >
                                <Checkbox
                                    id={`rmo-filter-${name}-${opt.id}`}
                                    checked={checked}
                                    onCheckedChange={() => toggle(opt.id)}
                                    className="border-black/20 data-[state=checked]:border-emerald-600 data-[state=checked]:bg-emerald-600 dark:border-white/20 dark:data-[state=checked]:border-emerald-500 dark:data-[state=checked]:bg-emerald-500"
                                />
                                {opt.dot && (
                                    <span
                                        className={`h-1.5 w-1.5 shrink-0 rounded-full ${opt.dot}`}
                                    />
                                )}
                                <span
                                    className={[
                                        'truncate text-[13px] select-none',
                                        checked
                                            ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                            : 'text-gray-600 dark:text-gray-400',
                                    ].join(' ')}
                                >
                                    {opt.label}
                                </span>
                            </label>
                        );
                    })}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

export function RmoFilterBar({
    searchValue,
    onSearchChange,
    uniquePages,
    selectedPageIds,
    onPageChange,
    parcelStatusConfig,
    parcelStatusOptions,
    selectedParcelStatuses,
    onParcelStatusChange,
    selectedStatuses,
    onStatusChange,
}: Props) {
    const [isOpen, setIsOpen] = useState(false);

    const activeFilterCount =
        selectedStatuses.length +
        selectedPageIds.length +
        selectedParcelStatuses.length;
    const hasActiveFilters = activeFilterCount > 0 || !!searchValue;

    const clearFilters = useCallback(() => {
        onSearchChange('');
        onPageChange([]);
        onParcelStatusChange([]);
        onStatusChange([]);
    }, [onSearchChange, onPageChange, onParcelStatusChange, onStatusChange]);

    const pageOptions = useMemo(
        () => uniquePages.map((p) => ({ id: p.id, label: p.name })),
        [uniquePages],
    );

    const parcelOptions = useMemo(
        () =>
            parcelStatusOptions.map((s) => ({
                id: s,
                label: parcelStatusConfig[s].label,
                dot: parcelStatusConfig[s].dot,
            })),
        [parcelStatusOptions, parcelStatusConfig],
    );

    const orderStatusOptions = useMemo(
        () =>
            ORDER_STATUSES.map((s) => ({
                id: s,
                label: s,
                dot: orderStatusConfig[s]?.dot ?? 'bg-gray-400',
            })),
        [],
    );

    return (
        <div className="mb-3 flex flex-wrap items-center gap-2">
            {/* Search */}
            <div className="relative w-60">
                <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                <input
                    type="text"
                    className="h-8 w-full rounded-lg border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-700 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                    placeholder="Search orders…"
                    value={searchValue}
                    onChange={(e) => onSearchChange(e.target.value)}
                />
            </div>

            {/* Filter popover — same design as dashboard Filters */}
            <Popover open={isOpen} onOpenChange={setIsOpen}>
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
                        {activeFilterCount > 0 && (
                            <button
                                onClick={clearFilters}
                                className="inline-flex items-center gap-1 text-[11px] font-medium text-gray-400 transition-colors hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                            >
                                <X className="h-3 w-3" />
                                Clear all
                            </button>
                        )}
                    </div>

                    {/* Filter groups */}
                    <div className="max-h-72 space-y-1 overflow-y-auto p-2">
                        <MultiSelectGroup
                            name="Page"
                            selected={selectedPageIds}
                            onChange={onPageChange}
                            options={pageOptions}
                        />
                        <MultiSelectGroup
                            name="J&T Parcel Status"
                            selected={selectedParcelStatuses}
                            onChange={onParcelStatusChange}
                            options={parcelOptions}
                        />
                        <MultiSelectGroup
                            name="Order Status"
                            selected={selectedStatuses}
                            onChange={onStatusChange}
                            options={orderStatusOptions}
                        />
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    );
}
