import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from 'clsx';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import { omit } from 'lodash';
import {
    Check,
    ChevronDown,
    Download,
    Image as ImageIcon,
    LayoutGrid,
    Loader2,
    Play,
    Search,
} from 'lucide-react';
import moment from 'moment';
import { ReactNode, useEffect, useMemo, useState } from 'react';
import {
    ColumnVisibilityMenu,
    INSIGHTS_OPTIONS,
    InsightFilterBuilder,
    InsightsMetrics,
    MetricFilter,
    StatusLabel,
    buildInsightsColumns,
    deserializeMetricFilters,
    serializeMetricFilters,
    useColumnPresets,
} from './_shared';

import DateOption = flatpickr.Options.DateOption;

type GroupBy = 'ad_name' | 'ad' | 'campaign' | 'ad_set' | 'account';

const GROUP_BY_OPTIONS: { value: GroupBy; label: string }[] = [
    { value: 'ad_name', label: 'Ad Name' },
    { value: 'ad', label: 'Ad Id' },
    { value: 'campaign', label: 'Campaign' },
    { value: 'ad_set', label: 'Ad Set' },
    { value: 'account', label: 'Ad Account' },
];

/** Whether the grouped dimension carries a per-row status + (for ads) a thumbnail. */
const HAS_STATUS: Record<GroupBy, boolean> = {
    ad_name: false,
    ad: true,
    campaign: true,
    ad_set: true,
    account: false,
};

interface Row extends InsightsMetrics {
    // Meta ad/campaign/ad-set ids are bigints beyond JS's safe-integer range,
    // so the server sends them as strings — never coerce back to a number.
    id: string;
    name: string | null;
    status?: string | null;
    effective_status?: string | null;
    thumbnail_url?: string | null;
    image_url?: string | null;
    video_id?: number | string | null;
    ads_count?: number;
}

interface AccountOption {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    rows: PaginatedData<Row>;
    accounts: AccountOption[];
    selectedAccounts: string[];
    dateRange: { since: string; until: string };
    query?: {
        sort?: string | null;
        groupBy?: GroupBy;
        perPage?: number | string;
        page?: number | string;
        since?: string;
        until?: string;
        filter?: { search?: string };
        metricFilters?: unknown[];
    };
}

function adsManagerUrl(slug: string) {
    return `/workspaces/${slug}/integrations/meta/ads-manager`;
}

function adDetailUrl(slug: string, adId: number | string) {
    return `/workspaces/${slug}/integrations/meta/ads-manager/ads/${adId}/detail`;
}

/* ───────────────────── Creative detail drawer ───────────────── */

interface AdDetail {
    dimensions: {
        ad_status: string | null;
        optimization_goal: string | null;
        ad_name: string | null;
        ad_id: string;
        adset_name: string | null;
        campaign_name: string | null;
        account_name: string | null;
        ad_type: string;
        call_to_action: string | null;
    };
    preview: { src: string | null };
}

function DimRow({
    label,
    value,
    mono,
}: {
    label: string;
    value: ReactNode;
    mono?: boolean;
}) {
    return (
        <div className="grid grid-cols-[120px_1fr] gap-2 py-1.5">
            <dt className="text-[11px] text-gray-400 dark:text-gray-500">
                {label}
            </dt>
            <dd
                className={clsx(
                    'text-[12px] text-gray-700 dark:text-gray-200',
                    mono && 'font-mono text-[11px]',
                )}
            >
                {value ?? <span className="text-gray-300">—</span>}
            </dd>
        </div>
    );
}

function CreativeDetailDrawer({
    slug,
    ad,
    onClose,
}: {
    slug: string;
    ad: Row | null;
    onClose: () => void;
}) {
    const [detail, setDetail] = useState<AdDetail | null>(null);
    const [loading, setLoading] = useState(false);
    const [src, setSrc] = useState<string | null>(null);
    const [iframeLoaded, setIframeLoaded] = useState(false);

    useEffect(() => {
        if (!ad) return;
        let active = true;
        setLoading(true);
        setDetail(null);
        setSrc(null);
        setIframeLoaded(false);
        fetch(adDetailUrl(slug, ad.id), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((d: AdDetail) => {
                if (!active) return;
                setDetail(d);
                setSrc(d.preview?.src ?? null);
                setLoading(false);
            })
            .catch(() => active && setLoading(false));
        return () => {
            active = false;
        };
    }, [ad?.id, slug]); // eslint-disable-line react-hooks/exhaustive-deps

    const dim = detail?.dimensions;
    const isImage = ad?.video_id == null;
    // Spinner while the detail request is in flight OR the iframe is still painting.
    const showSpinner = loading || (!!src && !iframeLoaded);

    return (
        <Sheet open={!!ad} onOpenChange={(o) => !o && onClose()}>
            <SheetContent
                side="right"
                className="w-full gap-0 overflow-y-auto p-0 sm:max-w-md"
            >
                <SheetHeader className="border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <SheetTitle className="truncate pr-6 text-[14px] tracking-tight text-gray-800 dark:text-gray-100">
                        {ad?.name ?? 'Creative'}
                    </SheetTitle>
                </SheetHeader>

                <div className="p-4">
                    {/* Phone-style frame around the in-feed ad preview. */}
                    <div className="flex justify-center rounded-lg bg-stone-100 py-6 dark:bg-zinc-950">
                        <div className="w-[336px] overflow-hidden rounded-[2rem] border-[8px] border-zinc-800 bg-black shadow-xl dark:border-zinc-700">
                            <div className="flex h-6 items-center justify-center bg-zinc-900">
                                <div className="h-1 w-10 rounded-full bg-zinc-600" />
                            </div>
                            <div className="relative h-[560px] bg-white dark:bg-zinc-900">
                                {src && (
                                    <iframe
                                        key={src}
                                        title="Creative preview"
                                        src={src}
                                        onLoad={() => setIframeLoaded(true)}
                                        className={clsx(
                                            'h-full w-full border-0',
                                            !iframeLoaded && 'invisible',
                                        )}
                                        allowFullScreen
                                    />
                                )}
                                {showSpinner ? (
                                    <div className="absolute inset-0 flex items-center justify-center">
                                        <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                                    </div>
                                ) : (
                                    !src && (
                                        <div className="absolute inset-0 flex items-center justify-center px-6">
                                            <p className="text-center font-mono text-[12px] text-gray-400 dark:text-gray-500">
                                                Preview unavailable for this ad.
                                            </p>
                                        </div>
                                    )
                                )}
                            </div>
                        </div>
                    </div>

                    {isImage && ad?.image_url && (
                        <a
                            href={ad.image_url}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-3 flex h-8 w-fit items-center gap-1.5 rounded-lg border border-black/6 px-3 font-mono text-[11px] text-gray-600 hover:border-black/12 hover:bg-stone-50 dark:border-white/6 dark:text-gray-300 dark:hover:bg-zinc-800"
                        >
                            <Download className="h-3.5 w-3.5" />
                            Download
                        </a>
                    )}

                    <p className="mt-5 mb-1 text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        Dimensions
                    </p>
                    <dl className="divide-y divide-black/4 dark:divide-white/4">
                        <DimRow label="Ad status" value={dim?.ad_status} />
                        <DimRow
                            label="Optimization goal"
                            value={dim?.optimization_goal}
                            mono
                        />
                        <DimRow
                            label="Ad"
                            value={
                                dim && (
                                    <span>
                                        {dim.ad_name}
                                        <span className="block font-mono text-[10px] text-gray-400">
                                            ID {dim.ad_id}
                                        </span>
                                    </span>
                                )
                            }
                        />
                        <DimRow label="Adset" value={dim?.adset_name} />
                        <DimRow label="Campaign" value={dim?.campaign_name} />
                        <DimRow label="Account" value={dim?.account_name} />
                        <DimRow label="Ad type" value={dim?.ad_type} />
                        <DimRow
                            label="Call to action"
                            value={dim?.call_to_action}
                            mono
                        />
                    </dl>
                </div>
            </SheetContent>
        </Sheet>
    );
}

/* ───────────────────── Account multi-picker ─────────────────── */

interface AccountMultiPickerProps {
    accounts: AccountOption[];
    selected: string[];
    onChange: (next: string[]) => void;
}

function AccountMultiPicker({
    accounts,
    selected,
    onChange,
}: AccountMultiPickerProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    useEffect(() => {
        if (!open) setSearch('');
    }, [open]);

    const allSelected = selected.length === accounts.length;
    const q = search.toLowerCase();
    const filtered = accounts.filter(
        (a) => !q || a.name.toLowerCase().includes(q),
    );

    const toggle = (id: string) => {
        const set = new Set(selected);
        if (set.has(id)) set.delete(id);
        else set.add(id);
        onChange(accounts.filter((a) => set.has(a.id)).map((a) => a.id));
    };

    const toggleAll = () =>
        onChange(allSelected ? [] : accounts.map((a) => a.id));

    const label = allSelected
        ? 'All accounts'
        : selected.length === 0
          ? 'No accounts'
          : `${selected.length} of ${accounts.length} accounts`;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-9 gap-1.5 font-mono! text-[12px]!"
                >
                    <LayoutGrid className="h-3.5 w-3.5" />
                    {label}
                    <ChevronDown className="h-3 w-3 text-gray-400" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-72 p-0 font-mono text-[11px]"
            >
                <div className="border-b border-black/6 p-2 dark:border-white/6">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 h-3 w-3 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder="Search accounts..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            autoFocus
                            className="h-7 w-full rounded-md border border-black/6 bg-stone-50 pr-2 pl-7 font-mono text-[11px] outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                        />
                    </div>
                </div>

                <label className="flex cursor-pointer items-center gap-2 border-b border-black/6 px-3 py-2 hover:bg-stone-50 dark:border-white/6 dark:hover:bg-zinc-800/50">
                    <input
                        type="checkbox"
                        checked={allSelected}
                        onChange={toggleAll}
                        className="h-3.5 w-3.5 rounded border-gray-300 accent-emerald-500"
                    />
                    <span className="font-medium text-gray-700 dark:text-gray-300">
                        Select all
                    </span>
                </label>

                <div className="max-h-64 overflow-y-auto py-1">
                    {filtered.length === 0 && (
                        <p className="py-4 text-center text-gray-400 dark:text-gray-500">
                            No accounts match.
                        </p>
                    )}
                    {filtered.map((a) => {
                        const checked = selected.includes(a.id);
                        return (
                            <button
                                key={a.id}
                                type="button"
                                onClick={() => toggle(a.id)}
                                className="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-gray-700 transition-colors hover:bg-stone-100 dark:text-gray-300 dark:hover:bg-zinc-700"
                            >
                                <span className="truncate">{a.name}</span>
                                {checked && (
                                    <Check className="h-3 w-3 shrink-0 text-emerald-500" />
                                )}
                            </button>
                        );
                    })}
                </div>
            </PopoverContent>
        </Popover>
    );
}

/* ───────────────────── Group-by selector ────────────────────── */

function GroupBySelect({
    value,
    onChange,
}: {
    value: GroupBy;
    onChange: (next: GroupBy) => void;
}) {
    const [open, setOpen] = useState(false);
    const active =
        GROUP_BY_OPTIONS.find((o) => o.value === value) ?? GROUP_BY_OPTIONS[0];

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-9 gap-1.5 font-mono! text-[12px]!"
                >
                    <span className="text-gray-400 dark:text-gray-500">
                        Group by
                    </span>
                    <span className="font-medium">{active.label}</span>
                    <ChevronDown className="h-3 w-3 text-gray-400" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-44 p-1 font-mono text-[11px]"
            >
                {GROUP_BY_OPTIONS.map((o) => (
                    <button
                        key={o.value}
                        type="button"
                        onClick={() => {
                            onChange(o.value);
                            setOpen(false);
                        }}
                        className="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-gray-700 transition-colors hover:bg-stone-100 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        {o.label}
                        {o.value === value && (
                            <Check className="h-3 w-3 text-emerald-500" />
                        )}
                    </button>
                ))}
            </PopoverContent>
        </Popover>
    );
}

export default function MetaAdsManager({
    workspace,
    rows,
    accounts,
    selectedAccounts,
    dateRange,
    query,
}: Props) {
    const indexUrl = adsManagerUrl(workspace.slug);
    const groupBy: GroupBy = query?.groupBy ?? 'ad_name';
    const groupLabel =
        GROUP_BY_OPTIONS.find((o) => o.value === groupBy)?.label ?? 'Ad Name';

    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [metricFilters, setMetricFilters] = useState<MetricFilter[]>(() =>
        deserializeMetricFilters(query?.metricFilters),
    );
    const [previewAd, setPreviewAd] = useState<Row | null>(null);

    // Send the account list only when it's a strict subset; an empty value means
    // "all accounts" on the server, matching the default.
    const accountsParam = (ids: string[]) =>
        ids.length === accounts.length ? undefined : ids;

    const navigate = (overrides: Record<string, unknown> = {}) =>
        router.get(
            indexUrl,
            {
                group_by: groupBy,
                accounts: accountsParam(selectedAccounts),
                since: dateRange.since,
                until: dateRange.until,
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                metric_filters: serializeMetricFilters(metricFilters),
                page: 1,
                per_page: query?.perPage ?? rows.per_page,
                ...overrides,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['rows', 'query'],
            },
        );

    useEffect(() => {
        const t = setTimeout(
            () => navigate({ page: searchValue ? 1 : (query?.page ?? 1) }),
            400,
        );
        return () => clearTimeout(t);
    }, [searchValue]); // eslint-disable-line react-hooks/exhaustive-deps

    const handleMetricFiltersChange = (next: MetricFilter[]) => {
        setMetricFilters(next);
        router.get(
            indexUrl,
            {
                group_by: groupBy,
                accounts: accountsParam(selectedAccounts),
                since: dateRange.since,
                until: dateRange.until,
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                metric_filters: serializeMetricFilters(next),
                page: 1,
                per_page: query?.perPage ?? rows.per_page,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['rows', 'query'],
            },
        );
    };

    // A full visit (component remounts) so per-dimension column presets re-init.
    const handleGroupByChange = (next: GroupBy) => {
        if (next === groupBy) return;
        router.get(
            indexUrl,
            {
                group_by: next,
                accounts: accountsParam(selectedAccounts),
                since: dateRange.since,
                until: dateRange.until,
            },
            { preserveScroll: true },
        );
    };

    const handleAccountsChange = (ids: string[]) => {
        router.get(
            indexUrl,
            {
                group_by: groupBy,
                accounts: accountsParam(ids),
                since: dateRange.since,
                until: dateRange.until,
                'filter[search]': searchValue || undefined,
                metric_filters: serializeMetricFilters(metricFilters),
            },
            { preserveScroll: true },
        );
    };

    const setDateRange = (since: string, until: string) => {
        router.get(
            indexUrl,
            {
                group_by: groupBy,
                accounts: accountsParam(selectedAccounts),
                since,
                until,
            },
            { preserveScroll: true },
        );
    };

    const showAdsCount = groupBy !== 'ad';

    const COLUMN_OPTIONS = [
        { id: 'name', label: groupLabel, category: 'General', required: true },
        ...INSIGHTS_OPTIONS,
    ];
    const {
        visibility: columnVisibility,
        setVisibility: setColumnVisibility,
        columnOrder,
        setColumnOrder,
        presets,
        savePreset,
        deletePreset,
        loadPreset,
        resetToDefault,
    } = useColumnPresets(
        `meta-ads-cols:${groupBy}`,
        Object.fromEntries(
            COLUMN_OPTIONS.map((o) => [o.id, !o.hiddenByDefault]),
        ),
        COLUMN_OPTIONS.map((o) => o.id),
    );

    const showThumbnail = groupBy === 'ad';
    const showStatus = HAS_STATUS[groupBy];

    const columns: ColumnDef<Row>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={groupLabel} />
            ),
            cell: ({ row }) => (
                <div className="flex items-start gap-3">
                    {showThumbnail && (
                        <button
                            type="button"
                            onClick={() => setPreviewAd(row.original)}
                            title="View creative"
                            className="group relative h-10 w-10 shrink-0 overflow-hidden rounded ring-emerald-500/40 transition-shadow hover:ring-2"
                        >
                            {row.original.thumbnail_url ||
                            row.original.image_url ? (
                                <img
                                    src={
                                        row.original.thumbnail_url ??
                                        row.original.image_url ??
                                        ''
                                    }
                                    alt=""
                                    className="h-10 w-10 object-cover"
                                />
                            ) : (
                                <div className="flex h-10 w-10 items-center justify-center bg-stone-100 text-gray-400 dark:bg-zinc-800 dark:text-gray-500">
                                    <ImageIcon className="h-4 w-4" />
                                </div>
                            )}
                            {row.original.video_id != null && (
                                <span className="absolute inset-0 flex items-center justify-center bg-black/35">
                                    <Play className="h-3.5 w-3.5 fill-white text-white" />
                                </span>
                            )}
                        </button>
                    )}
                    <div className="min-w-0 flex-1">
                        <span className="block truncate text-[12px] font-medium text-gray-700 dark:text-gray-300">
                            {row.original.name ?? '—'}
                        </span>
                        {showStatus && (
                            <StatusLabel
                                status={
                                    row.original.effective_status ??
                                    row.original.status ??
                                    null
                                }
                            />
                        )}
                        {showAdsCount && row.original.ads_count != null && (
                            <span className="block font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                {row.original.ads_count}{' '}
                                {row.original.ads_count === 1 ? 'ad' : 'ads'}
                            </span>
                        )}
                    </div>
                </div>
            ),
        },
        ...buildInsightsColumns<Row>(),
    ];

    return (
        <AppLayout>
            <Head title="Meta Ads · Ads Manager" />

            <div className="w-full p-4 md:p-6">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="mb-1 font-mono text-[10px] font-medium tracking-[0.12em] text-emerald-600 uppercase dark:text-emerald-400">
                            Meta Ads
                        </p>
                        <h1 className="text-[26px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                            Ads Manager
                        </h1>
                        <p className="mt-1 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                            {formatDate(new Date(dateRange.since), 'MMM d')} –{' '}
                            {formatDate(
                                new Date(dateRange.until),
                                'MMM d, yyyy',
                            )}
                        </p>
                    </div>

                    <DatePicker
                        id="meta-ads-date-range"
                        mode="range"
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setDateRange(
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                );
                            }
                        }}
                        defaultDate={
                            [
                                dateRange.since,
                                dateRange.until,
                            ] as never as DateOption
                        }
                    />
                </div>

                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <AccountMultiPicker
                            accounts={accounts}
                            selected={selectedAccounts}
                            onChange={handleAccountsChange}
                        />
                        <GroupBySelect
                            value={groupBy}
                            onChange={handleGroupByChange}
                        />
                        <div className="relative w-full max-w-xs">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                            <input
                                type="text"
                                placeholder={`Search ${groupLabel.toLowerCase()}...`}
                                value={searchValue}
                                onChange={(e) => setSearchValue(e.target.value)}
                                className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400 dark:focus:bg-zinc-900"
                            />
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <InsightFilterBuilder
                            filters={metricFilters}
                            onChange={handleMetricFiltersChange}
                        />
                        <ColumnVisibilityMenu
                            options={COLUMN_OPTIONS}
                            value={columnVisibility}
                            onChange={setColumnVisibility}
                            columnOrder={columnOrder}
                            onColumnOrderChange={setColumnOrder}
                            presets={presets}
                            onSavePreset={savePreset}
                            onDeletePreset={deletePreset}
                            onLoadPreset={loadPreset}
                            onReset={resetToDefault}
                        />
                        <span className="hidden font-mono text-[10px] text-gray-300 sm:inline dark:text-gray-600">
                            {rows.total.toLocaleString()} rows
                        </span>
                    </div>
                </div>

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns as ColumnDef<unknown>[]}
                        data={(rows.data ?? []) as unknown[]}
                        initialSorting={initialSorting}
                        meta={{ ...omit(rows, ['data']) }}
                        columnVisibility={columnVisibility}
                        onColumnVisibilityChange={setColumnVisibility}
                        columnOrder={columnOrder}
                        onColumnOrderChange={setColumnOrder}
                        onFetch={(params) =>
                            navigate({
                                sort: params?.sort,
                                page: params?.page ?? 1,
                                per_page:
                                    params?.per_page ??
                                    query?.perPage ??
                                    rows.per_page,
                            })
                        }
                    />
                </div>
            </div>

            <CreativeDetailDrawer
                slug={workspace.slug}
                ad={previewAd}
                onClose={() => setPreviewAd(null)}
            />
        </AppLayout>
    );
}
