export interface RequestOptions {
    body?: unknown;
    csrfToken?: string;
}

export const request = async (method: string, url: string, options: RequestOptions = {}) => {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    // Send CSRF token on all requests to satisfy Laravel/Sanctum protection,
    // even when there's no JSON body (DELETE/GET with query params).
    if (options.csrfToken) {
        headers['X-CSRF-TOKEN'] = options.csrfToken;
    }

    const res = await fetch(url, {
        method,
        credentials: 'include',
        headers,
        body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    });

    const text = await res.text();
    let json: unknown = {};
    if (text) {
        try {
            json = JSON.parse(text);
        } catch (err) {
            json = { message: text };
        }
    }

    return { res, json } as const;
};
