import { useCallback, useMemo, useState } from 'react';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ChartNoAxesColumn } from 'lucide-react';
import { groupedMetrics, MetricKey } from '@/types/metrics';
import { Checkbox } from '@/components/ui/checkbox';

interface Props {
    initialValue: MetricKey[];
    onChange: (value: MetricKey[]) => void;
}

const MetricPicker = ({ initialValue = [], onChange }: Props) => {
    const [isOpen, setIsOpen] = useState(false);
    const [localValue, setLocalValue] = useState<MetricKey[]>(initialValue);

    const handleApply = useCallback(() => {
        onChange(localValue);
        setIsOpen(false);
    }, [localValue, onChange]);

    const activeCount = useMemo(() => localValue.length, [localValue]);

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <button
                    className={[
                        'inline-flex h-9 items-center overflow-hidden rounded-[10px] border transition-all duration-150',
                        'bg-white dark:bg-zinc-900',
                        'shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] dark:shadow-none',
                        isOpen
                            ? 'border-emerald-500/40 ring-2 ring-emerald-500/10 dark:border-emerald-500/30'
                            : activeCount > 0
                              ? 'border-emerald-500/30 hover:border-emerald-500/50 dark:border-emerald-500/20 dark:hover:border-emerald-500/30'
                              : 'border-black/8 hover:border-black/14 dark:border-white/8 dark:hover:border-white/14',
                    ].join(' ')}
                >
                    {/* Icon cell */}
                    <span
                        className={[
                            'flex h-full w-9 shrink-0 items-center justify-center rounded-l-[10px] border-r transition-colors duration-150',
                            activeCount > 0
                                ? 'border-emerald-500/20 bg-emerald-500/[0.07] dark:border-emerald-500/15 dark:bg-emerald-500/10'
                                : 'border-black/6 bg-stone-50 dark:border-white/6 dark:bg-white/3',
                        ].join(' ')}
                    >
                        <ChartNoAxesColumn
                            className={[
                                'h-3.5 w-3.5 transition-colors duration-150',
                                activeCount > 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-gray-400 dark:text-gray-500',
                            ].join(' ')}
                        />
                    </span>

                    {/* Label + badge */}
                    <span className="flex items-center gap-2 px-3">
                        <span
                            className={[
                                'text-xs font-medium transition-colors duration-150',
                                activeCount > 0
                                    ? 'text-gray-700 dark:text-gray-200'
                                    : 'text-gray-500 dark:text-gray-400',
                            ].join(' ')}
                        >
                            Metrics
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
                className="w-[calc(100vw-2rem)] overflow-hidden rounded-[14px] border border-black/6 bg-white p-0 shadow-[0_8px_30px_rgba(0,0,0,0.08)] sm:w-72 dark:border-white/6 dark:bg-zinc-900 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
                align="start"
            >
                {/* Header */}
                <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <span className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                        Metrics
                    </span>
                    <span className="text-[11px] text-gray-400 dark:text-gray-500">
                        {activeCount} selected
                    </span>
                </div>

                {/* Metric groups */}
                <div className="max-h-72 overflow-y-auto p-2">
                    {groupedMetrics.map((g) => (
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
                <div className="flex gap-2 border-t border-black/6 px-4 py-3 dark:border-white/6">
                    <button
                        onClick={handleApply}
                        className="flex-1 rounded-lg bg-emerald-600 py-2 text-xs font-medium text-white transition-colors hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-400"
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
            </PopoverContent>
        </Popover>
    );
};

export default MetricPicker;
