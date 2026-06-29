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
import { Plus, Search, X } from 'lucide-react';
import { useState } from 'react';
import { INSIGHTS_OPTIONS, metricLabel } from '../../_shared';
import {
    isNameFilter,
    METRIC_OP_LABELS,
    NAME_OP_LABELS,
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
}: {
    filters: ReportFilter[];
    onChange: (filters: ReportFilter[]) => void;
}) {
    const hasName = filters.some(isNameFilter);

    const update = (index: number, next: ReportFilter) =>
        onChange(filters.map((f, i) => (i === index ? next : f)));
    const remove = (index: number) =>
        onChange(filters.filter((_, i) => i !== index));

    const addName = () =>
        onChange([...filters, { field: 'name', op: 'contains', value: '' }]);
    const addMetric = (field: string) =>
        onChange([...filters, { field, op: 'gte', value: 0 }]);

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
                onAddName={addName}
                onAddMetric={addMetric}
            />
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
    onAddName,
    onAddMetric,
}: {
    hasName: boolean;
    onAddName: () => void;
    onAddMetric: (field: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const q = search.toLowerCase();
    const metricMatches = INSIGHTS_OPTIONS.filter((o) =>
        o.label.toLowerCase().includes(q),
    );
    const showName = !hasName && (q === '' || 'name'.includes(q));

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
