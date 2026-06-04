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
import { useForm } from '@inertiajs/react';
import { Clapperboard, ExternalLink, FileImage, MessageSquare, Pencil, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    Creative,
    REVIEW_AVATAR_BG,
    REVIEW_STATUS_LABELS,
    Review,
    ReviewStatus,
} from '../types';
import { FinalBadge, FormatBadge, InitialAvatar, ReviewBadge } from './atoms';

// ─── Review Comment ────────────────────────────────────────────────────────────

function ReviewComment({
    review,
    creative,
    workspace,
    currentUserId,
    isLast,
}: {
    review: Review;
    creative: Creative;
    workspace: Workspace;
    currentUserId: number;
    isLast: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing, reset } = useForm({
        status: review.status,
        feedback: review.feedback ?? '',
    });

    const isAuthor = review.reviewer?.id === currentUserId;
    const initial = (review.reviewer?.name ?? '?').charAt(0).toUpperCase();

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/workspaces/${workspace.slug}/creatives/${creative.id}/reviews/${review.id}`, {
            onSuccess: () => { reset(); setEditing(false); },
        });
    };

    return (
        <div className="relative flex gap-3">
            {!isLast && <div className="absolute left-3.5 top-8 bottom-0 w-px bg-black/6 dark:bg-white/6" />}
            <div className={`relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full font-mono text-[11px] font-bold ring-2 ring-white dark:ring-zinc-900 ${REVIEW_AVATAR_BG[review.status]}`}>
                {initial}
            </div>
            <div className="min-w-0 flex-1 pb-4">
                {editing ? (
                    <div className="rounded-[12px] border border-black/6 bg-white p-3 dark:border-white/6 dark:bg-zinc-800/60">
                        <form onSubmit={submit} className="space-y-2.5">
                            <Select value={data.status} onValueChange={(v) => setData('status', v as ReviewStatus)}>
                                <SelectTrigger className="h-8 rounded-[8px] border-black/8 font-mono! text-[11px]! dark:border-white/8"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {(Object.entries(REVIEW_STATUS_LABELS) as [ReviewStatus, string][]).map(([v, l]) => (
                                        <SelectItem key={v} value={v} className="font-mono text-[11px]">{l}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <textarea value={data.feedback} onChange={(e) => setData('feedback', e.target.value)} rows={3} autoFocus placeholder="Feedback..."
                                className="w-full resize-none rounded-[8px] border border-black/8 bg-stone-50 p-2.5 font-mono! text-[12px]! text-gray-800 outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/8 dark:bg-zinc-700 dark:text-gray-100" />
                            <div className="flex gap-2">
                                <button type="submit" disabled={processing} className="flex h-7 items-center rounded-lg bg-emerald-600 px-3 font-mono! text-[11px]! font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Save</button>
                                <button type="button" onClick={() => { reset(); setEditing(false); }} className="flex h-7 items-center gap-1 rounded-lg px-2 font-mono! text-[11px]! text-gray-500 hover:bg-stone-100 dark:text-gray-400 dark:hover:bg-zinc-700">
                                    <X className="h-3 w-3" /> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                ) : (
                    <div className="rounded-[12px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-800/40">
                        <div className="flex items-center gap-2 border-b border-black/4 px-3 py-2 dark:border-white/4">
                            <span className="text-[12px] font-semibold text-gray-800 dark:text-gray-200">{review.reviewer?.name ?? 'Unknown'}</span>
                            <ReviewBadge status={review.status} />
                            <time className="ml-auto font-mono text-[10px] text-gray-400 dark:text-gray-600">{review.created_at}</time>
                            {isAuthor && (
                                <button onClick={() => setEditing(true)} title="Edit" className="rounded-md p-1 text-gray-300 transition-colors hover:bg-stone-100 hover:text-gray-600 dark:text-gray-700 dark:hover:bg-zinc-700 dark:hover:text-gray-400">
                                    <Pencil className="h-3 w-3" />
                                </button>
                            )}
                        </div>
                        {review.feedback
                            ? <p className="px-3 py-2.5 text-[12px] leading-relaxed text-gray-600 dark:text-gray-400">{review.feedback}</p>
                            : <p className="px-3 py-2.5 font-mono text-[11px] italic text-gray-300 dark:text-gray-700">No feedback</p>
                        }
                    </div>
                )}
            </div>
        </div>
    );
}

// ─── Reviews Tab ───────────────────────────────────────────────────────────────

function ReviewsTab({ creative, workspace, currentUserId, canReview }: { creative: Creative; workspace: Workspace; currentUserId: number; canReview: boolean }) {
    const { data, setData, post, processing, reset } = useForm({ status: 'for_approval' as ReviewStatus, feedback: '' });
    const reversed = useMemo(() => [...creative.reviews].reverse(), [creative.reviews]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/workspaces/${workspace.slug}/creatives/${creative.id}/reviews`, { onSuccess: () => reset() });
    };

    return (
        <div className="flex flex-col gap-6">
            {reversed.length === 0 ? (
                <div className="rounded-[12px] border border-dashed border-black/8 p-6 text-center dark:border-white/8">
                    <MessageSquare className="mx-auto h-6 w-6 text-gray-300 dark:text-gray-700" />
                    <p className="mt-2 font-mono text-[11px] text-gray-400 dark:text-gray-600">No reviews yet</p>
                </div>
            ) : (
                <div>
                    {reversed.map((r, i) => (
                        <ReviewComment key={r.id} review={r} creative={creative} workspace={workspace} currentUserId={currentUserId} isLast={i === reversed.length - 1} />
                    ))}
                </div>
            )}
            {canReview && (
                <div className="rounded-[14px] border border-black/6 bg-stone-50/60 dark:border-white/6 dark:bg-zinc-800/30">
                    <div className="border-b border-black/6 px-4 py-3 dark:border-white/6">
                        <p className="font-mono text-[10px] font-medium uppercase tracking-widest text-gray-400 dark:text-gray-600">Write a review</p>
                    </div>
                    <form onSubmit={submit} className="space-y-3 p-4">
                        <Select value={data.status} onValueChange={(v) => setData('status', v as ReviewStatus)}>
                            <SelectTrigger className="h-8 rounded-[8px] border-black/8 font-mono! text-[11px]! dark:border-white/8"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {(Object.entries(REVIEW_STATUS_LABELS) as [ReviewStatus, string][]).map(([v, l]) => (
                                    <SelectItem key={v} value={v} className="font-mono text-[11px]">{l}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <textarea value={data.feedback} onChange={(e) => setData('feedback', e.target.value)} rows={3} placeholder="Leave feedback or revision notes..."
                            className="w-full resize-none rounded-[8px] border border-black/8 bg-white p-2.5 font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600" />
                        <button type="submit" disabled={processing} className="flex h-8 w-full items-center justify-center rounded-lg bg-emerald-600 font-mono! text-[12px]! font-medium text-white transition-colors hover:bg-emerald-700 disabled:opacity-50">
                            Submit Review
                        </button>
                    </form>
                </div>
            )}
        </div>
    );
}

// ─── Detail Sheet ──────────────────────────────────────────────────────────────

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
    return (
        <Sheet open onOpenChange={(v) => !v && onClose()}>
            <SheetContent className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-[480px]">
                <Tabs defaultValue="details" className="flex h-full flex-col overflow-hidden">
                    {/* Fixed header */}
                    <div className="shrink-0">
                        <div className="flex items-start gap-3 border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                            <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] ${creative.format === 'video' ? 'bg-violet-50 dark:bg-violet-500/[0.08]' : 'bg-blue-50 dark:bg-blue-500/[0.08]'}`}>
                                {creative.format === 'video'
                                    ? <Clapperboard className="h-[18px] w-[18px] text-violet-500 dark:text-violet-400" />
                                    : <FileImage className="h-[18px] w-[18px] text-blue-500 dark:text-blue-400" />
                                }
                            </div>
                            <div className="min-w-0 flex-1">
                                <SheetTitle className="text-[15px] font-semibold leading-snug text-gray-900 dark:text-gray-100">
                                    {creative.name}
                                </SheetTitle>
                                <div className="mt-1.5 flex flex-wrap items-center gap-2">
                                    <FormatBadge format={creative.format} />
                                    <FinalBadge status={creative.final_status} />
                                    {creative.latest_review && <ReviewBadge status={creative.latest_review.status} />}
                                </div>
                                <div className="mt-2 flex items-center gap-1.5 text-[11px] text-gray-400 dark:text-gray-600">
                                    {creative.creator && (
                                        <>
                                            <InitialAvatar name={creative.creator.name} className="h-4 w-4 text-[8px] bg-emerald-500/[0.12] text-emerald-600 dark:text-emerald-400" />
                                            <span className="font-medium text-gray-500 dark:text-gray-400">{creative.creator.name}</span>
                                            <span className="text-gray-300 dark:text-gray-700">·</span>
                                        </>
                                    )}
                                    <span className="font-mono">{creative.creative_date}</span>
                                    {creative.assigned_reviewer && (
                                        <>
                                            <span className="text-gray-300 dark:text-gray-700">·</span>
                                            <span className="font-mono text-[10px] uppercase tracking-wide text-gray-400 dark:text-gray-600">Reviewer</span>
                                            <InitialAvatar name={creative.assigned_reviewer.name} className="h-4 w-4 bg-blue-500/[0.12] text-[8px] text-blue-600 dark:text-blue-400" />
                                            <span className="font-medium text-gray-500 dark:text-gray-400">{creative.assigned_reviewer.name}</span>
                                        </>
                                    )}
                                </div>
                            </div>
                            {canEdit && (
                                <button onClick={onEdit} className="flex h-8 shrink-0 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3 font-mono! text-[11px]! font-medium text-gray-600 transition-colors hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700">
                                    <Pencil className="h-3 w-3" /> Edit
                                </button>
                            )}
                        </div>
                        <div className="border-b border-black/6 px-5 dark:border-white/6">
                            <TabsList className="h-auto gap-0 rounded-none bg-transparent p-0">
                                {(['details', 'reviews'] as const).map((tab) => (
                                    <TabsTrigger key={tab} value={tab}
                                        className="relative h-9 rounded-none border-b-2 border-transparent px-3 font-mono text-[12px] font-medium capitalize text-gray-400 transition-none data-[state=active]:border-emerald-500 data-[state=active]:text-emerald-600 dark:text-gray-600 dark:data-[state=active]:text-emerald-400">
                                        {tab}
                                        {tab === 'reviews' && creative.review_count > 0 && (
                                            <span className="ml-1.5 rounded-full bg-stone-100 px-1.5 py-0.5 font-mono text-[9px] font-bold text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                                                {creative.review_count}
                                            </span>
                                        )}
                                    </TabsTrigger>
                                ))}
                            </TabsList>
                        </div>
                    </div>

                    {/* Scrollable panels */}
                    <TabsContent value="details" className="flex-1 overflow-y-auto">
                        <div className="divide-y divide-black/4 dark:divide-white/4">
                            {creative.picture_url && (
                                <div className="px-5 py-4">
                                    <a href={creative.picture_url} target="_blank" rel="noopener noreferrer"
                                        className="flex items-center gap-3 rounded-[12px] border border-violet-200 bg-violet-50 p-3 transition-colors hover:bg-violet-100 dark:border-violet-500/20 dark:bg-violet-500/[0.06] dark:hover:bg-violet-500/[0.10]">
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-500/[0.12]">
                                            {creative.format === 'video'
                                                ? <Clapperboard className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                                                : <FileImage className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                                            }
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="font-mono text-[11px] font-medium text-violet-700 dark:text-violet-300">Open Media</p>
                                            <p className="mt-0.5 truncate font-mono text-[10px] text-violet-500">{creative.picture_url}</p>
                                        </div>
                                        <ExternalLink className="h-3.5 w-3.5 shrink-0 text-violet-400" />
                                    </a>
                                </div>
                            )}
                            {(creative.approved_at || creative.ads_manager_link) && (
                                <div className="space-y-3 px-5 py-4">
                                    {creative.approved_at && (
                                        <div>
                                            <p className="mb-1 font-mono text-[9px] font-medium uppercase tracking-widest text-gray-400 dark:text-gray-600">Approved</p>
                                            <p className="font-mono text-[12px] font-medium text-emerald-600 dark:text-emerald-400">{creative.approved_at}</p>
                                        </div>
                                    )}
                                    {creative.ads_manager_link && (
                                        <div>
                                            <p className="mb-1 font-mono text-[9px] font-medium uppercase tracking-widest text-gray-400 dark:text-gray-600">Ads Manager</p>
                                            <a href={creative.ads_manager_link} target="_blank" rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1.5 rounded-lg bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 transition-colors hover:bg-stone-200 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700">
                                                Open Ads Manager <ExternalLink className="h-3 w-3" />
                                            </a>
                                        </div>
                                    )}
                                </div>
                            )}
                            {(creative.headline || creative.reference_link) && (
                                <div className="px-5 py-4">
                                    {creative.headline && (
                                        <div className={creative.reference_link ? 'mb-3' : ''}>
                                            <p className="mb-1 font-mono text-[9px] font-medium uppercase tracking-widest text-gray-400 dark:text-gray-600">Headline</p>
                                            <p className="text-[13px] font-medium text-gray-800 dark:text-gray-200">{creative.headline}</p>
                                        </div>
                                    )}
                                    {creative.reference_link && (
                                        <div>
                                            <p className="mb-1 font-mono text-[9px] font-medium uppercase tracking-widest text-gray-400 dark:text-gray-600">Reference</p>
                                            <a href={creative.reference_link} target="_blank" rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1.5 rounded-lg bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 transition-colors hover:bg-stone-200 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700">
                                                Open link <ExternalLink className="h-3 w-3" />
                                            </a>
                                        </div>
                                    )}
                                </div>
                            )}
                            {creative.caption && (
                                <div className="px-5 py-4">
                                    <p className="mb-1.5 font-mono text-[9px] font-medium uppercase tracking-widest text-gray-400 dark:text-gray-600">Caption</p>
                                    <p className="whitespace-pre-wrap text-[13px] leading-relaxed text-gray-700 dark:text-gray-300">{creative.caption}</p>
                                </div>
                            )}
                            {creative.description && (
                                <div className="px-5 py-4">
                                    <p className="mb-1.5 font-mono text-[9px] font-medium uppercase tracking-widest text-gray-400 dark:text-gray-600">Description</p>
                                    <p className="whitespace-pre-wrap text-[13px] leading-relaxed text-gray-600 dark:text-gray-400">{creative.description}</p>
                                </div>
                            )}
                            {creative.script && (
                                <div className="px-5 py-4">
                                    <p className="mb-1.5 font-mono text-[9px] font-medium uppercase tracking-widest text-gray-400 dark:text-gray-600">Script</p>
                                    <p className="whitespace-pre-wrap rounded-[10px] bg-stone-50 px-4 py-3 font-mono text-[12px] leading-relaxed text-gray-600 dark:bg-zinc-800/50 dark:text-gray-400">{creative.script}</p>
                                </div>
                            )}
                            {creative.notes && (
                                <div className="px-5 py-4">
                                    <p className="mb-1.5 font-mono text-[9px] font-medium uppercase tracking-widest text-amber-500 dark:text-amber-600">Notes</p>
                                    <p className="whitespace-pre-wrap rounded-[10px] bg-amber-50 px-4 py-3 font-mono text-[12px] leading-relaxed text-amber-700 dark:bg-amber-500/[0.07] dark:text-amber-400">{creative.notes}</p>
                                </div>
                            )}
                            {!creative.headline && !creative.reference_link && !creative.caption && !creative.description && !creative.script && !creative.notes && !creative.picture_url && (
                                <div className="px-5 py-10 text-center">
                                    <p className="font-mono text-[11px] text-gray-300 dark:text-gray-700">No details added yet</p>
                                </div>
                            )}
                        </div>
                    </TabsContent>

                    <TabsContent value="reviews" className="flex-1 overflow-y-auto px-5 py-5">
                        <ReviewsTab creative={creative} workspace={workspace} currentUserId={currentUserId} canReview={canReview} />
                    </TabsContent>
                </Tabs>
            </SheetContent>
        </Sheet>
    );
}
