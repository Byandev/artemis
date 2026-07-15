import { Check, ChevronDown, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface StatusOption {
    value: string;
    label: string;
}

interface Props {
    options: StatusOption[];
    selected: string[];
    onChange: (selected: string[]) => void;
    placeholder?: string;
    className?: string;
}

/**
 * A compact multi-select filter matching the finance theme, but with NO search
 * box and explicit Apply / Cancel buttons — selections are staged in a draft and
 * only committed on Apply.
 */
export function StatusFilter({
    options,
    selected,
    onChange,
    placeholder = 'All statuses',
    className = '',
}: Props) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<string[]>(selected);
    const ref = useRef<HTMLDivElement>(null);

    // Keep the draft in sync whenever the committed value or menu visibility changes.
    useEffect(() => {
        if (open) setDraft(selected);
    }, [open, selected]);

    useEffect(() => {
        if (!open) return;
        const handler = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                setOpen(false); // clicking away cancels (draft is discarded)
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [open]);

    const toggle = (value: string) =>
        setDraft((d) =>
            d.includes(value) ? d.filter((v) => v !== value) : [...d, value],
        );

    const apply = () => {
        onChange(draft);
        setOpen(false);
    };

    const cancel = () => {
        setDraft(selected);
        setOpen(false);
    };

    return (
        <div className={`relative ${className}`} ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="flex h-9 w-full items-center justify-between gap-1 rounded-[10px] border border-black/6 bg-stone-100 px-2.5 font-mono! text-[11px]! text-gray-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
            >
                <span className="truncate">
                    {selected.length === 0
                        ? placeholder
                        : `${selected.length} selected`}
                </span>
                <ChevronDown className="h-3 w-3 shrink-0 opacity-50" />
            </button>

            {selected.length > 0 && (
                <button
                    type="button"
                    aria-label="Clear"
                    className="absolute top-1/2 right-7 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    onClick={(e) => {
                        e.stopPropagation();
                        onChange([]);
                    }}
                >
                    <X className="h-3 w-3" />
                </button>
            )}

            {open && (
                <div className="absolute z-50 mt-1 w-full min-w-[190px] overflow-hidden rounded-[10px] border border-black/6 bg-white shadow-lg dark:border-white/6 dark:bg-zinc-800">
                    <div className="max-h-52 overflow-y-auto p-1">
                        {options.map((option) => {
                            const isSelected = draft.includes(option.value);
                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() => toggle(option.value)}
                                    className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left font-mono! text-[11px]! hover:bg-stone-100 dark:hover:bg-zinc-700"
                                >
                                    <span
                                        className={`flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border ${
                                            isSelected
                                                ? 'border-emerald-500 bg-emerald-500 text-white'
                                                : 'border-gray-300 dark:border-gray-600'
                                        }`}
                                    >
                                        {isSelected && (
                                            <Check className="h-2.5 w-2.5" />
                                        )}
                                    </span>
                                    <span className="text-gray-700 dark:text-gray-200">
                                        {option.label}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <div className="flex items-center justify-end gap-1.5 border-t border-black/6 p-1.5 dark:border-white/6">
                        <button
                            type="button"
                            onClick={cancel}
                            className="flex h-7 items-center rounded-md border border-black/8 bg-white px-3 font-mono! text-[11px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={apply}
                            className="flex h-7 items-center rounded-md bg-emerald-600 px-3 font-mono! text-[11px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            Apply
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
