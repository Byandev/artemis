import { ImageUp, Loader2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { fileSize } from '../lib/format';
import { putToBucket, requestPresign } from '../lib/presign';
import { BAR, FIELD_ERROR, LABEL, TRACK_THIN } from '../lib/ui';

/** Mirrors the `mimes:` rule on CoursesController@rules. */
const ACCEPTED = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
/** Mirrors `max:10240` (kilobytes) on the same rule. */
const MAX_KB = 10240;

interface Props {
    /** Endpoint that signs a cover upload for this workspace. */
    presignUrl: string;
    /** The newly picked file, or null when nothing is staged. */
    file: File | null;
    onFileChange: (file: File | null) => void;
    /**
     * Bucket key once the file has been uploaded. Null while it is still
     * uploading, or when the disk could not sign and the file itself is posted.
     */
    onKeyChange: (key: string | null) => void;
    /** Set once the user clears a cover that is already saved. */
    removed: boolean;
    onRemovedChange: (removed: boolean) => void;
    /** The saved cover, if the course already has one. */
    existing: { file_name: string; size: number } | null;
    /** Route that signs a URL for the saved cover, for the preview. */
    existingUrl?: string;
    /** Reports whether an upload is in flight, so the form can block submit. */
    onUploadingChange: (uploading: boolean) => void;
    /** Server-side error for this field. */
    error?: string;
}

/**
 * Rejects what the server would reject anyway, but without a round trip. The
 * server still validates — this only saves the user a failed submit.
 */
function localError(file: File): string | null {
    const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
    if (!ACCEPTED.includes(ext)) {
        return `Must be ${ACCEPTED.join(', ')}.`;
    }
    if (file.size / 1024 > MAX_KB) {
        return `Too large (${fileSize(file.size)}). Max ${MAX_KB / 1024} MB.`;
    }
    return null;
}

export default function CoverImageField({
    presignUrl,
    file,
    onFileChange,
    onKeyChange,
    removed,
    onRemovedChange,
    existing,
    existingUrl,
    onUploadingChange,
    error,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [clientError, setClientError] = useState<string | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [progress, setProgress] = useState<number | null>(null);

    // Object URLs have to be revoked or the blob leaks for the tab's lifetime.
    useEffect(() => {
        if (!file) {
            setPreviewUrl(null);
            return;
        }
        const url = URL.createObjectURL(file);
        setPreviewUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    async function accept(picked: File | null) {
        if (!picked) return;

        const problem = localError(picked);
        setClientError(problem);
        if (problem) return;

        onFileChange(picked);
        onRemovedChange(false);
        onKeyChange(null);

        // Straight to the bucket as soon as it's picked, so submitting the form
        // is a small JSON request rather than a multipart upload through PHP.
        setProgress(0);
        onUploadingChange(true);

        try {
            const presign = await requestPresign(
                presignUrl,
                picked,
                'image/jpeg',
            );

            if (presign.supported && presign.url && presign.key) {
                await putToBucket(
                    presign.url,
                    presign.headers ?? {},
                    picked,
                    setProgress,
                );
                onKeyChange(presign.key);
            }
            // When the disk cannot sign, the key stays null and the form posts
            // the file itself instead.
        } catch (e) {
            setClientError(e instanceof Error ? e.message : 'Upload failed.');
            onFileChange(null);
        } finally {
            setProgress(null);
            onUploadingChange(false);
        }
    }

    function clear() {
        onFileChange(null);
        onKeyChange(null);
        setClientError(null);
        if (inputRef.current) inputRef.current.value = '';
        // Only meaningful when a saved cover is being taken away.
        if (existing) onRemovedChange(true);
    }

    // What the preview shows: a freshly picked file wins, then the saved cover
    // unless it has been cleared.
    const showExisting = !file && existing && !removed;
    const shownUrl = previewUrl ?? (showExisting ? existingUrl : null);
    const shownName = file?.name ?? (showExisting ? existing.file_name : null);
    const shownSize = file?.size ?? (showExisting ? existing.size : null);

    const message = clientError ?? error;

    return (
        <div className="space-y-1.5">
            <label className={`block ${LABEL}`}>Cover Image</label>

            {shownName ? (
                <div className="flex items-center gap-3 rounded-[10px] border border-black/8 bg-stone-50 p-2.5 dark:border-white/8 dark:bg-zinc-800">
                    {shownUrl ? (
                        <img
                            src={shownUrl}
                            alt=""
                            className="h-12 w-12 shrink-0 rounded-lg object-cover"
                        />
                    ) : (
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-gray-400 dark:bg-zinc-700">
                            <ImageUp className="h-4 w-4" />
                        </div>
                    )}

                    <div className="min-w-0 flex-1">
                        <p className="truncate font-mono text-[12px] text-gray-700 dark:text-gray-200">
                            {shownName}
                        </p>
                        <p className="font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                            {shownSize !== null ? fileSize(shownSize) : ''}
                            {file && existing ? ' · replaces current' : ''}
                        </p>

                        {progress !== null && (
                            <div className={`mt-1.5 ${TRACK_THIN}`}>
                                <div
                                    className={BAR}
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        )}
                    </div>

                    {progress !== null ? (
                        <Loader2 className="h-4 w-4 shrink-0 animate-spin text-gray-400" />
                    ) : (
                        <button
                            type="button"
                            onClick={clear}
                            aria-label="Remove cover image"
                            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-gray-400 transition-all hover:bg-stone-200 hover:text-gray-700 dark:hover:bg-zinc-700 dark:hover:text-gray-200"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )}
                </div>
            ) : (
                <div
                    onClick={() => inputRef.current?.click()}
                    onDragOver={(e) => {
                        e.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={(e) => {
                        e.preventDefault();
                        setDragging(false);
                        accept(e.dataTransfer.files?.[0] ?? null);
                    }}
                    className={`flex cursor-pointer flex-col items-center justify-center gap-1 rounded-[10px] border border-dashed px-3 py-5 transition-all ${
                        dragging
                            ? 'border-emerald-500 bg-emerald-500/5'
                            : 'border-black/12 bg-stone-50 hover:border-black/20 dark:border-white/12 dark:bg-zinc-800 dark:hover:border-white/20'
                    }`}
                >
                    <ImageUp className="h-5 w-5 text-gray-400 dark:text-gray-500" />
                    <p className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                        Drop an image or{' '}
                        <span className="text-emerald-600 dark:text-emerald-400">
                            browse
                        </span>
                    </p>
                    <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                        JPG, PNG, WEBP, HEIC · max {MAX_KB / 1024} MB
                    </p>
                </div>
            )}

            <input
                ref={inputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={(e) => accept(e.target.files?.[0] ?? null)}
            />

            {message && <p className={FIELD_ERROR}>{message}</p>}
        </div>
    );
}
