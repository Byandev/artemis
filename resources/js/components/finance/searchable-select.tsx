import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Check, ChevronsUpDown, Search, X } from 'lucide-react';
import { ComponentType, useMemo, useState } from 'react';

/**
 * Searchable single-select over a list of string options — same interaction as
 * the creatives ProductPicker, but for plain string values. The current value is
 * shown as-is even if it isn't in `options` (so legacy/stored values still
 * display). Clearing sets the value back to an empty string.
 */
export function SearchableSelect({
    options,
    value,
    onChange,
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    emptyText = 'Nothing found.',
    icon: Icon,
    id,
}: {
    options: string[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    icon?: ComponentType<{ className?: string }>;
    id?: string;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return options;
        return options.filter((o) => o.toLowerCase().includes(q));
    }, [options, search]);

    return (
        <Popover
            open={open}
            onOpenChange={(o) => {
                setOpen(o);
                if (!o) setSearch('');
            }}
        >
            <PopoverTrigger asChild>
                <button
                    type="button"
                    id={id}
                    className="flex h-10 w-full items-center gap-2 rounded-[10px] border border-black/8 bg-stone-50 px-3 text-left transition-all outline-none hover:border-black/14 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:hover:border-white/14"
                >
                    {Icon && (
                        <Icon className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                    )}
                    {value ? (
                        <span className="flex-1 truncate font-mono text-[13px] text-gray-800 dark:text-gray-100">
                            {value}
                        </span>
                    ) : (
                        <span className="flex-1 truncate font-mono text-[13px] text-gray-400 dark:text-gray-500">
                            {placeholder}
                        </span>
                    )}
                    {value ? (
                        <span
                            role="button"
                            tabIndex={-1}
                            onClick={(e) => {
                                e.stopPropagation();
                                onChange('');
                            }}
                            className="rounded p-0.5 text-gray-300 transition-colors hover:bg-black/5 hover:text-gray-600 dark:hover:bg-white/10"
                        >
                            <X className="h-3.5 w-3.5" />
                        </span>
                    ) : (
                        <ChevronsUpDown className="h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" />
                    )}
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-[var(--radix-popover-trigger-width)] p-0"
            >
                <div className="flex items-center gap-2 border-b px-3">
                    <Search className="h-4 w-4 shrink-0 opacity-50" />
                    <input
                        autoFocus
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={searchPlaceholder}
                        className="h-10 w-full bg-transparent font-mono text-[12px] outline-none placeholder:text-muted-foreground"
                    />
                </div>
                <div className="max-h-[300px] overflow-y-auto p-1">
                    {filtered.length === 0 ? (
                        <p className="py-4 text-center font-mono text-[12px] text-gray-400">
                            {emptyText}
                        </p>
                    ) : (
                        filtered.map((o) => (
                            <button
                                key={o}
                                type="button"
                                onClick={() => {
                                    onChange(o === value ? '' : o);
                                    setOpen(false);
                                    setSearch('');
                                }}
                                className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left font-mono text-[12px] outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent"
                            >
                                <span className="flex-1 truncate">{o}</span>
                                {o === value && (
                                    <Check className="h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                )}
                            </button>
                        ))
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
