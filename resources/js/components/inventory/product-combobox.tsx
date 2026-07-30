import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ChevronsUpDown, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface ProductOption {
    id: number;
    name: string;
}

interface Props {
    products: ProductOption[];
    value: number | null;
    onChange: (id: number | null) => void;
    /** Trigger label shown when nothing is selected. */
    placeholder?: string;
    /** Override the trigger button classes (e.g. to size it in a table cell). */
    triggerClassName?: string;
    align?: 'start' | 'center' | 'end';
}

/**
 * A searchable product picker (Popover + search + filtered list), matching the
 * inventory-items "Set Product" control. Emits the product id, or null when
 * "No product" is chosen.
 */
export function ProductCombobox({
    products,
    value,
    onChange,
    placeholder = 'Select product…',
    triggerClassName,
    align = 'start',
}: Props) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const selected = products.find((p) => p.id === value) ?? null;

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        return q
            ? products.filter((p) => p.name.toLowerCase().includes(q))
            : products;
    }, [products, search]);

    const pick = (id: number | null) => {
        onChange(id);
        setOpen(false);
        setSearch('');
    };

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
                    onClick={(e) => e.stopPropagation()}
                    className={
                        triggerClassName ??
                        'flex h-8 min-w-[180px] items-center gap-1.5 rounded-md border border-black/8 bg-white px-2 font-mono! text-[12px]! text-gray-700 outline-none transition-colors hover:border-black/14 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200 dark:hover:border-white/14'
                    }
                >
                    <span
                        className={`flex-1 truncate text-left ${selected ? '' : 'text-gray-400 dark:text-gray-500'}`}
                    >
                        {selected ? selected.name : placeholder}
                    </span>
                    <ChevronsUpDown className="h-3.5 w-3.5 shrink-0 opacity-50" />
                </button>
            </PopoverTrigger>
            <PopoverContent
                align={align}
                className="w-64 p-0"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center gap-2 border-b border-black/6 px-3 dark:border-white/6">
                    <Search className="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-gray-500" />
                    <input
                        autoFocus
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search products…"
                        className="h-9 w-full bg-transparent font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 dark:text-gray-100 dark:placeholder:text-gray-600"
                    />
                </div>
                <div className="max-h-64 overflow-y-auto p-1">
                    <button
                        type="button"
                        onClick={() => pick(null)}
                        className="flex w-full items-center rounded-md px-2 py-1.5 text-left font-mono! text-[12px]! text-gray-500 transition-colors hover:bg-stone-100 dark:text-gray-400 dark:hover:bg-zinc-800"
                    >
                        No product (clear)
                    </button>
                    {filtered.length === 0 ? (
                        <p className="px-2 py-3 text-center font-mono text-[11px] text-gray-400 dark:text-gray-600">
                            No products found.
                        </p>
                    ) : (
                        filtered.map((p) => (
                            <button
                                type="button"
                                key={p.id}
                                onClick={() => pick(p.id)}
                                className={`flex w-full items-center rounded-md px-2 py-1.5 text-left font-mono! text-[12px]! transition-colors hover:bg-stone-100 dark:hover:bg-zinc-800 ${
                                    p.id === value
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-gray-700 dark:text-gray-200'
                                }`}
                            >
                                {p.name}
                            </button>
                        ))
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
