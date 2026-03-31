import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ListFilter, Search, X } from 'lucide-react';
import { useState } from 'react';

export interface FilterOption {
    id: string;
    name: string;
    dot?: string;
    text?: string;
}

interface FilterPopoverProps {
    label: string;
    options: FilterOption[];
    selected: string[];
    onChange: (value: string[]) => void;
    icon?: React.ElementType;
}

function FilterPopover({ label, options, selected, onChange, icon: Icon }: FilterPopoverProps) {
    const [open, setOpen] = useState(false);

    const toggle = (id: string) => {
        onChange(
            selected.includes(id)
                ? selected.filter((s) => s !== id)
                : [...selected, id],
        );
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className="flex h-8 items-center overflow-hidden px-0 text-[12px]! font-medium"
                >
                    {Icon && (
                        <div className="flex h-8 items-center justify-center rounded-l-md border border-r bg-gray-50 px-2 dark:bg-zinc-800">
                            <Icon className="h-3.5 w-3.5 text-gray-500" />
                        </div>
                    )}
                    <div className="flex items-center gap-1.5 px-3">
                        {label}
                        {selected.length > 0 && (
                            <span className="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-500/10 px-1 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                                {selected.length}
                            </span>
                        )}
                    </div>
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[calc(100vw-2rem)] overflow-hidden rounded-[14px] border border-black/6 bg-white p-0 shadow-[0_8px_30px_rgba(0,0,0,0.08)] sm:w-72 dark:border-white/6 dark:bg-zinc-900 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
                align="start"
            >
                <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <span className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                        {label}
                    </span>
                </div>
                <div className="max-h-64 overflow-y-auto">
                    {options.map((option) => (
                        <label
                            key={option.id}
                            className="flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 hover:bg-gray-50 dark:hover:bg-zinc-800"
                        >
                            <Checkbox
                                checked={selected.includes(option.id)}
                                onCheckedChange={() => toggle(option.id)}
                                className="h-3.5 w-3.5 rounded-sm border border-gray-200 dark:border-gray-700"
                            />
                            <div className="flex items-center gap-2">
                                {option.dot && (
                                    <div className={`h-2 w-2 rounded-full ${option.dot}`} />
                                )}
                                <span className={`text-[13px] ${option.text ?? ''}`}>
                                    {option.name}
                                </span>
                            </div>
                        </label>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}

interface RmoFilterControlsProps {
    searchValue: string;
    onSearchChange: (v: string) => void;
    orderStatusOptions: FilterOption[];
    selectedStatuses: string[];
    onStatusChange: (v: string[]) => void;
    parcelStatusOptions: FilterOption[];
    selectedParcelStatuses: string[];
    onParcelStatusChange: (v: string[]) => void;
    hasActiveFilters: boolean;
    onClearAll: () => void;
}

export function RmoFilterControls({
    searchValue,
    onSearchChange,
    orderStatusOptions,
    selectedStatuses,
    onStatusChange,
    parcelStatusOptions,
    selectedParcelStatuses,
    onParcelStatusChange,
    hasActiveFilters,
    onClearAll,
}: RmoFilterControlsProps) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="relative w-60">
                <Search className="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                <input
                    type="text"
                    className="h-8 w-full rounded-lg border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! outline-none focus:border-emerald-500 dark:bg-zinc-800 dark:text-gray-300"
                    placeholder="Search orders…"
                    value={searchValue}
                    onChange={(e) => onSearchChange(e.target.value)}
                />
            </div>

            <FilterPopover
                label="Order Status"
                options={orderStatusOptions}
                selected={selectedStatuses}
                onChange={onStatusChange}
                icon={ListFilter}
            />

            <FilterPopover
                label="Parcel Status"
                options={parcelStatusOptions}
                selected={selectedParcelStatuses}
                onChange={onParcelStatusChange}
                icon={ListFilter}
            />

            {hasActiveFilters && (
                <button
                    onClick={onClearAll}
                    className="inline-flex h-8 items-center gap-1 rounded-lg border border-red-200 bg-white px-3 text-[12px] text-red-600 hover:bg-red-50 dark:border-red-800 dark:bg-zinc-900 dark:text-red-400 dark:hover:bg-red-950/30"
                >
                    <X className="h-3.5 w-3.5" />
                    Clear all
                </button>
            )}
        </div>
    );
}
