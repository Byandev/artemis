import type { RouteDefinition } from './routes';

/**
 * The task API is session-authenticated and called from the browser, so every
 * request carries the CSRF header Laravel expects for a stateful request.
 */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
    }

    /** Get the first validation message for a field, if there is one. */
    public errorFor(field: string): string | undefined {
        return this.errors[field]?.[0];
    }
}

function csrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1] ?? '') : '';
}

type Method = RouteDefinition['method'];

async function request<T>(
    url: string,
    method: Method,
    body?: unknown,
): Promise<T> {
    const upload = body instanceof FormData;

    const response = await fetch(url, {
        method: method.toUpperCase(),
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            /*
             * A multipart body carries its own boundary, and only the browser
             * knows the one it generated -- setting the header by hand here
             * would name a boundary the body does not use and the server would
             * read no fields at all.
             */
            ...(upload ? {} : { 'Content-Type': 'application/json' }),
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: upload
            ? body
            : body === undefined
              ? undefined
              : JSON.stringify(body),
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const payload: unknown = await response
        .json()
        .catch(() => ({ message: response.statusText }));

    if (!response.ok) {
        const problem = payload as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        throw new ApiError(
            response.status,
            problem.message ?? 'The request failed.',
            problem.errors ?? {},
        );
    }

    return payload as T;
}

/**
 * Call an endpoint described by a route definition from `./routes`.
 *
 * Pass a `FormData` body to send a file; anything else is encoded as JSON.
 */
export function api<T>(route: RouteDefinition, body?: unknown): Promise<T> {
    return request<T>(route.url, route.method, body);
}

/** Call a GET endpoint by URL, for routes built with extra query parameters. */
export function apiGet<T>(url: string): Promise<T> {
    return request<T>(url, 'get');
}
