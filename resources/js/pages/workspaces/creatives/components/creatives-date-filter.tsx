import DatePicker from '@/components/ui/date-picker';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import flatpickr from 'flatpickr';
import { X } from 'lucide-react';
import { useCallback, useState } from 'react';
import { DATE_RANGE_FIELDS, DateField, PageProps } from '../types';
import DateOption = flatpickr.Options.DateOption;

type Filter = NonNullable<PageProps['query']['filter']>;

/** Local YYYY-MM-DD (timezone-safe, avoids UTC shifting the day). */
const fmt = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/** The date column selected by default when nothing is filtered yet. */
const DEFAULT_FIELD: DateField = 'creative_date';

/**
 * Date filter for the Creative Tracker list. A single dropdown picks which date
 * column to filter by (Creative Date, Created Date, Approved Date) and one range
 * picker beside it sets the bounds — only one date type filters at a time.
 * Switching the type carries the current range over to the newly-selected
 * column. Each range maps to `filter[<key>_from]` / `filter[<key>_to]`.
 */
export default function CreativesDateFilter({
    filter,
    onChange,
}: {
    filter?: Filter;
    onChange: (params: Record<string, string | undefined>) => void;
}) {
    const fromKey = (k: DateField) => `${k}_from` as keyof Filter;
    const toKey = (k: DateField) => `${k}_to` as keyof Filter;
    const hasRange = (k: DateField) =>
        !!filter?.[fromKey(k)] && !!filter?.[toKey(k)];

    // Start on whichever column already carries a range (e.g. from the URL),
    // otherwise fall back to the default (Creative Date).
    const [selectedField, setSelectedField] = useState<DateField>(
        () =>
            DATE_RANGE_FIELDS.find((f) => hasRange(f.key))?.key ??
            DEFAULT_FIELD,
    );
    const start = filter?.[fromKey(selectedField)];
    const end = filter?.[toKey(selectedField)];
    const hasActiveRange = !!start && !!end;

    // Apply a range to `field` only, clearing every other date column so a
    // single type filters at a time.
    const applyRange = useCallback(
        (field: DateField, from?: string, to?: string) => {
            const params: Record<string, string | undefined> = {};
            for (const { key } of DATE_RANGE_FIELDS) {
                params[`filter[${key}_from]`] =
                    key === field ? from : undefined;
                params[`filter[${key}_to]`] = key === field ? to : undefined;
            }
            onChange(params);
        },
        [onChange],
    );

    const handleFieldChange = useCallback(
        (field: string) => {
            const next = field as DateField;
            setSelectedField(next);
            // Carry any current range over to the newly-selected column.
            if (start && end) applyRange(next, start, end);
        },
        [start, end, applyRange],
    );

    const handleClear = useCallback(() => {
        applyRange(selectedField);
    }, [selectedField, applyRange]);

    return (
        <div className="flex items-center gap-2">
            <Select value={selectedField} onValueChange={handleFieldChange}>
                <SelectTrigger className="h-9 w-[170px] rounded-[10px] border-black/8 bg-white shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] hover:border-black/14 dark:border-white/8 dark:bg-zinc-900 dark:shadow-none dark:hover:border-white/14">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {DATE_RANGE_FIELDS.map(({ key, label }) => (
                        <SelectItem key={key} value={key}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <span className="text-[13px] font-medium text-gray-400 dark:text-gray-500">
                :
            </span>

            <DatePicker
                // Key on the resolved range so the picker remounts (and re-seeds
                // its display) whenever the range changes — including when it's
                // cleared, once the navigation drops the filter params.
                id={`creatives-date-${selectedField}`}
                key={`${selectedField}:${start ?? ''}:${end ?? ''}`}
                mode="range"
                placeholder="All dates"
                defaultDate={
                    (start && end
                        ? [start, end]
                        : undefined) as never as DateOption
                }
                onChange={(dates) => {
                    if (dates.length === 2) {
                        applyRange(selectedField, fmt(dates[0]), fmt(dates[1]));
                    } else if (dates.length === 0) {
                        applyRange(selectedField);
                    }
                }}
            />

            {hasActiveRange && (
                <button
                    onClick={handleClear}
                    aria-label="Clear date filter"
                    className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] border border-black/8 bg-white text-gray-400 shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] transition-colors hover:border-black/14 hover:text-red-500 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-500 dark:shadow-none dark:hover:border-white/14 dark:hover:text-red-400"
                >
                    <X className="h-3.5 w-3.5" />
                </button>
            )}
        </div>
    );
}
