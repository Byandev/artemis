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

/** The date column selected by default when nothing is filtered yet. */
const DEFAULT_FIELD: DateFieldKey = 'order_date';

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
 * Date filter for the Daily Sales Tracker. A single dropdown picks which date
 * column to filter by (Order Date, Shipped Out Date, …) and one range picker
 * beside it sets the bounds — only one date type filters at a time. Switching
 * the type carries the current range over to the newly-selected column.
 */
export default function DailySalesDateFilters({ value, onChange }: Props) {
    // Start on whichever column already carries a range (e.g. from the URL),
    // otherwise fall back to the default (Order Date).
    const [selectedField, setSelectedField] = useState<DateFieldKey>(
        () =>
            DATE_FIELDS.find((f) => hasRange(value, f.key))?.key ??
            DEFAULT_FIELD,
    );
    // Bumped to remount the picker so flatpickr re-reads defaultDate.
    const [resetKey, setResetKey] = useState(0);

    const start = value[`${selectedField}_start`];
    const end = value[`${selectedField}_end`];
    const hasActiveRange = !!start && !!end;

    // Apply a range to `field` only, clearing every other date column so a
    // single type filters at a time.
    const applyRange = useCallback(
        (field: DateFieldKey, from: string, to: string) => {
            onChange({
                ...EMPTY_DATE_FILTERS,
                [`${field}_start`]: from,
                [`${field}_end`]: to,
            });
        },
        [onChange],
    );

    const handleFieldChange = useCallback(
        (field: string) => {
            const next = field as DateFieldKey;
            setSelectedField(next);
            // Carry any current range over to the newly-selected column.
            if (start && end) applyRange(next, start, end);
            setResetKey((k) => k + 1);
        },
        [start, end, applyRange],
    );

    const handleClear = useCallback(() => {
        applyRange(selectedField, '', '');
        setResetKey((k) => k + 1);
    }, [selectedField, applyRange]);

    return (
        <div className="flex items-center gap-2">
            <Select value={selectedField} onValueChange={handleFieldChange}>
                <SelectTrigger className="h-9 w-[180px] bg-white dark:bg-zinc-900">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {DATE_FIELDS.map((field) => (
                        <SelectItem key={field.key} value={field.key}>
                            {field.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <span className="text-sm font-medium text-gray-400 dark:text-gray-500">
                :
            </span>

            <DatePicker
                id={`gencys-date-${selectedField}-${resetKey}`}
                key={`${selectedField}-${resetKey}`}
                mode="range"
                placeholder="Any date"
                defaultDate={
                    (start && end
                        ? [start, end]
                        : undefined) as never as DateOption
                }
                onChange={(dates) => {
                    if (dates.length === 2) {
                        applyRange(
                            selectedField,
                            moment(dates[0]).format('YYYY-MM-DD'),
                            moment(dates[1]).format('YYYY-MM-DD'),
                        );
                    } else if (dates.length === 0) {
                        applyRange(selectedField, '', '');
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
