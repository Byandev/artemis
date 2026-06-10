import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import { omit } from 'lodash';
import {
    Check,
    ChevronDown,
    Image as ImageIcon,
    LayoutGrid,
    Search,
} from 'lucide-react';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
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
    id: number;
    name: string | null;
    status?: string | null;
    effective_status?: string | null;
    thumbnail_url?: string | null;
    image_url?: string | null;
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
                    {showThumbnail &&
                        (row.original.thumbnail_url ||
                        row.original.image_url ? (
                            <img
                                src={
                                    row.original.thumbnail_url ??
                                    row.original.image_url ??
                                    ''
                                }
                                alt=""
                                className="h-10 w-10 shrink-0 rounded object-cover"
                            />
                        ) : (
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-stone-100 text-gray-400 dark:bg-zinc-800 dark:text-gray-500">
                                <ImageIcon className="h-4 w-4" />
                            </div>
                        ))}
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
        </AppLayout>
    );
}
