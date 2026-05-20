import { Button } from '@/components/ui/button';
import { SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { router } from '@inertiajs/react';
import { ColumnDef, VisibilityState } from '@tanstack/react-table';
import clsx from 'clsx';
import { Columns3 } from 'lucide-react';
import { useEffect, useState } from 'react';

export function StatusToggle({
    status,
    effectiveStatus,
}: {
    status: string | null;
    effectiveStatus: string | null;
}) {
    const isActive =
        status === 'ACTIVE' &&
        (effectiveStatus === 'ACTIVE' || effectiveStatus === 'IN_PROCESS');
    const isPaused = status === 'PAUSED';
    const isDeleted = status === 'DELETED' || status === 'ARCHIVED';

    return (
        <div
            className={clsx(
                'inline-flex h-5 w-9 cursor-default items-center rounded-full px-0.5 transition-colors',
                isActive && 'bg-emerald-500',
                isPaused && 'bg-stone-300 dark:bg-zinc-700',
                isDeleted && 'bg-red-200 dark:bg-red-900/30',
            )}
            title={effectiveStatus ?? status ?? 'Unknown'}
        >
            <span
                className={clsx(
                    'h-4 w-4 rounded-full bg-white shadow-sm transition-transform',
                    isActive && 'translate-x-4',
                    !isActive && 'translate-x-0',
                )}
            />
        </div>
    );
}

export function StatusLabel({ status }: { status: string | null }) {
    if (!status)
        return <span className="text-gray-300 dark:text-gray-600">—</span>;
    const map: Record<string, { label: string; cls: string; dot: string }> = {
        ACTIVE: {
            label: 'Active',
            cls: 'text-emerald-700 dark:text-emerald-400',
            dot: 'bg-emerald-500',
        },
        IN_PROCESS: {
            label: 'In Process',
            cls: 'text-blue-600 dark:text-blue-400',
            dot: 'bg-blue-500',
        },
        PAUSED: {
            label: 'Paused',
            cls: 'text-gray-500 dark:text-gray-400',
            dot: 'bg-gray-400',
        },
        DELETED: {
            label: 'Deleted',
            cls: 'text-red-500 dark:text-red-400',
            dot: 'bg-red-400',
        },
        ARCHIVED: {
            label: 'Archived',
            cls: 'text-gray-400 dark:text-gray-500',
            dot: 'bg-gray-300',
        },
        WITH_ISSUES: {
            label: 'With Issues',
            cls: 'text-amber-600 dark:text-amber-400',
            dot: 'bg-amber-500',
        },
        CAMPAIGN_PAUSED: {
            label: 'Campaign Paused',
            cls: 'text-gray-500 dark:text-gray-400',
            dot: 'bg-gray-400',
        },
        ADSET_PAUSED: {
            label: 'Ad Set Paused',
            cls: 'text-gray-500 dark:text-gray-400',
            dot: 'bg-gray-400',
        },
    };
    const c = map[status] ?? {
        label: status,
        cls: 'text-gray-500 dark:text-gray-400',
        dot: 'bg-gray-300',
    };
    return (
        <span
            className={clsx(
                'flex items-center gap-1.5 font-mono text-[11px]',
                c.cls,
            )}
        >
            <span className={clsx('h-1.5 w-1.5 rounded-full', c.dot)} />
            {c.label}
        </span>
    );
}

export function formatMoney(value: number | string | null | undefined) {
    const n = typeof value === 'string' ? parseFloat(value) : (value ?? 0);
    if (!n) return '—';
    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(n);
}

export function formatInt(value: number | null | undefined) {
    if (value == null || value === 0) return '—';
    return new Intl.NumberFormat().format(value);
}

export function formatBudget(
    daily: number | string | null,
    lifetime: number | string | null,
) {
    if (daily && parseFloat(String(daily)) > 0) {
        return { value: formatMoney(daily), label: 'Daily' };
    }
    if (lifetime && parseFloat(String(lifetime)) > 0) {
        return { value: formatMoney(lifetime), label: 'Lifetime' };
    }
    return { value: '—', label: '' };
}

export const PAGES = {
    campaigns: 'campaigns',
    adSets: 'ad-sets',
    ads: 'ads',
} as const;

export function adsManagerUrl(
    workspaceSlug: string,
    page: 'campaigns' | 'ad-sets' | 'ads',
) {
    return `/workspaces/${workspaceSlug}/integrations/meta/ads-manager/${page}`;
}

/* ───────────────────── formatters ──────────────────────── */

export function safeDiv(
    a: number | string | null | undefined,
    b: number | string | null | undefined,
): number {
    const na = Number(a);
    const nb = Number(b);
    if (!nb || !isFinite(na) || !isFinite(nb)) return 0;
    return na / nb;
}

export function formatPct(value: number) {
    if (!isFinite(value) || value === 0) return '—';
    return (value * 100).toFixed(2) + '%';
}

export function formatDecimal(value: number, places = 2) {
    if (!isFinite(value) || value === 0) return '—';
    return value.toFixed(places);
}

/* ───────────────────── insights metrics ────────────────── */
/**
 * All metric columns surfaced on Campaigns / Ad Sets / Ads. The shape mirrors
 * what AdsManagerController::metricSelects() returns from the SUM aggregate
 * over meta_ads_insights for the active date range.
 */
export interface InsightsMetrics {
    spend: number | string;
    impressions: number;
    reach: number;
    clicks: number;
    link_clicks: number;
    outbound_clicks: number;
    estimated_ad_recallers: number;
    page_photo_views: number;

    video_3sec_views: number;
    video_thruplay_views: number;
    video_p25_views: number;
    video_p50_views: number;
    video_p75_views: number;
    video_p100_views: number;

    page_engagement: number;
    page_engagement_value: number | string;
    page_likes: number;
    page_likes_value: number | string;
    photo_views_value: number | string;
    post_engagement: number;
    post_engagement_value: number | string;
    post_comments: number;
    post_comments_value: number | string;
    post_shares: number;
    post_shares_value: number | string;
    post_saves: number;
    post_saves_value: number | string;
    post_reactions: number;
    post_reactions_value: number | string;

    messaging_first_replies: number;
    messaging_first_replies_value: number | string;
    messaging_conversations_started: number;
    messaging_conversations_started_value: number | string;

    initiate_checkout: number;
    initiate_checkout_value: number | string;
    conversions: number;
    purchases: number;
    purchase_value: number | string;
    on_facebook_leads: number;
    on_facebook_leads_value: number | string;
    leads: number;
    lead_value: number | string;
}

type FieldKey = keyof InsightsMetrics;
type Formatter = (n: number) => string;

interface MetricSpec {
    id: string;
    label: string;
    category: string;
    /** Direct DB column. Sortable on the server. */
    field?: FieldKey;
    /** Frontend-derived value. Not sortable. */
    compute?: (row: InsightsMetrics) => number;
    /** Defaults to formatInt. */
    formatter?: Formatter;
    /** Hidden until user enables in the Columns menu. */
    hiddenByDefault?: boolean;
}

const moneyFmt: Formatter = (n) => formatMoney(n);
const intFmt: Formatter = (n) => formatInt(n);
const pctFmt: Formatter = (n) => formatPct(n);
const decimalFmt: Formatter = (n) => formatDecimal(n);

const METRIC_SPECS: MetricSpec[] = [
    /* ─── Delivery & Traffic ─── */
    {
        id: 'spend',
        label: 'Amount Spent',
        category: 'Delivery & Traffic',
        field: 'spend',
        formatter: moneyFmt,
    },
    {
        id: 'impressions',
        label: 'Impressions',
        category: 'Delivery & Traffic',
        field: 'impressions',
    },
    {
        id: 'reach',
        label: 'Reach',
        category: 'Delivery & Traffic',
        field: 'reach',
    },
    {
        id: 'frequency',
        label: 'Frequency',
        category: 'Delivery & Traffic',
        compute: (r) => safeDiv(r.impressions, r.reach),
        formatter: decimalFmt,
        hiddenByDefault: true,
    },
    {
        id: 'clicks',
        label: 'Clicks',
        category: 'Delivery & Traffic',
        field: 'clicks',
        hiddenByDefault: true,
    },
    {
        id: 'ctr',
        label: 'CTR',
        category: 'Delivery & Traffic',
        compute: (r) => safeDiv(r.clicks, r.impressions),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cpc',
        label: 'CPC',
        category: 'Delivery & Traffic',
        compute: (r) => safeDiv(r.spend, r.clicks),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cpm',
        label: 'CPM',
        category: 'Delivery & Traffic',
        compute: (r) => safeDiv(r.spend, r.impressions) * 1000,
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'link_clicks',
        label: 'Link Clicks',
        category: 'Delivery & Traffic',
        field: 'link_clicks',
        hiddenByDefault: true,
    },
    {
        id: 'link_ctr',
        label: 'Link Clicks CTR',
        category: 'Delivery & Traffic',
        compute: (r) => safeDiv(r.link_clicks, r.impressions),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_link_click',
        label: 'Cost Per Link Click',
        category: 'Delivery & Traffic',
        compute: (r) => safeDiv(r.spend, r.link_clicks),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'outbound_clicks',
        label: 'Outbound Clicks',
        category: 'Delivery & Traffic',
        field: 'outbound_clicks',
        hiddenByDefault: true,
    },
    {
        id: 'outbound_ctr',
        label: 'Outbound CTR',
        category: 'Delivery & Traffic',
        compute: (r) => safeDiv(r.outbound_clicks, r.impressions),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_outbound_click',
        label: 'Cost Per Outbound Click',
        category: 'Delivery & Traffic',
        compute: (r) => safeDiv(r.spend, r.outbound_clicks),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'estimated_ad_recallers',
        label: 'Estimated Ad Recallers',
        category: 'Delivery & Traffic',
        field: 'estimated_ad_recallers',
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_estimated_ad_recaller',
        label: 'Cost Per Estimated Recaller',
        category: 'Delivery & Traffic',
        compute: (r) => safeDiv(r.spend, r.estimated_ad_recallers),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'page_photo_views',
        label: 'Page Photo Views',
        category: 'Delivery & Traffic',
        field: 'page_photo_views',
        hiddenByDefault: true,
    },

    /* ─── Video ─── */
    {
        id: 'video_3sec_views',
        label: '3-Second Video Views',
        category: 'Video',
        field: 'video_3sec_views',
        hiddenByDefault: true,
    },
    {
        id: 'hook_rate',
        label: 'Hook Rate',
        category: 'Video',
        compute: (r) => safeDiv(r.video_3sec_views, r.impressions),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_3s_view',
        label: 'Cost Per 3s View',
        category: 'Video',
        compute: (r) => safeDiv(r.spend, r.video_3sec_views),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'video_thruplay_views',
        label: 'Thruplays',
        category: 'Video',
        field: 'video_thruplay_views',
        hiddenByDefault: true,
    },
    {
        id: 'hold_rate',
        label: 'Hold Rate',
        category: 'Video',
        compute: (r) => safeDiv(r.video_thruplay_views, r.video_3sec_views),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_thruplay',
        label: 'Cost Per Thruplay',
        category: 'Video',
        compute: (r) => safeDiv(r.spend, r.video_thruplay_views),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'video_p25_views',
        label: 'Video 25% Watched',
        category: 'Video',
        field: 'video_p25_views',
        hiddenByDefault: true,
    },
    {
        id: 'body_rate_25',
        label: 'Body Rate 25%',
        category: 'Video',
        compute: (r) => safeDiv(r.video_p25_views, r.video_3sec_views),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_p25',
        label: 'Cost Per 25% Watched',
        category: 'Video',
        compute: (r) => safeDiv(r.spend, r.video_p25_views),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'video_p50_views',
        label: 'Video 50% Watched',
        category: 'Video',
        field: 'video_p50_views',
        hiddenByDefault: true,
    },
    {
        id: 'body_rate_50',
        label: 'Body Rate 50%',
        category: 'Video',
        compute: (r) => safeDiv(r.video_p50_views, r.video_3sec_views),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_p50',
        label: 'Cost Per 50% Watched',
        category: 'Video',
        compute: (r) => safeDiv(r.spend, r.video_p50_views),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'video_p75_views',
        label: 'Video 75% Watched',
        category: 'Video',
        field: 'video_p75_views',
        hiddenByDefault: true,
    },
    {
        id: 'body_rate_75',
        label: 'Body Rate 75%',
        category: 'Video',
        compute: (r) => safeDiv(r.video_p75_views, r.video_3sec_views),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_p75',
        label: 'Cost Per 75% Watched',
        category: 'Video',
        compute: (r) => safeDiv(r.spend, r.video_p75_views),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'video_p100_views',
        label: 'Video 100% Watched',
        category: 'Video',
        field: 'video_p100_views',
        hiddenByDefault: true,
    },
    {
        id: 'body_rate_100',
        label: 'Body Rate 100%',
        category: 'Video',
        compute: (r) => safeDiv(r.video_p100_views, r.video_3sec_views),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_p100',
        label: 'Cost Per 100% Watched',
        category: 'Video',
        compute: (r) => safeDiv(r.spend, r.video_p100_views),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },

    /* ─── Engagement ─── */
    {
        id: 'page_engagement',
        label: 'Page Engagement',
        category: 'Engagement',
        field: 'page_engagement',
        hiddenByDefault: true,
    },
    {
        id: 'page_engagement_value',
        label: 'Page Engagement Value',
        category: 'Engagement',
        field: 'page_engagement_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_page_engagement',
        label: 'Cost Per Page Engagement',
        category: 'Engagement',
        compute: (r) => safeDiv(r.spend, r.page_engagement),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'page_engagement_rate',
        label: 'Page Engagement Conv. Rate',
        category: 'Engagement',
        compute: (r) => safeDiv(r.page_engagement, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'page_likes',
        label: 'Page Likes',
        category: 'Engagement',
        field: 'page_likes',
        hiddenByDefault: true,
    },
    {
        id: 'page_likes_value',
        label: 'Page Likes Value',
        category: 'Engagement',
        field: 'page_likes_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_page_like',
        label: 'Cost Per Page Like',
        category: 'Engagement',
        compute: (r) => safeDiv(r.spend, r.page_likes),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'page_likes_rate',
        label: 'Page Likes Conv. Rate',
        category: 'Engagement',
        compute: (r) => safeDiv(r.page_likes, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'photo_views_value',
        label: 'Photo Views Value',
        category: 'Engagement',
        field: 'photo_views_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_photo_view',
        label: 'Cost Per Photo View',
        category: 'Engagement',
        compute: (r) => safeDiv(r.spend, r.page_photo_views),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'photo_views_rate',
        label: 'Photo Views Conv. Rate',
        category: 'Engagement',
        compute: (r) => safeDiv(r.page_photo_views, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'post_engagement',
        label: 'Post Engagement',
        category: 'Engagement',
        field: 'post_engagement',
        hiddenByDefault: true,
    },
    {
        id: 'post_engagement_value',
        label: 'Post Engagement Value',
        category: 'Engagement',
        field: 'post_engagement_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_post_engagement',
        label: 'Cost Per Post Engagement',
        category: 'Engagement',
        compute: (r) => safeDiv(r.spend, r.post_engagement),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'post_comments',
        label: 'Post Comments',
        category: 'Engagement',
        field: 'post_comments',
        hiddenByDefault: true,
    },
    {
        id: 'post_comments_value',
        label: 'Post Comments Value',
        category: 'Engagement',
        field: 'post_comments_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_post_comment',
        label: 'Cost Per Post Comment',
        category: 'Engagement',
        compute: (r) => safeDiv(r.spend, r.post_comments),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'post_comments_rate',
        label: 'Post Comments Conv. Rate',
        category: 'Engagement',
        compute: (r) => safeDiv(r.post_comments, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'post_shares',
        label: 'Post Shares',
        category: 'Engagement',
        field: 'post_shares',
        hiddenByDefault: true,
    },
    {
        id: 'post_shares_value',
        label: 'Post Shares Value',
        category: 'Engagement',
        field: 'post_shares_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_post_share',
        label: 'Cost Per Post Share',
        category: 'Engagement',
        compute: (r) => safeDiv(r.spend, r.post_shares),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'post_shares_rate',
        label: 'Post Shares Conv. Rate',
        category: 'Engagement',
        compute: (r) => safeDiv(r.post_shares, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'post_saves',
        label: 'Post Saves',
        category: 'Engagement',
        field: 'post_saves',
        hiddenByDefault: true,
    },
    {
        id: 'post_saves_value',
        label: 'Post Saves Value',
        category: 'Engagement',
        field: 'post_saves_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_post_save',
        label: 'Cost Per Post Save',
        category: 'Engagement',
        compute: (r) => safeDiv(r.spend, r.post_saves),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'post_saves_rate',
        label: 'Post Saves Conv. Rate',
        category: 'Engagement',
        compute: (r) => safeDiv(r.post_saves, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'post_reactions',
        label: 'Post Reactions',
        category: 'Engagement',
        field: 'post_reactions',
        hiddenByDefault: true,
    },
    {
        id: 'post_reactions_value',
        label: 'Post Reactions Value',
        category: 'Engagement',
        field: 'post_reactions_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'post_reactions_rate',
        label: 'Post Reactions Conv. Rate',
        category: 'Engagement',
        compute: (r) => safeDiv(r.post_reactions, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },

    /* ─── Messaging ─── */
    {
        id: 'messaging_first_replies',
        label: 'New Messaging Conversations',
        category: 'Messaging',
        field: 'messaging_first_replies',
        hiddenByDefault: true,
    },
    {
        id: 'messaging_first_replies_value',
        label: 'Messaging First Reply Value',
        category: 'Messaging',
        field: 'messaging_first_replies_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_messaging_first_reply',
        label: 'Cost Per Messaging First Reply',
        category: 'Messaging',
        compute: (r) => safeDiv(r.spend, r.messaging_first_replies),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'messaging_first_reply_rate',
        label: 'Messaging First Reply Conv. Rate',
        category: 'Messaging',
        compute: (r) => safeDiv(r.messaging_first_replies, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'messaging_conversations_started',
        label: 'Messaging Conversations Started',
        category: 'Messaging',
        field: 'messaging_conversations_started',
        hiddenByDefault: true,
    },
    {
        id: 'messaging_conversations_started_value',
        label: 'Messaging Conversation Value',
        category: 'Messaging',
        field: 'messaging_conversations_started_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_messaging_conversation_started',
        label: 'Cost Per Messaging Conversation',
        category: 'Messaging',
        compute: (r) => safeDiv(r.spend, r.messaging_conversations_started),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'messaging_conversation_started_rate',
        label: 'Messaging Conversation Conv. Rate',
        category: 'Messaging',
        compute: (r) => safeDiv(r.messaging_conversations_started, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },

    /* ─── Commerce & Leads ─── */
    {
        id: 'initiate_checkout',
        label: 'Initiated Checkouts',
        category: 'Commerce & Leads',
        field: 'initiate_checkout',
        hiddenByDefault: true,
    },
    {
        id: 'initiate_checkout_value',
        label: 'Initiated Checkouts Value',
        category: 'Commerce & Leads',
        field: 'initiate_checkout_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_initiated_checkout',
        label: 'Cost Per Initiated Checkout',
        category: 'Commerce & Leads',
        compute: (r) => safeDiv(r.spend, r.initiate_checkout),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'initiated_checkout_rate',
        label: 'Initiated Checkouts Conv. Rate',
        category: 'Commerce & Leads',
        compute: (r) => safeDiv(r.initiate_checkout, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'conversions',
        label: 'Conversions',
        category: 'Commerce & Leads',
        field: 'conversions',
        hiddenByDefault: true,
    },
    {
        id: 'conversion_rate',
        label: 'Conversion Rate',
        category: 'Commerce & Leads',
        compute: (r) => safeDiv(r.conversions, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'purchases',
        label: 'Purchases',
        category: 'Commerce & Leads',
        field: 'purchases',
    },
    {
        id: 'purchase_value',
        label: 'Purchase Value',
        category: 'Commerce & Leads',
        field: 'purchase_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'avg_purchase_value',
        label: 'Avg Purchase Value',
        category: 'Commerce & Leads',
        compute: (r) => safeDiv(r.purchase_value, r.purchases),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_purchase',
        label: 'Cost Per Purchase',
        category: 'Commerce & Leads',
        compute: (r) => safeDiv(r.spend, r.purchases),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'purchase_conv_rate',
        label: 'Purchase Conv. Rate',
        category: 'Commerce & Leads',
        compute: (r) => safeDiv(r.purchases, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'roas',
        label: 'ROAS',
        category: 'Commerce & Leads',
        compute: (r) => safeDiv(r.purchase_value, r.spend),
        formatter: decimalFmt,
        hiddenByDefault: true,
    },
    {
        id: 'gross_profit_per_transaction',
        label: 'Gross Profit Per Transaction',
        category: 'Commerce & Leads',
        compute: (r) =>
            safeDiv(Number(r.purchase_value) - Number(r.spend), r.purchases),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'on_facebook_leads',
        label: 'On-Facebook Leads',
        category: 'Commerce & Leads',
        field: 'on_facebook_leads',
        hiddenByDefault: true,
    },
    {
        id: 'on_facebook_leads_value',
        label: 'On-Facebook Leads Value',
        category: 'Commerce & Leads',
        field: 'on_facebook_leads_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_on_facebook_lead',
        label: 'Cost Per On-Facebook Lead',
        category: 'Commerce & Leads',
        compute: (r) => safeDiv(r.spend, r.on_facebook_leads),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'on_facebook_lead_rate',
        label: 'On-Facebook Lead Conv. Rate',
        category: 'Commerce & Leads',
        compute: (r) => safeDiv(r.on_facebook_leads, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
    {
        id: 'leads',
        label: 'Leads',
        category: 'Commerce & Leads',
        field: 'leads',
        hiddenByDefault: true,
    },
    {
        id: 'lead_value',
        label: 'Lead Value',
        category: 'Commerce & Leads',
        field: 'lead_value',
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'cost_per_lead',
        label: 'Cost Per Lead',
        category: 'Commerce & Leads',
        compute: (r) => safeDiv(r.spend, r.leads),
        formatter: moneyFmt,
        hiddenByDefault: true,
    },
    {
        id: 'lead_conv_rate',
        label: 'Lead Conv. Rate',
        category: 'Commerce & Leads',
        compute: (r) => safeDiv(r.leads, r.clicks),
        formatter: pctFmt,
        hiddenByDefault: true,
    },
];

export const INSIGHTS_OPTIONS: ColumnOption[] = METRIC_SPECS.map((m) => ({
    id: m.id,
    label: m.label,
    category: m.category,
    hiddenByDefault: m.hiddenByDefault,
}));

function numCell(value: number, formatter: Formatter = intFmt) {
    return (
        <span className="text-right font-mono text-[12px] text-gray-700 dark:text-gray-300">
            {formatter(value)}
        </span>
    );
}

/** Build a ColumnDef[] for every metric. Row type must extend InsightsMetrics. */
export function buildInsightsColumns<
    T extends InsightsMetrics,
>(): ColumnDef<T>[] {
    return METRIC_SPECS.map((m) => {
        const fmt = m.formatter ?? intFmt;

        if (m.field) {
            return {
                id: m.id,
                accessorKey: m.field as string,
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title={m.label} />
                ),
                cell: ({ row }) => numCell(Number(row.original[m.field!]), fmt),
            } as ColumnDef<T>;
        }

        return {
            id: m.id,
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title={m.label}
                    enabled={false}
                />
            ),
            cell: ({ row }) => numCell(m.compute!(row.original), fmt),
        } as ColumnDef<T>;
    });
}

type AdsManagerTab = 'campaigns' | 'ad-sets' | 'ads';

interface AdsManagerTabsProps {
    workspaceSlug: string;
    active: AdsManagerTab;
    dateRange: { since: string; until: string };
    /** Query forwarded to deeper tabs so drill-down context is preserved. */
    carry?: {
        campaign?: string | number | null;
        ad_set?: string | number | null;
    };
}

const TAB_DEFS: { id: AdsManagerTab; label: string }[] = [
    { id: 'campaigns', label: 'Campaigns' },
    { id: 'ad-sets', label: 'Ad Sets' },
    { id: 'ads', label: 'Ads' },
];

/**
 * Persists column-visibility state in localStorage under a stable key so the
 * user's column choices stick across reloads and tab switches.
 */
export function useColumnVisibility(
    storageKey: string,
    defaults: VisibilityState = {},
) {
    const [visibility, setVisibility] = useState<VisibilityState>(() => {
        if (typeof window === 'undefined') return defaults;
        try {
            const raw = window.localStorage.getItem(storageKey);
            return raw ? { ...defaults, ...JSON.parse(raw) } : defaults;
        } catch {
            return defaults;
        }
    });

    useEffect(() => {
        if (typeof window === 'undefined') return;
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(visibility));
        } catch {
            /* ignore quota / private-mode errors */
        }
    }, [storageKey, visibility]);

    return [visibility, setVisibility] as const;
}

export interface ColumnOption {
    id: string;
    label: string;
    /** Section heading in the dropdown (e.g. "Delivery & Traffic"). */
    category?: string;
    /** Always shown, can't be hidden (e.g. the primary name column). */
    required?: boolean;
    /** When true and no saved state exists, the column starts hidden. */
    hiddenByDefault?: boolean;
}

interface ColumnVisibilityMenuProps {
    options: ColumnOption[];
    value: VisibilityState;
    onChange: (next: VisibilityState) => void;
}

export function ColumnVisibilityMenu({
    options,
    value,
    onChange,
}: ColumnVisibilityMenuProps) {
    const isVisible = (opt: ColumnOption) =>
        value[opt.id] !== undefined
            ? value[opt.id] !== false
            : !opt.hiddenByDefault;

    const visibleCount = options.filter(isVisible).length;
    const totalCount = options.length;

    const reset = () => {
        const cleared: VisibilityState = {};
        for (const o of options) cleared[o.id] = !o.hiddenByDefault;
        onChange(cleared);
    };

    // Preserve declaration order across categories while grouping for render.
    const grouped: { category: string; opts: ColumnOption[] }[] = [];
    for (const opt of options) {
        const cat = opt.category ?? 'General';
        const existing = grouped.find((g) => g.category === cat);
        if (existing) existing.opts.push(opt);
        else grouped.push({ category: cat, opts: [opt] });
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-9 gap-1.5 font-mono! text-[12px]!"
                >
                    <Columns3 className="h-3.5 w-3.5" />
                    Columns
                    <span className="text-gray-400 dark:text-gray-500">
                        {visibleCount}/{totalCount}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="max-h-[70vh] w-72 overflow-y-auto font-mono text-[12px]"
            >
                <DropdownMenuLabel className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                    Toggle Columns
                </DropdownMenuLabel>
                {grouped.map((g, idx) => (
                    <div key={g.category}>
                        <DropdownMenuSeparator />
                        <DropdownMenuLabel className="px-2 pt-2 pb-1 font-mono text-[9px] tracking-wider text-emerald-600 uppercase dark:text-emerald-400">
                            {g.category}
                        </DropdownMenuLabel>
                        {g.opts.map((opt) => {
                            const checked = isVisible(opt);
                            return (
                                <DropdownMenuCheckboxItem
                                    key={opt.id}
                                    checked={checked}
                                    disabled={opt.required}
                                    onCheckedChange={(next) =>
                                        onChange({
                                            ...value,
                                            [opt.id]: !!next,
                                        })
                                    }
                                    onSelect={(e) => e.preventDefault()}
                                >
                                    {opt.label}
                                    {opt.required && (
                                        <span className="ml-auto text-[10px] text-gray-300 dark:text-gray-600">
                                            locked
                                        </span>
                                    )}
                                </DropdownMenuCheckboxItem>
                            );
                        })}
                        {idx === grouped.length - 1 && (
                            <DropdownMenuSeparator />
                        )}
                    </div>
                ))}
                <button
                    onClick={reset}
                    className="w-full px-2 py-1.5 text-left text-[11px] text-gray-500 hover:bg-stone-100 dark:text-gray-400 dark:hover:bg-zinc-800"
                >
                    Reset to default
                </button>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function AdsManagerTabs({
    workspaceSlug,
    active,
    dateRange,
    carry,
}: AdsManagerTabsProps) {
    const go = (next: AdsManagerTab) => {
        const params: Record<string, string | number> = {
            since: dateRange.since,
            until: dateRange.until,
        };

        // Forward the parent filters only when they still apply to the target page.
        if (next === 'ad-sets' && carry?.campaign) {
            params.campaign = carry.campaign as string | number;
        }
        if (next === 'ads') {
            if (carry?.campaign)
                params.campaign = carry.campaign as string | number;
            if (carry?.ad_set) params.ad_set = carry.ad_set as string | number;
        }

        router.get(adsManagerUrl(workspaceSlug, next), params);
    };

    return (
        <div className="mb-4 border-b border-black/6 dark:border-white/6">
            <div className="flex items-center gap-1">
                {TAB_DEFS.map((t) => {
                    const isActive = active === t.id;
                    return (
                        <button
                            key={t.id}
                            onClick={() => !isActive && go(t.id)}
                            className={clsx(
                                'relative px-4 py-3 transition-colors',
                                isActive
                                    ? 'text-gray-800 dark:text-gray-100'
                                    : 'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300',
                            )}
                        >
                            <span className="text-[13px] font-medium tracking-tight">
                                {t.label}
                            </span>
                            <span
                                aria-hidden
                                className={clsx(
                                    'absolute inset-x-0 bottom-0 h-[2px] rounded-full transition-all',
                                    isActive
                                        ? 'bg-emerald-500 dark:bg-emerald-400'
                                        : 'bg-transparent',
                                )}
                            />
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
