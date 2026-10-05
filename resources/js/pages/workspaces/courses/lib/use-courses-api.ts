import { router } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';

export interface CoursesApiState<T> {
    data: T | null;
    loading: boolean;
    error: boolean;
    refetch: () => void;
}

/**
 * Fetches one section of the courses page from CourseCatalogController.
 * Cancels an in-flight request when the params change or the page unmounts,
 * so a slow response for an old search can't overwrite a newer one.
 */
export function useCoursesApi<T>(
    workspaceSlug: string,
    endpoint: '' | 'stats' | 'leaderboard',
    params?: Record<string, unknown>,
): CoursesApiState<T> {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    // Bumping the nonce re-runs the effect — that's the manual "refresh".
    const [nonce, setNonce] = useState(0);
    const refetch = useCallback(() => setNonce((n) => n + 1), []);

    // Creating, editing or deleting a course is an Inertia visit that lands
    // back on this page without remounting it, so the sections refetch after
    // any successful visit rather than keep showing the old figures.
    useEffect(() => router.on('success', () => refetch()), [refetch]);

    // Callers pass a fresh object literal every render, so the effect keys off
    // a serialised copy — depending on the object itself would refetch forever.
    const paramKey = JSON.stringify(params ?? {});

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(false);

        const path = endpoint === '' ? '' : `/${endpoint}`;

        axios
            .get<T>(`/api/workspaces/${workspaceSlug}/courses${path}`, {
                params: JSON.parse(paramKey),
                signal: controller.signal,
            })
            .then((res) => setData(res.data))
            .catch((err) => {
                if (axios.isCancel(err)) return;
                console.error(`courses: ${endpoint || 'list'} failed`, err);
                setError(true);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [workspaceSlug, endpoint, paramKey, nonce]);

    return { data, loading, error, refetch };
}
