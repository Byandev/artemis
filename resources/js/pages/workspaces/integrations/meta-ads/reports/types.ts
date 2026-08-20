import type { InsightsMetrics } from '../_shared';

export type ReportKind = 'top_performers' | 'custom_groups' | 'categorization';

export type GroupByKey =
    | 'ad'
    | 'ad_name'
    | 'campaign'
    | 'ad_set'
    | 'account'
    | 'ad_type';

/** A saved custom breakdown is referenced as `custom:{id}` in group_by. */
export type CustomBreakdownRef = `custom:${number}`;

export type BreakdownValue = GroupByKey | CustomBreakdownRef;

export type NameFilterOp = 'is' | 'is_not' | 'contains' | 'not_contains';

/** Numeric (HAVING) operators the data engine accepts for metric filters. */
export type MetricFilterOp = 'gt' | 'gte' | 'lt' | 'lte' | 'eq' | 'range';

/** Date operators for the entity lifecycle filters. */
export type DateFilterOp = 'on' | 'before' | 'after' | 'between';

/**
 * The row's own lifecycle dates — when the campaign/ad set/ad was created, and
 * when it started running. Distinct from the report's `since`/`until`, which
 * pick which insight days to sum.
 */
export type DateFilterField = 'created_date' | 'started_date';

export type NameFilter = {
    field: 'name';
    op: NameFilterOp;
    value: string;
};

export type MetricFilter = {
    field: string; // a metric id, e.g. 'spend', 'roas'
    op: MetricFilterOp;
    value: number;
    value2?: number; // upper bound for 'range'
};

export type DateFilter = {
    field: DateFilterField;
    op: DateFilterOp;
    value: string; // YYYY-MM-DD
    value2?: string; // upper bound for 'between'
};

export type ReportFilter = NameFilter | MetricFilter | DateFilter;

export const isNameFilter = (f: ReportFilter): f is NameFilter =>
    f.field === 'name';

export const isDateFilter = (f: ReportFilter): f is DateFilter =>
    f.field === 'created_date' || f.field === 'started_date';

/**
 * Positive test rather than "not a name filter" — `MetricFilter.field` is a bare
 * string, so anything unrecognised would otherwise be swept in as a metric.
 */
export const isMetricFilter = (f: ReportFilter): f is MetricFilter =>
    !isNameFilter(f) && !isDateFilter(f);

export const DATE_OP_LABELS: Record<DateFilterOp, string> = {
    on: 'is on',
    before: 'is before',
    after: 'is after',
    between: 'is between',
};

export const DATE_FIELD_LABELS: Record<DateFilterField, string> = {
    created_date: 'Created date',
    started_date: 'Started date',
};

/**
 * Which breakdowns can answer each date filter. Ads carry no `start_time` of
 * their own, so an ad-grained "Started date" is answered through the owning ad
 * set. Accounts have neither date, so neither filter applies there.
 */
export const DATE_FIELD_BREAKDOWNS: Record<DateFilterField, GroupByKey[]> = {
    created_date: ['ad', 'ad_name', 'ad_type', 'ad_set', 'campaign'],
    started_date: ['ad', 'ad_name', 'ad_type', 'ad_set', 'campaign'],
};

/**
 * Whether a date filter can be applied to the report's current breakdown.
 * Custom breakdowns aggregate ads, so they follow the ad-grained rules.
 */
export const dateFilterSupported = (
    field: DateFilterField,
    groupBy: BreakdownValue,
): boolean =>
    groupBy.startsWith('custom:') ||
    DATE_FIELD_BREAKDOWNS[field].includes(groupBy as GroupByKey);

export const METRIC_OP_LABELS: Record<MetricFilterOp, string> = {
    gt: 'greater than',
    gte: 'greater or equal',
    lt: 'less than',
    lte: 'less or equal',
    eq: 'equals',
    range: 'between',
};

/** How the report's rows are visualised. */
export type ChartStyle = 'gallery' | 'bar' | 'stacked_bar' | 'line' | 'area';

/** Presentation-only settings for the gallery / chart (persisted in config). */
export type ReportView = {
    /** Gallery card size: 1 (small) → 3 (large), maps to grid columns. */
    cardSize: number;
    hideThumbnails: boolean;
    /** How many rows to render in the gallery / chart. */
    itemsLoaded: number;
};

// `type` (not `interface`) so the object satisfies Inertia's FormDataConvertible
// index signature when passed straight to router.post/patch.
export type ReportConfig = {
    accounts: string[];
    since: string;
    until: string;
    group_by: BreakdownValue;
    metrics: string[];
    sort: string;
    filters: ReportFilter[];
    /** Internal-creator filter (ad-level). A member id, 'unassigned', or null. */
    creator_id?: number | 'unassigned' | null;
    chart: ChartStyle;
    view: ReportView;
};

/** A saved custom breakdown, as passed to the report builder. */
export interface CustomBreakdownItem {
    id: string;
    name: string;
}

/** Human label for a group_by value, resolving custom breakdowns by id. */
export function breakdownLabel(
    value: BreakdownValue,
    customBreakdowns: CustomBreakdownItem[] = [],
): string {
    if (value.startsWith('custom:')) {
        const id = value.slice('custom:'.length);
        return (
            customBreakdowns.find((b) => b.id === id)?.name ??
            'Custom breakdown'
        );
    }
    return GROUP_BY_LABELS[value as GroupByKey] ?? value;
}

export const CHART_LABELS: Record<ChartStyle, string> = {
    gallery: 'Gallery',
    bar: 'Bar',
    stacked_bar: 'Stacked Bar',
    line: 'Line',
    area: 'Area',
};

export const DEFAULT_VIEW: ReportView = {
    cardSize: 2,
    hideThumbnails: false,
    itemsLoaded: 12,
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
    ad_type: 'Ad type',
};

/**
 * Breakdown picker options, grouped by category like the SuperAds "Add
 * breakdown" menu. Only dimensions the data engine can group by are listed.
 */
export interface BreakdownOption {
    key: GroupByKey;
    label: string;
    category: string;
}

export const BREAKDOWN_OPTIONS: BreakdownOption[] = [
    { key: 'ad', label: 'Ad / Creative', category: 'Channel breakdowns' },
    { key: 'ad_name', label: 'Ad name', category: 'Channel breakdowns' },
    { key: 'ad_set', label: 'Adset name', category: 'Channel breakdowns' },
    { key: 'campaign', label: 'Campaign name', category: 'Channel breakdowns' },
    { key: 'account', label: 'Account name', category: 'Channel breakdowns' },
    { key: 'ad_type', label: 'Ad type', category: 'Channel breakdowns' },
];

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
        creator_id: null,
        chart: 'gallery',
        view: { ...DEFAULT_VIEW },
    };

    // "Compare custom groups" defaults to a campaign breakdown; the named
    // custom-group rule builder is a later phase.
    if (kind === 'custom_groups') {
        return { ...base, group_by: 'campaign' };
    }

    return base;
}
