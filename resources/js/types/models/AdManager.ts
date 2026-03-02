export interface Campaign {
    id: number;
    name: string;
    status: string;
    daily_budget: number | null;
    start_time: string;
    end_time: string | null;
    impressions?: number;
    clicks?: number;
    spend?: number;
    cpm?: number;
    ctr?: number;
    conversions?: number;
    cpc?: number;
    roas?: number;
    ad_account: {
        id: number;
        name: string;
    };
    created_at: string;
    updated_at: string;
}

export interface Ad {
    id: number;
    name: string;
    status: string;
    impressions?: number;
    clicks?: number;
    spend?: number;
    campaign: {
        id: number;
        name: string;
    };
    ad_set: {
        id: number;
        name: string;
    };
    created_at: string;
    updated_at: string;
}

export interface AdSet {
    id: number;
    name: string;
    status: string;
    daily_budget: number | null;
    impressions?: number;
    clicks?: number;
    spend?: number;
    conversions?: number;
    ctr?: number;
    cpc?: number;
    cpm?: number;
    campaign: {
        id: number;
        name: string;
    };
    created_at: string;
    updated_at: string;
}

export interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLinks[];
    from: number;
    to: number;
}

export interface PaginatedCampaigns extends PaginatedData<Campaign> {}

export interface PaginatedAdSets extends PaginatedData<AdSet> {}

export interface PaginatedAds extends PaginatedData<Ad> {}

export interface MetricFilter {
    metric: string;
    operator: string;
    value: string;
}

export interface StatusBadgeProps {
    status: string;
}

export interface PageProps {
    workspace: any;
    filters?: {
        start_date?: string;
        end_date?: string;
        status?: string;
        search?: string;
    }
}

export enum AdMetric {
    IMPRESSIONS = 'impressions',
    CLICKS = 'clicks',
    SPEND = 'spend',
}

export const AVAILABLE_AD_METRICS = [
    AdMetric.IMPRESSIONS,
    AdMetric.CLICKS,
    AdMetric.SPEND,
];