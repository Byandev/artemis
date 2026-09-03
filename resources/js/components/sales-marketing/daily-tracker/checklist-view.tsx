import { cn } from '@/lib/utils';
import type { CategoryTheme } from './category-theme';
import { FALLBACK_THEME } from './category-theme';
import DeliverableRow from './deliverable-row';
import MemberSummary from './member-summary';
import type { MemberProgress, TrackerCategory, TrackerItem } from './types';

/**
 * One member's day: their standing at the top, then their deliverables banded
 * by category.
 */
export default function ChecklistView({
    progress,
    categories,
    themes,
    categoryProgress,
    isDone,
    isPending,
    canTick,
    onToggle,
}: {
    progress: MemberProgress;
    categories: TrackerCategory[];
    themes: ReadonlyMap<string, CategoryTheme>;
    categoryProgress: (
        memberId: number,
        category: TrackerCategory,
    ) => { done: number; total: number };
    isDone: (memberId: number, itemId: number) => boolean;
    isPending: (memberId: number, itemId: number) => boolean;
    canTick: (memberId: number) => boolean;
    onToggle: (memberId: number, item: TrackerItem) => void;
}) {
    const memberId = progress.member.id;
    const locked = !canTick(memberId);

    return (
        <div className="flex flex-col gap-4">
            <MemberSummary progress={progress} />

            {locked && (
                <p className="px-1 font-mono! text-[10px]! tracking-wide text-gray-400 dark:text-gray-500">
                    Read only — you can tick your own deliverables.
                </p>
            )}

            {categories.map((category) => {
                const theme = themes.get(category.name) ?? FALLBACK_THEME;
                const { done, total } = categoryProgress(memberId, category);

                return (
                    <section
                        key={category.name}
                        className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900"
                    >
                        <header
                            className={cn(
                                'flex items-center justify-between gap-3 px-4 py-2.5',
                                theme.band,
                            )}
                        >
                            <h2
                                className={cn(
                                    'my-0! font-mono! text-[11px]! font-medium tracking-[0.08em]',
                                    theme.heading,
                                )}
                            >
                                {category.name}
                            </h2>
                            <span
                                className={cn(
                                    'font-mono! text-[10px]!',
                                    theme.count,
                                )}
                            >
                                {done}/{total}
                            </span>
                        </header>

                        <div className="divide-y divide-black/4 dark:divide-white/4">
                            {category.items.map((item) => (
                                <DeliverableRow
                                    key={item.id}
                                    item={item}
                                    done={isDone(memberId, item.id)}
                                    disabled={locked}
                                    pending={isPending(memberId, item.id)}
                                    onToggle={() => onToggle(memberId, item)}
                                />
                            ))}
                        </div>
                    </section>
                );
            })}
        </div>
    );
}
