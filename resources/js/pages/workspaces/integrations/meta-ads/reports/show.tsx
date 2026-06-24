import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/date-picker';
import { MultiSelect } from '@/components/ui/multi-select';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type PaginatedData } from '@/types';
import { Head, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import { metricLabel } from '../_shared';
import { BreakdownControl } from './components/BreakdownControl';
import { GALLERY_GRID } from './components/chart-constants';
import { ChartStyleControl } from './components/ChartStyleControl';
import { CreativePreviewSheet } from './components/CreativePreviewSheet';
import { FiltersBar } from './components/FiltersBar';
import { GalleryCard, GallerySkeleton } from './components/GalleryCard';
import { MetricPicker } from './components/MetricPicker';
import { ReportChart } from './components/ReportChart';
import { ReportTable } from './components/ReportTable';
import { SettingsPanel } from './components/SettingsPanel';
import { SortControl } from './components/SortControl';
import {
    adsManagerDataUrl,
    breakdownLabel,
    type CustomBreakdownItem,
    DEFAULT_VIEW,
    isNameFilter,
    type MetricFilter,
    type ReportConfig,
    type ReportRecord,
    type ReportRow,
    reportsUrl,
} from './types';

interface Props {
    workspace: { id: number; name: string; slug: string };
    report: ReportRecord;
    accounts: { id: string; name: string }[];
    customBreakdowns?: CustomBreakdownItem[];
}

export default function ReportShow({
    workspace,
    report,
    accounts,
    customBreakdowns = [],
}: Props) {
    const baseUrl = reportsUrl(workspace.slug);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Reports', href: baseUrl },
        { title: report.name, href: `${baseUrl}/${report.id}` },
    ];

    const [name, setName] = useState(report.name);
    const [description, setDescription] = useState(report.description ?? '');
    // Reports saved before chart/view existed won't carry those keys — default
    // them so the builder controls always have a value to bind to.
    const [config, setConfig] = useState<ReportConfig>(() => ({
        ...report.config,
        chart: report.config.chart ?? 'gallery',
        view: { ...DEFAULT_VIEW, ...(report.config.view ?? {}) },
    }));
    const [rows, setRows] = useState<PaginatedData<ReportRow> | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [previewAd, setPreviewAd] = useState<ReportRow | null>(null);

    const patch = (partial: Partial<ReportConfig>) => {
        setConfig((c) => ({ ...c, ...partial }));
        setDirty(true);
    };

    // The engine accepts one name filter (search + name_op) and any number of
    // numeric metric filters (HAVING via metric_filters). Split them out.
    const nameFilter = config.filters.find(isNameFilter) ?? null;
    const metricFilters = config.filters.filter(
        (f): f is MetricFilter => !isNameFilter(f),
    );
    const accountsKey = config.accounts.join(',');
    const filtersKey = JSON.stringify(config.filters);

    // Fetch rows from the existing Ads Manager data endpoint whenever the
    // configuration changes. The report is just a saved set of these params.
    useEffect(() => {
        let active = true;
        setLoading(true);

        const qs = new URLSearchParams();
        qs.set('group_by', config.group_by);
        qs.set('since', config.since);
        qs.set('until', config.until);
        config.accounts.forEach((a) => qs.append('accounts[]', a));
        if (config.sort) qs.set('sort', config.sort);
        if (nameFilter && nameFilter.value.trim() !== '') {
            qs.set('filter[search]', nameFilter.value.trim());
            qs.set('name_op', nameFilter.op);
        }
        // Only send well-formed metric filters (a value, plus value2 for range).
        const validMetricFilters = metricFilters.filter(
            (f) =>
                Number.isFinite(f.value) &&
                (f.op !== 'range' || Number.isFinite(f.value2 ?? NaN)),
        );
        if (validMetricFilters.length > 0) {
            qs.set('metric_filters', JSON.stringify(validMetricFilters));
        }
        qs.set('per_page', '48');

        fetch(`${adsManagerDataUrl(workspace.slug)}?${qs.toString()}`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((d: { rows: PaginatedData<ReportRow> }) => {
                if (active) {
                    setRows(d.rows);
                    setLoading(false);
                }
            })
            .catch(() => active && setLoading(false));

        return () => {
            active = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        config.group_by,
        config.since,
        config.until,
        config.sort,
        accountsKey,
        filtersKey,
        workspace.slug,
    ]);

    const save = () => {
        setSaving(true);
        router.patch(
            `${baseUrl}/${report.id}`,
            { name, description, config },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const showThumbnail = config.group_by === 'ad';
    const data = useMemo(() => rows?.data ?? [], [rows]);
    const visibleData = useMemo(
        () => data.slice(0, Math.max(1, config.view.itemsLoaded)),
        [data, config.view.itemsLoaded],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${report.name} · Report`} />

            <div className="p-4 sm:p-6">
                {/* Header: title + save/discard */}
                <div className="mb-4 flex items-start justify-between gap-4">
                    <div className="min-w-0 flex-1">
                        <input
                            value={name}
                            onChange={(e) => {
                                setName(e.target.value);
                                setDirty(true);
                            }}
                            className="w-full bg-transparent text-2xl font-semibold tracking-tight text-gray-900 outline-none focus:ring-0 dark:text-gray-50"
                            placeholder="Untitled report"
                        />
                        <input
                            value={description}
                            onChange={(e) => {
                                setDescription(e.target.value);
                                setDirty(true);
                            }}
                            className="mt-1 w-full bg-transparent text-sm text-gray-500 outline-none focus:ring-0 dark:text-gray-400"
                            placeholder="Add description..."
                        />
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.visit(baseUrl)}
                        >
                            Discard
                        </Button>
                        <Button
                            size="sm"
                            onClick={save}
                            disabled={saving || !dirty}
                        >
                            {saving ? 'Saving…' : 'Save report'}
                        </Button>
                    </div>
                </div>

                {/* Filter / config bar */}
                <div className="mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-black/6 bg-white/70 p-2 dark:border-white/6 dark:bg-zinc-900/70">
                    <MultiSelect
                        options={accounts.map((a) => ({
                            value: a.id,
                            label: a.name,
                        }))}
                        selected={config.accounts}
                        onChange={(accs) => patch({ accounts: accs })}
                        placeholder="All accounts"
                        compact
                        className="min-w-[180px]"
                    />

                    <DatePicker
                        id={`report-${report.id}-range`}
                        mode="range"
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                patch({
                                    since: moment(dates[0]).format(
                                        'YYYY-MM-DD',
                                    ),
                                    until: moment(dates[1]).format(
                                        'YYYY-MM-DD',
                                    ),
                                });
                            }
                        }}
                        defaultDate={[config.since, config.until] as never}
                    />

                    <BreakdownControl
                        value={config.group_by}
                        customBreakdowns={customBreakdowns}
                        onChange={(group_by) => patch({ group_by })}
                    />

                    <FiltersBar
                        filters={config.filters}
                        onChange={(filters) => patch({ filters })}
                    />
                </div>

                {/* Metrics + sort bar */}
                <div className="mb-4 flex flex-wrap items-center gap-2">
                    {config.metrics.map((id) => (
                        <span
                            key={id}
                            className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 py-1 pr-1 pl-2.5 text-xs font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            {metricLabel(id)}
                            <button
                                type="button"
                                onClick={() =>
                                    patch({
                                        metrics: config.metrics.filter(
                                            (m) => m !== id,
                                        ),
                                    })
                                }
                                className="rounded-full p-0.5 hover:bg-emerald-500/20"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </span>
                    ))}

                    <MetricPicker
                        selected={config.metrics}
                        onChange={(metrics) => patch({ metrics })}
                    />

                    <div className="ml-auto flex items-center gap-2">
                        <div className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <span>Sort by</span>
                            <SortControl
                                sort={config.sort}
                                metrics={config.metrics}
                                onChange={(sort) => patch({ sort })}
                            />
                        </div>

                        {config.chart === 'gallery' && (
                            <SettingsPanel
                                view={config.view}
                                onChange={(view) => patch({ view })}
                            />
                        )}

                        <ChartStyleControl
                            value={config.chart}
                            onChange={(chart) => patch({ chart })}
                        />
                    </div>
                </div>

                {/* Results: chart/gallery + the breakdown table beneath it */}
                {loading ? (
                    config.chart === 'gallery' ? (
                        <GallerySkeleton />
                    ) : (
                        <Skeleton className="h-[420px] w-full rounded-2xl" />
                    )
                ) : data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-black/10 py-20 text-center text-sm text-gray-400 dark:border-white/10 dark:text-gray-500">
                        No results for this configuration.
                    </div>
                ) : (
                    <>
                        {config.chart === 'gallery' ? (
                            <div
                                className={
                                    GALLERY_GRID[config.view.cardSize] ??
                                    GALLERY_GRID[2]
                                }
                            >
                                {visibleData.map((row) => (
                                    <GalleryCard
                                        key={row.id}
                                        row={row}
                                        metrics={config.metrics}
                                        showThumbnail={
                                            showThumbnail &&
                                            !config.view.hideThumbnails
                                        }
                                        onPreview={
                                            showThumbnail
                                                ? () => setPreviewAd(row)
                                                : undefined
                                        }
                                    />
                                ))}
                            </div>
                        ) : (
                            <ReportChart
                                chart={config.chart}
                                rows={visibleData}
                                metrics={config.metrics}
                                since={config.since}
                                until={config.until}
                            />
                        )}

                        <ReportTable
                            rows={visibleData}
                            metrics={config.metrics}
                            sort={config.sort}
                            onSort={(sort) => patch({ sort })}
                            showThumbnail={showThumbnail}
                            label={breakdownLabel(
                                config.group_by,
                                customBreakdowns,
                            )}
                            onRowClick={
                                showThumbnail
                                    ? (row) => setPreviewAd(row)
                                    : undefined
                            }
                        />
                    </>
                )}
            </div>

            <CreativePreviewSheet
                slug={workspace.slug}
                ad={previewAd}
                onClose={() => setPreviewAd(null)}
            />
        </AppLayout>
    );
}
