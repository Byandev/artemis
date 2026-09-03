import DailyTrackerController from '@/actions/App/Http/Controllers/Workspaces/SalesMarketing/DailyTrackerController';
import PageHeader from '@/components/common/PageHeader';
import ChecklistView from '@/components/sales-marketing/daily-tracker/checklist-view';
import MemberRoster from '@/components/sales-marketing/daily-tracker/member-roster';
import TeamMatrix from '@/components/sales-marketing/daily-tracker/team-matrix';
import type {
    CompletionPayload,
    TrackerItem,
    TrackerMember,
    TrackerViewer,
} from '@/components/sales-marketing/daily-tracker/types';
import { useTrackerBoard } from '@/components/sales-marketing/daily-tracker/use-tracker-board';
import ViewSwitch, {
    type TrackerView,
} from '@/components/sales-marketing/daily-tracker/view-switch';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

interface Props {
    workspace: Workspace;
    /** The day on screen, `YYYY-MM-DD`. */
    date: string;
    /** The view the URL asked for, already validated by the controller. */
    view: TrackerView;
    members: TrackerMember[];
    items: TrackerItem[];
    completions: CompletionPayload;
    viewer: TrackerViewer;
}

/**
 * `YYYY-MM-DD` read as a local date. `new Date('2026-09-03')` is UTC midnight,
 * which reads as the day before once the browser is west of Greenwich.
 */
function toLocalDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

const longDate = (value: string) =>
    toLocalDate(value).toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

/** A card for the cases where there is nothing to draw. */
function EmptyBoard({ message }: { message: string }) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-10 text-center dark:border-white/6 dark:bg-zinc-900">
            <p className="font-mono! text-[12px]! text-gray-400 dark:text-gray-500">
                {message}
            </p>
        </div>
    );
}

export default function DailyTrackerIndex({
    workspace,
    date,
    view: initialView,
    members,
    items,
    completions,
    viewer,
}: Props) {
    const [view, setView] = useState<TrackerView>(initialView);

    // Follow the server on a real visit (a date change, back/forward).
    useEffect(() => setView(initialView), [initialView]);

    // Open on your own row when you have one — the common case is ticking your
    // own deliverables — and fall back to whoever is first.
    const [selectedId, setSelectedId] = useState<number | null>(
        () =>
            members.find((member) => member.is_self)?.id ??
            members[0]?.id ??
            null,
    );

    // The roster changes as members are activated or retired; keep the
    // selection valid.
    useEffect(() => {
        setSelectedId((current) =>
            members.some((member) => member.id === current)
                ? current
                : (members[0]?.id ?? null),
        );
    }, [members]);

    const board = useTrackerBoard({
        workspaceSlug: workspace.slug,
        date,
        members,
        items,
        completions,
        viewer,
    });

    const selectedProgress = useMemo(
        () => (selectedId === null ? null : board.progress.get(selectedId)),
        [board.progress, selectedId],
    );

    /** The page's URL for a given day and view — what the address bar should read. */
    const urlFor = (day: string, tab: TrackerView) =>
        DailyTrackerController.index.url(workspace.slug, {
            query: { date: day, view: tab },
        });

    // Switching tab is pure presentation — no data changes — so the URL is
    // rewritten in place rather than re-fetched. A reload then lands on the
    // same tab, and so does anyone the link is sent to.
    const applyView = (next: TrackerView) => {
        setView(next);
        window.history.replaceState(
            window.history.state,
            '',
            urlFor(date, next),
        );
    };

    // The day needs the server, and carries the current tab with it so the
    // address bar does not lose it on the way.
    const applyDate = (value: string) => {
        if (!value || value === date) return;

        router.get(
            urlFor(value, view),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Daily Tracker`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Daily Tracker"
                    description={`Deliverables checklist per intern · ${longDate(date)}`}
                    stackActionsOnMobile
                >
                    <ViewSwitch value={view} onChange={applyView} />
                    <DatePicker
                        id="daily-tracker-date"
                        key={date}
                        mode="single"
                        compact
                        clearable={false}
                        placeholder="Pick a day"
                        defaultDate={date}
                        onChange={(_dates, dateStr) => applyDate(dateStr)}
                    />
                </PageHeader>

                {members.length === 0 ? (
                    <EmptyBoard message="No one is tracked on this board yet. A role granted “Tracked on Daily Tracker” puts its people on it." />
                ) : items.length === 0 ? (
                    <EmptyBoard message="No deliverables have been set up for this workspace yet." />
                ) : view === 'matrix' ? (
                    <TeamMatrix
                        members={members}
                        categories={board.categories}
                        themes={board.themes}
                        progress={board.progress}
                        isDone={board.isDone}
                        isPending={board.isPending}
                        canTick={board.canTick}
                        onToggle={board.toggle}
                    />
                ) : (
                    <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
                        <MemberRoster
                            members={members}
                            progress={board.progress}
                            selectedId={selectedId}
                            onSelect={setSelectedId}
                        />

                        {selectedProgress && (
                            <ChecklistView
                                progress={selectedProgress}
                                categories={board.categories}
                                themes={board.themes}
                                categoryProgress={board.categoryProgress}
                                isDone={board.isDone}
                                isPending={board.isPending}
                                canTick={board.canTick}
                                onToggle={board.toggle}
                            />
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
