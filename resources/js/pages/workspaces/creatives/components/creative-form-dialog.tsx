import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import { Clapperboard, FileImage, Upload, X } from 'lucide-react';
import React, { useRef, useState } from 'react';
import { Creative } from '../types';

// ─── Style tokens ─────────────────────────────────────────────────────────────

const fi = 'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100';
const fl = 'block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
const fe = 'mt-1 font-mono text-[11px] text-red-500';
const ft = 'w-full resize-none rounded-[10px] border border-black/8 bg-stone-50 p-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100';

// ─── Image upload ──────────────────────────────────────────────────────────────

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
                        <button type="button" onClick={() => inputRef.current?.click()} className="rounded-lg bg-white/90 px-3 py-1.5 font-mono text-[11px] font-medium text-gray-800 hover:bg-white">Change</button>
                        <button type="button" onClick={() => { setPreview(null); onClear(); if (inputRef.current) inputRef.current.value = ''; }} className="rounded-lg bg-red-500/90 px-3 py-1.5 font-mono text-[11px] font-medium text-white hover:bg-red-500">Remove</button>
                    </div>
                </div>
            ) : (
                <button type="button" onClick={() => inputRef.current?.click()} className="flex h-20 w-full flex-col items-center justify-center gap-1.5 rounded-[10px] border border-dashed border-black/10 bg-stone-50 transition-colors hover:bg-stone-100 dark:border-white/10 dark:bg-zinc-800/60 dark:hover:bg-zinc-800">
                    <Upload className="h-4 w-4 text-gray-400" />
                    <span className="font-mono text-[11px] text-gray-400">Click to upload image</span>
                </button>
            )}
            <input ref={inputRef} type="file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" className="hidden" onChange={handleFile} />
        </div>
    );
}

// ─── Form dialog ───────────────────────────────────────────────────────────────

type FormData = {
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
    ads_status: 'pending' | 'running' | 'kill' | 'skill';
    ads_manager_link: string;
    ads_remarks: string;
};

export function CreativeFormDialog({
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
    const { data, setData, post, put, processing, errors, reset } = useForm<FormData>({
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
        ads_status: creative?.ads_status ?? 'pending',
        ads_manager_link: creative?.ads_manager_link ?? '',
        ads_remarks: creative?.ads_remarks ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const url = isEdit
            ? `/workspaces/${workspace.slug}/creatives/${creative!.id}`
            : `/workspaces/${workspace.slug}/creatives`;
        (isEdit ? put : post)(url, { onSuccess: () => { reset(); onClose(); } });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-2xl dark:bg-zinc-900 [&_[data-default-close=true]]:hidden">
                <div className="relative border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEdit ? 'Edit Creative' : 'New Creative'}
                        </DialogTitle>
                    </DialogHeader>
                    <DialogClose className="absolute top-4 right-4 inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-black/5 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/6 dark:hover:text-gray-300">
                        <X className="h-4 w-4" /><span className="sr-only">Close</span>
                    </DialogClose>
                </div>

                <form onSubmit={submit}>
                    <div className="max-h-[calc(90vh-130px)] overflow-y-auto px-5 py-5">
                        <div className="space-y-6">
                            {/* Basic Info */}
                            <section className="space-y-3">
                                <p className={fl}>Basic Info</p>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <label htmlFor="cf-name" className={fl}>Name <span className="text-red-400">*</span></label>
                                        <input id="cf-name" className={fi} value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. Summer Sale Hook" />
                                        {errors.name && <p className={fe}>{errors.name}</p>}
                                    </div>
                                    <div className="space-y-1.5">
                                        <label htmlFor="cf-date" className={fl}>Date <span className="text-red-400">*</span></label>
                                        <input id="cf-date" type="date" className={fi} value={data.creative_date} onChange={(e) => setData('creative_date', e.target.value)} />
                                        {errors.creative_date && <p className={fe}>{errors.creative_date}</p>}
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className={fl}>Format <span className="text-red-400">*</span></label>
                                        <Select value={data.format} onValueChange={(v) => setData('format', v as 'video' | 'image')}>
                                            <SelectTrigger className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100">
                                                <SelectValue placeholder="Select format" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="video" className="font-mono text-[12px]"><span className="inline-flex items-center gap-1.5"><Clapperboard className="h-3.5 w-3.5" /> Video</span></SelectItem>
                                                <SelectItem value="image" className="font-mono text-[12px]"><span className="inline-flex items-center gap-1.5"><FileImage className="h-3.5 w-3.5" /> Image</span></SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.format && <p className={fe}>{errors.format}</p>}
                                    </div>
                                    <div className="space-y-1.5">
                                        <label htmlFor="cf-headline" className={fl}>Headline</label>
                                        <input id="cf-headline" className={fi} value={data.headline} onChange={(e) => setData('headline', e.target.value)} placeholder="Ad headline text" />
                                    </div>
                                </div>
                            </section>

                            {/* Content */}
                            <section className="space-y-3">
                                <p className={fl}>Content</p>
                                <div className="space-y-3">
                                    <div className="space-y-1.5">
                                        <label htmlFor="cf-desc" className={fl}>Description</label>
                                        <textarea id="cf-desc" className={ft} rows={2} value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="Brief overview" />
                                    </div>
                                    <div className="space-y-1.5">
                                        <label htmlFor="cf-script" className={fl}>Script</label>
                                        <textarea id="cf-script" className={ft} rows={4} value={data.script} onChange={(e) => setData('script', e.target.value)} placeholder="Full script or content outline" />
                                    </div>
                                    <div className="space-y-1.5">
                                        <label htmlFor="cf-caption" className={fl}>Caption</label>
                                        <textarea id="cf-caption" className={ft} rows={2} value={data.caption} onChange={(e) => setData('caption', e.target.value)} placeholder="Ad copy / post caption" />
                                    </div>
                                </div>
                            </section>

                            {/* Media & Links */}
                            <section className="space-y-3">
                                <p className={fl}>Media & Links</p>
                                <div className="space-y-3">
                                    {data.format === 'image' ? (
                                        <div className="space-y-1.5">
                                            <label className={fl}>Image</label>
                                            <ImageUploadField
                                                currentUrl={data.picture_url || null}
                                                onFileSelect={(file) => setData('picture_file', file)}
                                                onClear={() => { setData('picture_file', null); setData('picture_url', ''); }}
                                            />
                                            {errors.picture_file && <p className={fe}>{errors.picture_file}</p>}
                                        </div>
                                    ) : data.format === 'video' ? (
                                        <div className="space-y-1.5">
                                            <label htmlFor="cf-vid" className={fl}>Video URL</label>
                                            <input id="cf-vid" className={fi} value={data.picture_url} onChange={(e) => setData('picture_url', e.target.value)} placeholder="https://drive.google.com/..." />
                                        </div>
                                    ) : null}
                                    <div className="space-y-1.5">
                                        <label htmlFor="cf-ref" className={fl}>Reference Link</label>
                                        <input id="cf-ref" className={fi} value={data.reference_link} onChange={(e) => setData('reference_link', e.target.value)} placeholder="https://..." />
                                    </div>
                                </div>
                            </section>

                            {/* Campaign */}
                            <section className="space-y-3">
                                <p className={fl}>Campaign</p>
                                <div className="space-y-3">
                                    <div className="space-y-1.5">
                                        <label className={fl}>Ads Status</label>
                                        <Select value={data.ads_status} onValueChange={(v) => setData('ads_status', v as FormData['ads_status'])}>
                                            <SelectTrigger className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="pending" className="font-mono text-[12px]">Pending</SelectItem>
                                                <SelectItem value="running" className="font-mono text-[12px]">Running</SelectItem>
                                                <SelectItem value="kill" className="font-mono text-[12px]">Kill</SelectItem>
                                                <SelectItem value="skill" className="font-mono text-[12px]">Skill</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <label htmlFor="cf-ads-manager" className={fl}>Ads Manager Link</label>
                                        <input id="cf-ads-manager" className={fi} value={data.ads_manager_link} onChange={(e) => setData('ads_manager_link', e.target.value)} placeholder="https://business.facebook.com/adsmanager/..." />
                                        {errors.ads_manager_link && <p className={fe}>{errors.ads_manager_link}</p>}
                                    </div>
                                    <div className="space-y-1.5">
                                        <label htmlFor="cf-ads-remarks" className={fl}>Remarks</label>
                                        <textarea id="cf-ads-remarks" className={ft} rows={2} value={data.ads_remarks} onChange={(e) => setData('ads_remarks', e.target.value)} placeholder="Campaign notes, budget info, targeting details..." />
                                    </div>
                                </div>
                            </section>

                            {/* Notes */}
                            <section className="space-y-3">
                                <p className={fl}>Notes</p>
                                <textarea id="cf-notes" className={ft} rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} placeholder="Internal notes visible only to your team" />
                            </section>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-black/6 bg-stone-50/50 px-5 py-3 dark:border-white/6 dark:bg-white/2">
                        <button type="button" onClick={onClose} className="flex h-9 items-center rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700">Cancel</button>
                        <button type="submit" disabled={processing} className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50">
                            {isEdit ? 'Save Changes' : 'Create Creative'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
