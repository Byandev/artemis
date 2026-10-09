import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { api, ApiError, apiGet } from '../lib/api';
import type { TaskRoutes } from '../lib/routes';
import type {
    AssignableSpaceRole,
    Folder,
    Label,
    Paginated,
    Space,
    SpaceMember,
    Task,
    TaskFilters,
    TaskList,
    TaskStatus,
    TaskStatusInput,
    UserSummary,
} from '../types';
import { useDebouncedValue } from './use-debounced-value';

type Wrapped<T> = { data: T };

/** Keep statuses in the order the board renders their columns and groups. */
function sortStatuses(items: TaskStatus[]): TaskStatus[] {
    return [...items].sort((a, b) => a.position - b.position || a.id - b.id);
}

/** Match the order the members endpoint returns, so a new row lands in place. */
function sortMembers(items: SpaceMember[]): SpaceMember[] {
    return [...items].sort((a, b) => a.name.localeCompare(b.name));
}

/** The API allows a single default per space; mirror that locally. */
function applyDefault(items: TaskStatus[], changed: TaskStatus): TaskStatus[] {
    if (!changed.is_default) {
        return items;
    }

    return items.map((item) =>
        item.id === changed.id ? item : { ...item, is_default: false },
    );
}

export type BoardView = 'list' | 'board';

const EMPTY_FILTERS: TaskFilters = {
    search: '',
    status_id: '',
    priority: '',
    sort: 'position',
};

export type BoardOptions = {
    /**
     * Whether this page shows the task list. The detail page mounts the board
     * for its space data (statuses, labels, members) and the tree, but has no
     * task list to fill.
     */
    showsTasks: boolean;
    /** The space and list to open first, e.g. from `?space=&list=`. */
    initialSpaceId?: number | null;
    initialListId?: number | null;
};

/**
 * Drives the task board against the module's JSON endpoints, the same ones any
 * integration uses, so the UI never depends on a second, page-only data path.
 */
export function useTaskBoard(routes: TaskRoutes, options: BoardOptions) {
    /**
     * "View Tasks" alone is read-only; every change also needs "Manage Tasks".
     * The server enforces it -- this only keeps the controls from offering
     * what would be refused.
     */
    const canManageTasks = usePermission(PERMISSIONS.ManageTasks);
    const { showsTasks, initialSpaceId = null, initialListId = null } = options;
    const { spaces, tasks, lists } = routes;
    const folderRoutes = routes.folders;
    const statusRoutes = routes.statuses;
    const memberRoutes = routes.spaces.members;
    const candidateRoutes = routes.spaces.memberCandidates;

    const [allSpaces, setAllSpaces] = useState<Space[]>([]);
    const [spaceId, setSpaceId] = useState<number | null>(null);
    const [folders, setFolders] = useState<Folder[]>([]);
    const [taskLists, setTaskLists] = useState<TaskList[]>([]);
    const [statuses, setStatuses] = useState<TaskStatus[]>([]);
    const [labels, setLabels] = useState<Label[]>([]);
    const [members, setMembers] = useState<SpaceMember[]>([]);
    const [listId, setListId] = useState<number | null>(null);
    const [items, setItems] = useState<Task[]>([]);
    const [total, setTotal] = useState(0);
    const [filters, setFilters] = useState<TaskFilters>(EMPTY_FILTERS);
    const [view, setView] = useState<BoardView>('list');
    const [newTaskStatusId, setNewTaskStatusId] = useState<number | null>(null);
    const [loadingSpaces, setLoadingSpaces] = useState(true);
    const [loadingSpace, setLoadingSpace] = useState(false);
    const [loadingTasks, setLoadingTasks] = useState(false);

    const debouncedSearch = useDebouncedValue(filters.search);

    /**
     * The list to reopen once its space has loaded. Consumed once: after that
     * the tree drives the selection.
     */
    const pendingListId = useRef<number | null>(initialListId);

    const report = useCallback((cause: unknown) => {
        toast.error(
            cause instanceof ApiError
                ? cause.message
                : 'Something went wrong talking to the API.',
        );
    }, []);

    useEffect(() => {
        let active = true;

        api<Wrapped<Space[]>>(spaces.index())
            .then((response) => {
                if (!active) {
                    return;
                }

                setAllSpaces(response.data);
                setSpaceId(
                    (current) =>
                        current ??
                        response.data.find(
                            (space) => space.id === initialSpaceId,
                        )?.id ??
                        response.data[0]?.id ??
                        null,
                );
            })
            .catch(report)
            .finally(() => {
                if (active) {
                    setLoadingSpaces(false);
                }
            });

        return () => {
            active = false;
        };
    }, [spaces, initialSpaceId, report]);

    useEffect(() => {
        if (spaceId === null) {
            return;
        }

        let active = true;
        setLoadingSpace(true);

        Promise.all([
            api<Wrapped<Folder[]>>(spaces.folders.index(spaceId)),
            api<Wrapped<TaskList[]>>(spaces.lists.index(spaceId)),
            api<Wrapped<TaskStatus[]>>(spaces.statuses.index(spaceId)),
            api<Wrapped<Label[]>>(spaces.labels.index(spaceId)),
            api<Wrapped<SpaceMember[]>>(spaces.members.index(spaceId)),
        ])
            .then(
                ([folderPage, listPage, statusPage, labelPage, memberPage]) => {
                    if (!active) {
                        return;
                    }

                    setFolders(folderPage.data);
                    setTaskLists(listPage.data);
                    setStatuses(statusPage.data);
                    setLabels(labelPage.data);
                    setMembers(memberPage.data);

                    const pending = pendingListId.current;
                    pendingListId.current = null;

                    setListId(
                        listPage.data.some((list) => list.id === pending)
                            ? pending
                            : null,
                    );
                },
            )
            .catch(report)
            .finally(() => {
                if (active) {
                    setLoadingSpace(false);
                }
            });

        return () => {
            active = false;
        };
    }, [spaces, spaceId, report]);

    const loadTasks = useCallback(() => {
        if (spaceId === null || !showsTasks) {
            setItems([]);
            setTotal(0);

            return;
        }

        setLoadingTasks(true);

        const query: Record<string, string | number> = {
            'filter[space_id]': spaceId,
            include: 'status,labels,assignees',
            sort: filters.sort,
            per_page: 100,
        };

        if (listId !== null) {
            query['filter[list_id]'] = listId;
        }

        if (debouncedSearch.trim() !== '') {
            query['filter[search]'] = debouncedSearch.trim();
        }

        if (filters.status_id !== '') {
            query['filter[status_id]'] = filters.status_id;
        }

        if (filters.priority !== '') {
            query['filter[priority]'] = filters.priority;
        }

        apiGet<Paginated<Task>>(tasks.index.url({ query }))
            .then((page) => {
                setItems(page.data);
                setTotal(page.meta.total);
            })
            .catch(report)
            .finally(() => setLoadingTasks(false));
    }, [
        spaceId,
        listId,
        showsTasks,
        tasks,
        debouncedSearch,
        filters.sort,
        filters.status_id,
        filters.priority,
        report,
    ]);

    useEffect(loadTasks, [loadTasks]);

    /** The list a new task lands in: the open list, or the first one available. */
    const composeListId = useMemo(
        () => listId ?? taskLists[0]?.id ?? null,
        [listId, taskLists],
    );

    /**
     * Why the composer cannot be opened, or null when it can.
     *
     * A task needs both a destination list and a status group, and a space
     * starts life with neither — so this states the reason rather than leaving
     * a dead, unexplained button in the toolbar.
     */
    const addTaskBlockedReason = useMemo((): string | null => {
        if (!canManageTasks) {
            return 'You need the Manage Tasks permission to add tasks.';
        }

        if (loadingSpaces || loadingSpace) {
            return 'Loading workspace…';
        }

        if (composeListId === null) {
            return 'Create a list first — use “+ List” in the spaces panel.';
        }

        if (statuses.length === 0) {
            return 'This space has no statuses to file a task under.';
        }

        return null;
    }, [
        canManageTasks,
        loadingSpaces,
        loadingSpace,
        composeListId,
        statuses.length,
    ]);

    const canAddTask = addTaskBlockedReason === null;

    const activeList = useMemo(
        () => taskLists.find((list) => list.id === listId) ?? null,
        [taskLists, listId],
    );

    const activeSpace = useMemo(
        () => allSpaces.find((space) => space.id === spaceId) ?? null,
        [allSpaces, spaceId],
    );

    /**
     * The tree also renders on the detail page, where picking a space or list
     * means going back to the board. The board is remounted there, so the
     * choice travels in the query string.
     */
    const goToTasks = useCallback(
        (space: number | null, list: number | null) => {
            if (!showsTasks) {
                router.visit(routes.pages.index({ query: { space, list } }));
            }
        },
        [showsTasks, routes],
    );

    const openSpace = useCallback(
        (id: number) => {
            setSpaceId(id);
            goToTasks(id, null);
        },
        [goToTasks],
    );

    const openList = useCallback(
        (id: number | null) => {
            setListId(id);
            goToTasks(spaceId, id);
        },
        [goToTasks, spaceId],
    );

    /**
     * Open the inline composer in a status group, defaulting to the first one.
     *
     * Any active filter is dropped first. The group list is replaced by an
     * empty state whenever a filter matches nothing, which leaves no composer
     * to open, and a brand new task would not match the filter anyway — so
     * clicking "Add task" under a filter used to do nothing at all.
     */
    const requestNewTask = useCallback(() => {
        const target =
            statuses.find((status) => status.is_default) ?? statuses[0];

        if (target === undefined) {
            return;
        }

        setFilters((current) =>
            current.search === '' &&
            current.status_id === '' &&
            current.priority === ''
                ? current
                : { ...current, search: '', status_id: '', priority: '' },
        );

        setNewTaskStatusId(target.id);
    }, [statuses]);

    const clearNewTask = useCallback(() => setNewTaskStatusId(null), []);

    const setFilter = useCallback(
        <K extends keyof TaskFilters>(key: K, value: TaskFilters[K]) => {
            setFilters((current) => ({ ...current, [key]: value }));
        },
        [],
    );

    const resetFilters = useCallback(() => setFilters(EMPTY_FILTERS), []);

    const bumpListCount = useCallback((id: number, delta: number) => {
        setTaskLists((current) =>
            current.map((list) =>
                list.id === id && typeof list.tasks_count === 'number'
                    ? {
                          ...list,
                          tasks_count: Math.max(0, list.tasks_count + delta),
                      }
                    : list,
            ),
        );
    }, []);

    const createSpace = useCallback(
        async (name: string) => {
            const created = await api<Wrapped<Space>>(spaces.store(), { name });

            setAllSpaces((current) => [...current, created.data]);
            setSpaceId(created.data.id);
            toast.success(`Space “${created.data.name}” created.`);
        },
        [spaces],
    );

    const createFolder = useCallback(
        async (name: string) => {
            if (spaceId === null) {
                return;
            }

            const created = await api<Wrapped<Folder>>(
                spaces.folders.store(spaceId),
                { name },
            );

            setFolders((current) => [...current, created.data]);
        },
        [spaces, spaceId],
    );

    const createList = useCallback(
        async (name: string, folderId: number | null) => {
            if (spaceId === null) {
                return;
            }

            const created = await api<Wrapped<TaskList>>(
                spaces.lists.store(spaceId),
                { name, folder_id: folderId },
            );

            setTaskLists((current) => [
                ...current,
                { ...created.data, tasks_count: 0 },
            ]);
            setListId(created.data.id);
        },
        [spaces, spaceId],
    );

    /**
     * Deleting a space takes its folders, lists and tasks with it, so fall back
     * to whatever space remains rather than leaving the board pointed at a row
     * that no longer exists.
     */
    const deleteSpace = useCallback(
        async (space: Space) => {
            await api(spaces.destroy(space.id));

            const remaining = allSpaces.filter((item) => item.id !== space.id);

            setAllSpaces(remaining);

            if (spaceId === space.id) {
                setSpaceId(remaining[0]?.id ?? null);
                setListId(null);
                setFolders([]);
                setTaskLists([]);
                setStatuses([]);
                setMembers([]);
                setItems([]);
                setTotal(0);
            }
        },
        [spaces, allSpaces, spaceId],
    );

    /** Lists inside a folder cascade away with it at the database level. */
    const deleteFolder = useCallback(
        async (folder: Folder) => {
            await api(folderRoutes.destroy(folder.id));

            const orphaned = taskLists
                .filter((item) => item.folder_id === folder.id)
                .map((item) => item.id);

            setFolders((current) =>
                current.filter((item) => item.id !== folder.id),
            );
            setTaskLists((current) =>
                current.filter((item) => item.folder_id !== folder.id),
            );

            if (listId !== null && orphaned.includes(listId)) {
                setListId(null);
            } else {
                loadTasks();
            }
        },
        [folderRoutes, taskLists, listId, loadTasks],
    );

    const deleteList = useCallback(
        async (list: TaskList) => {
            await api(lists.destroy(list.id));

            setTaskLists((current) =>
                current.filter((item) => item.id !== list.id),
            );

            if (listId === list.id) {
                setListId(null);
            } else {
                loadTasks();
            }
        },
        [lists, listId, loadTasks],
    );

    /**
     * Statuses are per-space and fully user-defined, so the board keeps them in
     * step locally rather than refetching the whole space after every edit.
     */
    const createStatus = useCallback(
        async (input: TaskStatusInput) => {
            if (spaceId === null) {
                return;
            }

            const created = await api<Wrapped<TaskStatus>>(
                spaces.statuses.store(spaceId),
                input,
            );

            setStatuses((current) =>
                sortStatuses(
                    applyDefault([...current, created.data], created.data),
                ),
            );
        },
        [spaces, spaceId],
    );

    const updateStatus = useCallback(
        async (status: TaskStatus, input: Partial<TaskStatusInput>) => {
            const updated = await api<Wrapped<TaskStatus>>(
                statusRoutes.update(status.id),
                input,
            );

            setStatuses((current) =>
                sortStatuses(
                    applyDefault(
                        current.map((item) =>
                            item.id === updated.data.id ? updated.data : item,
                        ),
                        updated.data,
                    ),
                ),
            );

            // `completed` is derived from the status type, so a retype changes
            // how existing tasks read.
            loadTasks();
        },
        [statusRoutes, loadTasks],
    );

    const deleteStatus = useCallback(
        async (status: TaskStatus) => {
            await api(statusRoutes.destroy(status.id));

            setStatuses((current) =>
                current.filter((item) => item.id !== status.id),
            );

            loadTasks();
        },
        [statusRoutes, loadTasks],
    );

    /**
     * Membership is per-space and drives who a task can be assigned to, so the
     * board keeps its copy in step rather than refetching the whole space.
     */
    const addMember = useCallback(
        async (userId: number, role: AssignableSpaceRole) => {
            if (spaceId === null) {
                return;
            }

            const created = await api<Wrapped<SpaceMember>>(
                memberRoutes.store(spaceId),
                { user_id: userId, role },
            );

            setMembers((current) => sortMembers([...current, created.data]));
        },
        [memberRoutes, spaceId],
    );

    /**
     * Search the accounts that could join the open space. The endpoint already
     * drops the owner and anyone who is a member, so the picker never offers
     * someone the API would reject.
     */
    const searchMemberCandidates = useCallback(
        async (term: string): Promise<UserSummary[]> => {
            if (spaceId === null) {
                return [];
            }

            const response = await apiGet<Wrapped<UserSummary[]>>(
                candidateRoutes.index.url(spaceId, {
                    query: { 'filter[search]': term },
                }),
            );

            return response.data;
        },
        [candidateRoutes, spaceId],
    );

    const updateMemberRole = useCallback(
        async (member: SpaceMember, role: AssignableSpaceRole) => {
            if (spaceId === null) {
                return;
            }

            const updated = await api<Wrapped<SpaceMember>>(
                memberRoutes.update([spaceId, member.id]),
                { role },
            );

            setMembers((current) =>
                current.map((item) =>
                    item.id === updated.data.id ? updated.data : item,
                ),
            );
        },
        [memberRoutes, spaceId],
    );

    const removeMember = useCallback(
        async (member: SpaceMember) => {
            if (spaceId === null) {
                return;
            }

            await api(memberRoutes.destroy([spaceId, member.id]));

            setMembers((current) =>
                current.filter((item) => item.id !== member.id),
            );

            // Removing a member clears their assignments in this space.
            loadTasks();
        },
        [memberRoutes, spaceId, loadTasks],
    );

    const createTask = useCallback(
        async (payload: Record<string, unknown>) => {
            if (composeListId === null) {
                throw new ApiError(422, 'Create a list before adding tasks.');
            }

            await api(lists.tasks.store(composeListId), payload);

            bumpListCount(composeListId, 1);
            loadTasks();
        },
        [lists, composeListId, bumpListCount, loadTasks],
    );

    const updateTask = useCallback(
        async (task: Task, payload: Record<string, unknown>) => {
            // Show the change immediately, then reconcile with the server copy.
            const rollback = items;
            const optimistic: Partial<Task> = { ...payload };

            // Completion is derived from the status, so mirror it locally or the
            // checkbox will not flip until the response lands.
            if (typeof payload.status_id === 'number') {
                const next = statuses.find(
                    (status) => status.id === payload.status_id,
                );

                if (next !== undefined) {
                    optimistic.completed = next.is_complete;
                    optimistic.status = next;
                }
            }

            setItems((current) =>
                current.map((item) =>
                    item.id === task.id ? { ...item, ...optimistic } : item,
                ),
            );

            try {
                const updated = await api<Wrapped<Task>>(
                    tasks.update(task.id, {
                        query: { include: 'status,labels,assignees' },
                    }),
                    payload,
                );

                setItems((current) =>
                    current.map((item) =>
                        item.id === task.id ? updated.data : item,
                    ),
                );
            } catch (cause) {
                setItems(rollback);

                throw cause;
            }
        },
        [tasks, items, statuses],
    );

    const deleteTask = useCallback(
        async (task: Task) => {
            await api(tasks.destroy(task.id));

            setItems((current) =>
                current.filter((item) => item.id !== task.id),
            );
            setTotal((current) => Math.max(0, current - 1));
            bumpListCount(task.list_id, -1);
        },
        [tasks, bumpListCount],
    );

    return {
        canManageTasks,
        spaces: allSpaces,
        spaceId,
        setSpaceId,
        activeSpace,
        folders,
        lists: taskLists,
        statuses,
        labels,
        members,
        listId,
        setListId,
        openSpace,
        openList,
        activeList,
        composeListId,
        canAddTask,
        addTaskBlockedReason,
        tasks: items,
        total,
        filters,
        setFilter,
        resetFilters,
        view,
        setView,
        newTaskStatusId,
        requestNewTask,
        clearNewTask,
        loadingSpaces,
        loadingSpace,
        loadingTasks,
        report,
        createSpace,
        createFolder,
        createList,
        createStatus,
        updateStatus,
        deleteStatus,
        addMember,
        searchMemberCandidates,
        updateMemberRole,
        removeMember,
        deleteSpace,
        deleteFolder,
        deleteList,
        createTask,
        updateTask,
        deleteTask,
        reload: loadTasks,
    };
}
