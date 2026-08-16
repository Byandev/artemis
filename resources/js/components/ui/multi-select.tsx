import * as React from 'react';
import { Check, ChevronDown, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

export interface Option {
    value: string;
    label: string;
}

interface MultiSelectProps {
    options: Option[];
    selected: string[];
    onChange: (selected: string[]) => void;
    placeholder?: string;
    className?: string;
    compact?: boolean;
    /**
     * Rendered inside the open menu, above the search box — for controls that
     * belong to the filter itself (e.g. switching what the options are labelled
     * by). Compact mode only.
     */
    header?: React.ReactNode;
}

export function MultiSelect({
    options,
    selected,
    onChange,
    placeholder = 'Select items...',
    className = '',
    compact = false,
    header,
}: MultiSelectProps) {
    const [open, setOpen] = React.useState(false);
    const [search, setSearch] = React.useState('');
    const dropdownRef = React.useRef<HTMLDivElement>(null);

    // Close dropdown when clicking outside
    React.useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        if (open) {
            document.addEventListener('mousedown', handleClickOutside);
            return () => document.removeEventListener('mousedown', handleClickOutside);
        }
    }, [open]);

    const handleToggle = (value: string) => {
        if (selected.includes(value)) {
            onChange(selected.filter(v => v !== value));
        } else {
            onChange([...selected, value]);
        }
    };

    const handleRemove = (value: string) => {
        onChange(selected.filter(v => v !== value));
    };

    const filteredOptions = options.filter(option =>
        option.label.toLowerCase().includes(search.toLowerCase())
    );

    if (compact) {
        return (
            <div className={`relative ${className}`} ref={dropdownRef}>
                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    className="flex h-9 w-full items-center justify-between gap-1 rounded-[10px] border border-black/6 bg-stone-100 px-2.5 font-mono! text-[11px]! text-gray-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                >
                    <span className="truncate">
                        {selected.length === 0
                            ? placeholder
                            : `${selected.length} selected`}
                    </span>
                    <ChevronDown className="h-3 w-3 shrink-0 opacity-50" />
                </button>

                {selected.length > 0 && (
                    <button
                        type="button"
                        className="absolute top-1/2 right-7 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        onClick={(e) => {
                            e.stopPropagation();
                            onChange([]);
                        }}
                    >
                        <X className="h-3 w-3" />
                    </button>
                )}

                {open && (
                    <div className="absolute z-50 mt-1 w-full min-w-[200px] overflow-hidden rounded-[10px] border border-black/6 bg-white shadow-lg dark:border-white/6 dark:bg-zinc-800">
                        {header && (
                            <div className="border-b border-black/6 p-1.5 dark:border-white/6">
                                {header}
                            </div>
                        )}
                        <div className="border-b border-black/6 p-1.5 dark:border-white/6">
                            <input
                                type="text"
                                className="h-7 w-full rounded-md bg-stone-100 px-2 text-[11px] outline-none placeholder:text-gray-400 dark:bg-zinc-700 dark:text-gray-200"
                                placeholder="Search..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                autoFocus
                            />
                        </div>
                        <div className="max-h-52 overflow-y-auto p-1">
                            {filteredOptions.map((option) => {
                                const isSelected = selected.includes(option.value);
                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => handleToggle(option.value)}
                                        className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-[11px] hover:bg-stone-100 dark:hover:bg-zinc-700"
                                    >
                                        <span className={`flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border ${isSelected ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-gray-300 dark:border-gray-600'}`}>
                                            {isSelected && <Check className="h-2.5 w-2.5" />}
                                        </span>
                                        <span className="text-gray-700 dark:text-gray-200">{option.label}</span>
                                    </button>
                                );
                            })}
                            {filteredOptions.length === 0 && (
                                <p className="px-2 py-3 text-center text-[11px] text-gray-400">No results.</p>
                            )}
                        </div>
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className={`relative ${className}`} ref={dropdownRef}>
            {/* Selected items and input */}
            <div
                className="min-h-10 cursor-text rounded-[10px] border border-black/8 bg-stone-50 px-2.5 py-1.5 transition-all focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800"
                onClick={() => setOpen(true)}
            >
                <div className="flex flex-wrap gap-1 items-center">
                    {selected.map((value) => {
                        const option = options.find(o => o.value === value);
                        return (
                            <Badge key={value} variant="secondary" className="gap-1 font-mono! text-[11px]! font-normal">
                                {option?.label || value}
                                <button
                                    type="button"
                                    className="ml-1 hover:text-destructive"
                                    onMouseDown={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                    }}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        handleRemove(value);
                                    }}
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            </Badge>
                        );
                    })}
                    <input
                        type="text"
                        className="flex-1 min-w-[120px] bg-transparent font-mono! text-[13px]! text-gray-800 outline-none placeholder:text-gray-300 dark:text-gray-100 dark:placeholder:text-gray-600"
                        placeholder={selected.length === 0 ? placeholder : 'Search...'}
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            setOpen(true);
                        }}
                        onFocus={() => setOpen(true)}
                    />
                </div>
            </div>

            {/* Dropdown options */}
            {open && (
                <div className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-[10px] border border-black/8 bg-white p-1 shadow-lg dark:border-white/8 dark:bg-zinc-800">
                    {filteredOptions.map((option) => {
                        const isSelected = selected.includes(option.value);
                        return (
                            <div
                                key={option.value}
                                className="relative flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 font-mono! text-[12px]! text-gray-700 outline-none select-none hover:bg-stone-100 dark:text-gray-200 dark:hover:bg-zinc-700"
                                onClick={() => handleToggle(option.value)}
                            >
                                <span className={`flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border ${isSelected ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-gray-300 dark:border-gray-600'}`}>
                                    {isSelected && <Check className="h-2.5 w-2.5" />}
                                </span>
                                {option.label}
                            </div>
                        );
                    })}
                    {filteredOptions.length === 0 && search && (
                        <div className="p-6 text-center font-mono! text-[12px]! text-gray-400">
                            No results found.
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
