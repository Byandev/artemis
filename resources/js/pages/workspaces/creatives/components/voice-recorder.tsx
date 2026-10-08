import { clock } from '@/pages/workspaces/courses/lib/format';
import { Mic, Square, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useFallbackSrc } from './media-src';

/** Mirrors `voice_duration_seconds` headroom on StoreReviewRequest. */
const MAX_SECONDS = 300;

export interface VoiceClip {
    file: File;
    durationSeconds: number;
}

/**
 * What the browser can record, best first. Chrome/Firefox do Opus in WebM;
 * Safari only does AAC in MP4.
 */
function pickMimeType(): string {
    const candidates = [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/mp4',
        'audio/ogg;codecs=opus',
    ];
    return (
        candidates.find(
            (t) =>
                typeof MediaRecorder !== 'undefined' &&
                MediaRecorder.isTypeSupported(t),
        ) ?? ''
    );
}

const TONE = {
    button: 'border border-black/8 bg-white text-gray-600 hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700',
    recording:
        'bg-red-50 text-red-600 dark:bg-red-500/[0.12] dark:text-red-400',
    clip: 'bg-stone-100 dark:bg-zinc-800',
    muted: 'text-gray-400 dark:text-gray-500',
};

/**
 * Record a voice message in the browser, listen back, and keep or redo it.
 * Nothing leaves the page until the review form is submitted.
 */
export function VoiceRecorder({
    value,
    onChange,
    onStart,
    onRecordingChange,
}: {
    value: VoiceClip | null;
    onChange: (clip: VoiceClip | null) => void;
    /** Called as recording begins — e.g. to pause the video under review. */
    onStart?: () => void;
    /** Fires as recording starts and stops — e.g. to hold the submit button. */
    onRecordingChange?: (recording: boolean) => void;
}) {
    const t = TONE;
    const [recording, setRecording] = useState(false);
    const [elapsed, setElapsed] = useState(0);
    const [error, setError] = useState<string | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    const recorderRef = useRef<MediaRecorder | null>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const startedAtRef = useRef(0);

    // Object URLs have to be revoked or the blob leaks for the tab's lifetime.
    useEffect(() => {
        if (!value) {
            setPreviewUrl(null);
            return;
        }
        const url = URL.createObjectURL(value.file);
        setPreviewUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [value]);

    // Release the mic if the form goes away mid-recording.
    useEffect(
        () => () => {
            if (recorderRef.current?.state === 'recording') {
                recorderRef.current.stop();
            }
            streamRef.current?.getTracks().forEach((track) => track.stop());
        },
        [],
    );

    useEffect(() => {
        onRecordingChange?.(recording);
    }, [recording, onRecordingChange]);

    useEffect(() => {
        if (!recording) return;
        const id = window.setInterval(() => {
            const seconds = (Date.now() - startedAtRef.current) / 1000;
            setElapsed(seconds);
            if (seconds >= MAX_SECONDS) stop();
        }, 250);
        return () => window.clearInterval(id);
    }, [recording]);

    async function start() {
        setError(null);

        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
            setError('This browser cannot record audio.');
            return;
        }

        let stream: MediaStream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch {
            setError(
                'Microphone access was blocked. Allow it in the browser to record.',
            );
            return;
        }

        const mimeType = pickMimeType();
        const recorder = new MediaRecorder(
            stream,
            mimeType ? { mimeType } : undefined,
        );
        const chunks: Blob[] = [];

        recorder.ondataavailable = (e) => e.data.size && chunks.push(e.data);
        recorder.onstop = () => {
            stream.getTracks().forEach((track) => track.stop());
            const type = recorder.mimeType || mimeType || 'audio/webm';
            const ext = type.includes('mp4')
                ? 'm4a'
                : type.includes('ogg')
                  ? 'ogg'
                  : 'webm';
            const durationSeconds = Math.round(
                (Date.now() - startedAtRef.current) / 1000,
            );
            const blob = new Blob(chunks, { type });
            if (blob.size === 0) return;
            onChange({
                file: new File([blob], `voice-${Date.now()}.${ext}`, {
                    type: type.split(';')[0],
                }),
                durationSeconds,
            });
        };

        streamRef.current = stream;
        recorderRef.current = recorder;
        startedAtRef.current = Date.now();
        setElapsed(0);
        recorder.start();
        setRecording(true);
        onStart?.();
    }

    function stop() {
        if (recorderRef.current?.state === 'recording') {
            recorderRef.current.stop();
        }
        setRecording(false);
    }

    if (recording) {
        return (
            <div
                className={`flex h-9 items-center gap-2 rounded-lg px-2.5 ${t.recording}`}
            >
                <span className="h-2 w-2 animate-pulse rounded-full bg-red-500" />
                <span className="font-mono text-[12px] tabular-nums">
                    {clock(elapsed)}
                </span>
                <span className={`font-mono text-[10px] ${t.muted}`}>
                    / {clock(MAX_SECONDS)}
                </span>
                <div className="flex-1" />
                <button
                    type="button"
                    onClick={stop}
                    className="inline-flex h-7 items-center gap-1 rounded-md bg-red-500 px-2.5 font-mono text-[11px] font-medium text-white hover:bg-red-600"
                >
                    <Square className="h-3 w-3 fill-current" /> Stop
                </button>
            </div>
        );
    }

    if (value && previewUrl) {
        return (
            <div
                className={`flex items-center gap-2 rounded-lg p-1.5 ${t.clip}`}
            >
                <audio
                    src={previewUrl}
                    controls
                    className="h-8 min-w-0 flex-1"
                />
                <button
                    type="button"
                    onClick={() => onChange(null)}
                    aria-label="Discard voice message"
                    title="Discard and record again"
                    className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-md ${t.muted} hover:text-red-500`}
                >
                    <Trash2 className="h-3.5 w-3.5" />
                </button>
            </div>
        );
    }

    return (
        <div>
            <button
                type="button"
                onClick={start}
                className={`inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 font-mono text-[11px] font-medium transition-colors ${t.button}`}
            >
                <Mic className="h-3.5 w-3.5" /> Record voice message
            </button>
            {error && (
                <p className="mt-1 font-mono text-[11px] text-red-500">
                    {error}
                </p>
            )}
        </div>
    );
}

/** A review's saved voice message, played straight from its signed URL. */
export function VoiceNote({
    url,
    fallbackUrl,
    durationSeconds,
}: {
    url: string;
    /** App route that re-signs, for when `url` has expired. */
    fallbackUrl: string;
    durationSeconds: number | null;
}) {
    const [src, onError] = useFallbackSrc(url, fallbackUrl);

    return (
        <div className="flex items-center gap-2">
            <Mic className="h-3.5 w-3.5 shrink-0 text-violet-500" />
            <audio
                src={src}
                onError={onError}
                controls
                preload="none"
                className="h-8 min-w-0 flex-1"
            />
            {durationSeconds !== null && (
                <span className="shrink-0 font-mono text-[10px] text-gray-400 tabular-nums">
                    {clock(durationSeconds)}
                </span>
            )}
        </div>
    );
}
