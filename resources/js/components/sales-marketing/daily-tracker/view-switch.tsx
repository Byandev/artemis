import { cn } from '@/lib/utils';

export type TrackerView = 'checklist' | 'matrix';

const OPTIONS: { value: TrackerView; label: string }[] = [
    { value: 'checklist', label: 'Checklist' },
    { value: 'matrix', label: 'Team matrix' },
];

/** Segmented control for the two ways of reading the same board. */
export default function ViewSwitch({
    value,
    onChange,
}: {
    value: TrackerView;
    onChange: (view: TrackerView) => void;
}) {
    return (
        <div
            role="tablist"
            aria-label="Daily Tracker view"
            className="flex items-center gap-1 rounded-[10px] border border-black/6 bg-stone-100 p-1 dark:border-white/6 dark:bg-zinc-800"
        >
            {OPTIONS.map((option) => {
                const isActive = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="tab"
                        aria-selected={isActive}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'h-7 rounded-[7px] px-3 text-[12px] font-medium transition-colors',
                            isActive
                                ? 'bg-white text-gray-800 shadow-xs dark:bg-zinc-900 dark:text-gray-100'
                                : 'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
