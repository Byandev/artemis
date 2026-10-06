import { csrfFromCookie } from '@/pages/workspaces/courses/lib/presign';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * A creative file is uploaded to the bucket the moment it's picked, but only
 * becomes the creative's on save. Until then it's "pending", and if the form
 * is abandoned it would sit in the bucket unreferenced. This deletes it as
 * soon as it's clear it won't be saved:
 *
 * - replaced by another file, or cleared → deleted immediately;
 * - in-app navigation away → a "Discard upload?" prompt, deleted on confirm;
 * - tab closed / refreshed → the browser's own leave prompt, then a
 *   best-effort delete as the page goes away;
 * - the form unmounting any other way (e.g. browser Back inside the app,
 *   which Inertia doesn't route through its `before` event) → deleted.
 *
 * Whatever still slips through (a crash, a request that never lands) is swept
 * by `creatives:prune-pending-uploads`.
 */
export function usePendingUploadGuard({
    discardUrl,
    uploading,
    onDiscardedWhileHidden,
}: {
    /** DELETE endpoint that removes a pending upload by key. */
    discardUrl: string;
    /** An upload is still in flight — leaving would abandon it too. */
    uploading: boolean;
    /** The page came back from the back/forward cache after its upload was discarded. */
    onDiscardedWhileHidden: () => void;
}) {
    const pendingKey = useRef<string | null>(null);
    const uploadingRef = useRef(uploading);
    const discardedOnHide = useRef(false);
    const unmounted = useRef(false);
    const onDiscardedRef = useRef(onDiscardedWhileHidden);
    const [blockedUrl, setBlockedUrl] = useState<string | null>(null);

    uploadingRef.current = uploading;
    onDiscardedRef.current = onDiscardedWhileHidden;

    const discard = useCallback(
        (key: string) => {
            // keepalive lets the request outlive the page when it's closing.
            void fetch(discardUrl, {
                method: 'DELETE',
                keepalive: true,
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': csrfFromCookie(),
                },
                body: JSON.stringify({ key }),
            }).catch(() => {
                // Best effort — the prune command is the backstop.
            });
        },
        [discardUrl],
    );

    /** Track the current pending key; one it replaces is deleted right away. */
    const setPendingKey = useCallback(
        (key: string | null) => {
            const previous = pendingKey.current;
            if (previous && previous !== key) discard(previous);
            // An upload that finishes after the form is gone (the user left
            // mid-upload) has nothing left to save it — delete it on arrival.
            if (unmounted.current) {
                if (key) discard(key);
                return;
            }
            pendingKey.current = key;
        },
        [discard],
    );

    /** The save went through: the upload is the creative's now, not pending. */
    const markSaved = useCallback(() => {
        pendingKey.current = null;
    }, []);

    const hasUnsaved = () => !!pendingKey.current || uploadingRef.current;

    // In-app navigation (links, Cancel, Back to Creatives): hold it and ask.
    useEffect(
        () =>
            router.on('before', (event) => {
                const { visit } = event.detail;
                // The form's own submit is a POST/PUT; prefetches, partial
                // reloads and reloads of this same page aren't leaving it.
                if (visit.method !== 'get' || visit.prefetch) return;
                if (visit.only.length || visit.except.length) return;
                if (visit.url.pathname === window.location.pathname) return;
                if (!hasUnsaved()) return;
                event.preventDefault();
                setBlockedUrl(visit.url.href);
            }),
        [],
    );

    // Tab close / refresh / leaving the app: the browser's own prompt…
    useEffect(() => {
        const onBeforeUnload = (e: BeforeUnloadEvent) => {
            if (!hasUnsaved()) return;
            e.preventDefault();
            // Older browsers need returnValue set to show the prompt.
            e.returnValue = '';
        };
        // …then, once the page is actually going, delete the upload.
        const onPageHide = () => {
            if (!pendingKey.current) return;
            discard(pendingKey.current);
            pendingKey.current = null;
            discardedOnHide.current = true;
        };
        // Back/forward cache can restore the form with its now-deleted key.
        const onPageShow = (e: PageTransitionEvent) => {
            if (e.persisted && discardedOnHide.current) {
                discardedOnHide.current = false;
                onDiscardedRef.current();
            }
        };

        window.addEventListener('beforeunload', onBeforeUnload);
        window.addEventListener('pagehide', onPageHide);
        window.addEventListener('pageshow', onPageShow);
        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            window.removeEventListener('pagehide', onPageHide);
            window.removeEventListener('pageshow', onPageShow);
        };
    }, [discard]);

    // Any other way the form goes away with an unsaved upload.
    useEffect(() => {
        unmounted.current = false;
        return () => {
            unmounted.current = true;
            if (pendingKey.current) discard(pendingKey.current);
            pendingKey.current = null;
        };
    }, [discard]);

    /** "Discard & leave": delete the upload, then carry on to where they were going. */
    const confirmLeave = () => {
        const url = blockedUrl;
        if (pendingKey.current) discard(pendingKey.current);
        pendingKey.current = null;
        // Let the held visit through even if an upload is still in flight.
        uploadingRef.current = false;
        setBlockedUrl(null);
        if (url) router.visit(url);
    };

    const cancelLeave = () => setBlockedUrl(null);

    return {
        setPendingKey,
        markSaved,
        leavePromptOpen: blockedUrl !== null,
        confirmLeave,
        cancelLeave,
    };
}
