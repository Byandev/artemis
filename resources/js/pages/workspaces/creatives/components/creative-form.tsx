import DatePicker from '@/components/ui/date-picker';
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
import { router, useForm } from '@inertiajs/react';
import { Clapperboard, FileImage, Loader2 } from 'lucide-react';
import React from 'react';
import { AdsStatus, Creative, FinalStatus, Product, Reviewer } from '../types';
import { AssigneePicker } from './assignee-picker';
import { ProductPicker } from './product-picker';

// ─── Style tokens ─────────────────────────────────────────────────────────────

const fi =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100';
const fl =
    'block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
const fe = 'mt-1 font-mono text-[11px] text-red-500';
const ft =
    'w-full resize-none rounded-[10px] border border-black/8 bg-stone-50 p-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100';
const selectTrigger =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100';

type FormData = {
    name: string;
    creative_date: string;
    format: 'video' | 'image' | '';
    product_id: number | null;
    assigned_reviewer_ids: number[];
    description: string;
    script: string;
    picture_url: string;
    reference_link: string;
    caption: string;
    headline: string;
    notes: string;
    ads_status: AdsStatus;
    final_status: FinalStatus;
};

export function CreativeForm({
    workspace,
    creative,
    reviewers = [],
    products = [],
}: {
    workspace: Workspace;
    creative?: Creative;
    reviewers?: Reviewer[];
    products?: Product[];
}) {
    const isEdit = !!creative;
    const canUpdateStatus = usePermission(PERMISSIONS.UpdateCreativeStatus);
    const baseUrl = `/workspaces/${workspace.slug}/creatives`;

    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        name: creative?.name ?? '',
        creative_date: creative?.creative_date ?? '',
        format: creative?.format ?? '',
        product_id: creative?.product?.id ?? null,
        assigned_reviewer_ids:
            creative?.assigned_reviewers?.map((r) => r.id) ?? [],
        description: creative?.description ?? '',
        script: creative?.script ?? '',
        picture_url: creative?.picture_url ?? '',
        reference_link: creative?.reference_link ?? '',
        caption: creative?.caption ?? '',
        headline: creative?.headline ?? '',
        notes: creative?.notes ?? '',
        ads_status: creative?.ads_status ?? 'pending',
        final_status: creative?.final_status ?? 'for_approval',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEdit) {
            put(`${baseUrl}/${creative!.id}`);
        } else {
            post(baseUrl);
        }
    };

    return (
        <form
            onSubmit={submit}
            className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900"
        >
            <div className="space-y-6 px-5 py-5">
                {/* Basic Info */}
                <section className="space-y-3">
                    <p className={fl}>Basic Info</p>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <label htmlFor="cf-name" className={fl}>
                                Name <span className="text-red-400">*</span>
                            </label>
                            <input
                                id="cf-name"
                                className={fi}
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Unique: Example: MM-DD-YYYY-PRODUCT-EDITOR_NAME-AD_NAME"
                            />
                            {errors.name && <p className={fe}>{errors.name}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <label htmlFor="cf-date" className={fl}>
                                Date <span className="text-red-400">*</span>
                            </label>
                            <DatePicker
                                id="cf-date"
                                mode="single"
                                fullWidth
                                placeholder="Select date"
                                defaultDate={data.creative_date || undefined}
                                onChange={(_dates, dateStr) =>
                                    setData('creative_date', dateStr)
                                }
                            />
                            {errors.creative_date && (
                                <p className={fe}>{errors.creative_date}</p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <label className={fl}>
                                Format <span className="text-red-400">*</span>
                            </label>
                            <Select
                                value={data.format}
                                onValueChange={(v) =>
                                    setData('format', v as 'video' | 'image')
                                }
                            >
                                <SelectTrigger className={selectTrigger}>
                                    <SelectValue placeholder="Select format" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        value="video"
                                        className="font-mono text-[12px]"
                                    >
                                        <span className="inline-flex items-center gap-1.5">
                                            <Clapperboard className="h-3.5 w-3.5" />{' '}
                                            Video
                                        </span>
                                    </SelectItem>
                                    <SelectItem
                                        value="image"
                                        className="font-mono text-[12px]"
                                    >
                                        <span className="inline-flex items-center gap-1.5">
                                            <FileImage className="h-3.5 w-3.5" />{' '}
                                            Image
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.format && (
                                <p className={fe}>{errors.format}</p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <label htmlFor="cf-headline" className={fl}>
                                Headline{' '}
                                <span className="text-red-400">*</span>
                            </label>
                            <input
                                id="cf-headline"
                                className={fi}
                                value={data.headline}
                                onChange={(e) =>
                                    setData('headline', e.target.value)
                                }
                                placeholder="Ad headline text"
                            />
                            {errors.headline && (
                                <p className={fe}>{errors.headline}</p>
                            )}
                        </div>
                        <div className="col-span-2 space-y-1.5">
                            <label className={fl}>
                                Product{' '}
                                <span className="text-red-400">*</span>
                            </label>
                            <ProductPicker
                                products={products}
                                value={data.product_id}
                                onChange={(id) => setData('product_id', id)}
                            />
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-600">
                                Which product is this creative for?
                            </p>
                            {errors.product_id && (
                                <p className={fe}>{errors.product_id}</p>
                            )}
                        </div>
                    </div>
                </section>

                {/* Content */}
                <section className="space-y-3">
                    <p className={fl}>Content</p>
                    <div className="space-y-3">
                        <div className="space-y-1.5">
                            <label htmlFor="cf-desc" className={fl}>
                                Description
                            </label>
                            <textarea
                                id="cf-desc"
                                className={ft}
                                rows={2}
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder="Brief overview"
                            />
                            {errors.description && (
                                <p className={fe}>{errors.description}</p>
                            )}
                        </div>
                        {data.format === 'video' && (
                            <div className="space-y-1.5">
                                <label htmlFor="cf-script" className={fl}>
                                    Script
                                </label>
                                <textarea
                                    id="cf-script"
                                    className={ft}
                                    rows={4}
                                    value={data.script}
                                    onChange={(e) =>
                                        setData('script', e.target.value)
                                    }
                                    placeholder="Full script or content outline"
                                />
                                {errors.script && (
                                    <p className={fe}>{errors.script}</p>
                                )}
                            </div>
                        )}
                        <div className="space-y-1.5">
                            <label htmlFor="cf-caption" className={fl}>
                                Caption{' '}
                                <span className="text-red-400">*</span>
                            </label>
                            <textarea
                                id="cf-caption"
                                className={ft}
                                rows={2}
                                value={data.caption}
                                onChange={(e) =>
                                    setData('caption', e.target.value)
                                }
                                placeholder="Ad copy / post caption"
                            />
                            {errors.caption && (
                                <p className={fe}>{errors.caption}</p>
                            )}
                        </div>
                    </div>
                </section>

                {/* Media & Links */}
                <section className="space-y-3">
                    <p className={fl}>Media & Links</p>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <label htmlFor="cf-media" className={fl}>
                                Media Link{' '}
                                <span className="text-red-400">*</span>
                            </label>
                            <input
                                id="cf-media"
                                className={fi}
                                value={data.picture_url}
                                onChange={(e) =>
                                    setData('picture_url', e.target.value)
                                }
                                placeholder="https://drive.google.com/..."
                            />
                            {errors.picture_url && (
                                <p className={fe}>{errors.picture_url}</p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <label htmlFor="cf-ref" className={fl}>
                                Reference Link
                            </label>
                            <input
                                id="cf-ref"
                                className={fi}
                                value={data.reference_link}
                                onChange={(e) =>
                                    setData('reference_link', e.target.value)
                                }
                                placeholder="https://..."
                            />
                            {errors.reference_link && (
                                <p className={fe}>{errors.reference_link}</p>
                            )}
                        </div>
                    </div>
                </section>

                {/* Reviewers */}
                <section className="space-y-3">
                    <p className={fl}>Reviewers</p>
                    <div className="space-y-1.5">
                        <label className={fl}>Assigned Reviewers</label>
                        <AssigneePicker
                            reviewers={reviewers}
                            selectedIds={data.assigned_reviewer_ids}
                            onChange={(ids) =>
                                setData('assigned_reviewer_ids', ids)
                            }
                        />
                        {errors.assigned_reviewer_ids && (
                            <p className={fe}>{errors.assigned_reviewer_ids}</p>
                        )}
                    </div>
                </section>

                {/* Campaign */}
                <section className="space-y-3">
                    <p className={fl}>Campaign</p>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <label className={fl}>Ads Status</label>
                            <Select
                                value={data.ads_status}
                                onValueChange={(v) =>
                                    setData('ads_status', v as AdsStatus)
                                }
                                disabled={!isEdit || !canUpdateStatus}
                            >
                                <SelectTrigger className={selectTrigger}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        value="pending"
                                        className="font-mono text-[12px]"
                                    >
                                        Pending
                                    </SelectItem>
                                    <SelectItem
                                        value="running"
                                        className="font-mono text-[12px]"
                                    >
                                        Running
                                    </SelectItem>
                                    <SelectItem
                                        value="kill"
                                        className="font-mono text-[12px]"
                                    >
                                        Kill
                                    </SelectItem>
                                    <SelectItem
                                        value="scale"
                                        className="font-mono text-[12px]"
                                    >
                                        Scale
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.ads_status && (
                                <p className={fe}>{errors.ads_status}</p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <label className={fl}>Final Status</label>
                            <Select
                                value={data.final_status}
                                onValueChange={(v) =>
                                    setData('final_status', v as FinalStatus)
                                }
                                disabled={!isEdit || !canUpdateStatus}
                            >
                                <SelectTrigger className={selectTrigger}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        value="for_approval"
                                        className="font-mono text-[12px]"
                                    >
                                        For Approval
                                    </SelectItem>
                                    <SelectItem
                                        value="approved"
                                        className="font-mono text-[12px]"
                                    >
                                        Approved
                                    </SelectItem>
                                    <SelectItem
                                        value="for_revision"
                                        className="font-mono text-[12px]"
                                    >
                                        For Revision
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.final_status && (
                                <p className={fe}>{errors.final_status}</p>
                            )}
                        </div>
                    </div>
                </section>

                {/* Notes */}
                <section className="space-y-3">
                    <p className={fl}>Notes</p>
                    <textarea
                        id="cf-notes"
                        className={ft}
                        rows={2}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder="Internal notes visible only to your team"
                    />
                    {errors.notes && <p className={fe}>{errors.notes}</p>}
                </section>
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-black/6 bg-stone-50/50 px-5 py-3 dark:border-white/6 dark:bg-white/2">
                <button
                    type="button"
                    disabled={processing}
                    onClick={() => router.visit(baseUrl)}
                    className="flex h-9 items-center rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    disabled={processing}
                    className="flex h-9 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {processing && (
                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    )}
                    {processing
                        ? isEdit
                            ? 'Saving…'
                            : 'Creating…'
                        : isEdit
                          ? 'Save Changes'
                          : 'Create Creative'}
                </button>
            </div>
        </form>
    );
}
