/**
 * URL builders for the task module, scoped to one workspace.
 *
 * Artemis's Wayfinder output is generated at build time and gitignored, so the
 * module keeps its own small table rather than depending on generated files.
 * The shapes mirror Wayfinder's (`{ url, method }`, `.url()` helpers) so the
 * hooks read the same as anywhere else.
 */
export type RouteDefinition = {
    url: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
};

type Query = Record<string, string | number | null | undefined>;
type Options = { query?: Query };

function withQuery(url: string, options?: Options): string {
    const entries = Object.entries(options?.query ?? {}).filter(
        (entry): entry is [string, string | number] =>
            entry[1] !== null && entry[1] !== undefined && entry[1] !== '',
    );

    if (entries.length === 0) {
        return url;
    }

    const search = new URLSearchParams(
        entries.map(([key, value]) => [key, String(value)]),
    );

    return `${url}?${search.toString()}`;
}

/** A route that is read with GET and can also hand back its bare URL. */
function readable(path: (options?: Options) => string) {
    const route = (options?: Options): RouteDefinition => ({
        url: path(options),
        method: 'get',
    });

    route.url = path;

    return route;
}

export function createTaskRoutes(slug: string) {
    const api = `/api/workspaces/${encodeURIComponent(slug)}/task-management`;
    const pages = `/workspaces/${encodeURIComponent(slug)}/tasks`;

    const to = (
        method: RouteDefinition['method'],
        path: string,
        options?: Options,
    ): RouteDefinition => ({
        url: withQuery(`${api}/${path}`, options),
        method,
    });

    const spaces = {
        index: () => to('get', 'spaces'),
        store: () => to('post', 'spaces'),
        show: (id: number) => to('get', `spaces/${id}`),
        update: (id: number) => to('patch', `spaces/${id}`),
        destroy: (id: number) => to('delete', `spaces/${id}`),
        folders: {
            index: (id: number) => to('get', `spaces/${id}/folders`),
            store: (id: number) => to('post', `spaces/${id}/folders`),
        },
        lists: {
            index: (id: number) => to('get', `spaces/${id}/lists`),
            store: (id: number) => to('post', `spaces/${id}/lists`),
        },
        statuses: {
            index: (id: number) => to('get', `spaces/${id}/statuses`),
            store: (id: number) => to('post', `spaces/${id}/statuses`),
        },
        labels: {
            index: (id: number) => to('get', `spaces/${id}/labels`),
            store: (id: number) => to('post', `spaces/${id}/labels`),
        },
        members: {
            index: (id: number) => to('get', `spaces/${id}/members`),
            store: (id: number) => to('post', `spaces/${id}/members`),
            update: ([spaceId, userId]: [number, number]) =>
                to('patch', `spaces/${spaceId}/members/${userId}`),
            destroy: ([spaceId, userId]: [number, number]) =>
                to('delete', `spaces/${spaceId}/members/${userId}`),
        },
        memberCandidates: {
            index: {
                url: (id: number, options?: Options) =>
                    withQuery(`${api}/spaces/${id}/member-candidates`, options),
            },
        },
    };

    const tasks = {
        index: readable((options) => withQuery(`${api}/tasks`, options)),
        show: (id: number, options?: Options) =>
            to('get', `tasks/${id}`, options),
        update: (id: number, options?: Options) =>
            to('patch', `tasks/${id}`, options),
        destroy: (id: number) => to('delete', `tasks/${id}`),
        comments: {
            index: (id: number) => to('get', `tasks/${id}/comments`),
            store: (id: number) => to('post', `tasks/${id}/comments`),
        },
        attachments: {
            index: (id: number) => to('get', `tasks/${id}/attachments`),
            store: (id: number) => to('post', `tasks/${id}/attachments`),
        },
    };

    return {
        spaces,
        folders: {
            update: (id: number) => to('patch', `folders/${id}`),
            destroy: (id: number) => to('delete', `folders/${id}`),
        },
        lists: {
            update: (id: number) => to('patch', `lists/${id}`),
            destroy: (id: number) => to('delete', `lists/${id}`),
            tasks: {
                index: (id: number) => to('get', `lists/${id}/tasks`),
                store: (id: number) => to('post', `lists/${id}/tasks`),
            },
        },
        statuses: {
            update: (id: number) => to('patch', `statuses/${id}`),
            destroy: (id: number) => to('delete', `statuses/${id}`),
        },
        labels: {
            update: (id: number) => to('patch', `labels/${id}`),
            destroy: (id: number) => to('delete', `labels/${id}`),
        },
        comments: {
            update: (id: number) => to('patch', `comments/${id}`),
            destroy: (id: number) => to('delete', `comments/${id}`),
        },
        attachments: {
            destroy: (id: number) => to('delete', `attachments/${id}`),
        },
        tasks,
        /** The Inertia pages, as plain hrefs. */
        pages: {
            index: (options?: Options) => withQuery(pages, options),
            show: (id: number) => `${pages}/${id}`,
        },
    };
}

export type TaskRoutes = ReturnType<typeof createTaskRoutes>;
