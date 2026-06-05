import { Workspace } from '@/types/models/Workspace';
import { AdsStatus, FinalStatus, ReviewStatus } from '../../types';

export interface WorkItem {
    id: number;
    name: string;
    format: 'video' | 'image';
    creative_date: string | null;
    product: string | null;
    final_status: FinalStatus;
    feedback: string | null;
    reviewer: string | null;
}

export interface LeaderRow {
    creator_id: number;
    name: string;
    total: number;
    scaled: number;
    approval_rate: number;
}

export interface ActivityRow {
    id: number;
    creative_id: number;
    creative_name: string | null;
    status: ReviewStatus;
    feedback: string | null;
    reviewer: string | null;
    created_at: string | null;
}

export interface Kpis {
    total: number;
    video: number;
    image: number;
    awaiting_review: number;
    needs_revision: number;
    approved: number;
    approval_rate: number;
    ads: Record<AdsStatus, number>;
}

export interface Pipeline {
    waiting: number;
    for_approval: number;
    revision: number;
    approved: number;
    running_ads: number;
}

export interface Throughput {
    categories: string[];
    video: number[];
    image: number[];
}

export interface DashboardFilters {
    date_from: string;
    date_to: string;
    product_id: string;
    format: string;
}

export interface ProductOption {
    id: number;
    title: string;
}

export interface DashboardPageProps {
    workspace: Workspace;
    currentUserId: number;
    kpis: Kpis;
    pipeline: Pipeline;
    revisionList: WorkItem[];
    waitingList: WorkItem[];
    throughput: Throughput;
    leaderboard: LeaderRow[];
    recentActivity: ActivityRow[];
    products: ProductOption[];
    filters: DashboardFilters;
}

/** Patch applied to the current filter set, then pushed to the server. */
export type ApplyFilter = (patch: Partial<DashboardFilters>) => void;

/** Builds the edit URL for a creative id. */
export type EditUrl = (id: number) => string;
