import axios from 'axios';
import { ImageIcon, Loader2, Sparkles } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    BTN_PRIMARY,
    CARD,
    CARD_PAD,
    FIELD_ERROR,
    LABEL,
    MUTED,
} from '../../lib/ui';
import { type PackshotResponse, type PackshotState } from '../types';

interface Props {
    baseUrl: string;
    /** Null while the brief is still a draft; set once it has been filed. */
    productResearchId: number | null;
    /**
     * The brief so far, sent when there is no productResearchId yet so the server can file
     * it — a packshot is a file and a file needs an owner.
     */
    brief: Record<string, string>;
    /** Told the id the draft was filed under, so later calls reuse it. */
    onFiled: (productResearchId: number) => void;
    name: string;
    /** "Spray · Cardiovascular", under the name. */
    chip: string;
    state: PackshotState;
    onChange: (state: PackshotState) => void;
    count: number;
    onConfigure: () => void;
}

/**
 * Step 3. Once a name is chosen this becomes the packshot panel: drop your own
 * render on the left, or generate options on the right and pick one.
 */
export default function PackshotPanel({
    baseUrl,
    productResearchId,
    brief,
    onFiled,
    name,
    chip,
    state,
    onChange,
    count,
    onConfigure,
}: Props) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [busy, setBusy] = useState<null | 'generating' | 'uploading'>(null);
    const [error, setError] = useState<string | null>(null);

    /** Either the filed brief, or the fields the server needs to file it. */
    const identity =
        productResearchId !== null
            ? { product_research_id: String(productResearchId) }
            : brief;

    function absorb(data: PackshotResponse) {
        onChange({
            packshot: data.packshot,
            packshot_options: data.packshot_options,
        });
        onFiled(data.product_research_id);
    }

    function fail(e: unknown, fallback: string) {
        const message = axios.isAxiosError(e)
            ? (e.response?.data?.message ??
              (e.response?.status === 429
                  ? 'Too many tries in a row — wait a minute.'
                  : null))
            : null;
        setError(message ?? fallback);
    }

    async function generate() {
        if (busy) return;
        setBusy('generating');
        setError(null);

        try {
            const response = await axios.post<PackshotResponse>(
                `${baseUrl}/packshots`,
                identity,
            );
            absorb(response.data);
        } catch (e) {
            fail(e, 'Could not draw the packshots. Try again.');
        } finally {
            setBusy(null);
        }
    }

    async function upload(file: File | null) {
        if (!file || busy) return;
        setBusy('uploading');
        setError(null);

        try {
            const body = new FormData();
            body.append('packshot', file);
            Object.entries(identity).forEach(([key, value]) =>
                body.append(key, value),
            );

            const response = await axios.post<PackshotResponse>(
                `${baseUrl}/packshot`,
                body,
            );
            absorb(response.data);
        } catch (e) {
            fail(e, 'Could not upload that file.');
        } finally {
            setBusy(null);
            if (fileRef.current) fileRef.current.value = '';
        }
    }

    async function pick(mediaId: number) {
        // Only reachable once options exist, which means the brief is filed.
        if (productResearchId === null || busy) return;
        setError(null);

        try {
            const response = await axios.post<PackshotState>(
                `${baseUrl}/${productResearchId}/packshot/select`,
                { media_id: mediaId },
            );
            onChange(response.data);
        } catch (e) {
            fail(e, 'Could not select that option.');
        }
    }

    return (
        <div className={`${CARD} ${CARD_PAD}`}>
            <div className="flex flex-col gap-6 sm:flex-row">
                {/* The chosen packshot, or the drop zone that sets one. */}
                <div className="shrink-0">
                    {state.packshot ? (
                        <img
                            src={state.packshot.url}
                            alt=""
                            className="h-[215px] w-[215px] rounded-[10px] border border-black/6 object-contain dark:border-white/6"
                        />
                    ) : (
                        <div
                            onClick={() => fileRef.current?.click()}
                            onDragOver={(e) => {
                                e.preventDefault();
                                setDragging(true);
                            }}
                            onDragLeave={() => setDragging(false)}
                            onDrop={(e) => {
                                e.preventDefault();
                                setDragging(false);
                                upload(e.dataTransfer.files?.[0] ?? null);
                            }}
                            className={`flex h-[215px] w-[215px] flex-col items-center justify-center gap-1.5 rounded-[10px] border border-dashed px-4 text-center transition-colors ${
                                dragging
                                    ? 'border-emerald-500 bg-emerald-500/5'
                                    : 'cursor-pointer border-black/12 bg-stone-100 hover:border-black/20 dark:border-white/12 dark:bg-zinc-800'
                            }`}
                        >
                            {busy === 'uploading' ? (
                                <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
                            ) : (
                                <ImageIcon className="h-5 w-5 text-gray-400 dark:text-gray-500" />
                            )}
                            <p className="text-[13px] text-gray-500 dark:text-gray-400">
                                Drop your own packshot
                            </p>
                            <p className="text-[12px] text-gray-400 dark:text-gray-500">
                                or{' '}
                                <span className="underline underline-offset-2">
                                    browse files
                                </span>
                            </p>
                        </div>
                    )}

                    <input
                        ref={fileRef}
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={(e) => upload(e.target.files?.[0] ?? null)}
                    />
                </div>

                <div className="min-w-0 flex-1">
                    <p className={LABEL}>Selected</p>
                    <h3 className="mt-1 text-[22px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                        {name}
                    </h3>
                    {chip && (
                        <p className="mt-0.5 font-mono text-[12px] text-gray-400 dark:text-gray-500">
                            {chip}
                        </p>
                    )}

                    <div className="mt-4 flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            onClick={generate}
                            disabled={busy !== null}
                            className={BTN_PRIMARY}
                        >
                            {busy === 'generating' ? (
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                            ) : (
                                <Sparkles className="h-3.5 w-3.5" />
                            )}
                            {busy === 'generating'
                                ? 'Drawing…'
                                : `Generate ${count} options`}
                        </button>

                        <button
                            type="button"
                            onClick={onConfigure}
                            className="text-[13px] font-medium text-gray-600 underline underline-offset-4 transition-colors hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100"
                        >
                            Configure prompt
                        </button>
                    </div>

                    <p className={`mt-3 max-w-[460px] ${MUTED}`}>
                        A studio shot of the finished product, branding and all.
                        Upload your own render instead if you have one.
                    </p>

                    {busy === 'generating' && (
                        <p className={`mt-1 ${MUTED}`}>
                            Drawing {count} options takes a minute or so.
                        </p>
                    )}

                    {error && <p className={FIELD_ERROR}>{error}</p>}
                </div>
            </div>

            {state.packshot_options.length > 0 && (
                <div className="mt-6">
                    <p className={`mb-3 ${LABEL}`}>Options</p>
                    <div className="grid grid-cols-2 gap-3.5 sm:grid-cols-4 lg:grid-cols-5">
                        {state.packshot_options.map((option) => {
                            const chosen = state.packshot?.url === option.url;

                            return (
                                <button
                                    key={option.id}
                                    type="button"
                                    onClick={() => pick(option.id)}
                                    aria-pressed={chosen}
                                    className={`overflow-hidden rounded-[10px] border bg-stone-100 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:bg-zinc-800 ${
                                        chosen
                                            ? 'border-emerald-600/50 dark:border-emerald-400/50'
                                            : 'border-black/6 hover:border-black/20 dark:border-white/6 dark:hover:border-white/20'
                                    }`}
                                >
                                    <img
                                        src={option.url}
                                        alt=""
                                        className="aspect-square w-full object-contain"
                                    />
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
