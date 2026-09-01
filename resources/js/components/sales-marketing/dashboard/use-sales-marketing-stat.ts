import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';

interface TeamScope {
    activeTeamId: number | null;
}

export interface StatState<T> {
    data: T | null;
    loading: boolean;
    error: boolean;
    refetch: () => void;
}

/**
 * Self-contained fetcher for a single Sales & Marketing dashboard KPI. Hits its
 * own endpoint, cancels an in-flight request on unmount, and exposes an error
 * flag plus refetch so each tile renders its own skeleton and retry.
 */
export function useSalesMarketingStat<T>(
    workspaceSlug: string,
    endpoint: string,
    params?: Record<string, string | number>,
): StatState<T> {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    // Bumping the nonce re-runs the effect — that's the manual "retry".
    const [nonce, setNonce] = useState(0);
    const refetch = useCallback(() => setNonce((n) => n + 1), []);

    // The team switcher persists its choice to the session, so these XHRs
    // resolve the active team on their own. This is here purely so switching
    // teams re-runs the fetch instead of leaving the tile on stale numbers.
    const { teamScope } = usePage<{ teamScope: TeamScope | null }>().props;
    const activeTeamId = teamScope?.activeTeamId ?? null;

    // Callers pass a fresh object literal every render, so the effect keys off
    // a serialised copy — depending on the object itself would refetch forever.
    const paramKey = JSON.stringify(params ?? {});

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(false);

        axios
            .get<T>(
                `/api/workspaces/${workspaceSlug}/sales-marketing/dashboard/kpi/${endpoint}`,
                { params: JSON.parse(paramKey), signal: controller.signal },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (axios.isCancel(err)) return;
                console.error(`s&m dashboard: ${endpoint} failed`, err);
                setError(true);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [workspaceSlug, endpoint, paramKey, nonce, activeTeamId]);

    return { data, loading, error, refetch };
}
