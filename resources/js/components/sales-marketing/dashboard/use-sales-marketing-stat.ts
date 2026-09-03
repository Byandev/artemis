import { usePage } from '@inertiajs/react';
import axios from 'axios';
import moment from 'moment';
import { useCallback, useEffect, useState } from 'react';

/**
 * The window immediately before the given one, of the same length, so "previous
 * period" always compares like with like. Worked out client-side: the page owns
 * the date range, and every endpoint stays a plain "figures for this window"
 * lookup rather than growing a comparison of its own.
 */
export function previousWindow(dateRange: string[]): string[] {
    const start = moment(dateRange[0]);
    const days = moment(dateRange[1]).diff(start, 'days') + 1;

    return [
        start.clone().subtract(days, 'days').format('YYYY-MM-DD'),
        start.clone().subtract(1, 'days').format('YYYY-MM-DD'),
    ];
}

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
 * Self-contained fetcher for a single Sales & Marketing dashboard figure. Hits
 * its own endpoint, cancels an in-flight request on unmount, and exposes an
 * error flag plus refetch so each tile renders its own skeleton and retry.
 *
 * `endpoint` is the path under `.../dashboard/` — `kpi/total-sales`,
 * `leaders/highest-ad-spend`.
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
                `/api/workspaces/${workspaceSlug}/sales-marketing/dashboard/${endpoint}`,
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
