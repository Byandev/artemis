/**
 * The `?...` portion of an Inertia page url (`usePage().url`), or '' when it
 * has none. Use this rather than `window.location.search` so it works under SSR.
 */
export function queryStringOf(url: string): string {
    const i = url.indexOf('?');

    return i === -1 ? '' : url.slice(i);
}
