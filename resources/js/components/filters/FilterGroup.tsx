import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';

type IdLike = string | number;

type FilterGroupProps<T> = {
    getId: (item: T) => IdLike;
    getLabel: (item: T) => string;
    selected: IdLike[];
    onSelect: (id: IdLike) => void;
    options: T[];
    name: string;
};

export function FilterGroup<T>({
    name,
    options,
    getId,
    getLabel,
    selected,
    onSelect,
}: FilterGroupProps<T>) {
    const [open, setOpen] = useState(false);

    const activeCount = selected.length;

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger className={[
                'flex w-full items-center justify-between px-2 py-2 rounded-[8px] transition-colors',
                'hover:bg-black/[0.02] dark:hover:bg-white/[0.03]',
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
                <div>
                    {options.map((item) => {
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
