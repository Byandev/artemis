import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';

export interface StatState {
    value: number | null;
    loading: boolean;
    error: boolean;
    refetch: () => void;
}

/**
 * Self-contained fetcher for a single dashboard KPI. Hits its own endpoint,
 * cancels an in-flight request on unmount, and exposes an explicit error flag
 * plus refetch so each tile can render its own retry button.
 */
export function useInventoryStat(
    workspaceSlug: string,
    endpoint: string,
): StatState {
    const [value, setValue] = useState<number | null>(null);
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
            .get<{ value: number }>(
                `/api/workspaces/${workspaceSlug}/inventory/dashboard/kpi/${endpoint}`,
                { signal: controller.signal },
            )
            .then((res) => setValue(res.data.value))
            .catch((err) => {
                if (axios.isCancel(err)) return;
                console.error(`inventory dashboard: ${endpoint} failed`, err);
                setError(true);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [workspaceSlug, endpoint, nonce]);

    return { value, loading, error, refetch };
}
