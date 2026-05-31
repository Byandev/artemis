import { Button } from '@/components/ui/button';
import { SortableHeader } from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { ColumnDef, VisibilityState } from '@tanstack/react-table';
import clsx from 'clsx';
import { Check, ChevronDown, ChevronUp, Columns3, Filter, GripVertical, Plus, Search, X } from 'lucide-react';
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

export interface ColumnPreset {
    id: string;
    name: string;
    visibility: VisibilityState;
    columnOrder: string[];
}

function lsGet<T>(key: string, fallback: T): T {
    try {
        const raw = window.localStorage.getItem(key);
        if (raw) {
            const parsed = JSON.parse(raw);
            if (parsed !== null && parsed !== undefined) return parsed as T;
        }
    } catch { /* ignore */ }
    return fallback;
}

function lsSet(key: string, value: unknown) {
    try { window.localStorage.setItem(key, JSON.stringify(value)); } catch { /* ignore */ }
}

export function useColumnPresets(
    storageKey: string,
    defaults: VisibilityState,
    defaultOrder: string[],
) {
    const [visibility, setVisibility] = useState<VisibilityState>(() => {
        const stored = lsGet<VisibilityState>(storageKey, {});
        return Object.keys(stored).length > 0 ? { ...defaults, ...stored } : defaults;
    });

    const [columnOrder, setColumnOrder] = useState<string[]>(() => {
        const stored = lsGet<string[]>(`${storageKey}:order`, []);
        // Merge: keep stored order, append any new IDs, drop removed IDs
        const valid = stored.filter((id) => defaultOrder.includes(id));
        const missing = defaultOrder.filter((id) => !valid.includes(id));
        return [...valid, ...missing];
    });

    const [presets, setPresets] = useState<ColumnPreset[]>(() =>
        lsGet<ColumnPreset[]>(`${storageKey}:presets`, []),
    );

    useEffect(() => { lsSet(storageKey, visibility); }, [storageKey, visibility]);
    useEffect(() => { lsSet(`${storageKey}:order`, columnOrder); }, [storageKey, columnOrder]);
    useEffect(() => { lsSet(`${storageKey}:presets`, presets); }, [storageKey, presets]);

    const savePreset = (name: string) => {
        const preset: ColumnPreset = {
            id: crypto.randomUUID(),
            name: name.trim(),
            visibility: { ...visibility },
            columnOrder: [...columnOrder],
        };
        setPresets((prev) => [...prev, preset]);
    };

    const deletePreset = (id: string) =>
        setPresets((prev) => prev.filter((p) => p.id !== id));

    const loadPreset = (preset: ColumnPreset) => {
        setVisibility(preset.visibility);
        // Merge stored preset order with any new columns added since preset was saved
        const valid = preset.columnOrder.filter((id) => defaultOrder.includes(id));
        const missing = defaultOrder.filter((id) => !valid.includes(id));
        setColumnOrder([...valid, ...missing]);
    };

    const resetToDefault = () => {
        setVisibility(defaults);
        setColumnOrder(defaultOrder);
    };

    return { visibility, setVisibility, columnOrder, setColumnOrder, presets, savePreset, deletePreset, loadPreset, resetToDefault };
}

export interface ColumnOption {
    id: string;
    label: string;
    category?: string;
    required?: boolean;
    hiddenByDefault?: boolean;
}

interface ColumnVisibilityMenuProps {
    options: ColumnOption[];
    value: VisibilityState;
    onChange: (next: VisibilityState) => void;
    columnOrder: string[];
    onColumnOrderChange: (order: string[]) => void;
    presets: ColumnPreset[];
    onSavePreset: (name: string) => void;
    onDeletePreset: (id: string) => void;
    onLoadPreset: (preset: ColumnPreset) => void;
    onReset: () => void;
}

export function ColumnVisibilityMenu({
    options,
    value,
    onChange,
    columnOrder,
    onColumnOrderChange,
    presets,
    onSavePreset,
    onDeletePreset,
    onLoadPreset,
    onReset,
}: ColumnVisibilityMenuProps) {
    const [open, setOpen] = useState(false);

    // Draft state — uncommitted until Apply
    const [draftVisibility, setDraftVisibility] = useState<VisibilityState>(value);
    const [draftOrder, setDraftOrder] = useState<string[]>(columnOrder);

    // Left-panel state
    const [search, setSearch] = useState('');
    const [activeCategory, setActiveCategory] = useState('All');
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});
    const allCollapsed = Object.values(collapsed).every(Boolean);

    // Right-panel drag state
    const [dragId, setDragId] = useState<string | null>(null);
    const [dragOverId, setDragOverId] = useState<string | null>(null);

    // Preset save state
    const [presetName, setPresetName] = useState('');
    const [savingPreset, setSavingPreset] = useState(false);

    // Sync draft from committed state on open
    useEffect(() => {
        if (open) {
            setDraftVisibility(value);
            setDraftOrder(columnOrder);
            setSearch('');
            setActiveCategory('All');
            setCollapsed({});
            setSavingPreset(false);
            setPresetName('');
        }
    }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

    const optById = Object.fromEntries(options.map((o) => [o.id, o]));

    const isChecked = (id: string) => {
        const opt = optById[id];
        if (!opt) return false;
        return draftVisibility[id] !== undefined
            ? draftVisibility[id] !== false
            : !opt.hiddenByDefault;
    };

    const selectedCount = options.filter((o) => isChecked(o.id)).length;

    // Left panel: categories
    const categories = ['All', ...Array.from(new Set(options.map((o) => o.category ?? 'General')))];

    // Left panel: filtered + grouped options
    const q = search.toLowerCase();
    const visibleOpts = options.filter((o) => {
        if (q && !o.label.toLowerCase().includes(q)) return false;
        if (activeCategory !== 'All' && (o.category ?? 'General') !== activeCategory) return false;
        return true;
    });

    const grouped: { category: string; opts: ColumnOption[] }[] = [];
    for (const opt of visibleOpts) {
        const cat = opt.category ?? 'General';
        const g = grouped.find((x) => x.category === cat);
        if (g) g.opts.push(opt);
        else grouped.push({ category: cat, opts: [opt] });
    }

    const toggleCollapse = (cat: string) =>
        setCollapsed((prev) => ({ ...prev, [cat]: !prev[cat] }));
    const toggleCollapseAll = () => {
        if (allCollapsed) setCollapsed({});
        else setCollapsed(Object.fromEntries(grouped.map((g) => [g.category, true])));
    };

    // Right panel: selected columns in order
    const selectedInOrder = draftOrder
        .map((id) => optById[id])
        .filter((o): o is ColumnOption => !!o && isChecked(o.id));

    // Right-panel drag handlers
    const onDragStart = (id: string) => setDragId(id);
    const onDragOver = (e: React.DragEvent, id: string) => { e.preventDefault(); if (id !== dragId) setDragOverId(id); };
    const onDrop = (e: React.DragEvent, targetId: string) => {
        e.preventDefault();
        if (!dragId || dragId === targetId) { setDragId(null); setDragOverId(null); return; }
        const next = [...draftOrder];
        const from = next.indexOf(dragId);
        const to = next.indexOf(targetId);
        if (from !== -1 && to !== -1) { next.splice(from, 1); next.splice(to, 0, dragId); setDraftOrder(next); }
        setDragId(null); setDragOverId(null);
    };
    const onDragEnd = () => { setDragId(null); setDragOverId(null); };

    const handleApply = () => {
        onChange(draftVisibility);
        onColumnOrderChange(draftOrder);
        setOpen(false);
    };

    const handleSavePreset = () => {
        if (!presetName.trim()) return;
        // Save using current draft state
        onSavePreset(presetName.trim());
        // Also commit so preset matches what's applied
        onChange(draftVisibility);
        onColumnOrderChange(draftOrder);
        setPresetName('');
        setSavingPreset(false);
    };

    const handleReset = () => {
        onReset();
        setOpen(false);
    };

    return (
        <>
            <Button
                variant="outline"
                size="sm"
                onClick={() => setOpen(true)}
                className="h-9 gap-1.5 font-mono! text-[12px]!"
            >
                <Columns3 className="h-3.5 w-3.5" />
                Columns
                <span className="text-gray-400 dark:text-gray-500">{selectedCount}/{options.length}</span>
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="min-w-4xl w-full gap-0 overflow-hidden p-0 font-mono text-[12px]">
                    {/* Header */}
                    <div className="flex items-center justify-between border-b border-black/6 px-5 py-3.5 dark:border-white/6">
                        <h2 className="text-[14px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                            Customize columns
                        </h2>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="rounded-lg p-1 text-gray-400 hover:bg-stone-100 hover:text-gray-600 dark:hover:bg-zinc-800 dark:hover:text-gray-300"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>

                    <div className="flex" style={{ height: '520px' }}>
                        {/* ── Left panel ── */}
                        <div className="flex w-[55%] flex-col border-r border-black/6 dark:border-white/6">
                            {/* Search + collapse */}
                            <div className="flex items-center gap-2 border-b border-black/6 p-3 dark:border-white/6">
                                <div className="relative flex-1">
                                    <Search className="pointer-events-none absolute top-1/2 left-2.5 h-3 w-3 -translate-y-1/2 text-gray-400" />
                                    <input
                                        type="text"
                                        placeholder="Search for metrics or column settings"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="h-8 w-full rounded-lg border border-black/6 bg-stone-50 pr-3 pl-8 text-[11px] outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                                    />
                                </div>
                                <button
                                    type="button"
                                    onClick={toggleCollapseAll}
                                    className="flex h-8 shrink-0 items-center gap-1 rounded-lg border border-black/6 px-2.5 text-[11px] text-gray-500 hover:border-black/12 hover:text-gray-700 dark:border-white/6 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    {allCollapsed ? <ChevronDown className="h-3 w-3" /> : <ChevronUp className="h-3 w-3" />}
                                    {allCollapsed ? 'Expand all' : 'Collapse all'}
                                </button>
                            </div>

                            {/* Category tabs */}
                            {!search && (
                                <div className="flex gap-0 overflow-x-auto border-b border-black/6 dark:border-white/6">
                                    {categories.map((cat) => (
                                        <button
                                            key={cat}
                                            type="button"
                                            onClick={() => setActiveCategory(cat)}
                                            className={clsx(
                                                'shrink-0 border-b-2 px-3 py-2.5 text-[11px] transition-colors',
                                                activeCategory === cat
                                                    ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
                                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                                            )}
                                        >
                                            {cat}
                                        </button>
                                    ))}
                                </div>
                            )}

                            {/* Column groups */}
                            <div className="flex-1 overflow-y-auto">
                                {grouped.length === 0 && (
                                    <p className="py-8 text-center text-[11px] text-gray-400 dark:text-gray-500">No columns match.</p>
                                )}
                                {grouped.map((g) => (
                                    <div key={g.category}>
                                        <button
                                            type="button"
                                            onClick={() => toggleCollapse(g.category)}
                                            className="flex w-full items-center justify-between bg-stone-50 px-4 py-2 text-[10px] font-medium tracking-wider text-gray-500 uppercase hover:bg-stone-100 dark:bg-zinc-800/60 dark:text-gray-400 dark:hover:bg-zinc-800"
                                        >
                                            {g.category}
                                            {collapsed[g.category]
                                                ? <ChevronDown className="h-3 w-3" />
                                                : <ChevronUp className="h-3 w-3" />}
                                        </button>
                                        {!collapsed[g.category] && (
                                            <div className="grid grid-cols-2 gap-0 px-3 py-2">
                                                {g.opts.map((opt) => (
                                                    <label
                                                        key={opt.id}
                                                        className={clsx(
                                                            'flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 transition-colors hover:bg-stone-50 dark:hover:bg-zinc-800/50',
                                                            opt.required && 'cursor-default opacity-60',
                                                        )}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            disabled={opt.required}
                                                            checked={isChecked(opt.id)}
                                                            onChange={(e) =>
                                                                setDraftVisibility((prev) => ({ ...prev, [opt.id]: e.target.checked }))
                                                            }
                                                            className="h-3.5 w-3.5 rounded border-gray-300 accent-emerald-500"
                                                        />
                                                        <span className="text-[11px] text-gray-700 dark:text-gray-300">{opt.label}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {/* Preset save */}
                            <div className="border-t border-black/6 p-3 dark:border-white/6">
                                {savingPreset ? (
                                    <div className="flex items-center gap-2">
                                        <input
                                            autoFocus
                                            type="text"
                                            placeholder="Preset name..."
                                            value={presetName}
                                            onChange={(e) => setPresetName(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') handleSavePreset(); if (e.key === 'Escape') setSavingPreset(false); }}
                                            className="h-7 flex-1 rounded-md border border-black/6 bg-stone-50 px-2 text-[11px] outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                                        />
                                        <button type="button" onClick={handleSavePreset} className="h-7 rounded-md bg-emerald-500 px-2.5 text-[11px] font-medium text-white hover:bg-emerald-600">Save</button>
                                        <button type="button" onClick={() => setSavingPreset(false)} className="h-7 rounded-md border border-black/6 px-2 text-[11px] text-gray-500 hover:text-gray-700 dark:border-white/6 dark:text-gray-400">Cancel</button>
                                    </div>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => setSavingPreset(true)}
                                        className="flex items-center gap-1.5 rounded-md border border-black/6 px-3 py-1.5 text-[11px] text-gray-600 transition-colors hover:border-black/12 hover:bg-stone-50 dark:border-white/6 dark:text-gray-400 dark:hover:bg-zinc-800"
                                    >
                                        <Plus className="h-3 w-3" />
                                        Save as column preset
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* ── Right panel ── */}
                        <div className="flex w-[45%] flex-col">
                            <div className="border-b border-black/6 px-4 py-3 dark:border-white/6">
                                <p className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                                    {selectedCount} {selectedCount === 1 ? 'column' : 'columns'} selected
                                </p>
                                <p className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                                    Drag and drop to arrange columns as they'll appear in the table.
                                </p>
                            </div>

                            {/* Saved presets */}
                            {presets.length > 0 && (
                                <div className="border-b border-black/6 px-4 py-2 dark:border-white/6">
                                    <p className="mb-1.5 text-[9px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">Saved presets</p>
                                    <div className="flex flex-wrap gap-1">
                                        {presets.map((p) => (
                                            <div key={p.id} className="flex items-center gap-0.5 rounded-md border border-black/6 bg-stone-50 py-0.5 pl-2 pr-1 dark:border-white/6 dark:bg-zinc-800">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        onLoadPreset(p);
                                                        setDraftVisibility(p.visibility);
                                                        const valid = p.columnOrder.filter((id) => !!optById[id]);
                                                        const missing = options.filter((o) => !valid.includes(o.id)).map((o) => o.id);
                                                        setDraftOrder([...valid, ...missing]);
                                                    }}
                                                    className="text-[11px] text-gray-700 hover:text-emerald-600 dark:text-gray-300 dark:hover:text-emerald-400"
                                                >
                                                    {p.name}
                                                </button>
                                                <button type="button" onClick={() => onDeletePreset(p.id)} className="ml-0.5 rounded p-0.5 text-gray-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10">
                                                    <X className="h-2.5 w-2.5" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Draggable selected column list */}
                            <div className="flex-1 overflow-y-auto px-2 py-2">
                                {selectedInOrder.length === 0 && (
                                    <p className="py-8 text-center text-[11px] text-gray-400 dark:text-gray-500">No columns selected.</p>
                                )}
                                {selectedInOrder.map((opt) => (
                                    <div
                                        key={opt.id}
                                        draggable={!opt.required}
                                        onDragStart={() => onDragStart(opt.id)}
                                        onDragOver={(e) => onDragOver(e, opt.id)}
                                        onDrop={(e) => onDrop(e, opt.id)}
                                        onDragEnd={onDragEnd}
                                        className={clsx(
                                            'flex items-center gap-2 rounded-lg px-2 py-2 transition-colors',
                                            !opt.required && 'cursor-grab',
                                            dragId === opt.id && 'opacity-40',
                                            dragOverId === opt.id && 'border-t-2 border-emerald-500',
                                            'hover:bg-stone-50 dark:hover:bg-zinc-800/50',
                                        )}
                                    >
                                        <GripVertical className="h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" />
                                        <span className="flex-1 truncate text-[11px] text-gray-700 dark:text-gray-300">{opt.label}</span>
                                        {!opt.required && (
                                            <button
                                                type="button"
                                                onClick={() => setDraftVisibility((prev) => ({ ...prev, [opt.id]: false }))}
                                                className="shrink-0 rounded p-0.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10"
                                            >
                                                <X className="h-3.5 w-3.5" />
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {/* Footer actions */}
                            <div className="flex items-center justify-between border-t border-black/6 px-4 py-3 dark:border-white/6">
                                <button
                                    type="button"
                                    onClick={handleReset}
                                    className="text-[11px] text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    Reset to default
                                </button>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setOpen(false)}
                                        className="h-8 rounded-lg border border-black/6 px-4 text-[11px] text-gray-600 transition-colors hover:border-black/12 dark:border-white/6 dark:text-gray-400"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleApply}
                                        className="h-8 rounded-lg bg-emerald-500 px-4 text-[11px] font-medium text-white transition-colors hover:bg-emerald-600"
                                    >
                                        Apply
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </>
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

/* ───────────────────── Insight filters ──────────────────── */

export type MetricFilterOp = 'gt' | 'gte' | 'lt' | 'lte' | 'eq' | 'range';

export interface MetricFilter {
    id: string; // client-only key for React
    field: string;
    op: MetricFilterOp;
    value: string;
    value2: string; // only used when op === 'range'
}

const OP_LABELS: Record<MetricFilterOp, string> = {
    gt: '> Greater than',
    gte: '≥ Greater than or equal',
    lt: '< Less than',
    lte: '≤ Less than or equal',
    eq: '= Equal',
    range: '↔ Between',
};

const FILTERABLE_METRICS = METRIC_SPECS;

export function serializeMetricFilters(filters: MetricFilter[]): string | undefined {
    const clean = filters
        .filter((f) => f.field && f.op && f.value !== '')
        .filter((f) => f.op !== 'range' || f.value2 !== '')
        .map(({ field, op, value, value2 }) =>
            op === 'range' ? { field, op, value, value2 } : { field, op, value },
        );
    return clean.length ? JSON.stringify(clean) : undefined;
}

export function deserializeMetricFilters(raw: unknown): MetricFilter[] {
    if (!Array.isArray(raw) || raw.length === 0) return [];
    return raw.map((f: Record<string, string>) => ({
        id: crypto.randomUUID(),
        field: f.field ?? '',
        op: (f.op as MetricFilterOp) ?? 'gt',
        value: String(f.value ?? ''),
        value2: String(f.value2 ?? ''),
    }));
}

function newFilter(): MetricFilter {
    return { id: crypto.randomUUID(), field: 'spend', op: 'gt', value: '', value2: '' };
}

interface MetricComboboxProps {
    value: string;
    onValueChange: (v: string) => void;
    grouped: { category: string; specs: typeof FILTERABLE_METRICS }[];
}

function MetricCombobox({ value, onValueChange, grouped }: MetricComboboxProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    useEffect(() => {
        if (!open) setSearch('');
    }, [open]);

    const q = search.toLowerCase();
    const filtered = grouped
        .map((g) => ({
            ...g,
            specs: g.specs.filter(
                (s) => !q || s.label.toLowerCase().includes(q),
            ),
        }))
        .filter((g) => g.specs.length > 0);

    const selectedLabel =
        FILTERABLE_METRICS.find((s) => s.id === value)?.label ?? value;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="flex h-8 w-44 items-center justify-between gap-1 rounded-lg border border-black/6 bg-stone-50 px-2 font-mono text-[11px] text-gray-700 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:border-white/10"
                >
                    <span className="truncate">{selectedLabel}</span>
                    <ChevronDown className="h-3 w-3 shrink-0 text-gray-400" />
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-64 p-0 font-mono text-[11px]"
            >
                {/* Search */}
                <div className="border-b border-black/6 p-2 dark:border-white/6">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 h-3 w-3 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder="Search metric..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            autoFocus
                            className="h-7 w-full rounded-md border border-black/6 bg-stone-50 pr-2 pl-7 font-mono text-[11px] outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                        />
                    </div>
                </div>

                {/* List */}
                <div className="max-h-64 overflow-y-auto">
                    {filtered.length === 0 && (
                        <p className="py-4 text-center text-[11px] text-gray-400 dark:text-gray-500">
                            No metrics match.
                        </p>
                    )}
                    {filtered.map((g) => (
                        <div key={g.category}>
                            <div className="px-2 pt-2 pb-1 text-[9px] tracking-wider text-emerald-600 uppercase dark:text-emerald-400">
                                {g.category}
                            </div>
                            {g.specs.map((s) => (
                                <button
                                    key={s.id}
                                    type="button"
                                    onClick={() => {
                                        onValueChange(s.id);
                                        setOpen(false);
                                    }}
                                    className="flex w-full items-center justify-between px-2 py-1.5 text-left text-[11px] text-gray-700 transition-colors hover:bg-stone-100 dark:text-gray-300 dark:hover:bg-zinc-700"
                                >
                                    {s.label}
                                    {s.id === value && (
                                        <Check className="h-3 w-3 text-emerald-500" />
                                    )}
                                </button>
                            ))}
                        </div>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}

interface InsightFilterBuilderProps {
    filters: MetricFilter[];
    onChange: (filters: MetricFilter[]) => void;
}

export function InsightFilterBuilder({ filters, onChange }: InsightFilterBuilderProps) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<MetricFilter[]>(filters);

    // Sync draft from committed filters whenever the dropdown opens.
    useEffect(() => {
        if (open) setDraft(filters);
    }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

    const update = (id: string, patch: Partial<MetricFilter>) =>
        setDraft((prev) => prev.map((f) => (f.id === id ? { ...f, ...patch } : f)));
    const remove = (id: string) =>
        setDraft((prev) => prev.filter((f) => f.id !== id));
    const add = () => setDraft((prev) => [...prev, newFilter()]);

    const apply = () => {
        onChange(draft);
        setOpen(false);
    };
    const cancel = () => {
        setDraft(filters);
        setOpen(false);
    };

    const grouped: { category: string; specs: typeof FILTERABLE_METRICS }[] = [];
    for (const spec of FILTERABLE_METRICS) {
        const cat = spec.category;
        const g = grouped.find((x) => x.category === cat);
        if (g) g.specs.push(spec);
        else grouped.push({ category: cat, specs: [spec] });
    }

    const activeCount = filters.filter(
        (f) => f.field && f.op && f.value !== '' && (f.op !== 'range' || f.value2 !== ''),
    ).length;

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className={clsx(
                        'h-9 gap-1.5 font-mono! text-[12px]!',
                        activeCount > 0 &&
                            'border-emerald-500/40 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400',
                    )}
                >
                    <Filter className="h-3.5 w-3.5" />
                    Filters
                    {activeCount > 0 && (
                        <span className="flex h-4 w-4 items-center justify-center rounded-full bg-emerald-500 text-[10px] font-bold text-white">
                            {activeCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-[520px] p-3 font-mono text-[12px]"
                onCloseAutoFocus={(e) => e.preventDefault()}
            >
                <DropdownMenuLabel className="mb-2 font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                    Metric Filters
                </DropdownMenuLabel>

                {draft.length === 0 && (
                    <p className="py-2 text-center text-[11px] text-gray-400 dark:text-gray-500">
                        No filters. Click + Add Filter to start.
                    </p>
                )}

                <div className="space-y-2">
                    {draft.map((f) => (
                        <div key={f.id} className="flex items-center gap-1.5">
                            {/* Field */}
                            <MetricCombobox
                                value={f.field}
                                onValueChange={(v) => update(f.id, { field: v })}
                                grouped={grouped}
                            />

                            {/* Operator */}
                            <Select
                                value={f.op}
                                onValueChange={(v) => update(f.id, { op: v as MetricFilterOp })}
                            >
                                <SelectTrigger className="h-8 w-44 rounded-lg border border-black/6 bg-stone-50 px-2 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent className="font-mono text-[11px]">
                                    {(Object.entries(OP_LABELS) as [MetricFilterOp, string][]).map(
                                        ([op, label]) => (
                                            <SelectItem key={op} value={op}>
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>

                            {/* Value(s) */}
                            {f.op === 'range' ? (
                                <div className="flex items-center gap-1">
                                    <input
                                        type="number"
                                        placeholder="Min"
                                        value={f.value}
                                        onChange={(e) => update(f.id, { value: e.target.value })}
                                        className="h-8 w-20 rounded-lg border border-black/6 bg-stone-50 px-2 font-mono text-[11px] outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/6 dark:bg-zinc-800"
                                    />
                                    <span className="text-gray-400">–</span>
                                    <input
                                        type="number"
                                        placeholder="Max"
                                        value={f.value2}
                                        onChange={(e) => update(f.id, { value2: e.target.value })}
                                        className="h-8 w-20 rounded-lg border border-black/6 bg-stone-50 px-2 font-mono text-[11px] outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/6 dark:bg-zinc-800"
                                    />
                                </div>
                            ) : (
                                <input
                                    type="number"
                                    placeholder="Value"
                                    value={f.value}
                                    onChange={(e) => update(f.id, { value: e.target.value })}
                                    className="h-8 w-24 rounded-lg border border-black/6 bg-stone-50 px-2 font-mono text-[11px] outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/6 dark:bg-zinc-800"
                                />
                            )}

                            {/* Remove */}
                            <button
                                type="button"
                                onClick={() => remove(f.id)}
                                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    ))}
                </div>

                <DropdownMenuSeparator className="my-2" />

                <button
                    type="button"
                    onClick={add}
                    className="flex w-full items-center gap-1.5 rounded-lg px-2 py-1.5 text-[11px] text-emerald-600 transition-colors hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10"
                >
                    <Plus className="h-3.5 w-3.5" />
                    Add Filter
                </button>

                <DropdownMenuSeparator className="my-2" />

                <div className="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={cancel}
                        className="h-8 rounded-lg border border-black/6 px-3 text-[11px] text-gray-500 transition-colors hover:border-black/12 hover:text-gray-700 dark:border-white/6 dark:text-gray-400 dark:hover:border-white/12 dark:hover:text-gray-200"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={apply}
                        className="h-8 rounded-lg bg-emerald-500 px-3 text-[11px] font-medium text-white transition-colors hover:bg-emerald-600"
                    >
                        Apply
                    </button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
