import { useCallback, useEffect, useState } from 'react';
import { useTaskRoutes } from '../components/task-workspace';
import { api } from '../lib/api';
import type { Comment } from '../types';

type Wrapped<T> = { data: T };

/**
 * Reads and writes one task's discussion through the same JSON endpoints an
 * automated client uses, so there is no page-only data path.
 *
 * Every mutation folds the server's copy back into local state rather than
 * refetching the list, which is what makes a posted comment appear without a
 * reload.
 */
export function useTaskComments(
    taskId: number,
    report: (cause: unknown) => void,
) {
    const routes = useTaskRoutes();
    const { tasks, comments } = routes;
    const [list, setList] = useState<Comment[]>([]);
    const [loading, setLoading] = useState(true);
    const [posting, setPosting] = useState(false);

    useEffect(() => {
        let active = true;

        setLoading(true);

        api<Wrapped<Comment[]>>(tasks.comments.index(taskId))
            .then((response) => {
                if (active) {
                    setList(response.data);
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

    const post = useCallback(
        async (body: string): Promise<boolean> => {
            setPosting(true);

            try {
                const response = await api<Wrapped<Comment>>(
                    tasks.comments.store(taskId),
                    { body },
                );

                setList((current) => [...current, response.data]);

                return true;
            } catch (cause) {
                report(cause);

                return false;
            } finally {
                setPosting(false);
            }
        },
        [tasks, taskId, report],
    );

    const edit = useCallback(
        async (comment: Comment, body: string): Promise<boolean> => {
            try {
                const response = await api<Wrapped<Comment>>(
                    comments.update(comment.id),
                    { body },
                );

                setList((current) =>
                    current.map((item) =>
                        item.id === comment.id ? response.data : item,
                    ),
                );

                return true;
            } catch (cause) {
                report(cause);

                return false;
            }
        },
        [comments, report],
    );

    const remove = useCallback(
        async (comment: Comment) => {
            try {
                await api(comments.destroy(comment.id));

                setList((current) =>
                    current.filter((item) => item.id !== comment.id),
                );
            } catch (cause) {
                report(cause);
            }
        },
        [comments, report],
    );

    return { comments: list, loading, posting, post, edit, remove };
}
