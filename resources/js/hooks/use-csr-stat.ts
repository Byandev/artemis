import { useEffect, useState } from 'react';

/**
 * One CSR stat card's figures.
 *
 * Each card has its own endpoint, so each gets its own request and its own
 * loading flag. The AbortController is what keeps a fast sequence of date
 * changes from landing out of order — the last range picked is the one shown.
 *
 * Shared by the CSR analytics page (the workspace's figures) and the CSR
 * dashboard (the signed-in CSR's own): both read `/csrs/stats/{stat}` and both
 * answer in the same `value` / `previous_value` / `change` shape.
 */
export function useCsrStat<T>(
    workspaceSlug: string,
    stat: string,
    from: string,
    to: string,
    /** Anything else the endpoint needs — the comparison's metric, say. */
    extra?: Record<string, string>,
): [T | null, boolean] {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);
    // The effect compares its dependencies by identity, and an object literal
    // is a new one on every render; the serialised form is what actually says
    // whether the request has changed.
    const extraKey = JSON.stringify(extra ?? {});

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);

        const params = new URLSearchParams({
            from,
            to,
            ...(JSON.parse(extraKey) as Record<string, string>),
        });

        fetch(
            `/api/workspaces/${workspaceSlug}/csrs/stats/${stat}?${params.toString()}`,
            {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            },
        )
            .then((response) => {
                if (!response.ok) throw new Error(String(response.status));
                return response.json();
            })
            .then((payload: T) => {
                setData(payload);
                setLoading(false);
            })
            .catch((error: Error) => {
                // An abort is this effect superseding itself — the newer request
                // owns the loading flag now.
                if (error.name === 'AbortError') return;
                setLoading(false);
            });

        return () => controller.abort();
    }, [workspaceSlug, stat, from, to, extraKey]);

    return [data, loading];
}
