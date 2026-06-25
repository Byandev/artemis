import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { INSIGHTS_OPTIONS } from '../../_shared';

/** Searchable, category-grouped metric add menu. */
export function MetricPicker({
    selected,
    onChange,
}: {
    selected: string[];
    onChange: (metrics: string[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const grouped = useMemo(() => {
        const map = new Map<string, typeof INSIGHTS_OPTIONS>();
        INSIGHTS_OPTIONS.filter((o) =>
            o.label.toLowerCase().includes(search.toLowerCase()),
        ).forEach((o) => {
            const cat = o.category ?? 'Other';
            map.set(cat, [...(map.get(cat) ?? []), o]);
        });
        return [...map.entries()];
    }, [search]);

    const toggle = (id: string) =>
        onChange(
            selected.includes(id)
                ? selected.filter((m) => m !== id)
                : [...selected, id],
        );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="inline-flex items-center gap-1 rounded-full border border-dashed border-black/15 px-2.5 py-1 text-xs font-medium text-gray-500 hover:border-emerald-400 hover:text-emerald-600 dark:border-white/15 dark:text-gray-400"
                >
                    <Plus className="h-3 w-3" />
                    Add metric
                </button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-64 p-0">
                <div className="border-b border-black/6 p-2 dark:border-white/6">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search metrics..."
                            className="h-8 pl-7 text-xs"
                        />
                    </div>
                </div>
                <div className="max-h-72 overflow-auto p-1">
                    {grouped.map(([cat, opts]) => (
                        <div key={cat} className="mb-1">
                            <p className="px-2 py-1 text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                                {cat}
                            </p>
                            {opts.map((o) => (
                                <label
                                    key={o.id}
                                    className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs hover:bg-gray-50 dark:hover:bg-zinc-800"
                                >
                                    <Checkbox
                                        checked={selected.includes(o.id)}
                                        onCheckedChange={() => toggle(o.id)}
                                    />
                                    <span className="text-gray-700 dark:text-gray-200">
                                        {o.label}
                                    </span>
                                </label>
                            ))}
                        </div>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}
