import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Check, ChevronsUpDown, Package, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Product } from '../types';

/**
 * Searchable single-select product picker used in the creative form to link a
 * creative to the product it advertises.
 *
 * Uses a plain filterable button list (instead of cmdk) so list items are
 * reliably clickable inside the Popover.
 */
export function ProductPicker({
    products,
    value,
    onChange,
}: {
    products: Product[];
    value: number | null;
    onChange: (id: number | null) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const selected = products.find((p) => p.id === value) ?? null;

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return products;
        return products.filter((p) => p.title.toLowerCase().includes(q));
    }, [products, search]);

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
                    className="flex h-10 w-full items-center gap-2 rounded-[10px] border border-black/8 bg-stone-50 px-3 text-left transition-all outline-none hover:border-black/14 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:hover:border-white/14"
                >
                    <Package className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                    {selected ? (
                        <span className="flex-1 truncate font-mono text-[13px] text-gray-800 dark:text-gray-100">
                            {selected.title}
                        </span>
                    ) : (
                        <span className="flex-1 truncate font-mono text-[13px] text-gray-400 dark:text-gray-500">
                            No product
                        </span>
                    )}
                    {selected ? (
                        <span
                            role="button"
                            tabIndex={-1}
                            onClick={(e) => {
                                e.stopPropagation();
                                onChange(null);
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
                        placeholder="Search products..."
                        className="h-10 w-full bg-transparent font-mono text-[12px] outline-none placeholder:text-muted-foreground"
                    />
                </div>
                <div className="max-h-[300px] overflow-y-auto p-1">
                    {filtered.length === 0 ? (
                        <p className="py-4 text-center font-mono text-[12px] text-gray-400">
                            No products found.
                        </p>
                    ) : (
                        filtered.map((p) => (
                            <button
                                key={p.id}
                                type="button"
                                onClick={() => {
                                    onChange(p.id === value ? null : p.id);
                                    setOpen(false);
                                    setSearch('');
                                }}
                                className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left font-mono text-[12px] outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent"
                            >
                                <Package className="h-3.5 w-3.5 shrink-0 text-indigo-500 dark:text-indigo-400" />
                                <span className="flex-1 truncate">
                                    {p.title}
                                </span>
                                {p.id === value && (
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
