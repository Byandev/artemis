import { useEffect, useState } from 'react';

/**
 * App routes that redirect to a freshly signed URL. Previews load the signed
 * URL the page was given; these are only the fallback for when it fails —
 * typically because the page was left open past its expiry.
 */
export function mediaRoute(workspaceSlug: string, creativeId: number): string {
    return `/workspaces/${workspaceSlug}/creatives/${creativeId}/media`;
}

export function voiceRoute(
    workspaceSlug: string,
    creativeId: number,
    reviewId: number,
): string {
    return `/workspaces/${workspaceSlug}/creatives/${creativeId}/reviews/${reviewId}/voice`;
}

/**
 * The `src` for an <img>/<video>/<audio>: the signed URL, switching once to
 * the app route if it errors. Pass the returned handler as `onError`.
 */
export function useFallbackSrc(signed: string, fallback: string) {
    const [src, setSrc] = useState(signed);

    // A reload hands the page a newly signed URL; start from that one.
    useEffect(() => setSrc(signed), [signed]);

    const onError = () =>
        setSrc((current) => (current === fallback ? current : fallback));

    return [src, onError] as const;
}
