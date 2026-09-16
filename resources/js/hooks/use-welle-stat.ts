import { useEffect, useState } from 'react';

/**
 * One My ESC stat card's figures.
 *
 * Each card has its own endpoint, so each gets its own request and its own
 * loading flag — a slow count skeletons without holding up the card beside it.
 *
 * Takes a finished URL rather than a stat name so the call site can build it
 * from the Wayfinder action, which keeps the route in one place: see
 * `@/actions/App/Http/Controllers/API/Workspace/WelleStatsController`.
 *
 * The AbortController is what keeps a fast sequence of changes from landing out
 * of order — the last URL asked for is the one shown.
 */
export function useWelleStat<T>(url: string): [T | null, boolean] {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);

        fetch(url, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((response) => {
                if (!response.ok) throw new Error(String(response.status));
                return response.json();
            })
            .then((payload: T) => {
                setData(payload);
                setLoading(false);
            })
            .catch((error: Error) => {
                // An abort is this effect superseding itself — the newer
                // request owns the loading flag now.
                if (error.name === 'AbortError') return;
                setLoading(false);
            });

        return () => controller.abort();
    }, [url]);

    return [data, loading];
}
