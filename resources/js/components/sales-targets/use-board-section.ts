import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';

interface Options {
    /** Board slug the endpoints hang off. */
    workspaceSlug: string;
    /** Which section to fetch: 'kpis' | 'leader' | 'teams' | 'leaderboard'. */
    section: string;
    teamId?: number | null;
    /** Bumped by the header's Refresh — every section refetches on change. */
    refreshKey: number;
    /** Anything else the section's endpoint takes, e.g. the leaderboard's limit. */
    params?: Record<string, string | number>;
}

interface State<T> {
    data: T | null;
    loading: boolean;
    failed: boolean;
}

/**
 * Loads one section of the board from its own endpoint.
 *
 * Each section owns its request, so a slow or failing one doesn't hold up or
 * blank the others — the board degrades a panel at a time rather than all at
 * once. The team filter and the Refresh button are the only inputs; both simply
 * refetch.
 */
export function useBoardSection<T>({
    workspaceSlug,
    section,
    teamId,
    refreshKey,
    params,
}: Options): State<T> & { reload: () => void } {
    const [state, setState] = useState<State<T>>({
        data: null,
        loading: true,
        failed: false,
    });

    // A fresh object every render would restart the request every render, so the
    // effect keys off the params' contents rather than their identity.
    const paramsKey = JSON.stringify(params ?? {});

    const load = useCallback(
        (signal?: AbortSignal) => {
            setState((current) => ({ ...current, loading: true }));

            return axios
                .get(
                    `/public/workspaces/${workspaceSlug}/sales-targets/${section}`,
                    {
                        params: {
                            ...(teamId ? { team_id: teamId } : {}),
                            ...JSON.parse(paramsKey),
                        },
                        signal,
                    },
                )
                .then(({ data }) =>
                    setState({
                        data: data.data ?? null,
                        loading: false,
                        failed: false,
                    }),
                )
                .catch((error) => {
                    // An aborted request is a newer one taking over, not a failure.
                    if (axios.isCancel(error)) return;

                    setState({ data: null, loading: false, failed: true });
                });
        },
        [workspaceSlug, section, teamId, paramsKey],
    );

    useEffect(() => {
        const controller = new AbortController();
        load(controller.signal);

        return () => controller.abort();
    }, [load, refreshKey]);

    return { ...state, reload: () => load() };
}
