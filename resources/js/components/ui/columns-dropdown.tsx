import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { VisibilityState } from '@tanstack/react-table';
import { Columns3, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * One toggleable column. `id` must match the table column's id, which for a
 * sortable column is also the sort key the server receives.
 *
 * `required` columns cannot be hidden. `defaultVisible: false` starts a column
 * off — for tables where the extra columns are opt-in rather than the norm.
 */
export interface ColumnOption {
    id: string;
    label: string;
    required?: boolean;
    defaultVisible?: boolean;
    /** Groups the options under a heading in the menu. */
    group?: string;
}

/** The visibility a table starts with, before anything is remembered. */
export function defaultVisibility(options: ColumnOption[]): VisibilityState {
    return Object.fromEntries(
        options.map((o) => [o.id, o.defaultVisible !== false]),
    );
}

/**
 * Column visibility, remembered per browser under `storageKey`.
 *
 * Unknown or stale ids in storage are dropped, so removing a column from the
 * options here cannot resurrect it from a payload saved months ago.
 */
export function useColumnVisibility(
    options: ColumnOption[],
    storageKey: string,
) {
    const fallback = defaultVisibility(options);

    const [visibility, setVisibility] = useState<VisibilityState>(() => {
        try {
            const raw = window.localStorage.getItem(storageKey);
            if (!raw) return fallback;
            const stored = JSON.parse(raw) as VisibilityState;
            return {
                ...fallback,
                ...Object.fromEntries(
                    Object.entries(stored).filter(([id]) => id in fallback),
                ),
            };
        } catch {
            return fallback;
        }
    });

    useEffect(() => {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(visibility));
        } catch {
            /* storage unavailable (private mode, quota) — keep in-memory only */
        }
    }, [visibility, storageKey]);

    return { visibility, setVisibility };
}

// Radix's CheckboxItem only renders its indicator (a bare check) when checked,
// leaving unchecked rows visually empty. Style the indicator slot into a real
// box that's visible either way — keeping the primitive's menuitemcheckbox role
// and aria-checked rather than hand-rolling a control.
const checkboxItem = [
    'font-mono text-[12px] py-1.5 cursor-pointer',
    // the indicator slot → the box
    '[&>span:first-child]:size-4 [&>span:first-child]:rounded-[4px]',
    '[&>span:first-child]:border [&>span:first-child]:transition-colors',
    '[&>span:first-child]:border-black/20 dark:[&>span:first-child]:border-white/25',
    // the check itself
    '[&_svg]:size-3 [&_svg]:text-white [&_svg]:stroke-[3]',
].join(' ');

const checkboxItemChecked =
    '[&>span:first-child]:border-emerald-600 [&>span:first-child]:bg-emerald-600 dark:[&>span:first-child]:border-emerald-500 dark:[&>span:first-child]:bg-emerald-500';

export function ColumnsDropdown({
    options,
    visibility,
    onChange,
    contentClassName,
}: {
    options: ColumnOption[];
    visibility: VisibilityState;
    onChange: (next: VisibilityState) => void;
    /**
     * Extra classes for the menu panel. For a menu long enough to run off the
     * screen, this is where a tighter height cap goes — the default 70vh suits
     * the shorter menus, so caller-specific limits stay with the caller.
     */
    contentClassName?: string;
}) {
    const fallback = defaultVisibility(options);
    const shownCount = options.filter((o) => visibility[o.id] !== false).length;
    const isDefault = options.every(
        (o) => (visibility[o.id] !== false) === (fallback[o.id] !== false),
    );

    // Preserve the order the options were declared in; an undeclared group
    // heading is simply absent rather than rendering as a blank label.
    const groups = options.reduce<Record<string, ColumnOption[]>>(
        (acc, option) => {
            const key = option.group ?? '';
            (acc[key] ??= []).push(option);
            return acc;
        },
        {},
    );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button className="flex h-9 items-center gap-1.5 rounded-[10px] border border-black/10 bg-white px-3 font-mono! text-[12px]! text-gray-700 transition-colors hover:bg-stone-50 data-[state=open]:border-emerald-500 data-[state=open]:ring-2 data-[state=open]:ring-emerald-500/15 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-zinc-800">
                    <Columns3 className="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
                    Columns
                    {!isDefault && (
                        <span className="ml-0.5 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                            {shownCount}/{options.length}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className={cn(
                    'max-h-[70vh] w-60 overflow-y-auto p-1',
                    contentClassName,
                )}
            >
                {Object.entries(groups).map(([heading, groupOptions], index) => (
                    <div key={heading || `group-${index}`}>
                        {(heading || index === 0) && (
                            <>
                                {index > 0 && <DropdownMenuSeparator />}
                                <DropdownMenuLabel className="px-2 py-1.5 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    {heading || 'Toggle columns'}
                                </DropdownMenuLabel>
                                {index === 0 && !heading && (
                                    <DropdownMenuSeparator />
                                )}
                            </>
                        )}
                        {groupOptions.map((o) => {
                            const checked = visibility[o.id] !== false;
                            return (
                                <DropdownMenuCheckboxItem
                                    key={o.id}
                                    className={cn(
                                        checkboxItem,
                                        checked && checkboxItemChecked,
                                    )}
                                    checked={checked}
                                    disabled={o.required}
                                    // Keep the menu open so several columns can
                                    // be toggled in one go.
                                    onSelect={(e) => e.preventDefault()}
                                    onCheckedChange={(next) =>
                                        onChange({
                                            ...visibility,
                                            [o.id]: next,
                                        })
                                    }
                                >
                                    {o.label}
                                    {o.required && (
                                        // Disabled items are pointer-events-none,
                                        // so a title tooltip would never fire —
                                        // say it inline.
                                        <span className="ml-auto pl-2 text-[10px] text-gray-400 dark:text-gray-500">
                                            Always
                                        </span>
                                    )}
                                </DropdownMenuCheckboxItem>
                            );
                        })}
                    </div>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    className="cursor-pointer justify-center font-mono text-[12px] text-gray-500 focus:text-gray-700 dark:text-gray-400 dark:focus:text-gray-200"
                    disabled={isDefault}
                    onSelect={() => onChange(fallback)}
                >
                    <RotateCcw className="mr-1.5 h-3 w-3" />
                    Reset to default
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
