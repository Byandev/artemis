import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';

export interface ParcelJourneyState<T> {
    data: T | null;
    loading: boolean;
    error: boolean;
    refetch: () => void;
}

/**
 * Fetcher for one piece of the parcel journey page — a stat card's figure, or
 * the per-shop table. Each caller owns one of these, so a slow query skeletons
 * on its own instead of holding back the rest of the page. Cancels the
 * in-flight request when the date range (or sort, or page) changes and on
 * unmount, and exposes an error flag plus refetch for the caller's retry.
 *
 * `endpoint` is the path under `rts/parcel-journey/` — `kpi/sms-sent`, `shops`.
 */
export function useParcelJourneyData<T>(
    workspaceSlug: string,
    endpoint: string,
    params: Record<string, string | number | null | undefined>,
): ParcelJourneyState<T> {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    // Bumping the nonce re-runs the effect — that's the manual "retry".
    const [nonce, setNonce] = useState(0);
    const refetch = useCallback(() => setNonce((n) => n + 1), []);

    // Callers pass a fresh object literal every render, so the effect keys off
    // a serialised copy — depending on the object itself would refetch forever.
    const paramKey = JSON.stringify(params);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(false);

        axios
            .get<T>(
                `/api/workspaces/${workspaceSlug}/rts/parcel-journey/${endpoint}`,
                { params: JSON.parse(paramKey), signal: controller.signal },
            )
            .then((res) => setData(res.data))
            .catch((err) => {
                if (axios.isCancel(err)) return;
                console.error(`parcel journey: ${endpoint} failed`, err);
                setError(true);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [workspaceSlug, endpoint, paramKey, nonce]);

    return { data, loading, error, refetch };
}
