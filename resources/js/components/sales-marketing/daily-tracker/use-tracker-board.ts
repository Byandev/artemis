import DailyTrackerController from '@/actions/App/Http/Controllers/Workspaces/SalesMarketing/DailyTrackerController';
import axios from 'axios';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { assignCategoryThemes, type CategoryTheme } from './category-theme';
import type {
    CompletionPayload,
    CompletionSets,
    MemberProgress,
    TrackerCategory,
    TrackerItem,
    TrackerMember,
    TrackerViewer,
} from './types';

/** JSON object keys arrive as strings; the board works in numbers and sets. */
export function toCompletionSets(payload: CompletionPayload): CompletionSets {
    return new Map(
        Object.entries(payload).map(([memberId, itemIds]) => [
            Number(memberId),
            new Set(itemIds),
        ]),
    );
}

/** A new map with one member's item added or removed — never a mutation in place. */
function withToggled(
    sets: CompletionSets,
    memberId: number,
    itemId: number,
    completed: boolean,
): CompletionSets {
    const next = new Map(sets);
    const items = new Set(next.get(memberId) ?? []);

    if (completed) {
        items.add(itemId);
    } else {
        items.delete(itemId);
    }

    next.set(memberId, items);

    return next;
}

/** Items in board order, grouped into the bands they are drawn under. */
function groupByCategory(items: TrackerItem[]): TrackerCategory[] {
    const groups: TrackerCategory[] = [];

    for (const item of items) {
        const last = groups.at(-1);

        if (last?.name === item.category) {
            last.items.push(item);
            continue;
        }

        // A category that reappears after another one keeps its first group,
        // so the board never shows the same heading twice.
        const existing = groups.find((group) => group.name === item.category);

        if (existing) {
            existing.items.push(item);
        } else {
            groups.push({ name: item.category, items: [item] });
        }
    }

    return groups;
}

interface Options {
    workspaceSlug: string;
    /** The day on screen, as `YYYY-MM-DD`. */
    date: string;
    members: TrackerMember[];
    items: TrackerItem[];
    completions: CompletionPayload;
    viewer: TrackerViewer;
}

/**
 * The board's single source of truth.
 *
 * Completions live here as `member -> completed item ids`; every count,
 * percentage and tick mark on the page is derived from that one map, so the
 * roster, the checklist and the team matrix cannot disagree.
 *
 * Ticking is optimistic: the box flips immediately, the request follows, and a
 * failure puts the box back and says so. Server state replaces local state
 * whenever new props arrive (a date change, a back/forward navigation).
 */
export function useTrackerBoard({
    workspaceSlug,
    date,
    members,
    items,
    completions,
    viewer,
}: Options) {
    const [sets, setSets] = useState<CompletionSets>(() =>
        toCompletionSets(completions),
    );

    // Follow the server on a date change or a history navigation.
    useEffect(() => setSets(toCompletionSets(completions)), [completions]);

    // Boxes with a request in flight, keyed `member:item`, so a double-click
    // cannot queue two opposite writes for the same box. The ref is the guard —
    // it is current the instant a click is handled, where the state behind it
    // only lands on the next render and is there to grey the box out.
    const inFlight = useRef<Set<string>>(new Set());
    const [pending, setPending] = useState<ReadonlySet<string>>(new Set());

    const categories = useMemo(() => groupByCategory(items), [items]);

    const themes = useMemo(
        () => assignCategoryThemes(categories.map((category) => category.name)),
        [categories],
    );

    const isDone = useCallback(
        (memberId: number, itemId: number) =>
            sets.get(memberId)?.has(itemId) ?? false,
        [sets],
    );

    const isPending = useCallback(
        (memberId: number, itemId: number) =>
            pending.has(`${memberId}:${itemId}`),
        [pending],
    );

    /** The rows that belong to the viewer, and so are theirs to tick. */
    const ownRows = useMemo(
        () =>
            new Set(
                members
                    .filter((member) => member.is_self)
                    .map((member) => member.id),
            ),
        [members],
    );

    /** Everyone ticks their own row; ticking another's needs the manage grant. */
    const canTick = useCallback(
        (memberId: number) => viewer.can_manage || ownRows.has(memberId),
        [viewer, ownRows],
    );

    const progress = useMemo(() => {
        const total = items.length;

        return new Map<number, MemberProgress>(
            members.map((member) => {
                const done = items.filter((item) =>
                    sets.get(member.id)?.has(item.id),
                ).length;

                return [
                    member.id,
                    {
                        member,
                        done,
                        total,
                        percent:
                            total === 0 ? 0 : Math.round((done / total) * 100),
                        isComplete: total > 0 && done === total,
                    },
                ];
            }),
        );
    }, [members, items, sets]);

    /** How many of a category's items one member has ticked. */
    const categoryProgress = useCallback(
        (memberId: number, category: TrackerCategory) => ({
            done: category.items.filter((item) => isDone(memberId, item.id))
                .length,
            total: category.items.length,
        }),
        [isDone],
    );

    const toggle = useCallback(
        async (memberId: number, item: TrackerItem) => {
            const key = `${memberId}:${item.id}`;

            if (!canTick(memberId) || inFlight.current.has(key)) return;

            inFlight.current.add(key);

            const completed = !isDone(memberId, item.id);

            setSets((current) =>
                withToggled(current, memberId, item.id, completed),
            );
            setPending((current) => new Set(current).add(key));

            try {
                await axios.put(
                    DailyTrackerController.toggle.url(workspaceSlug),
                    {
                        item_id: item.id,
                        user_id: memberId,
                        date,
                        completed,
                    },
                );
            } catch {
                // Put the box back where it was; the server never took the change.
                setSets((current) =>
                    withToggled(current, memberId, item.id, !completed),
                );
                toast.error('Could not save that tick. Please try again.');
            } finally {
                inFlight.current.delete(key);
                setPending((current) => {
                    const next = new Set(current);
                    next.delete(key);

                    return next;
                });
            }
        },
        [canTick, isDone, workspaceSlug, date],
    );

    return {
        categories,
        /** Category name -> its tint on the board. */
        themes: themes as ReadonlyMap<string, CategoryTheme>,
        progress: progress as ReadonlyMap<number, MemberProgress>,
        categoryProgress,
        isDone,
        isPending,
        canTick,
        toggle,
    };
}
