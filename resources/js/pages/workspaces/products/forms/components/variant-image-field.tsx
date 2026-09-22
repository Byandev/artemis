import { ImageUp, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { FIELD_ERROR } from '../lib/ui';
import { type VariantImage } from '../types';

/** Mirrors the `mimes:` rule on FormController@rules. */
const ACCEPTED = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
/** Mirrors `max:10240` (kilobytes) on the same rule. */
const MAX_KB = 10240;

interface Props {
    /** The newly picked file, or null when nothing is staged. */
    file: File | null;
    onFileChange: (file: File | null) => void;
    /** The picture already saved on this size, if any. */
    existing: VariantImage | null;
    /** Set once the user clears a picture that is already saved. */
    removed: boolean;
    onRemovedChange: (removed: boolean) => void;
    /** Server-side error for this size's image. */
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
        return `Too large. Max ${MAX_KB / 1024} MB.`;
    }
    return null;
}

/**
 * The picture for one size. The file is held in the form state and posted with
 * the rest of it — small enough that a presigned upload straight to the bucket
 * would cost more complexity than it saves.
 */
export default function VariantImageField({
    file,
    onFileChange,
    existing,
    removed,
    onRemovedChange,
    error,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [clientError, setClientError] = useState<string | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

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

    function accept(picked: File | null) {
        if (!picked) return;

        const problem = localError(picked);
        setClientError(problem);
        if (problem) return;

        onFileChange(picked);
        onRemovedChange(false);
    }

    function clear() {
        onFileChange(null);
        setClientError(null);
        if (inputRef.current) inputRef.current.value = '';
        // Only meaningful when a saved picture is being taken away.
        if (existing) onRemovedChange(true);
    }

    // A freshly picked file wins, then the saved picture unless it was cleared.
    const shownUrl = previewUrl ?? (!removed ? (existing?.url ?? null) : null);
    const message = clientError ?? error;

    return (
        <div className="shrink-0">
            {shownUrl ? (
                <div className="group relative h-[88px] w-[88px]">
                    <img
                        src={shownUrl}
                        alt=""
                        className="h-full w-full rounded-[10px] border border-black/8 object-cover dark:border-white/8"
                    />
                    <button
                        type="button"
                        onClick={clear}
                        aria-label="Remove image"
                        className="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full border border-black/8 bg-white text-gray-500 shadow-sm transition-all hover:text-gray-800 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-gray-100"
                    >
                        <X className="h-3 w-3" />
                    </button>
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
                    className={`flex h-[88px] w-[88px] cursor-pointer flex-col items-center justify-center gap-0.5 rounded-[10px] border border-dashed px-2 text-center transition-all ${
                        dragging
                            ? 'border-emerald-500 bg-emerald-500/5'
                            : 'border-black/15 bg-stone-50 hover:border-black/25 dark:border-white/15 dark:bg-zinc-800 dark:hover:border-white/25'
                    }`}
                >
                    <ImageUp className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                    <p className="text-[11px] leading-tight font-medium text-gray-500 dark:text-gray-400">
                        Drop image
                    </p>
                    <p className="text-[10px] leading-tight text-gray-400 dark:text-gray-500">
                        or{' '}
                        <span className="underline decoration-dotted">
                            browse files
                        </span>
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

            {message && (
                <p className={`mt-1 max-w-[88px] ${FIELD_ERROR}`}>{message}</p>
            )}
        </div>
    );
}
