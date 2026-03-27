import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { ChevronDown, ChevronUp, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

type IdLike = string | number;

type FilterGroupProps<T> = {
    getId: (item: T) => IdLike;
    getLabel: (item: T) => string;
    selected: IdLike[];
    onSelect: (id: IdLike) => void;
    options: T[];
    name: string;
    searchable?: boolean;
};

export function FilterGroup<T>({
    name,
    options,
    getId,
    getLabel,
    selected,
    onSelect,
    searchable = options.length > 5,
}: FilterGroupProps<T>) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const activeCount = selected.length;

    const filtered = useMemo(() => {
        if (!search.trim()) return options;
        const q = search.toLowerCase();
        return options.filter((item) => getLabel(item).toLowerCase().includes(q));
    }, [options, search, getLabel]);

    return (
        <Collapsible open={open} onOpenChange={(v) => { setOpen(v); if (!v) setSearch(''); }}>
            <CollapsibleTrigger className={[
                'flex w-full items-center justify-between px-2 py-2 rounded-lg transition-colors',
                'hover:bg-black/2 dark:hover:bg-white/3',
            ].join(' ')}>
                <span className="flex items-center gap-2">
                    <span className="text-[10px] font-mono font-medium uppercase tracking-wider text-gray-300 dark:text-gray-600">
                        {name}
                    </span>
                    {activeCount > 0 && (
                        <span className="inline-flex items-center justify-center h-4 min-w-4 px-1 rounded-full bg-emerald-500/[0.10] text-emerald-600 dark:text-emerald-400 text-[10px] font-semibold tabular-nums">
                            {activeCount}
                        </span>
                    )}
                </span>
                {open
                    ? <ChevronUp className="h-3.5 w-3.5 text-gray-300 dark:text-gray-600" />
                    : <ChevronDown className="h-3.5 w-3.5 text-gray-300 dark:text-gray-600" />
                }
            </CollapsibleTrigger>

            <CollapsibleContent className="mt-0.5">
                {searchable && (
                    <div className="relative mx-2 mb-1">
                        <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3 w-3 -translate-y-1/2 text-gray-300 dark:text-gray-600" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={`Search ${name.toLowerCase()}…`}
                            className="w-full rounded-[7px] border border-black/8 bg-stone-50 py-1.5 pl-7 pr-2.5 text-[12px]! text-gray-700 placeholder-gray-300 outline-none transition-colors focus:border-black/20 dark:border-white/8 dark:bg-white/3 dark:text-gray-300 dark:placeholder-gray-600 dark:focus:border-white/20"
                        />
                    </div>
                )}
                <div className="max-h-44 overflow-y-auto">
                    {filtered.length === 0 ? (
                        <p className="px-2 py-3 text-center text-[11px] text-gray-400 dark:text-gray-600">
                            No results for "{search}"
                        </p>
                    ) : filtered.map((item) => {
                        const id = getId(item);
                        const idStr = String(id);
                        const checked = selected.includes(idStr);

                        return (
                            <label
                                key={idStr}
                                htmlFor={idStr}
                                className={[
                                    'flex items-center gap-2.5 px-2 py-2 rounded-[8px] cursor-pointer transition-colors',
                                    checked
                                        ? 'bg-emerald-500/[0.06] dark:bg-emerald-500/[0.08]'
                                        : 'hover:bg-black/[0.02] dark:hover:bg-white/[0.03]',
                                ].join(' ')}
                            >
                                <Checkbox
                                    id={idStr}
                                    name={idStr}
                                    checked={checked}
                                    onCheckedChange={() => onSelect(idStr)}
                                    className="border-black/20 dark:border-white/20 data-[state=checked]:bg-emerald-600 data-[state=checked]:border-emerald-600 dark:data-[state=checked]:bg-emerald-500 dark:data-[state=checked]:border-emerald-500"
                                />
                                <span className={[
                                    'text-[13px] truncate select-none',
                                    checked
                                        ? 'text-emerald-700 dark:text-emerald-400 font-medium'
                                        : 'text-gray-600 dark:text-gray-400',
                                ].join(' ')}>
                                    {getLabel(item)}
                                </span>
                            </label>
                        );
                    })}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
