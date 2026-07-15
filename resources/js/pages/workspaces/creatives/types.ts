import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';

export type ReviewStatus =
    | 'waiting_for_submission'
    | 'for_approval'
    | 'revision'
    | 'approved'
    | 'for_reapproval';

// Statuses a reviewer can actually choose when leaving a review.
export const REVIEW_STATUS_OPTIONS: ReviewStatus[] = ['approved', 'revision'];

export interface Review {
    id: number;
    status: ReviewStatus;
    feedback: string | null;
    reviewer: { id: number; name: string } | null;
    created_at: string;
}

export interface Creative {
    id: number;
    name: string;
    description: string | null;
    format: 'video' | 'image';
    creative_date: string;
    creative_date_label: string | null;
    created_at: string;
    /** How created_at compares to creative_date: late / early / on_time. */
    submission_status: 'late' | 'early' | 'on_time' | null;
    script: string | null;
    picture_url: string | null;
    reference_link: string | null;
    ads_status: AdsStatus;
    ads_manager_link: string | null;
    ads_remarks: string | null;
    final_status: FinalStatus;
    approved_at: string | null;
    approved_by: { id: number; name: string } | null;
    caption: string | null;
    headline: string | null;
    notes: string | null;
    creator: { id: number; name: string } | null;
    product: { id: number; title: string } | null;
    assigned_reviewers: { id: number; name: string }[];
    reviews: Review[];
    review_count: number;
    latest_review: { status: ReviewStatus; feedback: string | null } | null;
}

export interface Creator {
    id: number;
    name: string;
}

export interface Reviewer {
    id: number;
    name: string;
}

export interface Product {
    id: number;
    title: string;
}

export interface PageProps {
    workspace: Workspace;
    creatives: PaginatedData<Creative>;
    creators: Creator[];
    approvers: Creator[];
    products: Product[];
    reviewers: Reviewer[];
    query: {
        sort?: string;
        page?: number;
        per_page?: number;
        filter?: {
            search?: string;
            format?: string;
            ads_status?: string;
            final_status?: string;
            creator_id?: string;
            approved_by?: string;
            product_id?: string;
            creative_date_from?: string;
            creative_date_to?: string;
            created_at_from?: string;
            created_at_to?: string;
            approved_at_from?: string;
            approved_at_to?: string;
        };
    };
}

// The three independent date-range filters, keyed by their date column. Each
// maps to `filter[<key>_from]` / `filter[<key>_to]` query params.
export type DateField = 'creative_date' | 'created_at' | 'approved_at';

export const DATE_RANGE_FIELDS: { key: DateField; label: string }[] = [
    { key: 'creative_date', label: 'Creative Date' },
    { key: 'created_at', label: 'Created Date' },
    { key: 'approved_at', label: 'Approved Date' },
];

export type AdsStatus = 'pending' | 'running' | 'kill' | 'scale';

export const ADS_STATUS_LABELS: Record<AdsStatus, string> = {
    pending: 'Pending',
    running: 'Running',
    kill: 'Kill',
    scale: 'Scale',
};

export const ADS_BADGE: Record<AdsStatus, string> = {
    pending: 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
    running:
        'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/[0.12] dark:text-emerald-400',
    kill: 'bg-red-50 text-red-600 dark:bg-red-500/[0.12] dark:text-red-400',
    scale: 'bg-orange-50 text-orange-600 dark:bg-orange-500/[0.12] dark:text-orange-400',
};

export const ADS_DOT: Record<AdsStatus, string> = {
    pending: 'bg-gray-400 dark:bg-gray-500',
    running: 'bg-emerald-500',
    kill: 'bg-red-500',
    scale: 'bg-orange-500',
};

export const REVIEW_STATUS_LABELS: Record<ReviewStatus, string> = {
    waiting_for_submission: 'Waiting',
    for_approval: 'For Approval',
    revision: 'For Revision',
    approved: 'Approved',
    for_reapproval: 'For Re-approval',
};

export const REVIEW_BADGE: Record<ReviewStatus, string> = {
    waiting_for_submission:
        'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
    for_approval:
        'bg-blue-50 text-blue-600 dark:bg-blue-500/[0.12] dark:text-blue-400',
    revision:
        'bg-amber-50 text-amber-600 dark:bg-amber-500/[0.12] dark:text-amber-400',
    approved:
        'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/[0.12] dark:text-emerald-400',
    for_reapproval:
        'bg-purple-50 text-purple-600 dark:bg-purple-500/[0.12] dark:text-purple-400',
};

export const REVIEW_DOT: Record<ReviewStatus, string> = {
    waiting_for_submission: 'bg-gray-400 dark:bg-gray-500',
    for_approval: 'bg-blue-500',
    revision: 'bg-amber-500',
    approved: 'bg-emerald-500',
    for_reapproval: 'bg-purple-500',
};

export type FinalStatus = 'for_approval' | 'approved' | 'for_revision';

export const FINAL_STATUS_LABELS: Record<FinalStatus, string> = {
    for_approval: 'For Approval',
    approved: 'Approved',
    for_revision: 'For Revision',
};

export const FINAL_STATUS_BADGE: Record<FinalStatus, string> = {
    for_approval:
        'bg-blue-50 text-blue-600 dark:bg-blue-500/[0.12] dark:text-blue-400',
    approved:
        'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/[0.12] dark:text-emerald-400',
    for_revision:
        'bg-amber-50 text-amber-600 dark:bg-amber-500/[0.12] dark:text-amber-400',
};

export const FINAL_DOT: Record<FinalStatus, string> = {
    for_approval: 'bg-blue-500',
    approved: 'bg-emerald-500',
    for_revision: 'bg-amber-500',
};

export const REVIEW_AVATAR_BG: Record<ReviewStatus, string> = {
    waiting_for_submission:
        'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
    for_approval:
        'bg-blue-50 text-blue-600 dark:bg-blue-500/[0.15] dark:text-blue-400',
    revision:
        'bg-amber-50 text-amber-600 dark:bg-amber-500/[0.15] dark:text-amber-400',
    approved:
        'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/[0.15] dark:text-emerald-400',
    for_reapproval:
        'bg-purple-50 text-purple-600 dark:bg-purple-500/[0.15] dark:text-purple-400',
};
