import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import DatePicker from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { MultiSelect } from '@/components/ui/multi-select';
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
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type PaginatedData } from '@/types';
import { Head, router } from '@inertiajs/react';
import moment from 'moment';
import {
    ArrowDown,
    ArrowUp,
    ImageOff,
    Loader2,
    Plus,
    Search,
    Video,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    formatMetricValue,
    INSIGHTS_OPTIONS,
    metricLabel,
} from '../_shared';
import {
    type AdDetail,
    adDetailUrl,
    adsManagerDataUrl,
    type GroupByKey,
    GROUP_BY_LABELS,
    NAME_OP_LABELS,
    type NameFilterOp,
    type ReportConfig,
    type ReportFilter,
    type ReportRecord,
    type ReportRow,
    reportsUrl,
} from './types';

interface Props {
    workspace: { id: number; name: string; slug: string };
    report: ReportRecord;
    accounts: { id: string; name: string }[];
}

const GROUP_BY_OPTIONS: GroupByKey[] = [
    'ad',
    'ad_name',
    'campaign',
    'ad_set',
    'account',
];

export default function ReportShow({ workspace, report, accounts }: Props) {
    const baseUrl = reportsUrl(workspace.slug);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Reports', href: baseUrl },
        { title: report.name, href: `${baseUrl}/${report.id}` },
    ];

    const [name, setName] = useState(report.name);
    const [description, setDescription] = useState(report.description ?? '');
    const [config, setConfig] = useState<ReportConfig>(report.config);
    const [rows, setRows] = useState<PaginatedData<ReportRow> | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [previewAd, setPreviewAd] = useState<ReportRow | null>(null);

    const patch = (partial: Partial<ReportConfig>) => {
        setConfig((c) => ({ ...c, ...partial }));
        setDirty(true);
    };

    const filter = config.filters[0] ?? null;
    const accountsKey = config.accounts.join(',');

    // Fetch gallery rows from the existing Ads Manager data endpoint whenever
    // the configuration changes. The report is just a saved set of these params.
    useEffect(() => {
        let active = true;
        setLoading(true);

        const qs = new URLSearchParams();
        qs.set('group_by', config.group_by);
        qs.set('since', config.since);
        qs.set('until', config.until);
        config.accounts.forEach((a) => qs.append('accounts[]', a));
        if (config.sort) qs.set('sort', config.sort);
        if (filter && filter.value.trim() !== '') {
            qs.set('filter[search]', filter.value.trim());
            qs.set('name_op', filter.op);
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
        filter?.op,
        filter?.value,
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
    const data = rows?.data ?? [];

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
                        <Button size="sm" onClick={save} disabled={saving || !dirty}>
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
                                    since: moment(dates[0]).format('YYYY-MM-DD'),
                                    until: moment(dates[1]).format('YYYY-MM-DD'),
                                });
                            }
                        }}
                        defaultDate={[config.since, config.until] as never}
                    />

                    <div className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <span>Group by</span>
                        <Select
                            value={config.group_by}
                            onValueChange={(v) =>
                                patch({ group_by: v as GroupByKey })
                            }
                        >
                            <SelectTrigger className="h-8 w-[130px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {GROUP_BY_OPTIONS.map((g) => (
                                    <SelectItem key={g} value={g}>
                                        {GROUP_BY_LABELS[g]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <FilterControl
                        filter={filter}
                        onChange={(f) =>
                            patch({ filters: f ? [f] : [] })
                        }
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

                    <div className="ml-auto flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <span>Sort by</span>
                        <SortControl
                            sort={config.sort}
                            metrics={config.metrics}
                            onChange={(sort) => patch({ sort })}
                        />
                    </div>
                </div>

                {/* Gallery */}
                {loading ? (
                    <GallerySkeleton />
                ) : data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-black/10 py-20 text-center text-sm text-gray-400 dark:border-white/10 dark:text-gray-500">
                        No results for this configuration.
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        {data.map((row) => (
                            <GalleryCard
                                key={row.id}
                                row={row}
                                metrics={config.metrics}
                                showThumbnail={showThumbnail}
                                onPreview={
                                    showThumbnail
                                        ? () => setPreviewAd(row)
                                        : undefined
                                }
                            />
                        ))}
                    </div>
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

/* ───────────────────────── Gallery card ───────────────────────── */

function GalleryCard({
    row,
    metrics,
    showThumbnail,
    onPreview,
}: {
    row: ReportRow;
    metrics: string[];
    showThumbnail: boolean;
    onPreview?: () => void;
}) {
    const thumb = row.thumbnail_url || row.image_url || null;

    return (
        <div className="flex flex-col overflow-hidden rounded-xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            {showThumbnail && (
                <button
                    type="button"
                    onClick={onPreview}
                    title="View creative"
                    className="group relative aspect-square w-full cursor-pointer bg-gray-100 dark:bg-zinc-800"
                >
                    {thumb ? (
                        <img
                            src={thumb}
                            alt={row.name ?? ''}
                            className="h-full w-full object-cover"
                            loading="lazy"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center text-gray-300 dark:text-gray-600">
                            <ImageOff className="h-8 w-8" />
                        </div>
                    )}
                    {row.media_type === 'video' && (
                        <span className="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] font-medium text-white">
                            <Video className="h-3 w-3" />
                            Video
                        </span>
                    )}
                    <span className="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/10" />
                </button>
            )}
            <div className="flex flex-1 flex-col p-3">
                <p
                    className="truncate text-xs font-semibold text-gray-900 dark:text-gray-100"
                    title={row.name ?? ''}
                >
                    {row.name || '—'}
                </p>
                <dl className="mt-2 space-y-1">
                    {metrics.map((id) => (
                        <div
                            key={id}
                            className="flex items-center justify-between gap-2"
                        >
                            <dt className="truncate text-[11px] text-gray-400 dark:text-gray-500">
                                {metricLabel(id)}
                            </dt>
                            <dd className="font-mono text-[11px] font-medium text-gray-700 tabular-nums dark:text-gray-200">
                                {formatMetricValue(row, id)}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </div>
    );
}

/* ───────────────────── Creative preview sheet ───────────────────── */

function CreativePreviewSheet({
    slug,
    ad,
    onClose,
}: {
    slug: string;
    ad: ReportRow | null;
    onClose: () => void;
}) {
    const [detail, setDetail] = useState<AdDetail | null>(null);
    const [loading, setLoading] = useState(false);
    const [iframeLoaded, setIframeLoaded] = useState(false);

    useEffect(() => {
        if (!ad) return;
        let active = true;
        setLoading(true);
        setDetail(null);
        setIframeLoaded(false);

        fetch(adDetailUrl(slug, ad.id), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((d: AdDetail) => {
                if (active) {
                    setDetail(d);
                    setLoading(false);
                }
            })
            .catch(() => active && setLoading(false));

        return () => {
            active = false;
        };
    }, [ad?.id, slug]); // eslint-disable-line react-hooks/exhaustive-deps

    const src = detail?.preview?.src ?? null;
    const dim = detail?.dimensions;
    const showSpinner = loading || (!!src && !iframeLoaded);

    return (
        <Sheet open={!!ad} onOpenChange={(o) => !o && onClose()}>
            <SheetContent
                side="right"
                className="w-full gap-0 overflow-y-auto p-0 sm:max-w-md"
            >
                <SheetHeader className="border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <SheetTitle className="truncate pr-6 text-sm tracking-tight text-gray-800 dark:text-gray-100">
                        {ad?.name ?? 'Creative'}
                    </SheetTitle>
                </SheetHeader>

                <div className="p-4">
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
                                        className={`h-full w-full border-0 ${
                                            iframeLoaded ? '' : 'invisible'
                                        }`}
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
                                            <p className="text-center font-mono text-xs text-gray-400 dark:text-gray-500">
                                                Preview unavailable for this ad.
                                            </p>
                                        </div>
                                    )
                                )}
                            </div>
                        </div>
                    </div>

                    <p className="mt-5 mb-1 text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        Dimensions
                    </p>
                    <dl className="divide-y divide-black/4 dark:divide-white/4">
                        <PreviewRow label="Ad status" value={dim?.ad_status} />
                        <PreviewRow label="Adset" value={dim?.adset_name} />
                        <PreviewRow label="Campaign" value={dim?.campaign_name} />
                        <PreviewRow label="Account" value={dim?.account_name} />
                        <PreviewRow label="Ad type" value={dim?.ad_type} />
                        <PreviewRow
                            label="Call to action"
                            value={dim?.call_to_action}
                        />
                    </dl>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function PreviewRow({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid grid-cols-[120px_1fr] gap-2 py-1.5">
            <dt className="text-[11px] text-gray-400 dark:text-gray-500">
                {label}
            </dt>
            <dd className="text-xs text-gray-700 dark:text-gray-200">
                {value ?? <span className="text-gray-300">—</span>}
            </dd>
        </div>
    );
}

function GallerySkeleton() {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {Array.from({ length: 8 }).map((_, i) => (
                <div
                    key={i}
                    className="overflow-hidden rounded-xl border border-black/6 dark:border-white/6"
                >
                    <Skeleton className="aspect-square w-full" />
                    <div className="space-y-2 p-3">
                        <Skeleton className="h-3 w-3/4" />
                        <Skeleton className="h-3 w-full" />
                        <Skeleton className="h-3 w-full" />
                    </div>
                </div>
            ))}
        </div>
    );
}

/* ───────────────────────── Metric picker ───────────────────────── */

function MetricPicker({
    selected,
    onChange,
}: {
    selected: string[];
    onChange: (metrics: string[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const grouped = useMemo(() => {
        const map = new Map<string, typeof INSIGHTS_OPTIONS>();
        INSIGHTS_OPTIONS.filter((o) =>
            o.label.toLowerCase().includes(search.toLowerCase()),
        ).forEach((o) => {
            const cat = o.category ?? 'Other';
            map.set(cat, [...(map.get(cat) ?? []), o]);
        });
        return [...map.entries()];
    }, [search]);

    const toggle = (id: string) =>
        onChange(
            selected.includes(id)
                ? selected.filter((m) => m !== id)
                : [...selected, id],
        );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="inline-flex items-center gap-1 rounded-full border border-dashed border-black/15 px-2.5 py-1 text-xs font-medium text-gray-500 hover:border-emerald-400 hover:text-emerald-600 dark:border-white/15 dark:text-gray-400"
                >
                    <Plus className="h-3 w-3" />
                    Add metric
                </button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-64 p-0">
                <div className="border-b border-black/6 p-2 dark:border-white/6">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search metrics..."
                            className="h-8 pl-7 text-xs"
                        />
                    </div>
                </div>
                <div className="max-h-72 overflow-auto p-1">
                    {grouped.map(([cat, opts]) => (
                        <div key={cat} className="mb-1">
                            <p className="px-2 py-1 text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                                {cat}
                            </p>
                            {opts.map((o) => (
                                <label
                                    key={o.id}
                                    className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs hover:bg-gray-50 dark:hover:bg-zinc-800"
                                >
                                    <Checkbox
                                        checked={selected.includes(o.id)}
                                        onCheckedChange={() => toggle(o.id)}
                                    />
                                    <span className="text-gray-700 dark:text-gray-200">
                                        {o.label}
                                    </span>
                                </label>
                            ))}
                        </div>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}

/* ───────────────────────── Filter control ───────────────────────── */

function FilterControl({
    filter,
    onChange,
}: {
    filter: ReportFilter | null;
    onChange: (filter: ReportFilter | null) => void;
}) {
    if (!filter) {
        return (
            <button
                type="button"
                onClick={() =>
                    onChange({ field: 'name', op: 'contains', value: '' })
                }
                className="inline-flex items-center gap-1 rounded-md border border-dashed border-black/15 px-2 py-1.5 text-xs font-medium text-gray-500 hover:border-emerald-400 hover:text-emerald-600 dark:border-white/15 dark:text-gray-400"
            >
                <Plus className="h-3 w-3" />
                Add filter
            </button>
        );
    }

    return (
        <div className="flex items-center gap-1.5 rounded-md bg-gray-50 px-1.5 py-1 dark:bg-zinc-800">
            <span className="px-1 text-xs text-gray-500 dark:text-gray-400">
                Name
            </span>
            <Select
                value={filter.op}
                onValueChange={(op) =>
                    onChange({ ...filter, op: op as NameFilterOp })
                }
            >
                <SelectTrigger className="h-7 w-[150px] text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {(
                        Object.keys(NAME_OP_LABELS) as NameFilterOp[]
                    ).map((op) => (
                        <SelectItem key={op} value={op}>
                            {NAME_OP_LABELS[op]}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Input
                value={filter.value}
                onChange={(e) => onChange({ ...filter, value: e.target.value })}
                placeholder="value"
                className="h-7 w-[140px] text-xs"
            />
            <button
                type="button"
                onClick={() => onChange(null)}
                className="rounded p-1 text-gray-400 hover:text-red-500"
            >
                <X className="h-3.5 w-3.5" />
            </button>
        </div>
    );
}

/* ───────────────────────── Sort control ───────────────────────── */

function SortControl({
    sort,
    metrics,
    onChange,
}: {
    sort: string;
    metrics: string[];
    onChange: (sort: string) => void;
}) {
    const desc = sort.startsWith('-');
    const field = desc ? sort.slice(1) : sort;
    const options = metrics.length > 0 ? metrics : ['spend'];

    return (
        <div className="flex items-center gap-1">
            <Select
                value={field}
                onValueChange={(f) => onChange(`${desc ? '-' : ''}${f}`)}
            >
                <SelectTrigger className="h-8 w-[150px] text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((m) => (
                        <SelectItem key={m} value={m}>
                            {metricLabel(m)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <button
                type="button"
                title={desc ? 'Descending' : 'Ascending'}
                onClick={() => onChange(`${desc ? '' : '-'}${field}`)}
                className="rounded-md border border-black/10 p-1.5 text-gray-500 hover:text-emerald-600 dark:border-white/10 dark:text-gray-400"
            >
                {desc ? (
                    <ArrowDown className="h-3.5 w-3.5" />
                ) : (
                    <ArrowUp className="h-3.5 w-3.5" />
                )}
            </button>
        </div>
    );
}
