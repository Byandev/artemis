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
 * Self-contained fetcher for a single dashboard widget. Hits its own endpoint,
 * cancels an in-flight request on unmount, and exposes an explicit error flag
 * plus refetch so each widget can render its own retry button.
 */
export function useInventoryStat<T>(
    workspaceSlug: string,
    endpoint: string,
): StatState<T> {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    // Bumping the nonce re-runs the effect — that's the manual "refresh".
    const [nonce, setNonce] = useState(0);
    const refetch = useCallback(() => setNonce((n) => n + 1), []);

    // The team switcher navigates with ?team_id=…, which the server validates
    // and persists to the session — so these XHRs resolve the active team on
    // their own. This is here purely so switching teams re-runs the fetch
    // instead of leaving the widget on the previous team's numbers.
    const { teamScope } = usePage<{ teamScope: TeamScope | null }>().props;
    const activeTeamId = teamScope?.activeTeamId ?? null;

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(false);

        axios
            .get<T>(
                `/api/workspaces/${workspaceSlug}/inventory/dashboard/${endpoint}`,
                { signal: controller.signal },
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
    }, [workspaceSlug, endpoint, nonce, activeTeamId]);

    return { data, loading, error, refetch };
}
