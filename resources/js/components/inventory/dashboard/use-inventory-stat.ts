import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';

export interface DashboardRange {
    start: string;
    end: string;
}

export interface StatState<T> {
    data: T | null;
    loading: boolean;
    error: boolean;
    refetch: () => void;
}

/**
 * Self-contained fetcher for a single dashboard widget. Hits its own endpoint,
 * cancels in-flight requests on range change/unmount, and exposes an explicit
 * error flag + refetch so each panel can render its own retry button.
 */
export function useInventoryStat<T>(
    workspaceSlug: string,
    endpoint: string,
    range: DashboardRange,
): StatState<T> {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    // Bumping the nonce re-runs the effect — that's the manual "refresh".
    const [nonce, setNonce] = useState(0);
    const refetch = useCallback(() => setNonce((n) => n + 1), []);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(false);

        axios
            .get<T>(
                `/api/workspaces/${workspaceSlug}/inventory/dashboard/${endpoint}`,
                { params: range, signal: controller.signal },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (axios.isCancel(err)) return;
                console.error(`inventory dashboard: ${endpoint} failed`, err);
                setError(true);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
        // Depend on the range's primitives, not the object identity.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [workspaceSlug, endpoint, range.start, range.end, nonce]);

    return { data, loading, error, refetch };
}
