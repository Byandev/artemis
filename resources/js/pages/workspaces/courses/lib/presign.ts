/**
 * Shared plumbing for uploading a file straight to the bucket. The app signs a
 * URL, the browser PUTs to it, and only the resulting key comes back — so the
 * bytes never pass through nginx or PHP and no size ceiling applies.
 */

export function csrfFromCookie(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export interface PresignResponse {
    supported: boolean;
    key?: string;
    url?: string;
    headers?: Record<string, string>;
}

export async function requestPresign(
    endpoint: string,
    file: File,
    fallbackType: string,
): Promise<PresignResponse> {
    const res = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfFromCookie(),
        },
        body: JSON.stringify({
            file_name: file.name,
            content_type: file.type || fallbackType,
        }),
    });

    if (!res.ok) {
        throw new Error(
            res.status === 422
                ? 'That file type is not accepted.'
                : `Could not start the upload (${res.status}).`,
        );
    }

    return (await res.json()) as PresignResponse;
}

/**
 * PUT straight to the bucket with progress. fetch() cannot report upload
 * progress, so this is one of the few places XHR is still the right tool.
 */
export function putToBucket(
    url: string,
    headers: Record<string, string>,
    file: File,
    onProgress: (percentage: number) => void,
): Promise<void> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', url, true);

        Object.entries(headers).forEach(([key, value]) =>
            xhr.setRequestHeader(key, value),
        );

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                onProgress(Math.round((e.loaded / e.total) * 100));
            }
        };

        xhr.onload = () =>
            xhr.status >= 200 && xhr.status < 300
                ? resolve()
                : reject(
                      new Error(
                          `The bucket rejected the upload (${xhr.status}). Check the bucket's CORS rules.`,
                      ),
                  );
        xhr.onerror = () =>
            reject(
                new Error(
                    'Could not reach the bucket. Check the CORS rules allow PUT from this origin.',
                ),
            );
        xhr.onabort = () => reject(new Error('Upload cancelled.'));

        xhr.send(file);
    });
}

/**
 * Read a clip's length from the file itself. Uploads go straight to the bucket,
 * so the server never holds the bytes and can't probe them. Resolves null
 * rather than rejecting: a video the browser can't decode should still upload.
 */
export function readDuration(file: File): Promise<number | null> {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const probe = document.createElement('video');

        const done = (value: number | null) => {
            URL.revokeObjectURL(url);
            resolve(value);
        };

        probe.preload = 'metadata';
        probe.onloadedmetadata = () =>
            done(
                Number.isFinite(probe.duration)
                    ? Math.round(probe.duration)
                    : null,
            );
        probe.onerror = () => done(null);
        probe.src = url;
    });
}
