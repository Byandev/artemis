import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Check, ChevronDown } from 'lucide-react';
import { useState } from 'react';

export interface DropdownSelectOption {
    key: string;
    label: string;
}

interface Props {
    value: string;
    onChange: (value: string) => void;
    options: DropdownSelectOption[];
    label?: string;
    placeholder?: string;
    align?: 'start' | 'center' | 'end';
    width?: string;
}

export default function DropdownSelect({
    value,
    onChange,
    options,
    label = 'Select',
    placeholder = 'Select option',
    align = 'end',
    width = 'w-56',
}: Props) {
    const [open, setOpen] = useState(false);

    const selected = options.find((o) => o.key === value);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    className={`flex h-8 items-center overflow-hidden rounded-[10px] border transition-all ${
                        open
                            ? 'border-emerald-500 ring-2 ring-emerald-500/15'
                            : 'border-black/6 dark:border-white/6 hover:border-black/12 dark:hover:border-white/12'
                    } bg-stone-100 dark:bg-zinc-800`}
                >
                    <span className="flex h-full items-center justify-center border-r border-black/6 dark:border-white/6 px-2.5 text-gray-400 dark:text-gray-500">
                        <ChevronDown
                            className={`h-3.5 w-3.5 transition-transform ${open ? 'rotate-180 text-emerald-500' : ''}`}
                        />
                    </span>
                    <span className="px-3 text-[12px] font-semibold tracking-tight text-gray-700 dark:text-gray-200">
                        {selected?.label ?? placeholder}
                    </span>
                </button>
            </PopoverTrigger>
            <PopoverContent
                align={align}
                className={`${width} overflow-hidden rounded-xl border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900 p-1 shadow-lg dark:shadow-black/30`}
            >
                <p className="px-3 pb-1.5 pt-2 font-mono text-[10px] font-medium uppercase tracking-widest text-gray-400 dark:text-gray-500">
                    {label}
                </p>
                <div className="max-h-72 overflow-y-auto">
                {options.map((option) => (
                    <button
                        key={option.key}
                        className={`flex w-full items-center justify-between rounded-lg px-3 py-1.5 text-[12px]! font-medium tracking-tight transition-colors ${
                            value === option.key
                                ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-stone-50 dark:hover:bg-zinc-800'
                        }`}
                        onClick={() => {
                            onChange(option.key);
                            setOpen(false);
                        }}
                    >
                        {option.label}
                        {value === option.key && <Check className="h-3 w-3 text-emerald-500" />}
                    </button>
                ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}
