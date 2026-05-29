import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';

export type ReviewStatus =
    | 'waiting_for_submission'
    | 'for_approval'
    | 'revision'
    | 'approved'
    | 'for_reapproval';

export type AdsStatus = 'pending' | 'running' | 'kill' | 'skill';

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

export interface Creator {
    id: number;
    name: string;
}

export interface PageProps {
    workspace: Workspace;
    creatives: PaginatedData<Creative>;
    creators: Creator[];
    query: {
        sort?: string;
        page?: number;
        per_page?: number;
        filter?: {
            search?: string;
            format?: string;
            ads_status?: string;
            creator_id?: string;
            date_from?: string;
            date_to?: string;
        };
    };
}

export const REVIEW_STATUS_LABELS: Record<ReviewStatus, string> = {
    waiting_for_submission: 'Waiting',
    for_approval: 'For Approval',
    revision: 'Revision',
    approved: 'Approved',
    for_reapproval: 'For Re-approval',
};

export const REVIEW_BADGE: Record<ReviewStatus, string> = {
    waiting_for_submission: 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
    for_approval: 'bg-blue-50 text-blue-600 dark:bg-blue-500/[0.12] dark:text-blue-400',
    revision: 'bg-amber-50 text-amber-600 dark:bg-amber-500/[0.12] dark:text-amber-400',
    approved: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/[0.12] dark:text-emerald-400',
    for_reapproval: 'bg-purple-50 text-purple-600 dark:bg-purple-500/[0.12] dark:text-purple-400',
};

export const REVIEW_AVATAR_BG: Record<ReviewStatus, string> = {
    waiting_for_submission: 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
    for_approval: 'bg-blue-50 text-blue-600 dark:bg-blue-500/[0.15] dark:text-blue-400',
    revision: 'bg-amber-50 text-amber-600 dark:bg-amber-500/[0.15] dark:text-amber-400',
    approved: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/[0.15] dark:text-emerald-400',
    for_reapproval: 'bg-purple-50 text-purple-600 dark:bg-purple-500/[0.15] dark:text-purple-400',
};

export const ADS_STATUS_LABELS: Record<AdsStatus, string> = {
    pending: 'Pending',
    running: 'Running',
    kill: 'Kill',
    skill: 'Skill',
};

export const ADS_BADGE: Record<AdsStatus, string> = {
    pending: 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
    running: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/[0.12] dark:text-emerald-400',
    kill: 'bg-red-50 text-red-600 dark:bg-red-500/[0.12] dark:text-red-400',
    skill: 'bg-orange-50 text-orange-600 dark:bg-orange-500/[0.12] dark:text-orange-400',
};
