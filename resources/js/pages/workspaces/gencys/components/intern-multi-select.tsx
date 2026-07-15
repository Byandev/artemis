import { Checkbox } from '@/components/ui/checkbox';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Search, SlidersHorizontal, X } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface InternOption {
    id: number;
    full_name: string | null;
}

interface Props {
    interns: InternOption[];
    selected: string[];
    onApply: (ids: string[]) => void;
    label?: string;
}

/**
 * Popover multi-select for interns, styled to match the app's Filters control
 * (sliders trigger + checkbox list + Apply / Clear). Selections are staged
 * locally and only committed on "Apply Changes". Shared across the intern
 * daily-records table and the intern dashboard.
 */
export default function InternMultiSelect({
    interns,
    selected,
    onApply,
    label = 'Interns',
}: Props) {
    const [open, setOpen] = useState(false);
    const [local, setLocal] = useState<string[]>(selected);
    const [term, setTerm] = useState('');

    const visible = useMemo(() => {
        const q = term.trim().toLowerCase();
        return q
            ? interns.filter((i) =>
                  (i.full_name ?? '').toLowerCase().includes(q),
              )
            : interns;
    }, [interns, term]);

    const toggle = (id: string) =>
        setLocal((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );

    const handleOpenChange = (next: boolean) => {
        if (next) setLocal(selected);
        setOpen(next);
    };

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <button
                    className={[
                        'inline-flex h-9 min-w-max shrink-0 items-center overflow-hidden rounded-[10px] border transition-all duration-150',
                        'bg-white dark:bg-zinc-900',
                        'shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] dark:shadow-none',
                        open
                            ? 'border-emerald-500/40 ring-2 ring-emerald-500/10 dark:border-emerald-500/30'
                            : selected.length > 0
                              ? 'border-emerald-500/30 hover:border-emerald-500/50 dark:border-emerald-500/20'
                              : 'border-black/8 hover:border-black/14 dark:border-white/8 dark:hover:border-white/14',
                    ].join(' ')}
                >
                    <span
                        className={[
                            'flex h-full w-9 shrink-0 items-center justify-center rounded-l-[10px] border-r transition-colors duration-150',
                            selected.length > 0
                                ? 'border-emerald-500/20 bg-emerald-500/[0.07] dark:border-emerald-500/15 dark:bg-emerald-500/10'
                                : 'border-black/6 bg-stone-50 dark:border-white/6 dark:bg-white/3',
                        ].join(' ')}
                    >
                        <SlidersHorizontal
                            className={[
                                'h-3.5 w-3.5 transition-colors duration-150',
                                selected.length > 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-gray-400 dark:text-gray-500',
                            ].join(' ')}
                        />
                    </span>
                    <span className="flex items-center gap-2 px-3">
                        <span
                            className={[
                                'text-xs font-medium transition-colors duration-150',
                                selected.length > 0
                                    ? 'text-gray-700 dark:text-gray-200'
                                    : 'text-gray-500 dark:text-gray-400',
                            ].join(' ')}
                        >
                            {label}
                        </span>
                        {selected.length > 0 && (
                            <span className="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-500/[0.10] px-1 text-[10px] font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                {selected.length}
                            </span>
                        )}
                    </span>
                </button>
            </PopoverTrigger>

            <PopoverContent
                className="w-[calc(100vw-2rem)] overflow-hidden rounded-[14px] border border-black/6 bg-white p-0 shadow-[0_8px_30px_rgba(0,0,0,0.08)] sm:w-72 dark:border-white/6 dark:bg-zinc-900 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
                align="start"
            >
                <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <span className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                        {label}
                    </span>
                    <button
                        onClick={() => setLocal([])}
                        className="inline-flex items-center gap-1 text-[11px] font-medium text-gray-400 transition-colors hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                    >
                        <X className="h-3 w-3" />
                        Clear all
                    </button>
                </div>

                <div className="border-b border-black/6 p-2 dark:border-white/6">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Find intern…"
                            className="h-8 w-full rounded-[8px] border border-black/6 bg-stone-50 pr-2.5 pl-8 text-[12px] outline-none focus:border-emerald-500 dark:border-white/6 dark:bg-zinc-800"
                        />
                    </div>
                </div>

                <div className="max-h-64 space-y-0.5 overflow-y-auto p-2">
                    {visible.length === 0 ? (
                        <p className="px-2 py-3 text-center text-[11px] text-gray-400 dark:text-gray-600">
                            No interns
                        </p>
                    ) : (
                        visible.map((intern) => {
                            const id = String(intern.id);
                            const checked = local.includes(id);
                            return (
                                <label
                                    key={id}
                                    className={[
                                        'flex cursor-pointer items-center gap-2.5 rounded-[8px] px-2 py-2 transition-colors',
                                        checked
                                            ? 'bg-emerald-500/[0.06] dark:bg-emerald-500/[0.08]'
                                            : 'hover:bg-black/[0.02] dark:hover:bg-white/[0.03]',
                                    ].join(' ')}
                                >
                                    <Checkbox
                                        checked={checked}
                                        onCheckedChange={() => toggle(id)}
                                        className="border-black/20 data-[state=checked]:border-emerald-600 data-[state=checked]:bg-emerald-600 dark:border-white/20"
                                    />
                                    <span
                                        className={[
                                            'truncate text-[13px] select-none',
                                            checked
                                                ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                                : 'text-gray-600 dark:text-gray-400',
                                        ].join(' ')}
                                    >
                                        {intern.full_name ?? `#${intern.id}`}
                                    </span>
                                </label>
                            );
                        })
                    )}
                </div>

                <div className="flex gap-2 border-t border-black/6 px-4 py-3 dark:border-white/6">
                    <button
                        onClick={() => {
                            onApply(local);
                            setOpen(false);
                        }}
                        className="flex-1 rounded-lg bg-emerald-600 py-2 text-xs font-medium text-white transition-colors hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-400"
                    >
                        Apply Changes
                    </button>
                    <button
                        onClick={() => setOpen(false)}
                        className="rounded-lg border border-black/6 bg-white px-4 py-2 text-xs font-medium text-gray-500 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-400"
                    >
                        Cancel
                    </button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
