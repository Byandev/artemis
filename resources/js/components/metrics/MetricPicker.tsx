import { Checkbox } from '@/components/ui/checkbox';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    groupedMetrics,
    MetricConfig,
    metricConfigs,
    MetricKey,
} from '@/types/metrics';
import { ChartNoAxesColumn } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface Props {
    initialValue: MetricKey[];
    onChange: (value: MetricKey[]) => void;
    metrics?: MetricConfig[];
}

const MetricPicker = ({ initialValue = [], onChange, metrics }: Props) => {
    const [isOpen, setIsOpen] = useState(false);
    const [localValue, setLocalValue] = useState<MetricKey[]>(initialValue);

    const availableMetrics = metrics ?? metricConfigs;
    const metricKeySignature = availableMetrics
        .map((metric) => metric.key)
        .join('|');

    const allMetricKeys = useMemo<MetricKey[]>(
        () =>
            metricKeySignature
                ? (metricKeySignature.split('|') as MetricKey[])
                : [],
        [metricKeySignature],
    );

    const allMetricKeySet = useMemo(
        () => new Set<MetricKey>(allMetricKeys),
        [allMetricKeys],
    );

    const visibleGroups = useMemo(
        () =>
            groupedMetrics
                .map((group) => ({
                    ...group,
                    metrics: group.metrics.filter((metric) =>
                        allMetricKeySet.has(metric.key),
                    ),
                }))
                .filter((group) => group.metrics.length > 0),
        [allMetricKeySet],
    );

    useEffect(() => {
        setLocalValue(initialValue.filter((key) => allMetricKeySet.has(key)));
    }, [allMetricKeySet, initialValue, metricKeySignature]);

    const handleApply = useCallback(() => {
        onChange(localValue.filter((key) => allMetricKeySet.has(key)));
        setIsOpen(false);
    }, [allMetricKeySet, localValue, onChange]);

    const handleSelectAll = useCallback(
        () => setLocalValue(allMetricKeys),
        [allMetricKeys],
    );

    const handleClear = useCallback(() => setLocalValue([]), []);

    const activeCount = useMemo(
        () => localValue.filter((key) => allMetricKeySet.has(key)).length,
        [allMetricKeySet, localValue],
    );
    const allSelected =
        allMetricKeys.length > 0 && activeCount === allMetricKeys.length;

    const MIN_REQUIRED = 2;
    const minimumRequired = Math.min(MIN_REQUIRED, allMetricKeys.length);
    const canApply = activeCount >= minimumRequired;

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <button
                    className={[
                        'group/picker inline-flex h-9 min-w-max shrink-0 items-center overflow-hidden rounded-[10px] border transition-all duration-200',
                        'bg-gradient-to-b from-white to-stone-50 dark:from-zinc-900 dark:to-zinc-950',
                        'shadow-[0_1px_2px_rgba(16,24,40,0.06),0_1px_3px_rgba(16,24,40,0.10),inset_0_1px_0_rgba(255,255,255,0.5)] dark:shadow-[0_1px_2px_rgba(0,0,0,0.4),inset_0_1px_0_rgba(255,255,255,0.04)]',
                        'hover:-translate-y-px hover:shadow-[0_2px_4px_rgba(16,24,40,0.08),0_4px_10px_rgba(16,24,40,0.10),inset_0_1px_0_rgba(255,255,255,0.5)]',
                        'active:translate-y-0 active:shadow-[0_1px_2px_rgba(16,24,40,0.06),inset_0_1px_2px_rgba(16,24,40,0.08)]',
                        isOpen
                            ? 'border-emerald-500/50 ring-2 ring-emerald-500/15 dark:border-emerald-500/40'
                            : activeCount > 0
                              ? 'border-emerald-500/40 hover:border-emerald-500/60 dark:border-emerald-500/30 dark:hover:border-emerald-500/40'
                              : 'border-black/[0.08] hover:border-black/[0.16] dark:border-white/[0.08] dark:hover:border-white/[0.16]',
                    ].join(' ')}
                >
                    {/* Icon cell */}
                    <span
                        className={[
                            'flex h-full w-9 shrink-0 items-center justify-center rounded-l-[10px] border-r transition-colors duration-200',
                            activeCount > 0
                                ? 'border-emerald-500/20 bg-gradient-to-br from-emerald-500/[0.12] to-emerald-500/[0.06] dark:border-emerald-500/15 dark:from-emerald-500/[0.18] dark:to-emerald-500/[0.08]'
                                : 'border-black/[0.06] bg-gradient-to-br from-stone-50 to-stone-100 dark:border-white/[0.06] dark:from-zinc-800/60 dark:to-zinc-900/60',
                        ].join(' ')}
                    >
                        <ChartNoAxesColumn
                            className={[
                                'h-3.5 w-3.5 transition-all duration-200 group-hover/picker:scale-110',
                                activeCount > 0
                                    ? 'text-emerald-600 drop-shadow-[0_1px_1px_rgba(16,185,129,0.25)] dark:text-emerald-400'
                                    : 'text-gray-400 dark:text-gray-500',
                            ].join(' ')}
                        />
                    </span>

                    {/* Label + badge */}
                    <span className="flex items-center gap-2 px-3">
                        <span
                            className={[
                                'text-xs font-semibold tracking-tight transition-colors duration-200',
                                activeCount > 0
                                    ? 'text-gray-800 dark:text-gray-100'
                                    : 'text-gray-500 dark:text-gray-400',
                            ].join(' ')}
                        >
                            Metrics
                        </span>
                        {activeCount > 0 && (
                            <span className="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-gradient-to-b from-emerald-500 to-emerald-600 px-1 text-[10px] font-bold text-white tabular-nums shadow-[0_1px_2px_rgba(16,185,129,0.4),inset_0_1px_0_rgba(255,255,255,0.25)] dark:from-emerald-400 dark:to-emerald-500">
                                {activeCount}
                            </span>
                        )}
                    </span>
                </button>
            </PopoverTrigger>

            <PopoverContent
                className="w-[calc(100vw-2rem)] overflow-hidden rounded-[14px] border border-black/6 bg-white p-0 shadow-[0_8px_30px_rgba(0,0,0,0.08)] sm:w-72 dark:border-white/6 dark:bg-zinc-900 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
                align="start"
            >
                {/* Header */}
                <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <div className="flex items-baseline gap-2">
                        <span className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                            Metrics
                        </span>
                        <span className="text-[11px] text-gray-400 dark:text-gray-500">
                            {activeCount} selected
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handleSelectAll}
                            disabled={allSelected}
                            className="text-[11px]! font-medium! text-gray-400 transition-colors hover:text-emerald-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:text-gray-400 dark:text-gray-500 dark:hover:text-emerald-400 dark:disabled:hover:text-gray-500"
                        >
                            Select all
                        </button>
                        <span className="text-gray-200 dark:text-gray-700">
                            |
                        </span>
                        <button
                            type="button"
                            onClick={handleClear}
                            disabled={activeCount === 0}
                            className="text-[11px]! font-medium! text-gray-400 transition-colors hover:text-red-500 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:text-gray-400 dark:text-gray-500 dark:hover:text-red-400 dark:disabled:hover:text-gray-500"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                {/* Metric groups */}
                <div className="max-h-72 overflow-y-auto p-2">
                    {visibleGroups.map((g) => (
                        <div key={g.key} className="mb-1">
                            <p className="px-2 py-1.5 font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                                {g.label}
                            </p>
                            <div>
                                {g.metrics.map((m) => {
                                    const checked = localValue.includes(m.key);
                                    return (
                                        <label
                                            key={m.key}
                                            htmlFor={m.key}
                                            className={[
                                                'flex cursor-pointer items-center gap-2.5 rounded-[8px] px-2 py-2 transition-colors',
                                                checked
                                                    ? 'bg-emerald-500/[0.06] dark:bg-emerald-500/[0.08]'
                                                    : 'hover:bg-black/[0.02] dark:hover:bg-white/[0.03]',
                                            ].join(' ')}
                                        >
                                            <Checkbox
                                                id={m.key}
                                                name={m.key}
                                                checked={checked}
                                                onCheckedChange={() =>
                                                    setLocalValue((prev) =>
                                                        prev.includes(m.key)
                                                            ? prev.filter(
                                                                  (item) =>
                                                                      item !==
                                                                      m.key,
                                                              )
                                                            : [...prev, m.key],
                                                    )
                                                }
                                                className="border-black/20 data-[state=checked]:border-emerald-600 data-[state=checked]:bg-emerald-600 dark:border-white/20 dark:data-[state=checked]:border-emerald-500 dark:data-[state=checked]:bg-emerald-500"
                                            />
                                            <span
                                                className={[
                                                    'text-[13px] select-none',
                                                    checked
                                                        ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                                        : 'text-gray-600 dark:text-gray-400',
                                                ].join(' ')}
                                            >
                                                {m.name}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Footer */}
                <div className="border-t border-black/6 px-4 py-3 dark:border-white/6">
                    {!canApply && (
                        <p className="mb-2 text-[11px] text-amber-600 dark:text-amber-400">
                            Select at least {minimumRequired} metrics to
                            continue.
                        </p>
                    )}
                    <div className="flex gap-2">
                        <button
                            onClick={handleApply}
                            disabled={!canApply}
                            className="flex-1 rounded-lg bg-emerald-600 py-2 text-xs font-medium text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400 dark:bg-emerald-500 dark:hover:bg-emerald-400 dark:disabled:bg-zinc-800 dark:disabled:text-gray-500"
                        >
                            Apply
                        </button>
                        <button
                            onClick={() => {
                                setLocalValue(initialValue);
                                setIsOpen(false);
                            }}
                            className="rounded-lg border border-black/6 bg-white px-4 py-2 text-xs font-medium text-gray-500 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-400 dark:hover:border-white/10"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
};

export default MetricPicker;
