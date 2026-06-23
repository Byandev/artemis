import type { InsightsMetrics } from '../_shared';

export type ReportKind = 'top_performers' | 'custom_groups' | 'categorization';

export type GroupByKey = 'ad' | 'ad_name' | 'campaign' | 'ad_set' | 'account';

export type NameFilterOp = 'is' | 'is_not' | 'contains' | 'not_contains';

export type ReportFilter = {
    field: 'name';
    op: NameFilterOp;
    value: string;
};

// `type` (not `interface`) so the object satisfies Inertia's FormDataConvertible
// index signature when passed straight to router.post/patch.
export type ReportConfig = {
    accounts: string[];
    since: string;
    until: string;
    group_by: GroupByKey;
    metrics: string[];
    sort: string;
    filters: ReportFilter[];
};

export interface ReportListItem {
    id: number;
    name: string;
    description: string | null;
    kind: ReportKind;
    updated_at: string;
}

export interface ReportRecord {
    id: number;
    name: string;
    description: string | null;
    kind: ReportKind;
    config: ReportConfig;
}

/** A row returned by the /ads-manager/data endpoint (Gallery uses group_by=ad). */
export type ReportRow = InsightsMetrics & {
    id: string;
    name: string | null;
    status?: string | null;
    effective_status?: string | null;
    thumbnail_url?: string | null;
    image_url?: string | null;
    video_id?: string | null;
    media_type?: 'video' | 'image' | null;
    ads_count?: number;
};

export const GROUP_BY_LABELS: Record<GroupByKey, string> = {
    ad: 'Ad',
    ad_name: 'Ad name',
    campaign: 'Campaign',
    ad_set: 'Ad set',
    account: 'Account',
};

export const NAME_OP_LABELS: Record<NameFilterOp, string> = {
    is: 'is',
    is_not: 'is not',
    contains: 'contains',
    not_contains: 'does not contain',
};

export const reportsUrl = (slug: string): string =>
    `/workspaces/${slug}/integrations/meta/reports`;

export const adsManagerDataUrl = (slug: string): string =>
    `/workspaces/${slug}/integrations/meta/ads-manager/data`;

export const adDetailUrl = (slug: string, adId: string): string =>
    `/workspaces/${slug}/integrations/meta/ads-manager/ads/${adId}/detail`;

export interface AdDetail {
    dimensions: {
        ad_status: string | null;
        optimization_goal: string | null;
        ad_name: string | null;
        ad_id: string;
        adset_name: string | null;
        campaign_name: string | null;
        account_name: string | null;
        ad_type: string;
        media_type: 'video' | 'image' | null;
        call_to_action: string | null;
    };
    preview: { src: string | null };
}

/** ISO date (YYYY-MM-DD) for `offset` days before today (0 = today). */
export function isoDaysAgo(offset: number): string {
    const d = new Date();
    d.setDate(d.getDate() - offset);
    return d.toISOString().slice(0, 10);
}

/** Default builder config for a freshly created report of the given kind. */
export function defaultConfig(
    kind: ReportKind,
    accounts: string[],
): ReportConfig {
    const base: ReportConfig = {
        accounts,
        since: isoDaysAgo(13),
        until: isoDaysAgo(0),
        group_by: 'ad',
        metrics: ['spend', 'clicks', 'impressions'],
        sort: '-spend',
        filters: [],
    };

    // "Compare custom groups" defaults to a campaign breakdown; the named
    // custom-group rule builder is a later phase.
    if (kind === 'custom_groups') {
        return { ...base, group_by: 'campaign' };
    }

    return base;
}
