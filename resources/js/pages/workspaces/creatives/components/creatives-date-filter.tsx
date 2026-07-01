import DatePicker from '@/components/ui/date-picker';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { CalendarDays, X } from 'lucide-react';
import { useState } from 'react';
import { DATE_RANGE_FIELDS, DateField, PageProps } from '../types';

type Filter = NonNullable<PageProps['query']['filter']>;

/** Local YYYY-MM-DD (timezone-safe, avoids UTC shifting the day). */
const fmt = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/**
 * A "Dates" popover with three independent date-range pickers — one each for
 * Creative Date, Created Date and Approved Date — styled to match the Filters
 * control. Each range maps to `filter[<key>_from]` / `filter[<key>_to]`.
 */
export default function CreativesDateFilter({
    filter,
    onChange,
}: {
    filter?: Filter;
    onChange: (params: Record<string, string | undefined>) => void;
}) {
    const [open, setOpen] = useState(false);

    const fromKey = (k: DateField) => `${k}_from` as keyof Filter;
    const toKey = (k: DateField) => `${k}_to` as keyof Filter;

    const rangeFor = (k: DateField) => {
        const from = filter?.[fromKey(k)];
        const to = filter?.[toKey(k)];
        return from && to ? [from, to] : undefined;
    };

    const activeCount = DATE_RANGE_FIELDS.filter(
        ({ key }) => filter?.[fromKey(key)] && filter?.[toKey(key)],
    ).length;

    const setRange = (k: DateField, from?: string, to?: string) =>
        onChange({
            [`filter[${k}_from]`]: from,
            [`filter[${k}_to]`]: to,
        });

    const clearAll = () =>
        onChange(
            Object.fromEntries(
                DATE_RANGE_FIELDS.flatMap(({ key }) => [
                    [`filter[${key}_from]`, undefined],
                    [`filter[${key}_to]`, undefined],
                ]),
            ),
        );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    className={[
                        'inline-flex h-9 min-w-max shrink-0 items-center overflow-hidden rounded-[10px] border transition-all duration-150',
                        'bg-white dark:bg-zinc-900',
                        'shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] dark:shadow-none',
                        open
                            ? 'border-emerald-500/40 ring-2 ring-emerald-500/10 dark:border-emerald-500/30'
                            : activeCount > 0
                              ? 'border-emerald-500/30 hover:border-emerald-500/50 dark:border-emerald-500/20 dark:hover:border-emerald-500/30'
                              : 'border-black/8 hover:border-black/14 dark:border-white/8 dark:hover:border-white/14',
                    ].join(' ')}
                >
                    <span
                        className={[
                            'flex h-full w-9 shrink-0 items-center justify-center rounded-l-[10px] border-r transition-colors duration-150',
                            activeCount > 0
                                ? 'border-emerald-500/20 bg-emerald-500/[0.07] dark:border-emerald-500/15 dark:bg-emerald-500/10'
                                : 'border-black/6 bg-stone-50 dark:border-white/6 dark:bg-white/3',
                        ].join(' ')}
                    >
                        <CalendarDays
                            className={[
                                'h-3.5 w-3.5 transition-colors duration-150',
                                activeCount > 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-gray-400 dark:text-gray-500',
                            ].join(' ')}
                        />
                    </span>
                    <span className="flex items-center gap-2 px-3">
                        <span
                            className={[
                                'text-xs font-medium transition-colors duration-150',
                                activeCount > 0
                                    ? 'text-gray-700 dark:text-gray-200'
                                    : 'text-gray-500 dark:text-gray-400',
                            ].join(' ')}
                        >
                            Dates
                        </span>
                        {activeCount > 0 && (
                            <span className="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-500/[0.10] px-1 text-[10px] font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                {activeCount}
                            </span>
                        )}
                    </span>
                </button>
            </PopoverTrigger>

            <PopoverContent
                align="end"
                className="w-[calc(100vw-2rem)] overflow-visible rounded-[14px] border border-black/6 bg-white p-0 shadow-[0_8px_30px_rgba(0,0,0,0.08)] sm:w-80 dark:border-white/6 dark:bg-zinc-900 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
                // Don't auto-focus the first picker on open — focusing its input
                // makes flatpickr auto-open that calendar, so the user's first
                // click would just toggle it shut.
                onOpenAutoFocus={(e) => e.preventDefault()}
                // flatpickr renders its calendar into document.body, so a click
                // inside it counts as "outside" the popover. Keep the popover
                // open while interacting with any open calendar. The actual
                // clicked node is on the wrapped original event, not e.target.
                onInteractOutside={(e) => {
                    const original = (
                        e.detail as { originalEvent?: Event } | undefined
                    )?.originalEvent;
                    const target = (original?.target ?? e.target) as
                        | Element
                        | null
                        | undefined;
                    if (target?.closest?.('.flatpickr-calendar')) {
                        e.preventDefault();
                    }
                }}
            >
                <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <span className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                        Date ranges
                    </span>
                    {activeCount > 0 && (
                        <button
                            onClick={clearAll}
                            className="inline-flex items-center gap-1 text-[11px] font-medium text-gray-400 transition-colors hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                        >
                            <X className="h-3 w-3" />
                            Clear all
                        </button>
                    )}
                </div>

                <div className="space-y-3 p-4">
                    {DATE_RANGE_FIELDS.map(({ key, label }) => {
                        const isSet =
                            !!filter?.[fromKey(key)] && !!filter?.[toKey(key)];
                        return (
                            <div key={key} className="space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <span className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-600">
                                        {label}
                                    </span>
                                    {isSet && (
                                        <button
                                            onClick={() => setRange(key)}
                                            className="text-[10px] font-medium text-gray-400 transition-colors hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                                        >
                                            Clear
                                        </button>
                                    )}
                                </div>
                                <DatePicker
                                    id={`creatives-${key}-range`}
                                    mode="range"
                                    fullWidth
                                    placeholder="All dates"
                                    defaultDate={rangeFor(key) as never}
                                    onChange={(dates) => {
                                        if (dates.length === 2) {
                                            setRange(
                                                key,
                                                fmt(dates[0]),
                                                fmt(dates[1]),
                                            );
                                        } else if (dates.length === 0) {
                                            setRange(key);
                                        }
                                    }}
                                />
                            </div>
                        );
                    })}
                </div>
            </PopoverContent>
        </Popover>
    );
}
