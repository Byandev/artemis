import DatePicker from '@/components/ui/date-picker';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import flatpickr from 'flatpickr';
import { CalendarRange, X } from 'lucide-react';
import moment from 'moment';
import { useCallback, useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

/**
 * The date columns that can be range-filtered. Each maps to a pair of backend
 * filter keys (`{key}_start` / `{key}_end`) — the same scheme the controller's
 * DATE_RANGES expects.
 */
export const DATE_FIELDS = [
    { key: 'order_date', label: 'Order Date' },
    { key: 'shipped_out_date', label: 'Shipped Out Date' },
    { key: 'parcel_updated_date', label: 'Parcel Updated Date' },
    { key: 'encoded_date', label: 'Encoded Date' },
    { key: 'date_added', label: 'Date Added' },
] as const;

type DateFieldKey = (typeof DATE_FIELDS)[number]['key'];

/** Flat map of `{key}_start` / `{key}_end` → 'YYYY-MM-DD' (empty when unset). */
export type DateFilterValue = Record<string, string>;

export const EMPTY_DATE_FILTERS: DateFilterValue = Object.fromEntries(
    DATE_FIELDS.flatMap((f) => [
        [`${f.key}_start`, ''],
        [`${f.key}_end`, ''],
    ]),
);

const hasRange = (value: DateFilterValue, key: DateFieldKey) =>
    !!value[`${key}_start`] && !!value[`${key}_end`];

interface Props {
    value: DateFilterValue;
    onChange: (value: DateFilterValue) => void;
}

/**
 * Grouped date-range popover for the Daily Sales Tracker. Each date column gets
 * its own labelled range picker, styled to match the sibling Filters control.
 * Selections apply immediately (the parent debounces the refetch).
 */
export default function DailySalesDateFilters({ value, onChange }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    // Bumped on "Clear all" to remount the pickers with empty defaults, since
    // flatpickr only reads defaultDate on mount.
    const [resetKey, setResetKey] = useState(0);

    const activeCount = DATE_FIELDS.filter((f) =>
        hasRange(value, f.key),
    ).length;

    const setRange = useCallback(
        (key: DateFieldKey, start: string, end: string) => {
            onChange({
                ...value,
                [`${key}_start`]: start,
                [`${key}_end`]: end,
            });
        },
        [value, onChange],
    );

    const handleClearAll = useCallback(() => {
        onChange({ ...EMPTY_DATE_FILTERS });
        setResetKey((k) => k + 1);
    }, [onChange]);

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <button
                    className={[
                        'inline-flex h-9 min-w-max shrink-0 items-center overflow-hidden rounded-[10px] border transition-all duration-150',
                        'bg-white dark:bg-zinc-900',
                        'shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] dark:shadow-none',
                        isOpen
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
                        <CalendarRange
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
                className="w-[calc(100vw-2rem)] overflow-hidden rounded-[14px] border border-black/6 bg-white p-0 shadow-[0_8px_30px_rgba(0,0,0,0.08)] sm:w-80 dark:border-white/6 dark:bg-zinc-900 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
                align="end"
                // The flatpickr calendar renders in a body-level portal, so a
                // click inside it counts as "outside" the popover. Keep the
                // popover open when the interaction lands on the calendar.
                onInteractOutside={(e) => {
                    const target = e.detail.originalEvent
                        .target as HTMLElement | null;
                    if (target?.closest('.flatpickr-calendar')) {
                        e.preventDefault();
                    }
                }}
            >
                <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <span className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                        Date ranges
                    </span>
                    <button
                        onClick={handleClearAll}
                        disabled={activeCount === 0}
                        className="inline-flex items-center gap-1 text-[11px] font-medium text-gray-400 transition-colors hover:text-red-500 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-500 dark:hover:text-red-400"
                    >
                        <X className="h-3 w-3" />
                        Clear all
                    </button>
                </div>

                <div className="max-h-[60vh] space-y-3 overflow-y-auto p-4">
                    {DATE_FIELDS.map((field) => {
                        const start = value[`${field.key}_start`];
                        const end = value[`${field.key}_end`];
                        return (
                            <div key={field.key} className="space-y-1.5">
                                <label className="font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                                    {field.label}
                                </label>
                                <DatePicker
                                    id={`gencys-date-${field.key}-${resetKey}`}
                                    key={`${field.key}-${resetKey}`}
                                    mode="range"
                                    fullWidth
                                    compact
                                    placeholder="Any date"
                                    defaultDate={
                                        (start && end
                                            ? [start, end]
                                            : undefined) as never as DateOption
                                    }
                                    onChange={(dates) => {
                                        if (dates.length === 2) {
                                            setRange(
                                                field.key,
                                                moment(dates[0]).format(
                                                    'YYYY-MM-DD',
                                                ),
                                                moment(dates[1]).format(
                                                    'YYYY-MM-DD',
                                                ),
                                            );
                                        } else if (dates.length === 0) {
                                            setRange(field.key, '', '');
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
