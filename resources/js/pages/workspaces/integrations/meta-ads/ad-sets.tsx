import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import { omit } from 'lodash';
import { ChevronLeft, ChevronRight, Search } from 'lucide-react';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import {
    AdsManagerTabs,
    ColumnVisibilityMenu,
    InsightFilterBuilder,
    INSIGHTS_OPTIONS,
    InsightsMetrics,
    MetricFilter,
    StatusLabel,
    StatusToggle,
    adsManagerUrl,
    buildInsightsColumns,
    deserializeMetricFilters,
    formatBudget,
    serializeMetricFilters,
    useColumnPresets,
} from './_shared';

import DateOption = flatpickr.Options.DateOption;

interface AdSetRow extends InsightsMetrics {
    id: number;
    name: string;
    status: string | null;
    effective_status: string | null;
    optimization_goal: string | null;
    daily_budget: number | string | null;
    lifetime_budget: number | string | null;
    meta_ads_campaign_id: number;
    campaign_name: string | null;
}

interface Props {
    workspace: Workspace;
    rows: PaginatedData<AdSetRow>;
    context: { campaign: { id: number; name: string } | null };
    dateRange: { since: string; until: string };
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        campaign?: string;
        since?: string;
        until?: string;
        filter?: { search?: string };
        metricFilters?: unknown[];
    };
}

export default function MetaAdsAdSets({
    workspace,
    rows,
    context,
    dateRange,
    query,
}: Props) {
    const indexUrl = adsManagerUrl(workspace.slug, 'ad-sets');
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [metricFilters, setMetricFilters] = useState<MetricFilter[]>(
        () => deserializeMetricFilters(query?.metricFilters),
    );

    const navigate = (overrides: Record<string, unknown> = {}) =>
        router.get(
            indexUrl,
            {
                campaign: query?.campaign,
                since: dateRange.since,
                until: dateRange.until,
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                metric_filters: serializeMetricFilters(metricFilters),
                page: 1,
                per_page: query?.perPage ?? rows.per_page,
                ...overrides,
            },
            { preserveState: true, replace: true, preserveScroll: true, only: ['rows', 'query'] },
        );

    useEffect(() => {
        const t = setTimeout(() => navigate({ page: searchValue ? 1 : (query?.page ?? 1) }), 400);
        return () => clearTimeout(t);
    }, [searchValue]); // eslint-disable-line react-hooks/exhaustive-deps

    const handleMetricFiltersChange = (next: MetricFilter[]) => {
        setMetricFilters(next);
        router.get(
            indexUrl,
            {
                campaign: query?.campaign,
                since: dateRange.since,
                until: dateRange.until,
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                metric_filters: serializeMetricFilters(next),
                page: 1,
                per_page: query?.perPage ?? rows.per_page,
            },
            { preserveState: true, replace: true, preserveScroll: true, only: ['rows', 'query'] },
        );
    };

    const setDateRange = (since: string, until: string) => {
        router.get(
            indexUrl,
            { campaign: query?.campaign, since, until },
            { preserveScroll: true },
        );
    };

    const drillToAds = (adSetId: number) => {
        router.get(adsManagerUrl(workspace.slug, 'ads'), {
            campaign: query?.campaign,
            ad_set: adSetId,
            since: dateRange.since,
            until: dateRange.until,
        });
    };

    const clearCampaign = () => {
        router.get(indexUrl, {
            since: dateRange.since,
            until: dateRange.until,
        });
    };

    const COLUMN_OPTIONS = [
        { id: 'toggle', label: 'Status Toggle', category: 'General' },
        { id: 'name', label: 'Ad Set', category: 'General', required: true },
        {
            id: 'optimization_goal',
            label: 'Optimization',
            category: 'General',
        },
        { id: 'daily_budget', label: 'Budget', category: 'General' },
        ...INSIGHTS_OPTIONS,
    ];
    const { visibility: columnVisibility, setVisibility: setColumnVisibility, columnOrder, setColumnOrder, presets, savePreset, deletePreset, loadPreset, resetToDefault } = useColumnPresets(
        'meta-ads-cols:ad-sets',
        Object.fromEntries(COLUMN_OPTIONS.map((o) => [o.id, !o.hiddenByDefault])),
        COLUMN_OPTIONS.map((o) => o.id),
    );

    const columns: ColumnDef<AdSetRow>[] = [
        {
            id: 'toggle',
            cell: ({ row }) => (
                <StatusToggle
                    status={row.original.status}
                    effectiveStatus={row.original.effective_status}
                />
            ),
        },
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Ad Set" />
            ),
            cell: ({ row }) => (
                <button
                    onClick={() => drillToAds(row.original.id)}
                    className="flex w-full flex-col items-start gap-0.5 text-left"
                >
                    <span className="text-[12px] font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                        {row.original.name}
                    </span>
                    <StatusLabel
                        status={
                            row.original.effective_status ?? row.original.status
                        }
                    />
                    {row.original.campaign_name && !context.campaign && (
                        <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            in {row.original.campaign_name}
                        </span>
                    )}
                </button>
            ),
        },
        {
            accessorKey: 'optimization_goal',
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Optimization"
                    enabled={false}
                />
            ),
            cell: ({ row }) => (
                <span className="text-[11px] text-gray-500 dark:text-gray-400">
                    {row.original.optimization_goal ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'daily_budget',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Budget" />
            ),
            cell: ({ row }) => {
                const b = formatBudget(
                    row.original.daily_budget,
                    row.original.lifetime_budget,
                );
                return (
                    <div className="flex flex-col">
                        <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                            {b.value}
                        </span>
                        {b.label && (
                            <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                {b.label}
                            </span>
                        )}
                    </div>
                );
            },
        },
        ...buildInsightsColumns<AdSetRow>(),
    ];

    return (
        <AppLayout>
            <Head title="Meta Ads · Ad Sets" />

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
                        id="meta-ads-adsets-date-range"
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

                <AdsManagerTabs
                    workspaceSlug={workspace.slug}
                    active="ad-sets"
                    dateRange={dateRange}
                    carry={{ campaign: query?.campaign }}
                />

                {context.campaign && (
                    <div className="mb-4 flex items-center gap-2 font-mono text-[11px] text-gray-500 dark:text-gray-400">
                        <button
                            onClick={() =>
                                router.get(
                                    adsManagerUrl(workspace.slug, 'campaigns'),
                                    {
                                        since: dateRange.since,
                                        until: dateRange.until,
                                    },
                                )
                            }
                            className="rounded px-1.5 py-0.5 transition-colors hover:bg-stone-100 hover:text-emerald-600 dark:hover:bg-zinc-800 dark:hover:text-emerald-400"
                        >
                            All Campaigns
                        </button>
                        <ChevronRight className="h-3 w-3 text-gray-300" />
                        <span className="rounded px-1.5 py-0.5 text-gray-700 dark:text-gray-200">
                            {context.campaign.name}
                        </span>
                    </div>
                )}

                <div className="mb-3 flex items-center justify-between gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder="Search ad sets..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400 dark:focus:bg-zinc-900"
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        {context.campaign && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={clearCampaign}
                            >
                                <ChevronLeft className="mr-1 h-3.5 w-3.5" />
                                Back
                            </Button>
                        )}
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
                            {rows.total.toLocaleString()} ad sets
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
                        onFetch={(params) => navigate({
                            sort: params?.sort,
                            page: params?.page ?? 1,
                            per_page: params?.per_page ?? query?.perPage ?? rows.per_page,
                        })}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
