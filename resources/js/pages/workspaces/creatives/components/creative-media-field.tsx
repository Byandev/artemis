import { fileSize } from '@/pages/workspaces/courses/lib/format';
import {
    putToBucket,
    requestPresign,
} from '@/pages/workspaces/courses/lib/presign';
import { Clapperboard, FileImage, Loader2, Upload, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CreativeMedia } from '../types';

const fl =
    'block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
const fe = 'mt-1 font-mono text-[11px] text-red-500';

interface Props {
    /** Endpoint that signs an upload for this workspace. */
    presignUrl: string;
    /** The newly picked file, or null when nothing is staged. */
    file: File | null;
    onFileChange: (file: File | null) => void;
    /**
     * Bucket key once the file has been uploaded. Null while it is still
     * uploading, or when the disk could not sign and the file itself is posted.
     */
    onKeyChange: (key: string | null) => void;
    /** Set once the user clears a file that is already saved. */
    removed: boolean;
    onRemovedChange: (removed: boolean) => void;
    /** The saved file, if the creative already has one. */
    existing: CreativeMedia | null;
    /** Route that signs a URL for the saved file, for the preview. */
    existingUrl?: string;
    /** Reports whether an upload is in flight, so the form can block submit. */
    onUploadingChange: (uploading: boolean) => void;
    error?: string;
}

function isVideo(type: string | undefined): boolean {
    return !!type?.startsWith('video/');
}

/**
 * Picks an image or video and uploads it straight to the bucket the moment it
 * is chosen, so saving the form is a small request rather than a multipart
 * upload through PHP.
 */
export function CreativeMediaField({
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

        if (!picked.type.startsWith('image/') && !isVideo(picked.type)) {
            setClientError('Must be an image or a video.');
            return;
        }

        setClientError(null);
        onFileChange(picked);
        onRemovedChange(false);
        onKeyChange(null);
        setProgress(0);
        onUploadingChange(true);

        try {
            const presign = await requestPresign(
                presignUrl,
                picked,
                'application/octet-stream',
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
        // Only meaningful when a saved file is being taken away.
        if (existing) onRemovedChange(true);
    }

    // A freshly picked file wins, then the saved one unless it was cleared.
    const showExisting = !file && existing && !removed;
    const shownUrl = previewUrl ?? (showExisting ? existingUrl : null);
    const shownName = file?.name ?? (showExisting ? existing.file_name : null);
    const shownSize = file?.size ?? (showExisting ? existing.size : null);
    const shownIsVideo = isVideo(
        file?.type ?? (showExisting ? existing.mime_type : undefined),
    );
    const Icon = shownIsVideo ? Clapperboard : FileImage;

    const message = clientError ?? error;

    return (
        <div className="space-y-1.5">
            <label className={fl}>Upload File</label>

            {shownName ? (
                <div className="flex items-center gap-3 rounded-[10px] border border-black/8 bg-stone-50 p-2.5 dark:border-white/8 dark:bg-zinc-800">
                    {shownUrl && !shownIsVideo ? (
                        <img
                            src={shownUrl}
                            alt=""
                            className="h-12 w-12 shrink-0 rounded-lg object-cover"
                        />
                    ) : shownUrl && shownIsVideo ? (
                        <video
                            src={shownUrl}
                            muted
                            preload="metadata"
                            className="h-12 w-12 shrink-0 rounded-lg bg-black object-cover"
                        />
                    ) : (
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-gray-400 dark:bg-zinc-700">
                            <Icon className="h-4 w-4" />
                        </div>
                    )}

                    <div className="min-w-0 flex-1">
                        <p className="truncate font-mono text-[12px] text-gray-700 dark:text-gray-200">
                            {shownName}
                        </p>
                        <p className="font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                            {shownSize !== null ? fileSize(shownSize) : ''}
                            {file && existing ? ' · replaces current' : ''}
                            {progress !== null ? ` · ${progress}%` : ''}
                        </p>

                        {progress !== null && (
                            <div className="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-black/6 dark:bg-white/8">
                                <div
                                    className="h-full rounded-full bg-emerald-600 transition-all"
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
                            aria-label="Remove file"
                            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-gray-400 transition-all hover:bg-stone-200 hover:text-gray-700 dark:hover:bg-zinc-700 dark:hover:text-gray-200"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )}
                </div>
            ) : (
                <div
                    role="button"
                    tabIndex={0}
                    onClick={() => inputRef.current?.click()}
                    onKeyDown={(e) =>
                        (e.key === 'Enter' || e.key === ' ') &&
                        inputRef.current?.click()
                    }
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
                    <Upload className="h-5 w-5 text-gray-400 dark:text-gray-500" />
                    <p className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                        Drop an image or video, or{' '}
                        <span className="text-emerald-600 dark:text-emerald-400">
                            browse
                        </span>
                    </p>
                </div>
            )}

            <input
                ref={inputRef}
                type="file"
                accept="image/*,video/*"
                className="hidden"
                onChange={(e) => accept(e.target.files?.[0] ?? null)}
            />

            {message && <p className={fe}>{message}</p>}
        </div>
    );
}
