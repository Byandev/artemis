import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ExternalLink,
    Eye,
    EyeOff,
    MapPin,
    Maximize,
    MessageSquare,
    Pause,
    Play,
    Trash2,
    Volume2,
    VolumeX,
    X,
} from 'lucide-react';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { ReviewBadge } from './components/atoms';
import { DeleteReviewDialog } from './components/delete-review-dialog';
import { ImageAnnotator, Mark } from './components/image-annotator';
import { mediaRoute, voiceRoute } from './components/media-src';
import { formatClock } from './components/review-timestamp';
import {
    VoiceClip,
    VoiceNote,
    VoiceRecorder,
} from './components/voice-recorder';
import {
    Creative,
    REVIEW_AVATAR_BG,
    REVIEW_STATUS_LABELS,
    REVIEW_STATUS_OPTIONS,
    Region,
    Review,
    ReviewStatus,
} from './types';

/** How long a timestamped review stays "on screen" once playback reaches it. */
const ACTIVE_WINDOW_SECONDS = 3;

function initialOf(review: Review): string {
    return (review.reviewer?.name ?? '?').charAt(0).toUpperCase();
}

export default function CreativeReview({
    workspace,
    creative,
}: {
    workspace: Workspace;
    creative: Creative;
}) {
    const baseUrl = `/workspaces/${workspace.slug}/creatives`;
    // Stream straight from the signed URL the page was given. If it lapses
    // (the page was left open for hours) fall back to the media route, which
    // signs a fresh one.
    const routeUrl = mediaRoute(workspace.slug, creative.id);
    const [mediaUrl, setMediaUrl] = useState(creative.media?.url ?? routeUrl);
    const voiceSources = (review: Review) =>
        review.voice
            ? {
                  url: review.voice.url,
                  fallbackUrl: voiceRoute(
                      workspace.slug,
                      creative.id,
                      review.id,
                  ),
              }
            : undefined;
    const { props } = usePage<{ auth?: { user?: { id: number } } }>();
    const currentUserId = props.auth?.user?.id ?? 0;

    // Only reviewers assigned to this specific creative may submit a review,
    // even if they hold the Review Creatives permission.
    const canReview = usePermission(PERMISSIONS.ReviewCreatives);
    const canSubmitReview =
        canReview &&
        creative.assigned_reviewers.some((r) => r.id === currentUserId);

    const isVideo = !!creative.media?.mime_type.startsWith('video/');
    const isImage = !!creative.media?.mime_type.startsWith('image/');

    // ─── Player state ─────────────────────────────────────────────────────
    const videoRef = useRef<HTMLVideoElement>(null);
    const stageRef = useRef<HTMLDivElement>(null);
    const [playing, setPlaying] = useState(false);
    const [muted, setMuted] = useState(false);
    const [time, setTime] = useState(0);
    const [duration, setDuration] = useState(0);

    const seek = (seconds: number, { pause = true } = {}) => {
        const video = videoRef.current;
        if (!video) return;
        video.currentTime = Math.max(0, Math.min(seconds, duration || seconds));
        setTime(video.currentTime);
        // Jumping to a note means looking at that frame — hold it there.
        if (pause) video.pause();
    };

    const togglePlay = () => {
        const video = videoRef.current;
        if (!video) return;
        if (video.paused) void video.play();
        else video.pause();
    };

    // Space / ← / → drive the player, except while typing a review.
    useEffect(() => {
        if (!isVideo) return;
        const onKey = (e: KeyboardEvent) => {
            const target = e.target as HTMLElement;
            if (
                target.closest(
                    'input, textarea, [contenteditable], [role="combobox"]',
                )
            ) {
                return;
            }
            const video = videoRef.current;
            if (!video) return;
            if (e.code === 'Space') {
                e.preventDefault();
                togglePlay();
            } else if (e.code === 'ArrowLeft') {
                seek(video.currentTime - 5, { pause: false });
            } else if (e.code === 'ArrowRight') {
                seek(video.currentTime + 5, { pause: false });
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
        // seek/togglePlay only read the ref and duration.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isVideo, duration]);

    // ─── Reviews ──────────────────────────────────────────────────────────
    const timestamped = useMemo(
        () =>
            creative.reviews
                .filter((r) => r.timestamp_seconds !== null)
                .sort((a, b) => a.timestamp_seconds! - b.timestamp_seconds!),
        [creative.reviews],
    );
    const general = useMemo(
        () =>
            creative.reviews
                .filter((r) => r.timestamp_seconds === null && !r.region)
                .reverse(),
        [creative.reviews],
    );

    // ─── Image markup ─────────────────────────────────────────────────────
    // Numbered oldest first, so a mark keeps its number as reviews are added.
    const marks: Mark[] = useMemo(
        () =>
            creative.reviews
                .filter((r) => r.region)
                .map((r, i) => ({
                    id: r.id,
                    n: i + 1,
                    region: r.region!,
                    status: r.status,
                })),
        [creative.reviews],
    );
    const marked = useMemo(
        () =>
            marks.map((m) => ({
                mark: m,
                review: creative.reviews.find((r) => r.id === m.id)!,
            })),
        [marks, creative.reviews],
    );
    const [region, setRegion] = useState<Region | null>(null);
    const [hoveredId, setHoveredId] = useState<number | null>(null);
    const [showMarks, setShowMarks] = useState(true);
    const [deleting, setDeleting] = useState<Review | null>(null);

    // Only the reviewer who wrote a review may delete it (as with editing).
    const deleteHandler = (review: Review) =>
        review.reviewer?.id === currentUserId
            ? () => setDeleting(review)
            : undefined;

    const selectMark = (id: number) => {
        setHoveredId(id);
        cardRefs.current
            .get(id)
            ?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    // The notes playback is currently passing over.
    const activeIds = useMemo(
        () =>
            new Set(
                timestamped
                    .filter(
                        (r) =>
                            time >= r.timestamp_seconds! - 0.1 &&
                            time < r.timestamp_seconds! + ACTIVE_WINDOW_SECONDS,
                    )
                    .map((r) => r.id),
            ),
        [timestamped, time],
    );
    const active = timestamped.filter((r) => activeIds.has(r.id));

    // Keep the note being played in view in the sidebar.
    const cardRefs = useRef(new Map<number, HTMLDivElement>());
    const firstActiveId = active[0]?.id;
    useEffect(() => {
        if (!playing || firstActiveId === undefined) return;
        cardRefs.current
            .get(firstActiveId)
            ?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, [firstActiveId, playing]);

    return (
        <>
            <Head title={`Review · ${creative.name}`} />
            <div className="fixed inset-0 z-50 flex flex-col bg-stone-50 text-gray-900 lg:flex-row dark:bg-zinc-950 dark:text-gray-100">
                {/* ─── Stage ───────────────────────────────────────────── */}
                <main className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <header className="flex shrink-0 items-center gap-3 border-b border-black/6 bg-white px-4 py-3 dark:border-white/6 dark:bg-transparent">
                        <Link
                            href={baseUrl}
                            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-stone-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/8 dark:hover:text-gray-100"
                            aria-label="Back to Creatives"
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-[14px] font-semibold">
                                {creative.name}
                            </p>
                            <p className="truncate font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                {[
                                    creative.code,
                                    creative.product?.title,
                                    creative.creator?.name,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        </div>
                        {creative.latest_review && (
                            <ReviewBadge
                                status={creative.latest_review.status}
                            />
                        )}
                    </header>

                    <div
                        ref={stageRef}
                        className={`relative flex min-h-[240px] flex-1 items-center justify-center ${isVideo ? 'bg-black' : 'bg-stone-200 dark:bg-black'}`}
                    >
                        {isVideo ? (
                            <>
                                <video
                                    ref={videoRef}
                                    src={mediaUrl}
                                    // Start buffering on open, not on the
                                    // first press of play.
                                    preload="auto"
                                    playsInline
                                    onError={() => {
                                        if (mediaUrl !== routeUrl) {
                                            const at =
                                                videoRef.current?.currentTime ??
                                                0;
                                            setMediaUrl(routeUrl);
                                            requestAnimationFrame(() =>
                                                seek(at),
                                            );
                                        }
                                    }}
                                    onClick={togglePlay}
                                    onPlay={() => setPlaying(true)}
                                    onPause={() => setPlaying(false)}
                                    onTimeUpdate={(e) =>
                                        setTime(e.currentTarget.currentTime)
                                    }
                                    onLoadedMetadata={(e) =>
                                        setDuration(
                                            e.currentTarget.duration || 0,
                                        )
                                    }
                                    className="absolute inset-0 h-full w-full cursor-pointer object-contain"
                                />
                            </>
                        ) : isImage ? (
                            <>
                                <ImageAnnotator
                                    src={mediaUrl}
                                    onError={() =>
                                        mediaUrl !== routeUrl &&
                                        setMediaUrl(routeUrl)
                                    }
                                    alt={creative.name}
                                    marks={marks}
                                    draft={region}
                                    onDraw={
                                        canSubmitReview ? setRegion : undefined
                                    }
                                    highlightId={hoveredId}
                                    onHover={setHoveredId}
                                    onSelect={selectMark}
                                    showMarks={showMarks}
                                />
                                {marks.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => setShowMarks((v) => !v)}
                                        className="absolute top-3 right-3 inline-flex items-center gap-1.5 rounded-lg bg-black/60 px-2.5 py-1.5 font-mono text-[11px] text-gray-200 backdrop-blur hover:bg-black/80"
                                    >
                                        {showMarks ? (
                                            <EyeOff className="h-3.5 w-3.5" />
                                        ) : (
                                            <Eye className="h-3.5 w-3.5" />
                                        )}
                                        {showMarks
                                            ? 'Hide marks'
                                            : 'Show marks'}
                                    </button>
                                )}
                            </>
                        ) : (
                            <div className="px-6 text-center">
                                <p className="font-mono text-[12px] text-gray-500 dark:text-gray-500">
                                    No file uploaded to Artemis for this
                                    creative.
                                </p>
                                {creative.picture_url && (
                                    <a
                                        href={creative.picture_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 font-mono text-[12px] text-gray-700 shadow-sm hover:bg-stone-50 dark:bg-white/8 dark:text-gray-200 dark:shadow-none dark:hover:bg-white/12"
                                    >
                                        Open media link{' '}
                                        <ExternalLink className="h-3.5 w-3.5" />
                                    </a>
                                )}
                            </div>
                        )}
                    </div>

                    {isVideo && (
                        <Controls
                            playing={playing}
                            muted={muted}
                            time={time}
                            duration={duration}
                            reviews={timestamped}
                            activeIds={activeIds}
                            onTogglePlay={togglePlay}
                            onToggleMute={() => {
                                const video = videoRef.current;
                                if (!video) return;
                                video.muted = !video.muted;
                                setMuted(video.muted);
                            }}
                            onSeek={(s) => seek(s, { pause: !playing })}
                            onJump={(s) => seek(s)}
                            onFullscreen={() =>
                                void stageRef.current?.requestFullscreen?.()
                            }
                        />
                    )}
                </main>

                {/* ─── Sidebar ─────────────────────────────────────────── */}
                <aside className="flex max-h-[55vh] min-h-0 w-full shrink-0 flex-col border-t border-black/6 bg-white lg:max-h-none lg:w-[400px] lg:border-t-0 lg:border-l dark:border-white/6 dark:bg-zinc-900">
                    <div className="flex shrink-0 items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                        <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Reviews · {creative.review_count}
                        </p>
                        {isVideo && (
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-600">
                                Space play · ←/→ 5s
                            </p>
                        )}
                        {isImage && canSubmitReview && (
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-600">
                                Drag to mark an area · click for a point
                            </p>
                        )}
                    </div>

                    <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4">
                        {creative.reviews.length === 0 && (
                            <div className="py-10 text-center">
                                <MessageSquare className="mx-auto h-6 w-6 text-gray-300 dark:text-gray-700" />
                                <p className="mt-2 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                    No reviews yet
                                </p>
                                {canSubmitReview && isVideo && (
                                    <p className="mt-0.5 font-mono text-[10px] text-gray-400 dark:text-gray-600">
                                        Pause on a frame and leave a note below.
                                    </p>
                                )}
                                {canSubmitReview && isImage && (
                                    <p className="mt-0.5 font-mono text-[10px] text-gray-400 dark:text-gray-600">
                                        Mark an area on the image and leave a
                                        note below.
                                    </p>
                                )}
                            </div>
                        )}

                        {timestamped.length > 0 && (
                            <div className="space-y-2">
                                <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-600">
                                    On the timeline
                                </p>
                                {timestamped.map((r) => (
                                    <ReviewCard
                                        key={r.id}
                                        review={r}
                                        voice={voiceSources(r)}
                                        onDelete={deleteHandler(r)}
                                        active={activeIds.has(r.id)}
                                        onJump={
                                            isVideo
                                                ? () =>
                                                      seek(r.timestamp_seconds!)
                                                : undefined
                                        }
                                        cardRef={(el) => {
                                            if (el)
                                                cardRefs.current.set(r.id, el);
                                            else cardRefs.current.delete(r.id);
                                        }}
                                    />
                                ))}
                            </div>
                        )}

                        {marked.length > 0 && (
                            <div className="space-y-2">
                                <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-600">
                                    On the image
                                </p>
                                {marked.map(({ mark, review }) => (
                                    <div
                                        key={review.id}
                                        onMouseEnter={() =>
                                            setHoveredId(review.id)
                                        }
                                        onMouseLeave={() => setHoveredId(null)}
                                    >
                                        <ReviewCard
                                            review={review}
                                            voice={voiceSources(review)}
                                            onDelete={deleteHandler(review)}
                                            markNumber={mark.n}
                                            active={hoveredId === review.id}
                                            onJump={() => {
                                                setShowMarks(true);
                                                setHoveredId(review.id);
                                            }}
                                            cardRef={(el) => {
                                                if (el)
                                                    cardRefs.current.set(
                                                        review.id,
                                                        el,
                                                    );
                                                else
                                                    cardRefs.current.delete(
                                                        review.id,
                                                    );
                                            }}
                                        />
                                    </div>
                                ))}
                            </div>
                        )}

                        {general.length > 0 && (
                            <div className="space-y-2">
                                <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-600">
                                    Whole creative
                                </p>
                                {general.map((r) => (
                                    <ReviewCard
                                        key={r.id}
                                        review={r}
                                        voice={voiceSources(r)}
                                        onDelete={deleteHandler(r)}
                                        active={false}
                                    />
                                ))}
                            </div>
                        )}
                    </div>

                    {canSubmitReview ? (
                        <Composer
                            workspace={workspace}
                            creative={creative}
                            isVideo={isVideo}
                            isImage={isImage}
                            region={region}
                            onClearRegion={() => setRegion(null)}
                            time={time}
                            onFocus={() => videoRef.current?.pause()}
                            readTime={() => videoRef.current?.currentTime ?? 0}
                        />
                    ) : (
                        <p className="shrink-0 border-t border-black/6 px-4 py-3 font-mono text-[11px] text-gray-400 dark:border-white/6 dark:text-gray-600">
                            Only reviewers assigned to this creative can leave a
                            review.
                        </p>
                    )}
                </aside>
            </div>

            <DeleteReviewDialog
                review={deleting}
                url={
                    deleting
                        ? `${baseUrl}/${creative.id}/reviews/${deleting.id}`
                        : ''
                }
                onClose={() => setDeleting(null)}
                onDeleted={() => setHoveredId(null)}
            />
        </>
    );
}

// ─── Controls ─────────────────────────────────────────────────────────────────

function Controls({
    playing,
    muted,
    time,
    duration,
    reviews,
    activeIds,
    onTogglePlay,
    onToggleMute,
    onSeek,
    onJump,
    onFullscreen,
}: {
    playing: boolean;
    muted: boolean;
    time: number;
    duration: number;
    reviews: Review[];
    activeIds: Set<number>;
    onTogglePlay: () => void;
    onToggleMute: () => void;
    onSeek: (seconds: number) => void;
    onJump: (seconds: number) => void;
    onFullscreen: () => void;
}) {
    const pct = (s: number) =>
        duration > 0 ? Math.min(100, (s / duration) * 100) : 0;

    const scrub = (e: React.MouseEvent<HTMLDivElement>) => {
        if (!duration) return;
        const rect = e.currentTarget.getBoundingClientRect();
        onSeek(((e.clientX - rect.left) / rect.width) * duration);
    };

    const iconBtn =
        'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-300 transition-colors hover:bg-white/8 hover:text-white';

    return (
        <div className="shrink-0 border-t border-black/6 bg-white px-4 pt-6 pb-3 dark:border-white/6 dark:bg-transparent">
            {/* Timeline: a marker per timestamped review, above the scrubber */}
            <div className="relative">
                {reviews.map((r) => (
                    <button
                        key={r.id}
                        type="button"
                        onClick={() => onJump(r.timestamp_seconds!)}
                        title={`${formatClock(r.timestamp_seconds)} · ${r.reviewer?.name ?? 'Unknown'}${r.feedback ? ` — ${r.feedback}` : ''}`}
                        style={{ left: `${pct(r.timestamp_seconds!)}%` }}
                        className={`absolute -top-5 flex h-4 w-4 -translate-x-1/2 items-center justify-center rounded-full font-mono text-[9px] font-bold transition-transform hover:scale-125 ${REVIEW_AVATAR_BG[r.status]} ${activeIds.has(r.id) ? 'scale-125 ring-2 ring-violet-400' : ''}`}
                    >
                        {initialOf(r)}
                    </button>
                ))}
                <div
                    onClick={scrub}
                    className="group relative h-1.5 cursor-pointer rounded-full bg-black/10 dark:bg-white/12"
                >
                    <div
                        className="absolute inset-y-0 left-0 rounded-full bg-violet-500"
                        style={{ width: `${pct(time)}%` }}
                    />
                    <div
                        className="absolute top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-violet-600 opacity-0 shadow transition-opacity group-hover:opacity-100 dark:bg-white"
                        style={{ left: `${pct(time)}%` }}
                    />
                </div>
            </div>

            <div className="mt-2 flex items-center gap-1">
                <button
                    type="button"
                    onClick={onTogglePlay}
                    className={iconBtn}
                    aria-label={playing ? 'Pause' : 'Play'}
                >
                    {playing ? (
                        <Pause className="h-4 w-4 fill-current" />
                    ) : (
                        <Play className="h-4 w-4 fill-current" />
                    )}
                </button>
                <button
                    type="button"
                    onClick={onToggleMute}
                    className={iconBtn}
                    aria-label={muted ? 'Unmute' : 'Mute'}
                >
                    {muted ? (
                        <VolumeX className="h-4 w-4" />
                    ) : (
                        <Volume2 className="h-4 w-4" />
                    )}
                </button>
                <p className="ml-1 font-mono text-[12px] text-gray-600 tabular-nums dark:text-gray-400">
                    {formatClock(time)}{' '}
                    <span className="text-gray-400 dark:text-gray-600">
                        / {formatClock(duration)}
                    </span>
                </p>
                <div className="flex-1" />
                <button
                    type="button"
                    onClick={onFullscreen}
                    className={iconBtn}
                    aria-label="Fullscreen video"
                >
                    <Maximize className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}

// ─── Review card ──────────────────────────────────────────────────────────────

function ReviewCard({
    review,
    voice,
    onDelete,
    markNumber,
    active,
    onJump,
    cardRef,
}: {
    review: Review;
    /** Where to play the review's voice message from, if it has one. */
    voice?: { url: string; fallbackUrl: string };
    /** Present only on the current user's own reviews. */
    onDelete?: () => void;
    /** The number of this review's mark on the image. */
    markNumber?: number;
    active: boolean;
    onJump?: () => void;
    cardRef?: (el: HTMLDivElement | null) => void;
}) {
    return (
        <div
            ref={cardRef}
            onClick={onJump}
            className={`rounded-[12px] border p-3 transition-colors ${
                active
                    ? 'border-violet-500/60 bg-violet-50 dark:bg-violet-500/10'
                    : 'border-black/6 bg-white dark:border-white/6 dark:bg-white/[0.03]'
            } ${onJump ? 'cursor-pointer hover:border-white/14' : ''}`}
        >
            <div className="flex items-center gap-2">
                <div
                    className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-mono text-[10px] font-bold ${REVIEW_AVATAR_BG[review.status]}`}
                >
                    {markNumber ?? initialOf(review)}
                </div>
                <p className="min-w-0 flex-1 truncate text-[12px] font-semibold text-gray-800 dark:text-gray-200">
                    {review.reviewer?.name ?? 'Unknown'}
                </p>
                {review.timestamp_seconds !== null && (
                    <span className="inline-flex items-center gap-1 rounded-md bg-violet-50 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-violet-600 tabular-nums dark:bg-violet-500/15 dark:text-violet-300">
                        <Play className="h-2.5 w-2.5 fill-current" />
                        {formatClock(review.timestamp_seconds)}
                    </span>
                )}
                <ReviewBadge status={review.status} />
                {onDelete && (
                    <button
                        type="button"
                        onClick={(e) => {
                            // The card itself jumps to the review's moment/area.
                            e.stopPropagation();
                            onDelete();
                        }}
                        title="Delete review"
                        aria-label="Delete review"
                        className="-mr-1 shrink-0 rounded-md p-1 text-gray-300 transition-colors hover:bg-red-50 hover:text-red-500 dark:text-gray-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                    >
                        <Trash2 className="h-3 w-3" />
                    </button>
                )}
            </div>
            {review.feedback && (
                <p className="mt-2 text-[12px] leading-relaxed break-words whitespace-pre-wrap text-gray-600 dark:text-gray-300">
                    {review.feedback}
                </p>
            )}
            {review.voice && voice && (
                // The player has its own clicks; don't let them jump the video.
                <div className="mt-2" onClick={(e) => e.stopPropagation()}>
                    <VoiceNote
                        url={voice.url}
                        fallbackUrl={voice.fallbackUrl}
                        durationSeconds={review.voice.duration_seconds}
                    />
                </div>
            )}
            <time className="mt-1.5 block font-mono text-[10px] text-gray-400 dark:text-gray-600">
                {review.created_at}
            </time>
        </div>
    );
}

// ─── Composer ─────────────────────────────────────────────────────────────────

function Composer({
    workspace,
    creative,
    isVideo,
    isImage,
    region,
    onClearRegion,
    time,
    onFocus,
    readTime,
}: {
    workspace: Workspace;
    creative: Creative;
    isVideo: boolean;
    isImage: boolean;
    /** The area marked on the image for this review, if any. */
    region: Region | null;
    onClearRegion: () => void;
    /** The playhead, for the live "At 0:12" label. */
    time: number;
    /** Pauses the video so the note lands on the frame being looked at. */
    onFocus: () => void;
    /** The exact playhead at submit time. */
    readTime: () => number;
}) {
    const [pinned, setPinned] = useState(true);
    const [voice, setVoice] = useState<VoiceClip | null>(null);
    const [recording, setRecording] = useState(false);
    const { data, setData, post, transform, processing, errors, reset } =
        useForm({
            status: 'revision' as ReviewStatus,
            feedback: '',
        });

    const attachTime = isVideo && pinned;

    const submit = (e?: React.FormEvent) => {
        e?.preventDefault();
        // Stop first, or the comment goes out without the clip being recorded.
        if (recording) return;
        transform((d) => ({
            ...d,
            timestamp_seconds: attachTime ? readTime() : null,
            ...(isImage ? { region } : {}),
            // Sending a file makes this multipart; Inertia switches over itself.
            ...(voice
                ? {
                      voice: voice.file,
                      voice_duration_seconds: voice.durationSeconds,
                  }
                : {}),
        }));
        post(`/workspaces/${workspace.slug}/creatives/${creative.id}/reviews`, {
            // Keep the player mounted so playback position survives the reload.
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                reset('feedback');
                setVoice(null);
                onClearRegion();
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className="shrink-0 space-y-2.5 border-t border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900"
        >
            <div className="flex items-center gap-2">
                {isVideo && (
                    <button
                        type="button"
                        onClick={() => setPinned((p) => !p)}
                        title={
                            pinned
                                ? 'Attached to this moment — click to review the whole video'
                                : 'Click to attach to the current moment'
                        }
                        className={`inline-flex h-8 shrink-0 items-center gap-1 rounded-lg px-2.5 font-mono text-[11px] font-semibold tabular-nums transition-colors ${
                            pinned
                                ? 'bg-violet-50 text-violet-600 hover:bg-violet-100 dark:bg-violet-500/20 dark:text-violet-300 dark:hover:bg-violet-500/30'
                                : 'bg-stone-100 text-gray-400 line-through hover:bg-stone-200 dark:bg-white/6 dark:text-gray-500 dark:hover:bg-white/10'
                        }`}
                    >
                        <Play className="h-2.5 w-2.5 fill-current" /> At{' '}
                        {formatClock(time)}
                    </button>
                )}
                {isImage &&
                    (region ? (
                        <span className="inline-flex h-8 shrink-0 items-center gap-1 rounded-lg bg-violet-50 pr-1 pl-2.5 font-mono text-[11px] font-semibold text-violet-600 dark:bg-violet-500/20 dark:text-violet-300">
                            <MapPin className="h-3 w-3" />
                            {region.w === 0 && region.h === 0
                                ? 'Point'
                                : 'Area'}{' '}
                            marked
                            <button
                                type="button"
                                onClick={onClearRegion}
                                aria-label="Clear marked area"
                                className="rounded p-0.5 hover:bg-violet-100 dark:hover:bg-violet-500/30"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </span>
                    ) : (
                        <span className="inline-flex h-8 shrink-0 items-center gap-1 rounded-lg bg-stone-100 px-2.5 font-mono text-[11px] text-gray-500 dark:bg-white/6">
                            <MapPin className="h-3 w-3" /> Whole image
                        </span>
                    ))}
                <Select
                    value={data.status}
                    onValueChange={(v) => setData('status', v as ReviewStatus)}
                >
                    <SelectTrigger className="h-8 flex-1 rounded-[8px] border-black/8 bg-stone-50 font-mono! text-[11px]! text-gray-800 dark:border-white/10 dark:bg-zinc-800 dark:text-gray-200">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent className="z-[60]">
                        {REVIEW_STATUS_OPTIONS.map((v) => (
                            <SelectItem
                                key={v}
                                value={v}
                                className="font-mono text-[11px]"
                            >
                                {REVIEW_STATUS_LABELS[v]}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <textarea
                value={data.feedback}
                onChange={(e) => setData('feedback', e.target.value)}
                onFocus={onFocus}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
                        submit();
                    }
                }}
                rows={3}
                placeholder={
                    attachTime
                        ? 'What needs to change at this moment?'
                        : region
                          ? 'What needs to change in this area?'
                          : 'Leave feedback or revision notes...'
                }
                className="w-full resize-none rounded-[8px] border border-black/8 bg-stone-50 p-2.5 font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 focus:border-violet-500 focus:ring-1 focus:ring-violet-500/30 dark:border-white/10 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600"
            />
            <VoiceRecorder
                value={voice}
                onChange={setVoice}
                onStart={onFocus}
                onRecordingChange={setRecording}
            />
            {(() => {
                // The server keys these by API field, not by the form's own.
                const e = errors as Record<string, string>;
                const message =
                    e.feedback ??
                    e.voice ??
                    e.timestamp_seconds ??
                    e.region ??
                    e['region.x'] ??
                    e['region.y'] ??
                    e['region.w'] ??
                    e['region.h'];
                return message ? (
                    <p className="font-mono text-[11px] text-red-400">
                        {message}
                    </p>
                ) : null;
            })()}
            <button
                type="submit"
                disabled={processing || recording}
                title={recording ? 'Stop recording to comment' : undefined}
                className="flex h-8 w-full items-center justify-center rounded-lg bg-violet-600 font-mono! text-[12px]! font-medium text-white transition-colors hover:bg-violet-700 disabled:opacity-50"
            >
                {attachTime
                    ? `Comment at ${formatClock(time)}`
                    : region
                      ? 'Comment on this area'
                      : 'Submit Review'}
                <span className="ml-2 text-[10px] text-violet-200/70">⌘↵</span>
            </button>
        </form>
    );
}
