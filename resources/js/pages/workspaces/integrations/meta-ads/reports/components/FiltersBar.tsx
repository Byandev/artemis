import DatePicker from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Plus, Search, TriangleAlert, X } from 'lucide-react';
import moment from 'moment';
import { useRef, useState } from 'react';
import { INSIGHTS_OPTIONS, metricLabel } from '../../_shared';
import {
    DATE_FIELD_LABELS,
    DATE_OP_LABELS,
    dateFilterSupported,
    isDateFilter,
    isNameFilter,
    METRIC_OP_LABELS,
    NAME_OP_LABELS,
    type BreakdownValue,
    type DateFilter,
    type DateFilterField,
    type DateFilterOp,
    type MetricFilter,
    type MetricFilterOp,
    type NameFilter,
    type NameFilterOp,
    type ReportFilter,
} from '../types';

/**
 * SuperAds-style filter bar: one optional Name filter plus any number of numeric
 * metric filters. Name maps to the engine's search/name_op; metric filters map
 * to the `metric_filters` HAVING params.
 */
export function FiltersBar({
    filters,
    onChange,
    groupBy,
    dateRange,
}: {
    filters: ReportFilter[];
    onChange: (filters: ReportFilter[]) => void;
    /** The report's breakdown — decides which date filters can be answered. */
    groupBy: BreakdownValue;
    /** The report's reporting window, used to seed a new date filter. */
    dateRange: { since: string; until: string };
}) {
    const hasName = filters.some(isNameFilter);
    const usedDateFields = filters.filter(isDateFilter).map((f) => f.field);

    const update = (index: number, next: ReportFilter) =>
        onChange(filters.map((f, i) => (i === index ? next : f)));
    const remove = (index: number) =>
        onChange(filters.filter((_, i) => i !== index));

    const addName = () =>
        onChange([...filters, { field: 'name', op: 'contains', value: '' }]);
    const addMetric = (field: string) =>
        onChange([...filters, { field, op: 'gte', value: 0 }]);
    // Seeded to the report's own window rather than today — a fresh "is on today"
    // filter matches almost nothing, so adding one would blank the report and
    // look broken.
    const addDate = (field: DateFilterField) =>
        onChange([
            ...filters,
            {
                field,
                op: 'between',
                value: dateRange.since,
                value2: dateRange.until,
            },
        ]);

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            {filters.map((f, i) =>
                isNameFilter(f) ? (
                    <NameFilterChip
                        key={`name-${i}`}
                        filter={f}
                        onChange={(next) => update(i, next)}
                        onRemove={() => remove(i)}
                    />
                ) : isDateFilter(f) ? (
                    <DateFilterChip
                        key={`date-${i}-${f.field}`}
                        filter={f}
                        supported={dateFilterSupported(f.field, groupBy)}
                        onChange={(next) => update(i, next)}
                        onRemove={() => remove(i)}
                    />
                ) : (
                    <MetricFilterChip
                        key={`metric-${i}-${f.field}`}
                        filter={f}
                        onChange={(next) => update(i, next)}
                        onRemove={() => remove(i)}
                    />
                ),
            )}

            <AddFilterButton
                hasName={hasName}
                usedDateFields={usedDateFields}
                groupBy={groupBy}
                onAddName={addName}
                onAddMetric={addMetric}
                onAddDate={addDate}
            />
        </div>
    );
}

/**
 * Created/Started date chip. The picker only exists once the filter has been
 * added from the "Add filter" menu, and the second picker only appears for the
 * "is between" operator.
 */
function DateFilterChip({
    filter,
    supported,
    onChange,
    onRemove,
}: {
    filter: DateFilter;
    supported: boolean;
    onChange: (f: DateFilter) => void;
    onRemove: () => void;
}) {
    // DatePicker only rebuilds its flatpickr instance when `defaultDate` changes,
    // so its onChange can hold a closure captured before an operator switch (which
    // leaves `value` untouched). Spreading that stale filter would silently revert
    // the operator — merge against a ref of the current one instead.
    const current = useRef(filter);
    current.current = filter;

    const patch = (next: Partial<DateFilter>) =>
        onChange({ ...current.current, ...next } as DateFilter);

    return (
        <div className="flex items-center gap-1.5 rounded-md bg-gray-50 px-1.5 py-1 dark:bg-zinc-800">
            <span className="px-1 text-xs text-gray-500 dark:text-gray-400">
                {DATE_FIELD_LABELS[filter.field]}
            </span>

            {!supported && (
                <span
                    className="flex items-center text-amber-500"
                    title="This breakdown has no such date — the filter is ignored."
                >
                    <TriangleAlert className="h-3.5 w-3.5" />
                </span>
            )}

            <Select
                value={filter.op}
                onValueChange={(op) =>
                    patch({
                        op: op as DateFilterOp,
                        // Seed the upper bound so "between" is valid immediately.
                        value2:
                            op === 'between'
                                ? (filter.value2 ?? filter.value)
                                : undefined,
                    })
                }
            >
                <SelectTrigger className="h-7 w-[120px] text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {(Object.keys(DATE_OP_LABELS) as DateFilterOp[]).map(
                        (op) => (
                            <SelectItem key={op} value={op}>
                                {DATE_OP_LABELS[op]}
                            </SelectItem>
                        ),
                    )}
                </SelectContent>
            </Select>

            <DatePicker
                id={`date-filter-${filter.field}-from`}
                compact
                clearable={false}
                placeholder="Pick a date"
                defaultDate={filter.value}
                onChange={(dates) => {
                    if (dates.length > 0) {
                        patch({ value: moment(dates[0]).format('YYYY-MM-DD') });
                    }
                }}
            />

            {filter.op === 'between' && (
                <>
                    <span className="text-xs text-gray-400">and</span>
                    <DatePicker
                        id={`date-filter-${filter.field}-to`}
                        compact
                        clearable={false}
                        placeholder="Pick a date"
                        defaultDate={filter.value2 ?? filter.value}
                        onChange={(dates) => {
                            if (dates.length > 0) {
                                patch({
                                    value2: moment(dates[0]).format(
                                        'YYYY-MM-DD',
                                    ),
                                });
                            }
                        }}
                    />
                </>
            )}

            <button
                type="button"
                onClick={onRemove}
                className="rounded p-1 text-gray-400 hover:text-red-500"
            >
                <X className="h-3.5 w-3.5" />
            </button>
        </div>
    );
}

function NameFilterChip({
    filter,
    onChange,
    onRemove,
}: {
    filter: NameFilter;
    onChange: (f: NameFilter) => void;
    onRemove: () => void;
}) {
    return (
        <div className="flex items-center gap-1.5 rounded-md bg-gray-50 px-1.5 py-1 dark:bg-zinc-800">
            <span className="px-1 text-xs text-gray-500 dark:text-gray-400">
                Name
            </span>
            <Select
                value={filter.op}
                onValueChange={(op) =>
                    onChange({ ...filter, op: op as NameFilterOp })
                }
            >
                <SelectTrigger className="h-7 w-[140px] text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {(Object.keys(NAME_OP_LABELS) as NameFilterOp[]).map(
                        (op) => (
                            <SelectItem key={op} value={op}>
                                {NAME_OP_LABELS[op]}
                            </SelectItem>
                        ),
                    )}
                </SelectContent>
            </Select>
            <Input
                value={filter.value}
                onChange={(e) => onChange({ ...filter, value: e.target.value })}
                placeholder="value"
                className="h-7 w-[130px] text-xs"
            />
            <button
                type="button"
                onClick={onRemove}
                className="rounded p-1 text-gray-400 hover:text-red-500"
            >
                <X className="h-3.5 w-3.5" />
            </button>
        </div>
    );
}

function MetricFilterChip({
    filter,
    onChange,
    onRemove,
}: {
    filter: MetricFilter;
    onChange: (f: MetricFilter) => void;
    onRemove: () => void;
}) {
    return (
        <div className="flex items-center gap-1.5 rounded-md bg-gray-50 px-1.5 py-1 dark:bg-zinc-800">
            <span className="px-1 text-xs text-gray-500 dark:text-gray-400">
                {metricLabel(filter.field)}
            </span>
            <Select
                value={filter.op}
                onValueChange={(op) =>
                    onChange({ ...filter, op: op as MetricFilterOp })
                }
            >
                <SelectTrigger className="h-7 w-[150px] text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {(Object.keys(METRIC_OP_LABELS) as MetricFilterOp[]).map(
                        (op) => (
                            <SelectItem key={op} value={op}>
                                {METRIC_OP_LABELS[op]}
                            </SelectItem>
                        ),
                    )}
                </SelectContent>
            </Select>
            <Input
                type="number"
                value={filter.value}
                onChange={(e) =>
                    onChange({ ...filter, value: Number(e.target.value) })
                }
                placeholder="value"
                className="h-7 w-[90px] text-xs"
            />
            {filter.op === 'range' && (
                <>
                    <span className="text-xs text-gray-400">and</span>
                    <Input
                        type="number"
                        value={filter.value2 ?? ''}
                        onChange={(e) =>
                            onChange({
                                ...filter,
                                value2: Number(e.target.value),
                            })
                        }
                        placeholder="value"
                        className="h-7 w-[90px] text-xs"
                    />
                </>
            )}
            <button
                type="button"
                onClick={onRemove}
                className="rounded p-1 text-gray-400 hover:text-red-500"
            >
                <X className="h-3.5 w-3.5" />
            </button>
        </div>
    );
}

function AddFilterButton({
    hasName,
    usedDateFields,
    groupBy,
    onAddName,
    onAddMetric,
    onAddDate,
}: {
    hasName: boolean;
    usedDateFields: DateFilterField[];
    groupBy: BreakdownValue;
    onAddName: () => void;
    onAddMetric: (field: string) => void;
    onAddDate: (field: DateFilterField) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const q = search.toLowerCase();
    const metricMatches = INSIGHTS_OPTIONS.filter((o) =>
        o.label.toLowerCase().includes(q),
    );
    const showName = !hasName && (q === '' || 'name'.includes(q));

    // Only offer a date the current breakdown can actually answer, and only
    // once — a second copy of the same field would just fight the first.
    const dateMatches = (
        Object.keys(DATE_FIELD_LABELS) as DateFilterField[]
    ).filter(
        (field) =>
            !usedDateFields.includes(field) &&
            dateFilterSupported(field, groupBy) &&
            DATE_FIELD_LABELS[field].toLowerCase().includes(q),
    );

    return (
        <Popover
            open={open}
            onOpenChange={(o) => {
                setOpen(o);
                if (o) setSearch('');
            }}
        >
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="inline-flex items-center gap-1 rounded-md border border-dashed border-black/15 px-2 py-1.5 text-xs font-medium text-gray-500 hover:border-emerald-400 hover:text-emerald-600 dark:border-white/15 dark:text-gray-400"
                >
                    <Plus className="h-3 w-3" />
                    Add filter
                </button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-60 p-0">
                <div className="border-b border-black/6 p-2 dark:border-white/6">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search fields..."
                            className="h-8 pl-7 text-xs"
                        />
                    </div>
                </div>
                <div className="max-h-72 overflow-auto p-1">
                    {showName && (
                        <button
                            type="button"
                            onClick={() => {
                                onAddName();
                                setOpen(false);
                            }}
                            className="flex w-full items-center rounded-md px-2 py-1.5 text-left text-xs text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-zinc-800"
                        >
                            Name
                        </button>
                    )}
                    {dateMatches.length > 0 && (
                        <>
                            <p className="px-2 py-1 text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                                Dates
                            </p>
                            {dateMatches.map((field) => (
                                <button
                                    key={field}
                                    type="button"
                                    onClick={() => {
                                        onAddDate(field);
                                        setOpen(false);
                                    }}
                                    className="flex w-full items-center rounded-md px-2 py-1.5 text-left text-xs text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-zinc-800"
                                >
                                    {DATE_FIELD_LABELS[field]}
                                </button>
                            ))}
                        </>
                    )}
                    <p className="px-2 py-1 text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                        Metrics
                    </p>
                    {metricMatches.map((o) => (
                        <button
                            key={o.id}
                            type="button"
                            onClick={() => {
                                onAddMetric(o.id);
                                setOpen(false);
                            }}
                            className="flex w-full items-center rounded-md px-2 py-1.5 text-left text-xs text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-zinc-800"
                        >
                            {o.label}
                        </button>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}
