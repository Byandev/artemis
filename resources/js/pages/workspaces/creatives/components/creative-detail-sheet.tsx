import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Workspace } from '@/types/models/Workspace';
import { Link, useForm } from '@inertiajs/react';
import {
    CalendarCheck,
    Clapperboard,
    Download,
    ExternalLink,
    FileImage,
    Flag,
    LucideIcon,
    MapPin,
    Maximize2,
    Megaphone,
    MessageSquare,
    Package,
    Pencil,
    Plus,
    Trash2,
    Users,
    X,
} from 'lucide-react';
import { ComponentType, ReactNode, useMemo, useRef, useState } from 'react';
import {
    Creative,
    REVIEW_AVATAR_BG,
    REVIEW_STATUS_LABELS,
    REVIEW_STATUS_OPTIONS,
    Review,
    ReviewStatus,
} from '../types';
import { AdsBadge, FinalBadge, InitialAvatar, ReviewBadge } from './atoms';
import { CreativeCode } from './creative-code';
import { DeleteReviewDialog } from './delete-review-dialog';
import { mediaRoute, useFallbackSrc, voiceRoute } from './media-src';
import {
    TimestampChip,
    TimestampInput,
    formatClock,
    timestampPayload,
} from './review-timestamp';
import { VoiceClip, VoiceNote, VoiceRecorder } from './voice-recorder';

// ─── Shared primitives ──────────────────────────────────────────────────────────

const sectionLabel =
    'font-mono text-[9px] font-medium uppercase tracking-widest text-gray-400 dark:text-gray-600';
const muted = 'font-mono text-[11px] text-gray-300 dark:text-gray-700';

function Section({ label, children }: { label?: string; children: ReactNode }) {
    return (
        <div className="px-5 py-4">
            {label && <p className={`mb-2 ${sectionLabel}`}>{label}</p>}
            {children}
        </div>
    );
}

/** A single key/value row in the Properties list, with a leading icon for scannability. */
function Prop({
    icon: Icon,
    label,
    children,
}: {
    icon: LucideIcon;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-start justify-between gap-3 py-2.5">
            <dt className="flex shrink-0 items-center gap-1.5 pt-0.5 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                <Icon className="h-3 w-3" /> {label}
            </dt>
            <dd className="flex min-w-0 flex-wrap items-center justify-end gap-1.5 text-right">
                {children}
            </dd>
        </div>
    );
}

function ReviewerChip({ name }: { name: string }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-blue-500/[0.08] py-0.5 pr-2 pl-0.5 font-mono text-[11px] font-medium text-blue-700 dark:bg-blue-500/[0.15] dark:text-blue-300">
            <InitialAvatar
                name={name}
                className="h-4 w-4 bg-blue-500/20 text-[8px] text-blue-700 dark:text-blue-300"
            />
            {name}
        </span>
    );
}

function LinkPill({ href, children }: { href: string; children: ReactNode }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 rounded-lg bg-stone-100 px-2.5 py-1.5 font-mono text-[11px] font-medium text-gray-600 transition-colors hover:bg-stone-200 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700"
        >
            {children} <ExternalLink className="h-3 w-3" />
        </a>
    );
}

/** A labelled block of prose (caption / description / script / notes). */
function TextBlock({
    label,
    children,
    tone = 'plain',
}: {
    label: string;
    children: ReactNode;
    tone?: 'plain' | 'mono' | 'note';
}) {
    const body =
        tone === 'note'
            ? 'whitespace-pre-wrap rounded-[10px] bg-amber-50 px-4 py-3 font-mono text-[12px] leading-relaxed text-amber-700 dark:bg-amber-500/[0.07] dark:text-amber-400'
            : tone === 'mono'
              ? 'whitespace-pre-wrap rounded-[10px] bg-stone-50 px-4 py-3 font-mono text-[12px] leading-relaxed text-gray-600 dark:bg-zinc-800/50 dark:text-gray-400'
              : 'whitespace-pre-wrap text-[13px] leading-relaxed text-gray-600 dark:text-gray-400';
    return (
        <Section label={label}>
            <p className={body}>{children}</p>
        </Section>
    );
}

// ─── Review Comment ────────────────────────────────────────────────────────────

function ReviewComment({
    review,
    creative,
    workspace,
    currentUserId,
    isLast,
    onSeek,
    currentTime,
}: {
    review: Review;
    creative: Creative;
    workspace: Workspace;
    currentUserId: number;
    isLast: boolean;
    /** Jumps the player to a second; absent when there's no player. */
    onSeek?: (seconds: number) => void;
    currentTime?: () => number;
}) {
    const [editing, setEditing] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const { data, setData, put, transform, processing, errors, reset } =
        useForm({
            status: review.status,
            feedback: review.feedback ?? '',
            timestamp: formatClock(review.timestamp_seconds),
        });

    const isAuthor = review.reviewer?.id === currentUserId;
    const initial = (review.reviewer?.name ?? '?').charAt(0).toUpperCase();

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform(({ timestamp, ...d }) => ({
            ...d,
            timestamp_seconds: timestampPayload(timestamp),
        }));
        put(
            `/workspaces/${workspace.slug}/creatives/${creative.id}/reviews/${review.id}`,
            {
                onSuccess: () => {
                    reset();
                    setEditing(false);
                },
            },
        );
    };

    return (
        <div className="relative flex gap-3">
            <DeleteReviewDialog
                review={confirmingDelete ? review : null}
                url={`/workspaces/${workspace.slug}/creatives/${creative.id}/reviews/${review.id}`}
                onClose={() => setConfirmingDelete(false)}
            />
            {!isLast && (
                <div className="absolute top-8 bottom-0 left-3.5 w-px bg-black/6 dark:bg-white/6" />
            )}
            <div
                className={`relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full font-mono text-[11px] font-bold ring-2 ring-white dark:ring-zinc-900 ${REVIEW_AVATAR_BG[review.status]}`}
            >
                {initial}
            </div>
            <div className="min-w-0 flex-1 pb-5">
                {editing ? (
                    <div className="rounded-[12px] border border-black/6 bg-white p-3 shadow-[0_1px_3px_rgba(0,0,0,0.04)] dark:border-white/6 dark:bg-zinc-800/60 dark:shadow-none">
                        <form onSubmit={submit} className="space-y-2.5">
                            <Select
                                value={data.status}
                                onValueChange={(v) =>
                                    setData('status', v as ReviewStatus)
                                }
                            >
                                <SelectTrigger className="h-8 rounded-[8px] border-black/8 font-mono! text-[11px]! dark:border-white/8">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
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
                            <textarea
                                value={data.feedback}
                                onChange={(e) =>
                                    setData('feedback', e.target.value)
                                }
                                rows={3}
                                autoFocus
                                placeholder="Feedback..."
                                className="w-full resize-none rounded-[8px] border border-black/8 bg-stone-50 p-2.5 font-mono! text-[12px]! text-gray-800 outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/8 dark:bg-zinc-700 dark:text-gray-100"
                            />
                            {creative.format === 'video' && (
                                <TimestampInput
                                    value={data.timestamp}
                                    onChange={(v) => setData('timestamp', v)}
                                    currentTime={currentTime}
                                    error={
                                        (errors as Record<string, string>)
                                            .timestamp_seconds
                                    }
                                />
                            )}
                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex h-7 items-center rounded-lg bg-emerald-600 px-3 font-mono! text-[11px]! font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    Save
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        reset();
                                        setEditing(false);
                                    }}
                                    className="flex h-7 items-center gap-1 rounded-lg px-2 font-mono! text-[11px]! text-gray-500 hover:bg-stone-100 dark:text-gray-400 dark:hover:bg-zinc-700"
                                >
                                    <X className="h-3 w-3" /> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-[12px] border border-black/6 bg-white shadow-[0_1px_3px_rgba(0,0,0,0.04)] dark:border-white/6 dark:bg-zinc-800/40 dark:shadow-none">
                        <div className="flex items-start gap-2.5 border-b border-black/4 px-3 py-2.5 dark:border-white/4">
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-[12px] font-semibold text-gray-800 dark:text-gray-200">
                                    {review.reviewer?.name ?? 'Unknown'}
                                </p>
                                <time className="font-mono text-[10px] text-gray-400 dark:text-gray-600">
                                    {review.created_at}
                                </time>
                            </div>
                            {review.region && (
                                <Link
                                    href={`/workspaces/${workspace.slug}/creatives/${creative.id}/review`}
                                    title="See the marked area"
                                    className="inline-flex items-center gap-1 rounded-md bg-violet-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-violet-600 hover:bg-violet-100 dark:bg-violet-500/[0.12] dark:text-violet-400"
                                >
                                    <MapPin className="h-2.5 w-2.5" /> Area
                                </Link>
                            )}
                            <ReviewBadge status={review.status} />
                            {isAuthor && (
                                <button
                                    onClick={() => setEditing(true)}
                                    title="Edit review"
                                    className="-mr-1 shrink-0 rounded-md p-1 text-gray-300 transition-colors hover:bg-stone-100 hover:text-gray-600 dark:text-gray-700 dark:hover:bg-zinc-700 dark:hover:text-gray-400"
                                >
                                    <Pencil className="h-3 w-3" />
                                </button>
                            )}
                            {isAuthor && (
                                <button
                                    onClick={() => setConfirmingDelete(true)}
                                    title="Delete review"
                                    aria-label="Delete review"
                                    className="-mr-1 shrink-0 rounded-md p-1 text-gray-300 transition-colors hover:bg-red-50 hover:text-red-500 dark:text-gray-700 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                >
                                    <Trash2 className="h-3 w-3" />
                                </button>
                            )}
                        </div>
                        {review.feedback ? (
                            <p className="px-3 py-2.5 text-[12px] leading-relaxed break-words text-gray-600 dark:text-gray-400">
                                {review.timestamp_seconds !== null && (
                                    <>
                                        <TimestampChip
                                            seconds={review.timestamp_seconds}
                                            onSeek={onSeek}
                                        />{' '}
                                    </>
                                )}
                                {review.feedback}
                            </p>
                        ) : review.timestamp_seconds !== null ? (
                            <p className="px-3 py-2.5">
                                <TimestampChip
                                    seconds={review.timestamp_seconds}
                                    onSeek={onSeek}
                                />
                            </p>
                        ) : !review.voice ? (
                            <p className="px-3 py-2.5 font-mono text-[11px] text-gray-300 italic dark:text-gray-700">
                                No feedback left
                            </p>
                        ) : null}
                        {review.voice && (
                            <div className="px-3 pb-2.5 first:pt-2.5 [&:not(:first-child)]:pt-0">
                                <VoiceNote
                                    url={review.voice.url}
                                    fallbackUrl={voiceRoute(
                                        workspace.slug,
                                        creative.id,
                                        review.id,
                                    )}
                                    durationSeconds={
                                        review.voice.duration_seconds
                                    }
                                />
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

// ─── Reviews Tab ───────────────────────────────────────────────────────────────

function ReviewsTab({
    creative,
    workspace,
    currentUserId,
    canReview,
}: {
    creative: Creative;
    workspace: Workspace;
    currentUserId: number;
    canReview: boolean;
}) {
    const { data, setData, post, transform, processing, errors, reset } =
        useForm({
            status: 'approved' as ReviewStatus,
            feedback: '',
            timestamp: '',
        });
    const [showForm, setShowForm] = useState(false);

    // Only an uploaded video can be played (and seeked) here; a video that
    // lives behind a Drive link can still take a typed timestamp.
    const isVideo = creative.format === 'video';
    const playable =
        isVideo && !!creative.media?.mime_type.startsWith('video/');
    const playerRef = useRef<HTMLVideoElement>(null);
    const [playerSrc, onPlayerError] = useFallbackSrc(
        creative.media?.url ?? '',
        mediaRoute(workspace.slug, creative.id),
    );
    const [duration, setDuration] = useState<number | null>(null);

    const seek = playable
        ? (seconds: number) => {
              const video = playerRef.current;
              if (!video) return;
              video.currentTime = seconds;
              video.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
              void video.play();
          }
        : undefined;
    const currentTime = playable
        ? () => playerRef.current?.currentTime ?? 0
        : undefined;

    /** Pause on the frame being reviewed and open the form stamped with it. */
    const reviewAtCurrentTime = () => {
        const video = playerRef.current;
        if (!video) return;
        video.pause();
        setData('timestamp', formatClock(video.currentTime));
        setShowForm(true);
    };

    const timestamped = useMemo(
        () => creative.reviews.filter((r) => r.timestamp_seconds !== null),
        [creative.reviews],
    );
    const reversed = useMemo(
        () => [...creative.reviews].reverse(),
        [creative.reviews],
    );

    // Only reviewers assigned to this specific creative may submit a review,
    // even if they hold the Review Creatives permission.
    const canSubmitReview = useMemo(
        () =>
            canReview &&
            creative.assigned_reviewers.some((r) => r.id === currentUserId),
        [canReview, creative.assigned_reviewers, currentUserId],
    );

    const [voice, setVoice] = useState<VoiceClip | null>(null);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform(({ timestamp, ...d }) => ({
            ...d,
            timestamp_seconds: timestampPayload(timestamp),
            // Sending a file makes this multipart; Inertia switches over itself.
            ...(voice
                ? {
                      voice: voice.file,
                      voice_duration_seconds: voice.durationSeconds,
                  }
                : {}),
        }));
        post(`/workspaces/${workspace.slug}/creatives/${creative.id}/reviews`, {
            onSuccess: () => {
                reset();
                setVoice(null);
                setShowForm(false);
            },
        });
    };

    const closeForm = () => {
        reset();
        setVoice(null);
        setShowForm(false);
    };

    return (
        <div className="flex flex-col gap-5">
            <Link
                href={`/workspaces/${workspace.slug}/creatives/${creative.id}/review`}
                className="flex h-9 w-full items-center justify-center gap-1.5 rounded-[10px] bg-zinc-900 font-mono text-[12px] font-medium text-white transition-colors hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-gray-200"
            >
                <Maximize2 className="h-3.5 w-3.5" /> Open full-screen review
            </Link>

            {/* Player — reviews can point at a moment in it */}
            {playable && (
                <div>
                    <video
                        ref={playerRef}
                        src={playerSrc}
                        onError={onPlayerError}
                        controls
                        preload="metadata"
                        onLoadedMetadata={(e) =>
                            setDuration(e.currentTarget.duration || null)
                        }
                        className="max-h-[300px] w-full rounded-[12px] bg-black"
                    />
                    {/* Where each timestamped review sits on the timeline */}
                    {duration && timestamped.length > 0 && (
                        <div className="relative mt-2 h-3 rounded-full bg-stone-100 dark:bg-zinc-800">
                            {timestamped.map((r) => (
                                <button
                                    key={r.id}
                                    type="button"
                                    onClick={() => seek?.(r.timestamp_seconds!)}
                                    title={`${formatClock(r.timestamp_seconds)} · ${r.reviewer?.name ?? 'Unknown'}${r.feedback ? ` — ${r.feedback}` : ''}`}
                                    style={{
                                        left: `${Math.min(100, (r.timestamp_seconds! / duration) * 100)}%`,
                                    }}
                                    className={`absolute top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-white dark:ring-zinc-900 ${REVIEW_AVATAR_BG[r.status]}`}
                                />
                            ))}
                        </div>
                    )}
                    {canSubmitReview && (
                        <button
                            type="button"
                            onClick={reviewAtCurrentTime}
                            className="mt-2 flex h-8 w-full items-center justify-center gap-1.5 rounded-[10px] bg-violet-50 font-mono text-[12px] font-medium text-violet-600 transition-colors hover:bg-violet-100 dark:bg-violet-500/[0.1] dark:text-violet-400 dark:hover:bg-violet-500/[0.18]"
                        >
                            <MessageSquare className="h-3.5 w-3.5" /> Review at
                            current time
                        </button>
                    )}
                </div>
            )}

            {/* Assigned reviewers */}
            <div>
                <p className={`mb-2 ${sectionLabel}`}>Assigned reviewers</p>
                {creative.assigned_reviewers.length > 0 ? (
                    <div className="flex flex-wrap items-center gap-1.5">
                        {creative.assigned_reviewers.map((r) => (
                            <ReviewerChip key={r.id} name={r.name} />
                        ))}
                    </div>
                ) : (
                    <p className={muted}>
                        No one is assigned to review this yet.
                    </p>
                )}
            </div>

            {/* Reviews timeline */}
            <div>
                <div className="mb-2 flex items-center justify-between">
                    <p className={sectionLabel}>
                        Reviews
                        {creative.review_count > 0
                            ? ` · ${creative.review_count}`
                            : ''}
                    </p>
                </div>
                {reversed.length === 0 ? (
                    <div className="rounded-[12px] border border-dashed border-black/8 px-6 py-8 text-center dark:border-white/8">
                        <MessageSquare className="mx-auto h-6 w-6 text-gray-300 dark:text-gray-700" />
                        <p className="mt-2 font-mono text-[11px] text-gray-400 dark:text-gray-600">
                            No reviews yet
                        </p>
                        {canSubmitReview && (
                            <p className="mt-0.5 font-mono text-[10px] text-gray-300 dark:text-gray-700">
                                Be the first to leave feedback.
                            </p>
                        )}
                    </div>
                ) : (
                    <div>
                        {reversed.map((r, i) => (
                            <ReviewComment
                                key={r.id}
                                review={r}
                                creative={creative}
                                workspace={workspace}
                                currentUserId={currentUserId}
                                isLast={i === reversed.length - 1}
                                onSeek={seek}
                                currentTime={currentTime}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Add review — collapsed into a button until clicked */}
            {canSubmitReview &&
                (showForm ? (
                    <div className="overflow-hidden rounded-[14px] border border-black/6 bg-stone-50/60 dark:border-white/6 dark:bg-zinc-800/30">
                        <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                            <p className={sectionLabel}>Write a review</p>
                            <button
                                type="button"
                                onClick={closeForm}
                                className="rounded-md p-1 text-gray-300 transition-colors hover:bg-stone-100 hover:text-gray-600 dark:text-gray-700 dark:hover:bg-zinc-700 dark:hover:text-gray-400"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <form onSubmit={submit} className="space-y-3 p-4">
                            <Select
                                value={data.status}
                                onValueChange={(v) =>
                                    setData('status', v as ReviewStatus)
                                }
                            >
                                <SelectTrigger className="h-8 rounded-[8px] border-black/8 font-mono! text-[11px]! dark:border-white/8">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
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
                            <textarea
                                value={data.feedback}
                                onChange={(e) =>
                                    setData('feedback', e.target.value)
                                }
                                rows={3}
                                autoFocus
                                placeholder="Leave feedback or revision notes..."
                                className="w-full resize-none rounded-[8px] border border-black/8 bg-white p-2.5 font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600"
                            />
                            <VoiceRecorder
                                value={voice}
                                onChange={setVoice}
                                onStart={() => playerRef.current?.pause()}
                            />
                            {(errors as Record<string, string>).voice && (
                                <p className="font-mono text-[11px] text-red-500">
                                    {(errors as Record<string, string>).voice}
                                </p>
                            )}
                            {isVideo && (
                                <TimestampInput
                                    value={data.timestamp}
                                    onChange={(v) => setData('timestamp', v)}
                                    currentTime={currentTime}
                                    error={
                                        (errors as Record<string, string>)
                                            .timestamp_seconds
                                    }
                                />
                            )}
                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex h-8 flex-1 items-center justify-center rounded-lg bg-emerald-600 font-mono! text-[12px]! font-medium text-white transition-colors hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    Submit Review
                                </button>
                                <button
                                    type="button"
                                    onClick={closeForm}
                                    className="flex h-8 items-center rounded-lg border border-black/8 bg-white px-3 font-mono! text-[12px]! font-medium text-gray-600 transition-colors hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                ) : (
                    <button
                        type="button"
                        onClick={() => setShowForm(true)}
                        className="flex h-9 w-full items-center justify-center gap-1.5 rounded-[10px] border border-dashed border-black/12 font-mono text-[12px] font-medium text-gray-500 transition-colors hover:border-emerald-500/40 hover:bg-emerald-500/[0.03] hover:text-emerald-600 dark:border-white/12 dark:text-gray-400 dark:hover:text-emerald-400"
                    >
                        <Plus className="h-3.5 w-3.5" /> Add Review
                    </button>
                ))}
        </div>
    );
}

// ─── Detail Sheet ──────────────────────────────────────────────────────────────

const FORMAT_META: Record<
    'video' | 'image',
    { icon: ComponentType<{ className?: string }>; tint: string; fg: string }
> = {
    video: {
        icon: Clapperboard,
        tint: 'bg-violet-50 dark:bg-violet-500/[0.10]',
        fg: 'text-violet-500 dark:text-violet-400',
    },
    image: {
        icon: FileImage,
        tint: 'bg-blue-50 dark:bg-blue-500/[0.10]',
        fg: 'text-blue-500 dark:text-blue-400',
    },
};

/**
 * The file uploaded into Artemis, previewed inline straight from its signed
 * URL. `routeUrl` is the app route: the Download link, and the fallback if
 * the signed URL has expired.
 */
function UploadedMedia({
    media,
    routeUrl,
}: {
    media: NonNullable<Creative['media']>;
    routeUrl: string;
}) {
    const isVideo = media.mime_type.startsWith('video/');
    const [src, onError] = useFallbackSrc(media.url, routeUrl);

    return (
        <div className="mb-2 overflow-hidden rounded-[12px] border border-black/6 bg-stone-50 dark:border-white/6 dark:bg-zinc-800/50">
            {isVideo ? (
                <video
                    src={src}
                    onError={onError}
                    controls
                    preload="metadata"
                    className="max-h-[360px] w-full bg-black"
                />
            ) : (
                <a href={src} target="_blank" rel="noopener noreferrer">
                    <img
                        src={src}
                        onError={onError}
                        alt={media.file_name}
                        className="max-h-[360px] w-full object-contain"
                    />
                </a>
            )}
            <div className="flex items-center gap-2 px-3 py-2">
                <p className="min-w-0 flex-1 truncate font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {media.file_name}
                </p>
                <a
                    href={`${routeUrl}?download=1`}
                    className="inline-flex items-center gap-1 font-mono text-[11px] text-emerald-600 hover:underline dark:text-emerald-400"
                >
                    <Download className="h-3.5 w-3.5" /> Download
                </a>
            </div>
        </div>
    );
}

export function CreativeDetailSheet({
    creative,
    workspace,
    currentUserId,
    canEdit,
    canReview,
    onEdit,
    onClose,
}: {
    creative: Creative;
    workspace: Workspace;
    currentUserId: number;
    canEdit: boolean;
    canReview: boolean;
    onEdit: () => void;
    onClose: () => void;
}) {
    const fmt = FORMAT_META[creative.format];
    const FormatIcon = fmt.icon;

    const hasContent = !!(
        creative.headline ||
        creative.caption ||
        creative.description ||
        creative.script ||
        creative.notes
    );
    const hasLinks = !!(creative.reference_link || creative.ads_manager_link);

    return (
        <Sheet open onOpenChange={(v) => !v && onClose()}>
            <SheetContent className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-[480px]">
                <Tabs
                    defaultValue="details"
                    className="flex h-full flex-col overflow-hidden"
                >
                    {/* Fixed header */}
                    <div className="shrink-0">
                        <div className="flex items-start gap-3 border-b border-black/6 pt-5 pr-12 pb-4 pl-5 dark:border-white/6">
                            <div
                                className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] ${fmt.tint}`}
                            >
                                <FormatIcon
                                    className={`h-[18px] w-[18px] ${fmt.fg}`}
                                />
                            </div>
                            <div className="min-w-0 flex-1">
                                <SheetTitle className="text-[15px] leading-snug font-semibold text-gray-900 dark:text-gray-100">
                                    {creative.name}
                                </SheetTitle>
                                <div className="mt-1.5 flex items-center gap-1.5 text-[11px] text-gray-400 dark:text-gray-600">
                                    {creative.code && (
                                        <CreativeCode code={creative.code} />
                                    )}
                                    {creative.creator && (
                                        <>
                                            <InitialAvatar
                                                name={creative.creator.name}
                                                className="h-4 w-4 bg-emerald-500/[0.12] text-[8px] text-emerald-600 dark:text-emerald-400"
                                            />
                                            <span className="font-medium text-gray-500 dark:text-gray-400">
                                                {creative.creator.name}
                                            </span>
                                            <span className="text-gray-300 dark:text-gray-700">
                                                ·
                                            </span>
                                        </>
                                    )}
                                    <span className="font-mono">
                                        {creative.creative_date}
                                    </span>
                                </div>
                            </div>
                            {canEdit && (
                                <button
                                    onClick={onEdit}
                                    className="flex h-8 shrink-0 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3 font-mono! text-[11px]! font-medium text-gray-600 transition-colors hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700"
                                >
                                    <Pencil className="h-3 w-3" /> Edit
                                </button>
                            )}
                        </div>
                        <div className="border-b border-black/6 px-5 dark:border-white/6">
                            <TabsList className="h-auto gap-0 rounded-none bg-transparent p-0">
                                {(['details', 'reviews'] as const).map(
                                    (tab) => (
                                        <TabsTrigger
                                            key={tab}
                                            value={tab}
                                            className="relative h-9 rounded-none border-b-2 border-transparent px-3 font-mono text-[12px] font-medium text-gray-400 capitalize transition-none data-[state=active]:border-emerald-500 data-[state=active]:text-emerald-600 dark:text-gray-600 dark:data-[state=active]:text-emerald-400"
                                        >
                                            {tab}
                                            {tab === 'reviews' &&
                                                creative.review_count > 0 && (
                                                    <span className="ml-1.5 rounded-full bg-stone-100 px-1.5 py-0.5 font-mono text-[9px] font-bold text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                                                        {creative.review_count}
                                                    </span>
                                                )}
                                        </TabsTrigger>
                                    ),
                                )}
                            </TabsList>
                        </div>
                    </div>

                    {/* Details panel */}
                    <TabsContent
                        value="details"
                        className="flex-1 overflow-y-auto"
                    >
                        <div className="divide-y divide-black/4 dark:divide-white/4">
                            {/* Properties */}
                            <Section label="Properties">
                                <dl className="divide-y divide-black/4 dark:divide-white/4">
                                    <Prop icon={Package} label="Product">
                                        {creative.product ? (
                                            <span className="inline-flex max-w-full items-center gap-1.5 rounded-md bg-indigo-50 px-2 py-1 font-mono text-[11px] font-medium text-indigo-600 dark:bg-indigo-500/[0.12] dark:text-indigo-400">
                                                <Package className="h-3 w-3 shrink-0" />{' '}
                                                <span className="truncate">
                                                    {creative.product.title}
                                                </span>
                                            </span>
                                        ) : (
                                            <span className={muted}>
                                                No product
                                            </span>
                                        )}
                                    </Prop>
                                    <Prop icon={Users} label="Reviewers">
                                        {creative.assigned_reviewers.length >
                                        0 ? (
                                            creative.assigned_reviewers.map(
                                                (r) => (
                                                    <ReviewerChip
                                                        key={r.id}
                                                        name={r.name}
                                                    />
                                                ),
                                            )
                                        ) : (
                                            <span className={muted}>
                                                Unassigned
                                            </span>
                                        )}
                                    </Prop>
                                    <Prop icon={Megaphone} label="Ads status">
                                        <AdsBadge
                                            status={creative.ads_status}
                                        />
                                    </Prop>
                                    <Prop icon={Flag} label="Final status">
                                        <FinalBadge
                                            status={creative.final_status}
                                        />
                                    </Prop>
                                    <Prop
                                        icon={MessageSquare}
                                        label="Latest review"
                                    >
                                        {creative.latest_review ? (
                                            <ReviewBadge
                                                status={
                                                    creative.latest_review
                                                        .status
                                                }
                                            />
                                        ) : (
                                            <span className={muted}>—</span>
                                        )}
                                    </Prop>
                                    {creative.approved_at && (
                                        <Prop
                                            icon={CalendarCheck}
                                            label="Approved"
                                        >
                                            <span className="font-mono text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                                                {creative.approved_at}
                                            </span>
                                        </Prop>
                                    )}
                                </dl>
                            </Section>

                            {/* Media */}
                            {(creative.media || creative.picture_url) && (
                                <Section label="Media">
                                    {creative.media && (
                                        <UploadedMedia
                                            media={creative.media}
                                            routeUrl={mediaRoute(
                                                workspace.slug,
                                                creative.id,
                                            )}
                                        />
                                    )}
                                    {creative.picture_url && (
                                        <a
                                            href={creative.picture_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="group flex items-center gap-3 rounded-[12px] border border-black/6 bg-stone-50 p-3 transition-colors hover:border-black/12 hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800/50 dark:hover:border-white/12 dark:hover:bg-zinc-800"
                                        >
                                            <div
                                                className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${fmt.tint}`}
                                            >
                                                <FormatIcon
                                                    className={`h-4 w-4 ${fmt.fg}`}
                                                />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                                                    Open media
                                                </p>
                                                <p className="mt-0.5 truncate font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                                    {creative.picture_url}
                                                </p>
                                            </div>
                                            <ExternalLink className="h-3.5 w-3.5 shrink-0 text-gray-300 transition-colors group-hover:text-gray-500 dark:text-gray-600" />
                                        </a>
                                    )}
                                </Section>
                            )}

                            {/* Links */}
                            {hasLinks && (
                                <Section label="Links">
                                    <div className="flex flex-wrap gap-2">
                                        {creative.reference_link && (
                                            <LinkPill
                                                href={creative.reference_link}
                                            >
                                                Reference
                                            </LinkPill>
                                        )}
                                        {creative.ads_manager_link && (
                                            <LinkPill
                                                href={creative.ads_manager_link}
                                            >
                                                Ads Manager
                                            </LinkPill>
                                        )}
                                    </div>
                                </Section>
                            )}

                            {/* Content */}
                            {creative.headline && (
                                <Section label="Headline">
                                    <p className="text-[13px] font-medium text-gray-800 dark:text-gray-200">
                                        {creative.headline}
                                    </p>
                                </Section>
                            )}
                            {creative.caption && (
                                <TextBlock label="Caption">
                                    {creative.caption}
                                </TextBlock>
                            )}
                            {creative.description && (
                                <TextBlock label="Description">
                                    {creative.description}
                                </TextBlock>
                            )}
                            {creative.script && (
                                <TextBlock label="Script" tone="mono">
                                    {creative.script}
                                </TextBlock>
                            )}
                            {creative.notes && (
                                <TextBlock label="Notes" tone="note">
                                    {creative.notes}
                                </TextBlock>
                            )}

                            {!hasContent &&
                                !hasLinks &&
                                !creative.picture_url &&
                                !creative.media && (
                                    <div className="px-5 py-10 text-center">
                                        <p className={muted}>
                                            No content added yet
                                        </p>
                                    </div>
                                )}
                        </div>
                    </TabsContent>

                    {/* Reviews panel */}
                    <TabsContent
                        value="reviews"
                        className="flex-1 overflow-y-auto px-5 py-5"
                    >
                        <ReviewsTab
                            creative={creative}
                            workspace={workspace}
                            currentUserId={currentUserId}
                            canReview={canReview}
                        />
                    </TabsContent>
                </Tabs>
            </SheetContent>
        </Sheet>
    );
}
