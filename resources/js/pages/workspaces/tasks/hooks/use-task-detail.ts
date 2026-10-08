import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { useTaskRoutes } from '../components/task-workspace';
import { api, ApiError } from '../lib/api';
import type { Task } from '../types';

type Wrapped<T> = { data: T };

/**
 * Reads and edits a single task through the module's JSON endpoints. The page
 * route hands over only an id, so everything here comes from the same API the
 * board and any integration use.
 */
export function useTaskDetail(taskId: number) {
    const routes = useTaskRoutes();
    const { tasks, lists } = routes;
    const [task, setTask] = useState<Task | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    const report = useCallback((cause: unknown) => {
        toast.error(
            cause instanceof ApiError
                ? cause.message
                : 'Something went wrong talking to the API.',
        );
    }, []);

    const load = useCallback(() => {
        let active = true;

        setLoading(true);

        api<Wrapped<Task>>(tasks.show(taskId))
            .then((response) => {
                if (active) {
                    setTask(response.data);
                }
            })
            .catch(report)
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, [tasks, taskId, report]);

    useEffect(load, [load]);

    /** Patch the task and adopt the server's copy, which recomputes `completed`. */
    const update = useCallback(
        async (payload: Record<string, unknown>) => {
            setSaving(true);

            try {
                const response = await api<Wrapped<Task>>(
                    tasks.update(taskId),
                    payload,
                );

                setTask(response.data);
            } catch (cause) {
                report(cause);
            } finally {
                setSaving(false);
            }
        },
        [tasks, taskId, report],
    );

    const addSubtask = useCallback(
        async (name: string) => {
            if (task === null) {
                return;
            }

            try {
                await api(lists.tasks.store(task.list_id), {
                    name,
                    status_id: task.status_id,
                    parent_id: task.id,
                });

                load();
            } catch (cause) {
                report(cause);
            }
        },
        [lists, task, load, report],
    );

    /** Subtasks are ordinary tasks, so patch them and refresh the tree. */
    const updateSubtask = useCallback(
        async (subtask: Task, payload: Record<string, unknown>) => {
            try {
                await api(tasks.update(subtask.id), payload);

                load();
            } catch (cause) {
                report(cause);
            }
        },
        [tasks, load, report],
    );

    const remove = useCallback(async () => {
        try {
            await api(tasks.destroy(taskId));

            router.visit(
                routes.pages.index({
                    query: { space: task?.list?.space_id, list: task?.list_id },
                }),
            );
        } catch (cause) {
            report(cause);
        }
    }, [routes, tasks, task, taskId, report]);

    return {
        task,
        loading,
        saving,
        update,
        addSubtask,
        updateSubtask,
        remove,
        report,
    };
}
