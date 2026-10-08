import { Head, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { TaskToolbar } from './components/task-toolbar';
import {
    TaskBoardView,
    TaskEmptyState,
    TaskListView,
} from './components/task-views';
import { useTaskWorkspace } from './components/task-workspace';
import { TasksShell } from './components/tasks-shell';
import { EMPTY_PANEL } from './lib/ui';

function TasksBoard() {
    const board = useTaskWorkspace();

    const filtered =
        board.filters.search !== '' ||
        board.filters.status_id !== '' ||
        board.filters.priority !== '';

    const View = board.view === 'board' ? TaskBoardView : TaskListView;

    const folder = board.folders.find(
        (item) => item.id === board.activeList?.folder_id,
    );

    /**
     * Ancestors only — the heading below states the current view, so repeating
     * it as the last crumb just says the same thing twice.
     */
    const crumbs = [board.activeSpace?.name, folder?.name].filter(
        (crumb): crumb is string => Boolean(crumb),
    );

    const noWorkspace =
        !board.loadingSpaces &&
        !board.loadingSpace &&
        board.statuses.length === 0;

    return (
        <>
            <Head title="Tasks" />

            <div className="mx-auto flex h-full min-h-0 w-full max-w-[1280px] flex-1 flex-col gap-4 p-4 md:p-7">
                <header className="flex flex-col gap-1.5">
                    <nav
                        aria-label="Breadcrumb"
                        className="flex items-center gap-1.5 font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground uppercase"
                    >
                        {crumbs.map((crumb, index) => (
                            <span
                                key={crumb}
                                className="flex items-center gap-1.5"
                            >
                                {index > 0 && (
                                    <ChevronRight className="size-3 opacity-50" />
                                )}
                                <span>{crumb}</span>
                            </span>
                        ))}
                    </nav>

                    <div className="flex flex-wrap items-baseline gap-3">
                        <h1 className="text-[22px] leading-none font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                            {board.activeList?.name ?? 'All tasks'}
                        </h1>
                        <span className="text-sm text-muted-foreground">
                            {board.total} task{board.total === 1 ? '' : 's'}
                        </span>
                    </div>
                </header>

                <TaskToolbar
                    filters={board.filters}
                    statuses={board.statuses}
                    view={board.view}
                    onView={board.setView}
                    onFilter={board.setFilter}
                    onReset={board.resetFilters}
                    onAddTask={board.requestNewTask}
                    canAddTask={board.canAddTask}
                    addTaskBlockedReason={board.addTaskBlockedReason}
                />

                <div className="-mx-1 min-h-0 flex-1 overflow-y-auto px-1">
                    {noWorkspace ? (
                        <div className={EMPTY_PANEL}>
                            <p className="text-xl font-semibold tracking-[-0.025em] text-foreground">
                                No space selected
                            </p>
                            <p className="max-w-xs text-sm leading-relaxed text-muted-foreground">
                                Create a space from the panel on the left to
                                start organising work.
                            </p>
                        </div>
                    ) : !board.loadingTasks &&
                      board.tasks.length === 0 &&
                      filtered ? (
                        <TaskEmptyState filtered />
                    ) : (
                        <View
                            tasks={board.tasks}
                            statuses={board.statuses}
                            members={board.members}
                            loading={board.loadingTasks}
                            canCreate={board.canAddTask}
                            newTaskStatusId={board.newTaskStatusId}
                            onNewTaskOpened={board.clearNewTask}
                            onCreate={board.createTask}
                            onUpdate={board.updateTask}
                            onDelete={board.deleteTask}
                            onError={board.report}
                        />
                    )}
                </div>
            </div>
        </>
    );
}

type Props = {
    workspace: { id: number; name: string; slug: string };
};

/** Read `?space=&list=` so a link back from a task reopens its list. */
function idFromQuery(url: string, key: string): number | null {
    const query = url.split('?')[1] ?? '';
    const value = Number(new URLSearchParams(query).get(key));

    return Number.isInteger(value) && value > 0 ? value : null;
}

export default function TasksIndex({ workspace }: Props) {
    const { url } = usePage();

    return (
        <TasksShell
            slug={workspace.slug}
            breadcrumbs={[
                { title: 'Tasks', href: `/workspaces/${workspace.slug}/tasks` },
            ]}
            options={{
                showsTasks: true,
                initialSpaceId: idFromQuery(url, 'space'),
                initialListId: idFromQuery(url, 'list'),
            }}
        >
            <TasksBoard />
        </TasksShell>
    );
}
