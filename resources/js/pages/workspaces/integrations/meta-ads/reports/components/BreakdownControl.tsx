import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ChevronDown, Search } from 'lucide-react';
import { useState } from 'react';
import {
    BREAKDOWN_OPTIONS,
    breakdownLabel,
    type BreakdownValue,
    type CustomBreakdownItem,
} from '../types';

/**
 * SuperAds-style "Group by" picker: a searchable, categorized popover listing
 * the fixed dimensions plus the workspace's saved custom breakdowns.
 */
export function BreakdownControl({
    value,
    customBreakdowns,
    onChange,
}: {
    value: BreakdownValue;
    customBreakdowns: CustomBreakdownItem[];
    onChange: (key: BreakdownValue) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const q = search.toLowerCase();

    const options: { key: BreakdownValue; label: string; category: string }[] =
        [
            ...BREAKDOWN_OPTIONS,
            ...customBreakdowns.map((b) => ({
                key: `custom:${b.id}` as BreakdownValue,
                label: b.name,
                category: 'Custom breakdowns',
            })),
        ];
    const matches = options.filter((o) => o.label.toLowerCase().includes(q));
    const categories = [...new Set(matches.map((o) => o.category))];

    return (
        <div className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
            <span>Group by</span>
            <Popover
                open={open}
                onOpenChange={(o) => {
                    setOpen(o);
                    if (o) setSearch('');
                }}
            >
                <PopoverTrigger asChild>
                    <button
                        type="button"
                        className="inline-flex h-8 items-center gap-1.5 rounded-md border border-black/10 bg-white px-2.5 text-xs font-medium text-gray-700 hover:border-emerald-400 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200"
                    >
                        {breakdownLabel(value, customBreakdowns)}
                        <ChevronDown className="h-3 w-3 opacity-50" />
                    </button>
                </PopoverTrigger>
                <PopoverContent align="start" className="w-64 p-0">
                    <div className="border-b border-black/6 p-2 dark:border-white/6">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search breakdowns..."
                                className="h-8 pl-7 text-xs"
                            />
                        </div>
                    </div>
                    <div className="max-h-72 overflow-auto p-1">
                        {categories.length === 0 && (
                            <p className="px-2 py-4 text-center text-xs text-gray-400">
                                No breakdowns match.
                            </p>
                        )}
                        {categories.map((cat) => (
                            <div key={cat} className="mb-1">
                                <p className="px-2 py-1 text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                                    {cat}
                                </p>
                                {matches
                                    .filter((o) => o.category === cat)
                                    .map((o) => (
                                        <button
                                            key={o.key}
                                            type="button"
                                            onClick={() => {
                                                onChange(o.key);
                                                setOpen(false);
                                            }}
                                            className={`flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-xs hover:bg-gray-50 dark:hover:bg-zinc-800 ${
                                                o.key === value
                                                    ? 'font-medium text-emerald-600 dark:text-emerald-400'
                                                    : 'text-gray-700 dark:text-gray-200'
                                            }`}
                                        >
                                            {o.label}
                                            {o.key === value && (
                                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                            )}
                                        </button>
                                    ))}
                            </div>
                        ))}
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    );
}
