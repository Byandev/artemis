import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';

interface Options {
    /** Board slug the endpoints hang off. */
    workspaceSlug: string;
    /** Which section to fetch: 'kpis' | 'teams' | 'team-trend'. */
    section: string;
    teamId?: number | null;
    /** Pins every section to one target; null lets each resolve the day itself. */
    targetId?: number | null;
    /** Bumped by the header's Refresh — every section refetches on change. */
    refreshKey: number;
    /** Anything else the section's endpoint takes, e.g. the trend's team id. */
    params?: Record<string, string | number>;
    /**
     * Skip the request until its inputs exist. The leader's sparkline is fetched
     * for whichever team the board ranks first, which isn't known until the team
     * rows have arrived — firing before that would ask for nothing.
     */
    enabled?: boolean;
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
 * once. The pinned target, the team filter and the Refresh button are the only
 * inputs; all of them simply refetch.
 */
export function useBoardSection<T>({
    workspaceSlug,
    section,
    teamId,
    targetId,
    refreshKey,
    params,
    enabled = true,
}: Options): State<T> & { reload: () => void } {
    const [state, setState] = useState<State<T>>({
        data: null,
        // A disabled section isn't waiting on anything, so it must not report
        // itself as loading — the header's Refresh spinner watches this.
        loading: enabled,
        failed: false,
    });

    // A fresh object every render would restart the request every render, so the
    // effect keys off the params' contents rather than their identity.
    const paramsKey = JSON.stringify(params ?? {});

    const load = useCallback(
        (signal?: AbortSignal) => {
            if (!enabled) {
                setState({ data: null, loading: false, failed: false });

                return;
            }

            setState((current) => ({ ...current, loading: true }));

            return axios
                .get(
                    `/public/workspaces/${workspaceSlug}/sales-targets/${section}`,
                    {
                        params: {
                            ...(targetId ? { id: targetId } : {}),
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
        [workspaceSlug, section, teamId, targetId, paramsKey, enabled],
    );

    useEffect(() => {
        const controller = new AbortController();
        load(controller.signal);

        return () => controller.abort();
    }, [load, refreshKey]);

    return { ...state, reload: () => load() };
}
