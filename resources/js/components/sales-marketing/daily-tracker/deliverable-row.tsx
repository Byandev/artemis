import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import type { TrackerItem } from './types';

/** The pill a weekly deliverable carries, so a daily one is the unmarked case. */
const CADENCE_BADGE: Partial<Record<TrackerItem['cadence'], string>> = {
    weekly: 'WEEKLY',
};

/**
 * One deliverable: a box, the question, and whatever it is tagged with.
 *
 * The question is a real `<label>` for the box, so the whole line is a hit
 * target and screen readers announce the two together.
 */
export default function DeliverableRow({
    item,
    done,
    disabled,
    pending,
    onToggle,
}: {
    item: TrackerItem;
    done: boolean;
    /** True when the viewer may not tick this row (it is someone else's). */
    disabled: boolean;
    pending: boolean;
    onToggle: () => void;
}) {
    const inputId = `deliverable-${item.id}`;
    const cadenceBadge = CADENCE_BADGE[item.cadence];

    return (
        <div
            className={cn(
                'flex items-center gap-3 px-4 py-3 transition-colors',
                !disabled &&
                    'hover:bg-black/[0.015] dark:hover:bg-white/[0.015]',
                pending && 'opacity-60',
            )}
        >
            <Checkbox
                id={inputId}
                checked={done}
                disabled={disabled}
                onCheckedChange={onToggle}
                className="size-5 rounded-[6px] border-black/15 data-[state=checked]:border-emerald-500 data-[state=checked]:bg-emerald-500 data-[state=checked]:text-white dark:border-white/20"
            />

            <label
                htmlFor={inputId}
                className={cn(
                    'min-w-0 flex-1 text-[13px] leading-snug',
                    disabled ? 'cursor-default' : 'cursor-pointer',
                    done
                        ? 'text-gray-400 line-through dark:text-gray-500'
                        : 'text-gray-700 dark:text-gray-200',
                )}
            >
                {item.label}
            </label>

            <div className="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                {cadenceBadge && (
                    <span className="rounded-md bg-pink-500/10 px-2 py-1 font-mono! text-[9px]! font-medium tracking-wider text-pink-600 dark:text-pink-400">
                        {cadenceBadge}
                    </span>
                )}
                {item.tags.map((tag) => (
                    <span
                        key={tag}
                        className="rounded-md bg-emerald-500/8 px-2 py-1 font-mono! text-[9px]! font-medium tracking-wider text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
                    >
                        {tag}
                    </span>
                ))}
            </div>
        </div>
    );
}
