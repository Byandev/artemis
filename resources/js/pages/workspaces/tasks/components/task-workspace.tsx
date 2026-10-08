import type { ReactNode } from 'react';
import { createContext, useContext, useMemo } from 'react';
import { type BoardOptions, useTaskBoard } from '../hooks/use-task-board';
import { createTaskRoutes, type TaskRoutes } from '../lib/routes';

type TaskWorkspace = ReturnType<typeof useTaskBoard>;

const TaskRoutesContext = createContext<TaskRoutes | null>(null);
const TaskWorkspaceContext = createContext<TaskWorkspace | null>(null);

/**
 * Holds the space tree and task state for one tasks page, above both the tree
 * panel and the page body, so the tree can drive navigation the way a ClickUp
 * sidebar does. The routes are built once here for the workspace in the URL.
 */
export function TaskWorkspaceProvider({
    slug,
    children,
    ...options
}: BoardOptions & { slug: string; children: ReactNode }) {
    const routes = useMemo(() => createTaskRoutes(slug), [slug]);

    return (
        <TaskRoutesContext.Provider value={routes}>
            <BoardProvider routes={routes} options={options}>
                {children}
            </BoardProvider>
        </TaskRoutesContext.Provider>
    );
}

function BoardProvider({
    routes,
    options,
    children,
}: {
    routes: TaskRoutes;
    options: BoardOptions;
    children: ReactNode;
}) {
    const board = useTaskBoard(routes, options);

    return (
        <TaskWorkspaceContext.Provider value={board}>
            {children}
        </TaskWorkspaceContext.Provider>
    );
}

export function useTaskWorkspace(): TaskWorkspace {
    const workspace = useContext(TaskWorkspaceContext);

    if (workspace === null) {
        throw new Error(
            'useTaskWorkspace must be used inside a TaskWorkspaceProvider.',
        );
    }

    return workspace;
}

export function useTaskRoutes(): TaskRoutes {
    const routes = useContext(TaskRoutesContext);

    if (routes === null) {
        throw new Error(
            'useTaskRoutes must be used inside a TaskWorkspaceProvider.',
        );
    }

    return routes;
}
