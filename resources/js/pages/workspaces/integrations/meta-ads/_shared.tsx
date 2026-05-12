import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { router } from '@inertiajs/react';
import { VisibilityState } from '@tanstack/react-table';
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

interface ColumnOption {
    id: string;
    label: string;
    /** Always shown, can't be hidden (e.g. the primary name column). */
    required?: boolean;
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
    const visibleCount = options.filter((o) => value[o.id] !== false).length;
    const totalCount = options.length;

    const reset = () => {
        const cleared: VisibilityState = {};
        for (const o of options) cleared[o.id] = true;
        onChange(cleared);
    };

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
                className="w-56 font-mono text-[12px]"
            >
                <DropdownMenuLabel className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                    Toggle Columns
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {options.map((opt) => {
                    const checked = value[opt.id] !== false;
                    return (
                        <DropdownMenuCheckboxItem
                            key={opt.id}
                            checked={checked}
                            disabled={opt.required}
                            onCheckedChange={(next) =>
                                onChange({ ...value, [opt.id]: !!next })
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
                <DropdownMenuSeparator />
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
