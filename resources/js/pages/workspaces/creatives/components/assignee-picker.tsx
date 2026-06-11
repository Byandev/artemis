import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Check, UserPlus, X } from 'lucide-react';
import { useState } from 'react';
import { Reviewer } from '../types';
import { InitialAvatar } from './atoms';

/**
 * ClickUp-style multi-assignee picker. Shows the currently assigned reviewers as
 * overlapping avatar chips and opens a searchable, toggleable people list.
 */
export function AssigneePicker({
    reviewers,
    selectedIds,
    onChange,
}: {
    reviewers: Reviewer[];
    selectedIds: number[];
    onChange: (ids: number[]) => void;
}) {
    const [open, setOpen] = useState(false);

    const selected = reviewers.filter((r) => selectedIds.includes(r.id));

    const toggle = (id: number) => {
        onChange(selectedIds.includes(id) ? selectedIds.filter((x) => x !== id) : [...selectedIds, id]);
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="flex min-h-10 w-full flex-wrap items-center gap-1.5 rounded-[10px] border border-black/8 bg-stone-50 px-2.5 py-1.5 text-left transition-all outline-none hover:border-black/14 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:hover:border-white/14"
                >
                    {selected.length === 0 ? (
                        <span className="inline-flex items-center gap-1.5 font-mono text-[13px] text-gray-400 dark:text-gray-500">
                            <UserPlus className="h-3.5 w-3.5" /> Assign reviewers
                        </span>
                    ) : (
                        selected.map((r) => (
                            <span
                                key={r.id}
                                className="inline-flex items-center gap-1 rounded-full bg-blue-500/[0.10] py-0.5 pr-1.5 pl-0.5 font-mono text-[11px] font-medium text-blue-700 dark:bg-blue-500/[0.15] dark:text-blue-300"
                            >
                                <InitialAvatar name={r.name} className="h-4 w-4 bg-blue-500/20 text-[8px] text-blue-700 dark:text-blue-300" />
                                {r.name}
                                <span
                                    role="button"
                                    tabIndex={-1}
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        toggle(r.id);
                                    }}
                                    className="ml-0.5 rounded-full p-0.5 text-blue-500/70 transition-colors hover:bg-blue-500/20 hover:text-blue-700 dark:hover:text-blue-200"
                                >
                                    <X className="h-2.5 w-2.5" />
                                </span>
                            </span>
                        ))
                    )}
                </button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-[var(--radix-popover-trigger-width)] p-0">
                <Command>
                    <CommandInput placeholder="Search people..." className="font-mono text-[12px]" />
                    <CommandList>
                        <CommandEmpty className="py-4 text-center font-mono text-[12px] text-gray-400">No people found.</CommandEmpty>
                        <CommandGroup>
                            {reviewers.map((r) => {
                                const isSelected = selectedIds.includes(r.id);
                                return (
                                    <CommandItem
                                        key={r.id}
                                        value={r.name}
                                        onSelect={() => toggle(r.id)}
                                        className="flex items-center gap-2 font-mono text-[12px]"
                                    >
                                        <InitialAvatar name={r.name} className="h-5 w-5 bg-blue-500/[0.12] text-[9px] text-blue-600 dark:text-blue-400" />
                                        <span className="flex-1 truncate">{r.name}</span>
                                        {isSelected && <Check className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
