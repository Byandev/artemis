import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Check, ChevronsUpDown, Package, X } from 'lucide-react';
import { useState } from 'react';
import { Product } from '../types';

/**
 * Searchable single-select product picker used in the creative form to link a
 * creative to the product it advertises.
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
    const selected = products.find((p) => p.id === value) ?? null;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="flex h-10 w-full items-center gap-2 rounded-[10px] border border-black/8 bg-stone-50 px-3 text-left transition-all outline-none hover:border-black/14 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:hover:border-white/14"
                >
                    <Package className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                    {selected ? (
                        <span className="flex-1 truncate font-mono text-[13px] text-gray-800 dark:text-gray-100">{selected.title}</span>
                    ) : (
                        <span className="flex-1 truncate font-mono text-[13px] text-gray-400 dark:text-gray-500">No product</span>
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
            <PopoverContent align="start" className="w-[var(--radix-popover-trigger-width)] p-0">
                <Command>
                    <CommandInput placeholder="Search products..." className="font-mono text-[12px]" />
                    <CommandList>
                        <CommandEmpty className="py-4 text-center font-mono text-[12px] text-gray-400">No products found.</CommandEmpty>
                        <CommandGroup>
                            {products.map((p) => (
                                <CommandItem
                                    key={p.id}
                                    value={p.title}
                                    onSelect={() => {
                                        onChange(p.id === value ? null : p.id);
                                        setOpen(false);
                                    }}
                                    className="flex items-center gap-2 font-mono text-[12px]"
                                >
                                    <Package className="h-3.5 w-3.5 text-indigo-500 dark:text-indigo-400" />
                                    <span className="flex-1 truncate">{p.title}</span>
                                    {p.id === value && <Check className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
