import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData, SharedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce } from 'lodash';
import {
    Clapperboard,
    ExternalLink,
    FileImage,
    MessageSquare,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

// ─── Types ────────────────────────────────────────────────────────────────────

type ReviewStatus =
    | 'waiting_for_submission'
    | 'for_approval'
    | 'revision'
    | 'approved'
    | 'for_reapproval';

type AdsStatus = 'pending' | 'running' | 'kill' | 'skill';

interface Review {
    id: number;
    status: ReviewStatus;
    feedback: string | null;
    reviewer: { id: number; name: string } | null;
    created_at: string;
}

interface Creative {
    id: number;
    name: string;
    description: string | null;
    format: 'video' | 'image';
    creative_date: string;
    script: string | null;
    picture_url: string | null;
    reference_link: string | null;
    caption: string | null;
    headline: string | null;
    notes: string | null;
    creator: { id: number; name: string } | null;
    reviews: Review[];
    review_count: number;
    latest_review: { status: ReviewStatus; feedback: string | null } | null;
    ads_campaign: { id: number; ads_status: AdsStatus; ads_manager_link: string | null; remarks: string | null } | null;
}

interface Props {
    workspace: Workspace;
    creatives: PaginatedData<Creative>;
    query: {
        sort?: string;
        page?: number;
        per_page?: number;
        filter?: { search?: string; format?: string; review_status?: string };
    };
}

// ─── Status config ────────────────────────────────────────────────────────────

const REVIEW_STATUS_LABELS: Record<ReviewStatus, string> = {
    waiting_for_submission: 'Waiting',
    for_approval: 'For Approval',
    revision: 'Revision',
    approved: 'Approved',
    for_reapproval: 'For Re-approval',
};

const REVIEW_BADGE: Record<ReviewStatus, string> = {
    waiting_for_submission: 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
    for_approval: 'bg-blue-50 text-blue-600 dark:bg-blue-500/[0.12] dark:text-blue-400',
    revision: 'bg-amber-50 text-amber-600 dark:bg-amber-500/[0.12] dark:text-amber-400',
    approved: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/[0.12] dark:text-emerald-400',
    for_reapproval: 'bg-purple-50 text-purple-600 dark:bg-purple-500/[0.12] dark:text-purple-400',
};

const REVIEW_AVATAR_BG: Record<ReviewStatus, string> = {
    waiting_for_submission: 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
    for_approval: 'bg-blue-50 text-blue-600 dark:bg-blue-500/[0.15] dark:text-blue-400',
    revision: 'bg-amber-50 text-amber-600 dark:bg-amber-500/[0.15] dark:text-amber-400',
    approved: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/[0.15] dark:text-emerald-400',
    for_reapproval: 'bg-purple-50 text-purple-600 dark:bg-purple-500/[0.15] dark:text-purple-400',
};

const ADS_STATUS_LABELS: Record<AdsStatus, string> = {
    pending: 'Pending',
    running: 'Running',
    kill: 'Kill',
    skill: 'Skill',
};

const ADS_BADGE: Record<AdsStatus, string> = {
    pending: 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
    running: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/[0.12] dark:text-emerald-400',
    kill: 'bg-red-50 text-red-600 dark:bg-red-500/[0.12] dark:text-red-400',
    skill: 'bg-orange-50 text-orange-600 dark:bg-orange-500/[0.12] dark:text-orange-400',
};

// ─── Atoms ────────────────────────────────────────────────────────────────────

function ReviewBadge({ status }: { status: ReviewStatus }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[11px] font-medium ${REVIEW_BADGE[status]}`}>
            {REVIEW_STATUS_LABELS[status]}
        </span>
    );
}

function AdsBadge({ status }: { status: AdsStatus }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[11px] font-medium ${ADS_BADGE[status]}`}>
            {ADS_STATUS_LABELS[status]}
        </span>
    );
}

function FormatBadge({ format }: { format: 'video' | 'image' }) {
    return (
        <span className="inline-flex items-center gap-1 font-mono text-[11px] text-gray-500 dark:text-gray-400">
            {format === 'video'
                ? <Clapperboard className="h-3 w-3" />
                : <FileImage className="h-3 w-3" />
            }
            <span className="capitalize">{format}</span>
        </span>
    );
}

function SectionLabel({ children }: { children: React.ReactNode }) {
    return (
        <p className="font-mono text-[10px] font-medium uppercase tracking-widest text-gray-300 dark:text-gray-600">
            {children}
        </p>
    );
}

function InitialAvatar({ name, className = '' }: { name: string; className?: string }) {
    return (
        <span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full font-mono text-[11px] font-bold ${className}`}>
            {name.charAt(0).toUpperCase()}
        </span>
    );
}

// ─── Image Upload Field ───────────────────────────────────────────────────────

function ImageUploadField({
    currentUrl,
    onFileSelect,
    onClear,
}: {
    currentUrl?: string | null;
    onFileSelect: (file: File) => void;
    onClear: () => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);

    const handleFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        onFileSelect(file);
        const reader = new FileReader();
        reader.onload = () => setPreview(reader.result as string);
        reader.readAsDataURL(file);
    };

    const displayUrl = preview ?? currentUrl;

    return (
        <div>
            {displayUrl ? (
                <div className="group relative overflow-hidden rounded-[10px] border border-black/6 dark:border-white/6">
                    <img src={displayUrl} alt="Preview" className="h-32 w-full object-cover" />
                    <div className="absolute inset-0 flex items-center justify-center gap-2 bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            className="rounded-lg bg-white/90 px-3 py-1.5 font-mono text-[11px] font-medium text-gray-800 hover:bg-white"
                        >
                            Change
                        </button>
                        <button
                            type="button"
                            onClick={() => { setPreview(null); onClear(); if (inputRef.current) inputRef.current.value = ''; }}
                            className="rounded-lg bg-red-500/90 px-3 py-1.5 font-mono text-[11px] font-medium text-white hover:bg-red-500"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    className="flex h-20 w-full flex-col items-center justify-center gap-1.5 rounded-[10px] border border-dashed border-black/10 bg-stone-50 transition-colors hover:bg-stone-100 dark:border-white/10 dark:bg-zinc-800/60 dark:hover:bg-zinc-800"
                >
                    <Upload className="h-4 w-4 text-gray-400" />
                    <span className="font-mono text-[11px] text-gray-400">Click to upload image</span>
                </button>
            )}
            <input ref={inputRef} type="file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" className="hidden" onChange={handleFile} />
        </div>
    );
}

// ─── Creative Form Dialog ─────────────────────────────────────────────────────

type CreativeFormData = {
    name: string;
    creative_date: string;
    format: 'video' | 'image' | '';
    description: string;
    script: string;
    picture_file: File | null;
    picture_url: string;
    reference_link: string;
    caption: string;
    headline: string;
    notes: string;
};

function CreativeFormDialog({
    open,
    onClose,
    creative,
    workspace,
}: {
    open: boolean;
    onClose: () => void;
    creative?: Creative;
    workspace: Workspace;
}) {
    const isEdit = !!creative;
    const { data, setData, post, put, processing, errors, reset } =
        useForm<CreativeFormData>({
            name: creative?.name ?? '',
            creative_date: creative?.creative_date ?? '',
            format: creative?.format ?? '',
            description: creative?.description ?? '',
            script: creative?.script ?? '',
            picture_file: null,
            picture_url: creative?.picture_url ?? '',
            reference_link: creative?.reference_link ?? '',
            caption: creative?.caption ?? '',
            headline: creative?.headline ?? '',
            notes: creative?.notes ?? '',
        });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const url = isEdit
            ? `/workspaces/${workspace.slug}/creatives/${creative!.id}`
            : `/workspaces/${workspace.slug}/creatives`;
        (isEdit ? put : post)(url, {
            onSuccess: () => { reset(); onClose(); },
        });
    };

    const isImage = data.format === 'image';

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="text-[15px]">
                        {isEdit ? 'Edit Creative' : 'New Creative'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-6 pt-1">
                    <section className="space-y-3">
                        <SectionLabel>Basic Info</SectionLabel>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="cf-name" className="text-[12px] text-gray-600 dark:text-gray-400">Name *</Label>
                                <Input id="cf-name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. Summer Sale Hook" />
                                {errors.name && <p className="font-mono text-[11px] text-red-500">{errors.name}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="cf-date" className="text-[12px] text-gray-600 dark:text-gray-400">Date *</Label>
                                <Input id="cf-date" type="date" value={data.creative_date} onChange={(e) => setData('creative_date', e.target.value)} />
                                {errors.creative_date && <p className="font-mono text-[11px] text-red-500">{errors.creative_date}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-[12px] text-gray-600 dark:text-gray-400">Format *</Label>
                                <Select value={data.format} onValueChange={(v) => setData('format', v as 'video' | 'image')}>
                                    <SelectTrigger><SelectValue placeholder="Select format" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="video">🎬 Video</SelectItem>
                                        <SelectItem value="image">🖼️ Image</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.format && <p className="font-mono text-[11px] text-red-500">{errors.format}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="cf-headline" className="text-[12px] text-gray-600 dark:text-gray-400">Headline</Label>
                                <Input id="cf-headline" value={data.headline} onChange={(e) => setData('headline', e.target.value)} placeholder="Ad headline text" />
                            </div>
                        </div>
                    </section>

                    <section className="space-y-3">
                        <SectionLabel>Content</SectionLabel>
                        <div className="space-y-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="cf-desc" className="text-[12px] text-gray-600 dark:text-gray-400">Description</Label>
                                <Textarea id="cf-desc" value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2} placeholder="Brief overview" />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="cf-script" className="text-[12px] text-gray-600 dark:text-gray-400">Script</Label>
                                <Textarea id="cf-script" value={data.script} onChange={(e) => setData('script', e.target.value)} rows={4} placeholder="Full script or content outline" />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="cf-caption" className="text-[12px] text-gray-600 dark:text-gray-400">Caption</Label>
                                <Textarea id="cf-caption" value={data.caption} onChange={(e) => setData('caption', e.target.value)} rows={2} placeholder="Ad copy / post caption" />
                            </div>
                        </div>
                    </section>

                    <section className="space-y-3">
                        <SectionLabel>Media & Links</SectionLabel>
                        <div className="space-y-3">
                            {/* Image upload OR video URL depending on format */}
                            {isImage ? (
                                <div className="space-y-1.5">
                                    <Label className="text-[12px] text-gray-600 dark:text-gray-400">Image</Label>
                                    <ImageUploadField
                                        currentUrl={data.picture_url || null}
                                        onFileSelect={(file) => setData('picture_file', file)}
                                        onClear={() => { setData('picture_file', null); setData('picture_url', ''); }}
                                    />
                                    {errors.picture_file && <p className="font-mono text-[11px] text-red-500">{errors.picture_file}</p>}
                                </div>
                            ) : data.format === 'video' ? (
                                <div className="space-y-1.5">
                                    <Label htmlFor="cf-vid" className="text-[12px] text-gray-600 dark:text-gray-400">Video URL</Label>
                                    <Input id="cf-vid" value={data.picture_url} onChange={(e) => setData('picture_url', e.target.value)} placeholder="https://drive.google.com/..." />
                                </div>
                            ) : null}

                            <div className="space-y-1.5">
                                <Label htmlFor="cf-ref" className="text-[12px] text-gray-600 dark:text-gray-400">Reference Link</Label>
                                <Input id="cf-ref" value={data.reference_link} onChange={(e) => setData('reference_link', e.target.value)} placeholder="https://..." />
                            </div>
                        </div>
                    </section>

                    <section className="space-y-3">
                        <SectionLabel>Notes</SectionLabel>
                        <Textarea id="cf-notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={2} placeholder="Internal notes visible only to your team" />
                    </section>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose} className="h-8 font-mono! text-[12px]!">Cancel</Button>
                        <Button type="submit" disabled={processing} className="h-8 bg-emerald-600 font-mono! text-[12px]! hover:bg-emerald-700">
                            {isEdit ? 'Save Changes' : 'Create Creative'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// ─── Review Comment Card ──────────────────────────────────────────────────────

function ReviewComment({
    review,
    creative,
    workspace,
    currentUserId,
}: {
    review: Review;
    creative: Creative;
    workspace: Workspace;
    currentUserId: number;
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
        <div className="flex gap-3">
            {/* Avatar */}
            <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full font-mono text-[12px] font-bold ${REVIEW_AVATAR_BG[review.status]}`}>
                {initial}
            </div>

            {/* Body */}
            <div className="min-w-0 flex-1">
                {editing ? (
                    <div className="rounded-[12px] border border-black/6 bg-white p-3 dark:border-white/6 dark:bg-zinc-900">
                        <form onSubmit={submit} className="space-y-3">
                            <Select value={data.status} onValueChange={(v) => setData('status', v as ReviewStatus)}>
                                <SelectTrigger className="h-8 font-mono! text-[12px]!"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {(Object.entries(REVIEW_STATUS_LABELS) as [ReviewStatus, string][]).map(([v, l]) => (
                                        <SelectItem key={v} value={v} className="font-mono text-[12px]">{l}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Textarea
                                value={data.feedback}
                                onChange={(e) => setData('feedback', e.target.value)}
                                rows={3}
                                className="font-mono! text-[12px]!"
                                placeholder="Feedback..."
                                autoFocus
                            />
                            <div className="flex gap-2">
                                <Button type="submit" size="sm" disabled={processing} className="h-7 bg-emerald-600 font-mono! text-[11px]! hover:bg-emerald-700">Save</Button>
                                <Button type="button" size="sm" variant="ghost" className="h-7 font-mono! text-[11px]!" onClick={() => { reset(); setEditing(false); }}>
                                    <X className="mr-1 h-3 w-3" /> Cancel
                                </Button>
                            </div>
                        </form>
                    </div>
                ) : (
                    <>
                        {/* Header */}
                        <div className="flex items-center gap-2">
                            <span className="text-[13px] font-semibold text-gray-800 dark:text-gray-200">
                                {review.reviewer?.name ?? 'Unknown'}
                            </span>
                            <ReviewBadge status={review.status} />
                            <time className="ml-auto font-mono text-[10px] text-gray-400 dark:text-gray-600">
                                {review.created_at}
                            </time>
                            {isAuthor && (
                                <button
                                    onClick={() => setEditing(true)}
                                    className="rounded p-0.5 text-gray-300 transition-colors hover:bg-stone-100 hover:text-gray-600 dark:text-gray-700 dark:hover:bg-zinc-800 dark:hover:text-gray-400"
                                    title="Edit your review"
                                >
                                    <Pencil className="h-3 w-3" />
                                </button>
                            )}
                        </div>

                        {/* Feedback */}
                        {review.feedback && (
                            <p className="mt-1.5 text-[13px] leading-relaxed text-gray-600 dark:text-gray-400">
                                {review.feedback}
                            </p>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}

// ─── Reviews Tab ──────────────────────────────────────────────────────────────

function ReviewsTab({
    creative,
    workspace,
    currentUserId,
    canEdit,
}: {
    creative: Creative;
    workspace: Workspace;
    currentUserId: number;
    canEdit: boolean;
}) {
    const { data, setData, post, processing, reset } = useForm({
        status: 'for_approval' as ReviewStatus,
        feedback: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/workspaces/${workspace.slug}/creatives/${creative.id}/reviews`, {
            onSuccess: () => reset(),
        });
    };

    const reversed = useMemo(() => [...creative.reviews].reverse(), [creative.reviews]);

    return (
        <div className="flex flex-col gap-5">
            {reversed.length === 0 ? (
                <p className="font-mono text-[12px] text-gray-400 dark:text-gray-600">No reviews yet.</p>
            ) : (
                <div className="flex flex-col gap-4">
                    {reversed.map((r) => (
                        <ReviewComment
                            key={r.id}
                            review={r}
                            creative={creative}
                            workspace={workspace}
                            currentUserId={currentUserId}
                        />
                    ))}
                </div>
            )}

            {canEdit && (
                <form
                    onSubmit={submit}
                    className="space-y-3 rounded-[14px] border border-black/6 bg-stone-50/80 p-4 dark:border-white/6 dark:bg-zinc-800/40"
                >
                    <SectionLabel>Write a review</SectionLabel>
                    <Select value={data.status} onValueChange={(v) => setData('status', v as ReviewStatus)}>
                        <SelectTrigger className="h-8 font-mono! text-[12px]!"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            {(Object.entries(REVIEW_STATUS_LABELS) as [ReviewStatus, string][]).map(([v, l]) => (
                                <SelectItem key={v} value={v} className="font-mono text-[12px]">{l}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Textarea
                        value={data.feedback}
                        onChange={(e) => setData('feedback', e.target.value)}
                        rows={3}
                        className="font-mono! text-[12px]!"
                        placeholder="Leave feedback or revision notes..."
                    />
                    <Button type="submit" size="sm" className="w-full bg-emerald-600 font-mono! text-[12px]! hover:bg-emerald-700" disabled={processing}>
                        Submit Review
                    </Button>
                </form>
            )}
        </div>
    );
}

// ─── Ads Tab ──────────────────────────────────────────────────────────────────

function AdsTab({ creative, workspace, canEdit }: { creative: Creative; workspace: Workspace; canEdit: boolean }) {
    const { data, setData, put, processing } = useForm({
        ads_status: creative.ads_campaign?.ads_status ?? ('pending' as AdsStatus),
        ads_manager_link: creative.ads_campaign?.ads_manager_link ?? '',
        remarks: creative.ads_campaign?.remarks ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/workspaces/${workspace.slug}/creatives/${creative.id}/ads-campaign`);
    };

    const camp = creative.ads_campaign;

    return (
        <div className="space-y-4">
            {/* Current state */}
            <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900 space-y-3">
                <div>
                    <SectionLabel>Status</SectionLabel>
                    <div className="mt-2">
                        {camp ? <AdsBadge status={camp.ads_status} /> : <span className="font-mono text-[12px] text-gray-400 dark:text-gray-600">No campaign set</span>}
                    </div>
                </div>
                {camp?.ads_manager_link && (
                    <div>
                        <SectionLabel>Ads Manager</SectionLabel>
                        <a
                            href={camp.ads_manager_link}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mt-1.5 inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 font-mono text-[11px] font-medium text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-500/[0.10] dark:text-emerald-400"
                        >
                            Open in Ads Manager <ExternalLink className="h-3 w-3" />
                        </a>
                    </div>
                )}
                {camp?.remarks && (
                    <div>
                        <SectionLabel>Remarks</SectionLabel>
                        <p className="mt-1.5 whitespace-pre-wrap rounded-[10px] bg-stone-50 px-3 py-2.5 font-mono text-[12px] leading-relaxed text-gray-500 dark:bg-zinc-800/60 dark:text-gray-400">{camp.remarks}</p>
                    </div>
                )}
            </div>

            {canEdit && (
                <form
                    onSubmit={submit}
                    className="space-y-3 rounded-[14px] border border-black/6 bg-stone-50/80 p-4 dark:border-white/6 dark:bg-zinc-800/40"
                >
                    <SectionLabel>Update Campaign</SectionLabel>
                    <div className="space-y-1.5">
                        <Label className="font-mono text-[11px] text-gray-500">Status</Label>
                        <Select value={data.ads_status} onValueChange={(v) => setData('ads_status', v as AdsStatus)}>
                            <SelectTrigger className="h-8 font-mono! text-[12px]!"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {(Object.entries(ADS_STATUS_LABELS) as [AdsStatus, string][]).map(([v, l]) => (
                                    <SelectItem key={v} value={v} className="font-mono text-[12px]">{l}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label className="font-mono text-[11px] text-gray-500">Ads Manager Link</Label>
                        <Input
                            value={data.ads_manager_link}
                            onChange={(e) => setData('ads_manager_link', e.target.value)}
                            placeholder="https://adsmanager.facebook.com/..."
                            className="h-8 font-mono! text-[12px]!"
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label className="font-mono text-[11px] text-gray-500">Remarks</Label>
                        <Textarea
                            value={data.remarks}
                            onChange={(e) => setData('remarks', e.target.value)}
                            rows={3}
                            className="font-mono! text-[12px]!"
                            placeholder="Campaign notes, budget info, targeting details..."
                        />
                    </div>
                    <Button type="submit" size="sm" className="w-full bg-emerald-600 font-mono! text-[12px]! hover:bg-emerald-700" disabled={processing}>
                        Save
                    </Button>
                </form>
            )}
        </div>
    );
}

// ─── Detail Sheet ─────────────────────────────────────────────────────────────

function DetailField({ label, children }: { label: string; children?: React.ReactNode }) {
    if (!children) return null;
    return (
        <div>
            <SectionLabel>{label}</SectionLabel>
            <div className="mt-1.5">{children}</div>
        </div>
    );
}

function CreativeDetailSheet({
    creative,
    workspace,
    currentUserId,
    canEdit,
    onEdit,
    onClose,
}: {
    creative: Creative;
    workspace: Workspace;
    currentUserId: number;
    canEdit: boolean;
    onEdit: () => void;
    onClose: () => void;
}) {
    return (
        <Sheet open onOpenChange={(v) => !v && onClose()}>
            <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                <SheetHeader className="shrink-0 border-b border-black/6 px-6 py-5 dark:border-white/6">
                    <div className="flex items-start gap-3">
                        <div className="min-w-0 flex-1">
                            <SheetTitle className="truncate text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                                {creative.name}
                            </SheetTitle>
                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                <FormatBadge format={creative.format} />
                                {creative.latest_review && <ReviewBadge status={creative.latest_review.status} />}
                                <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                    {creative.creative_date}
                                </span>
                            </div>
                        </div>
                        {canEdit && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={onEdit}
                                className="h-7 shrink-0 border-black/6 font-mono! text-[11px]! text-gray-600 hover:bg-stone-100 dark:border-white/6 dark:text-gray-400 dark:hover:bg-zinc-800"
                            >
                                <Pencil className="mr-1 h-3 w-3" /> Edit
                            </Button>
                        )}
                    </div>
                </SheetHeader>

                <Tabs defaultValue="details" className="flex flex-1 flex-col overflow-hidden">
                    <div className="shrink-0 border-b border-black/6 px-6 dark:border-white/6">
                        <TabsList className="h-auto gap-1 rounded-none bg-transparent p-0">
                            {(['details', 'reviews', 'ads'] as const).map((tab) => (
                                <TabsTrigger
                                    key={tab}
                                    value={tab}
                                    className="relative h-9 rounded-none border-b-2 border-transparent px-3 font-mono text-[12px] font-medium capitalize text-gray-400 transition-none data-[state=active]:border-emerald-500 data-[state=active]:text-emerald-600 dark:text-gray-600 dark:data-[state=active]:text-emerald-400"
                                >
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

                    <TabsContent value="details" className="flex-1 overflow-y-auto px-6 py-5">
                        <div className="space-y-4">
                            {/* Image preview */}
                            {creative.picture_url && creative.format === 'image' && (
                                <div className="overflow-hidden rounded-[12px] border border-black/6 dark:border-white/6">
                                    <img src={creative.picture_url} alt={creative.name} className="h-40 w-full object-cover" />
                                </div>
                            )}

                            {/* Creator */}
                            {creative.creator && (
                                <div>
                                    <SectionLabel>Creator</SectionLabel>
                                    <div className="mt-1.5 flex items-center gap-2">
                                        <InitialAvatar name={creative.creator.name} className="bg-emerald-500/[0.12] text-emerald-600 dark:text-emerald-400" />
                                        <span className="text-[13px] text-gray-700 dark:text-gray-300">{creative.creator.name}</span>
                                    </div>
                                </div>
                            )}

                            <DetailField label="Headline">
                                {creative.headline && (
                                    <p className="text-[13px] text-gray-700 dark:text-gray-300">{creative.headline}</p>
                                )}
                            </DetailField>

                            <DetailField label="Description">
                                {creative.description && (
                                    <p className="whitespace-pre-wrap text-[13px] leading-relaxed text-gray-700 dark:text-gray-300">{creative.description}</p>
                                )}
                            </DetailField>

                            <DetailField label="Script">
                                {creative.script && (
                                    <p className="whitespace-pre-wrap rounded-[10px] bg-stone-50 px-3 py-2.5 font-mono text-[12px] leading-relaxed text-gray-600 dark:bg-zinc-800/60 dark:text-gray-400">{creative.script}</p>
                                )}
                            </DetailField>

                            <DetailField label="Caption">
                                {creative.caption && (
                                    <p className="whitespace-pre-wrap text-[13px] leading-relaxed text-gray-700 dark:text-gray-300">{creative.caption}</p>
                                )}
                            </DetailField>

                            {/* Video URL */}
                            {creative.picture_url && creative.format === 'video' && (
                                <DetailField label="Video">
                                    <a
                                        href={creative.picture_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 font-mono text-[11px] font-medium text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-500/[0.10] dark:text-emerald-400"
                                    >
                                        Open video <ExternalLink className="h-3 w-3" />
                                    </a>
                                </DetailField>
                            )}

                            {creative.reference_link && (
                                <DetailField label="Reference">
                                    <a
                                        href={creative.reference_link}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1.5 rounded-lg bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 hover:bg-stone-200 dark:bg-zinc-800 dark:text-gray-400"
                                    >
                                        Open link <ExternalLink className="h-3 w-3" />
                                    </a>
                                </DetailField>
                            )}

                            <DetailField label="Notes">
                                {creative.notes && (
                                    <p className="whitespace-pre-wrap rounded-[10px] bg-stone-50 px-3 py-2.5 font-mono text-[12px] leading-relaxed text-gray-500 dark:bg-zinc-800/60 dark:text-gray-400">{creative.notes}</p>
                                )}
                            </DetailField>
                        </div>
                    </TabsContent>

                    <TabsContent value="reviews" className="flex-1 overflow-y-auto px-6 py-5">
                        <ReviewsTab creative={creative} workspace={workspace} currentUserId={currentUserId} canEdit={canEdit} />
                    </TabsContent>

                    <TabsContent value="ads" className="flex-1 overflow-y-auto px-6 py-5">
                        <AdsTab creative={creative} workspace={workspace} canEdit={canEdit} />
                    </TabsContent>
                </Tabs>
            </SheetContent>
        </Sheet>
    );
}

// ─── Main Page ─────────────────────────────────────────────────────────────────

export default function CreativesIndex({ workspace, creatives, query }: Props) {
    const { auth } = usePage<SharedData>().props;
    const currentUserId = auth?.user?.id ?? 0;

    const canCreate = usePermission(PERMISSIONS.CreateCreatives);
    const canEdit = usePermission(PERMISSIONS.EditCreatives);
    const canDelete = usePermission(PERMISSIONS.DeleteCreatives);

    const [createOpen, setCreateOpen] = useState(false);
    const [editCreative, setEditCreative] = useState<Creative | null>(null);
    const [detailCreative, setDetailCreative] = useState<Creative | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);

    const baseUrl = `/workspaces/${workspace.slug}/creatives`;
    const initialSorting = useMemo(() => toFrontendSort(query.sort ?? null), [query.sort]);
    const [search, setSearch] = useState(query.filter?.search ?? '');

    const navigate = useCallback(
        (params: Record<string, string | number | null | undefined>) => {
            router.get(
                baseUrl,
                {
                    sort: query.sort,
                    page: 1,
                    per_page: query.per_page,
                    'filter[search]': query.filter?.search || undefined,
                    'filter[format]': query.filter?.format || undefined,
                    'filter[review_status]': query.filter?.review_status || undefined,
                    ...params,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [baseUrl, query],
    );

    const debouncedSearch = useCallback(
        debounce((value: string) => navigate({ 'filter[search]': value || undefined, page: 1 }), 400),
        [navigate],
    );

    useEffect(() => {
        if (search !== (query.filter?.search ?? '')) debouncedSearch(search);
        return () => debouncedSearch.cancel();
    }, [search]);

    const handleDelete = (id: number) => {
        router.delete(`${baseUrl}/${id}`, { onSuccess: () => setDeleteId(null) });
    };

    const columns: ColumnDef<Creative>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Creative" />,
            cell: ({ row: { original: c } }) => (
                <div>
                    <p className="text-[13px] font-medium text-black dark:text-gray-200">{c.name}</p>
                    {c.headline && (
                        <p className="mt-0.5 max-w-[200px] truncate font-mono text-[11px] text-gray-400 dark:text-gray-600">{c.headline}</p>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'creative_date',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Date" />,
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">{row.original.creative_date}</span>
            ),
        },
        {
            accessorKey: 'format',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Format" />,
            cell: ({ row }) => <FormatBadge format={row.original.format} />,
        },
        {
            accessorKey: 'creator',
            enableSorting: false,
            header: () => <span className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-300 dark:text-gray-600">Creator</span>,
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">{row.original.creator?.name ?? '—'}</span>
            ),
        },
        {
            accessorKey: 'latest_review',
            enableSorting: false,
            header: () => <span className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-300 dark:text-gray-600">Review</span>,
            cell: ({ row: { original: c } }) => (
                <div className="flex items-center gap-1.5">
                    {c.latest_review
                        ? <ReviewBadge status={c.latest_review.status} />
                        : <span className="font-mono text-[11px] text-gray-300 dark:text-gray-700">—</span>
                    }
                    {c.review_count > 0 && (
                        <span className="inline-flex items-center gap-0.5 font-mono text-[10px] text-gray-400 dark:text-gray-600">
                            <MessageSquare className="h-3 w-3" />
                            {c.review_count}
                        </span>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'ads_campaign',
            enableSorting: false,
            header: () => <span className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-300 dark:text-gray-600">Ads</span>,
            cell: ({ row }) =>
                row.original.ads_campaign
                    ? <AdsBadge status={row.original.ads_campaign.ads_status} />
                    : <span className="font-mono text-[11px] text-gray-300 dark:text-gray-700">—</span>,
        },
        {
            id: 'actions',
            enableSorting: false,
            header: () => null,
            cell: ({ row: { original: c } }) =>
                canEdit || canDelete ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:bg-zinc-700 dark:hover:text-gray-300"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <MoreHorizontal className="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {canEdit && (
                                <DropdownMenuItem className="font-mono text-[12px]" onClick={(e) => { e.stopPropagation(); setEditCreative(c); }}>
                                    <Pencil className="mr-2 h-3.5 w-3.5" /> Edit
                                </DropdownMenuItem>
                            )}
                            {canEdit && canDelete && <DropdownMenuSeparator />}
                            {canDelete && (
                                <DropdownMenuItem className="font-mono text-[12px] text-red-500 focus:text-red-500" onClick={(e) => { e.stopPropagation(); setDeleteId(c.id); }}>
                                    <Trash2 className="mr-2 h-3.5 w-3.5" /> Delete
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null,
        },
    ];

    return (
        <AppLayout>
            <Head title="Creative Tracker" />
            <div className="p-6">
                <PageHeader
                    title="Creative Tracker"
                    description="Track creatives from ideation through review and launch"
                >
                    {canCreate && (
                        <Button size="sm" onClick={() => setCreateOpen(true)} className="h-8 bg-emerald-600 font-mono! text-[12px]! hover:bg-emerald-700">
                            <Plus className="mr-1.5 h-3.5 w-3.5" /> New Creative
                        </Button>
                    )}
                </PageHeader>

                {/* Filter toolbar */}
                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <div className="relative min-w-[220px] flex-1">
                        <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            className="h-8 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 pr-3 font-mono text-[12px] text-gray-800 placeholder:text-gray-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search name, headline..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>

                    <div className="flex overflow-hidden rounded-[10px] border border-black/6 dark:border-white/6">
                        {([
                            { key: '', label: 'All' },
                            { key: 'video', label: '🎬 Video' },
                            { key: 'image', label: '🖼️ Image' },
                        ] as const).map(({ key, label }) => (
                            <button
                                key={key}
                                onClick={() => navigate({ 'filter[format]': key || undefined, page: 1 })}
                                className={`h-8 border-r border-black/6 px-3 font-mono text-[11px] font-medium last:border-r-0 transition-colors dark:border-white/6 ${
                                    (query.filter?.format ?? '') === key
                                        ? 'bg-emerald-500/[0.08] text-emerald-600 dark:bg-emerald-500/[0.10] dark:text-emerald-400'
                                        : 'bg-white text-gray-500 hover:bg-stone-50 dark:bg-zinc-900 dark:text-gray-500 dark:hover:bg-zinc-800'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    <Select
                        value={query.filter?.review_status ?? 'all'}
                        onValueChange={(v) => navigate({ 'filter[review_status]': v === 'all' ? undefined : v, page: 1 })}
                    >
                        <SelectTrigger className="h-8 w-[160px] rounded-[10px] border-black/6 font-mono! text-[12px]! dark:border-white/6">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all" className="font-mono text-[12px]">All statuses</SelectItem>
                            {(Object.entries(REVIEW_STATUS_LABELS) as [ReviewStatus, string][]).map(([v, l]) => (
                                <SelectItem key={v} value={v} className="font-mono text-[12px]">{l}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    data={creatives.data}
                    meta={creatives}
                    initialSorting={initialSorting}
                    onFetch={(params) => navigate({
                        sort: (params?.sort as string) ?? undefined,
                        page: (params?.page as number) ?? undefined,
                        per_page: (params?.per_page as number) ?? undefined,
                    })}
                    onRowClick={(row) => setDetailCreative(row)}
                />
            </div>

            <CreativeFormDialog open={createOpen} onClose={() => setCreateOpen(false)} workspace={workspace} />

            {editCreative && (
                <CreativeFormDialog open onClose={() => setEditCreative(null)} creative={editCreative} workspace={workspace} />
            )}

            {detailCreative && (
                <CreativeDetailSheet
                    creative={detailCreative}
                    workspace={workspace}
                    currentUserId={currentUserId}
                    canEdit={canEdit}
                    onEdit={() => { setEditCreative(detailCreative); setDetailCreative(null); }}
                    onClose={() => setDetailCreative(null)}
                />
            )}

            <Dialog open={deleteId !== null} onOpenChange={(v) => !v && setDeleteId(null)}>
                <DialogContent className="max-w-sm">
                    <DialogHeader>
                        <DialogTitle className="text-[15px]">Delete Creative</DialogTitle>
                    </DialogHeader>
                    <p className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                        Permanently deletes the creative along with all reviews and campaign data.
                    </p>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteId(null)} className="h-8 font-mono! text-[12px]!">Cancel</Button>
                        <Button variant="destructive" onClick={() => deleteId !== null && handleDelete(deleteId)} className="h-8 font-mono! text-[12px]!">Delete</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
