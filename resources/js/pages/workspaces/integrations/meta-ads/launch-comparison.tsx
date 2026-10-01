import DatePicker from '@/components/ui/date-picker';
import Pagination from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';
import { ChartColumn, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { OwnerOption } from '../components/inline-owner';
import {
    AccountMultiPicker,
    AccountOption,
    GridFilter,
    INSIGHTS_OPTIONS,
    InsightFilterBuilder,
    InsightsMetrics,
    ObjectiveOption,
    deserializeGridFilters,
    formatMetricNumber,
    metricLabel,
    metricValue,
    serializeDateFilters,
    serializeMetricFilters,
} from './_shared';
import {
    MetricPicker,
    RowTimelineModal,
    TimelineTarget,
} from './row-timeline-modal';

type Level = 'campaign' | 'ad_set' | 'ad';
type Mode = 'daily' | 'cumulative';

/** One day of raw insight columns; null for a day that hasn't happened yet. */
type DayPoint = (InsightsMetrics & { date: string }) | null;

interface Item {
    id: string;
    name: string;
    parent: string | null;
    start_date: string;
    points: DayPoint[];
}

interface Props {
    workspace: { id: number; name: string; slug: string };
    accounts: AccountOption[];
    selectedAccounts: string[];
    level: Level;
    days: number;
    dayPresets: number[];
    maxDays: number;
    perPageOptions: number[];
    /** Only launches that started in this window (Y-m-d, inclusive); open ends are null. */
    startRange: { from: string | null; to: string | null };
    /** The Ads Manager filters in force, as the server parsed them. */
    filters: {
        search: string;
        creatorId: string | null;
        metric: unknown[];
        date: unknown[];
    };
    objectives: ObjectiveOption[];
    members: OwnerOption[];
    items: Item[];
    pagination: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
        from: number | null;
        to: number | null;
    };
}

const LEVELS: { id: Level; label: string; plural: string }[] = [
    { id: 'campaign', label: 'Campaign', plural: 'campaigns' },
    { id: 'ad_set', label: 'Ad set', plural: 'ad sets' },
    { id: 'ad', label: 'Ad', plural: 'ads' },
];

/**
 * Unique-person counts (reach, ad recallers) don't add across days or across
 * the ads a campaign sums — every summed figure would overcount — so they're
 * left out here rather than shown wrong. Frequency and the cost per recaller
 * are built on them.
 */
const NON_ADDITIVE = new Set([
    'reach',
    'frequency',
    'estimated_ad_recallers',
    'cost_per_estimated_ad_recaller',
]);

const METRIC_OPTIONS = INSIGHTS_OPTIONS.filter((o) => !NON_ADDITIVE.has(o.id));

const DEFAULT_METRICS = ['spend', 'purchases', 'cost_per_purchase', 'roas'];

/** A past day with no delivery is a real 0, unlike the grid's em-dash. */
function formatValue(metric: string, value: number): string {
    return value === 0 ? '0' : formatMetricNumber(metric, value);
}

/** "Sep 1, 2026" — the year matters once launches span a new year. */
function longDate(iso: string): string {
    const d = new Date(`${iso}T00:00:00`);

    return isNaN(d.getTime())
        ? iso
        : d.toLocaleDateString(undefined, {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          });
}

/** Local calendar date as Y-m-d — not toISOString(), which shifts to UTC. */
function toIsoDate(d: Date): string {
    const pad = (v: number) => String(v).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function addDays(iso: string, n: number): string {
    const d = new Date(`${iso}T00:00:00`);
    d.setDate(d.getDate() + n);

    return toIsoDate(d);
}

/**
 * The points a metric is computed from. Running totals accumulate the RAW
 * columns and then apply the metric's formula, so a running ROAS is total
 * revenue over total spend to date — not a sum of daily ratios.
 */
function seriesFor(item: Item, mode: Mode): DayPoint[] {
    if (mode === 'daily') return item.points;

    let running: Record<string, number> | null = null;

    return item.points.map((p) => {
        if (!p) return null;

        const next: Record<string, number> = { ...(running ?? {}) };
        for (const [k, v] of Object.entries(p)) {
            if (k !== 'date') next[k] = (next[k] ?? 0) + Number(v ?? 0);
        }
        running = next;

        return { ...(next as unknown as InsightsMetrics), date: p.date };
    });
}

/* ───────────────────── controls ───────────────────── */

function Segmented<T extends string | number>({
    value,
    options,
    onChange,
    label,
}: {
    value: T;
    options: { id: T; label: string }[];
    onChange: (next: T) => void;
    label: string;
}) {
    return (
        <div
            role="radiogroup"
            aria-label={label}
            className="flex h-9 items-center gap-0.5 rounded-[10px] bg-stone-100 p-0.5 dark:bg-zinc-800"
        >
            {options.map((o) => (
                <button
                    key={o.id}
                    type="button"
                    role="radio"
                    aria-checked={value === o.id}
                    onClick={() => onChange(o.id)}
                    className={clsx(
                        'h-8 rounded-[8px] px-2.5 font-mono text-[11px] transition-colors',
                        value === o.id
                            ? 'bg-white text-gray-800 shadow-sm dark:bg-zinc-900 dark:text-gray-100'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                    )}
                >
                    {o.label}
                </button>
            ))}
        </div>
    );
}

/** The preset day counts plus a free number for anything else. */
function DaysControl({
    value,
    presets,
    max,
    onChange,
}: {
    value: number;
    presets: number[];
    max: number;
    onChange: (next: number) => void;
}) {
    const [draft, setDraft] = useState(
        presets.includes(value) ? '' : String(value),
    );
    useEffect(
        () => setDraft(presets.includes(value) ? '' : String(value)),
        [value, presets],
    );

    const commit = () => {
        const n = parseInt(draft, 10);
        if (!isFinite(n)) return;
        const clamped = Math.max(1, Math.min(max, n));
        if (clamped !== value) onChange(clamped);
        else setDraft(String(clamped));
    };

    return (
        <div className="flex items-center gap-1.5">
            <Segmented
                label="Days to show"
                value={presets.includes(value) ? value : -1}
                options={presets.map((d) => ({ id: d, label: `${d}d` }))}
                onChange={onChange}
            />
            <input
                type="number"
                min={1}
                max={max}
                inputMode="numeric"
                placeholder="Custom"
                aria-label={`Custom number of days, up to ${max}`}
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                onBlur={commit}
                onKeyDown={(e) => e.key === 'Enter' && commit()}
                className={clsx(
                    'h-9 w-20 rounded-[10px] border bg-stone-100 px-2.5 font-mono text-[11px] text-gray-800 outline-none placeholder:text-gray-400 focus:border-emerald-500 dark:bg-zinc-800 dark:text-gray-100',
                    presets.includes(value)
                        ? 'border-black/6 dark:border-white/6'
                        : 'border-emerald-500',
                )}
            />
        </div>
    );
}

/* ───────────────────── table ───────────────────── */

interface Series {
    item: Item;
    points: DayPoint[];
}

/**
 * One row per launch. Columns are grouped by day — Day 1 → Day N across the
 * top — and each day splits into its calendar date plus one column per
 * metric. The date sits in the row because Day N lands on a different date
 * for each launch. Future days show their date, muted, and no values.
 */
function ComparisonTable({
    metrics,
    series,
    days,
    onOpenTimeline,
}: {
    metrics: string[];
    series: Series[];
    days: number;
    onOpenTimeline: (item: Item) => void;
}) {
    const dayIdx = Array.from({ length: days }, (_, i) => i);
    // Each day group opens with a divider so the groups read apart.
    const groupStart = 'border-l border-black/6 dark:border-white/6';

    return (
        // Scrolls inside its own box both ways, so the header can stay pinned
        // to the top and the launch names to the left.
        <div className="max-h-[75vh] overflow-auto rounded-[12px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <table className="w-full font-mono text-[11px]">
                {/* Collapsed borders don't travel with a sticky header, so its
                    bottom edge is a shadow; every cell gets the surface so
                    rows don't show through. The corner cell sits above both
                    sticky axes. */}
                <thead className="sticky top-0 z-20 shadow-[0_1px_0_var(--border)] [&_th]:bg-white dark:[&_th]:bg-zinc-900">
                    <tr className="border-b border-black/6 dark:border-white/6">
                        <th
                            rowSpan={2}
                            className="sticky left-0 z-10 w-56 min-w-56 bg-white px-3 py-2 text-left align-bottom font-medium text-gray-400 sm:w-80 sm:min-w-80 dark:bg-zinc-900 dark:text-gray-500"
                        >
                            Launch
                        </th>
                        {/* Pinned beside the name from sm up, where the name
                            column is exactly w-80; on phones it scrolls so the
                            pinned area doesn't swallow the screen. */}
                        <th
                            rowSpan={2}
                            className="w-32 min-w-32 px-3 py-2 text-left align-bottom font-medium whitespace-nowrap text-gray-400 sm:sticky sm:left-80 sm:z-10 dark:text-gray-500"
                        >
                            Start date
                        </th>
                        {dayIdx.map((i) => (
                            <th
                                key={i}
                                colSpan={metrics.length + 1}
                                className={clsx(
                                    groupStart,
                                    'px-3 py-2 text-center font-medium whitespace-nowrap text-gray-700 dark:text-gray-200',
                                )}
                            >
                                Day {i + 1}
                            </th>
                        ))}
                    </tr>
                    <tr className="border-b border-black/6 dark:border-white/6">
                        {dayIdx.map((i) => [
                            <th
                                key={`${i}-date`}
                                className={clsx(
                                    groupStart,
                                    'px-3 py-1.5 text-left font-normal whitespace-nowrap text-gray-400 dark:text-gray-500',
                                )}
                            >
                                Date
                            </th>,
                            ...metrics.map((m) => (
                                <th
                                    key={`${i}-${m}`}
                                    className="px-3 py-1.5 text-right font-normal whitespace-nowrap text-gray-500 dark:text-gray-400"
                                >
                                    {metricLabel(m)}
                                </th>
                            )),
                        ])}
                    </tr>
                </thead>
                <tbody>
                    {series.map((s) => (
                        <tr
                            key={s.item.id}
                            className="border-b border-black/4 last:border-0 dark:border-white/4"
                        >
                            <td className="sticky left-0 z-10 w-56 max-w-56 min-w-56 bg-white px-3 py-1.5 sm:w-80 sm:max-w-80 sm:min-w-80 dark:bg-zinc-900">
                                <div className="flex items-start gap-1.5">
                                    <div className="min-w-0 flex-1">
                                        <span
                                            className="block truncate font-medium text-gray-700 dark:text-gray-200"
                                            title={s.item.name}
                                        >
                                            {s.item.name}
                                        </span>
                                        {s.item.parent && (
                                            <span className="mt-0.5 block truncate text-[10px] text-gray-400 dark:text-gray-500">
                                                {s.item.parent}
                                            </span>
                                        )}
                                    </div>
                                    {/* Same button and modal as the Ads
                                        Manager rows. */}
                                    <button
                                        type="button"
                                        onClick={() => onOpenTimeline(s.item)}
                                        title="View timeline"
                                        aria-label={`View the timeline for ${s.item.name}`}
                                        className="mt-0.5 shrink-0 rounded-md p-1 text-gray-300 transition-colors hover:bg-stone-100 hover:text-emerald-600 dark:text-gray-600 dark:hover:bg-zinc-700 dark:hover:text-emerald-400"
                                    >
                                        <ChartColumn className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                            <td className="w-32 min-w-32 bg-white px-3 py-1.5 whitespace-nowrap text-gray-600 sm:sticky sm:left-80 sm:z-10 dark:bg-zinc-900 dark:text-gray-300">
                                {longDate(s.item.start_date)}
                            </td>
                            {dayIdx.map((i) => {
                                const p = s.points[i];

                                return [
                                    <td
                                        key={`${i}-date`}
                                        className={clsx(
                                            groupStart,
                                            'px-3 py-1.5 whitespace-nowrap',
                                            p
                                                ? 'text-gray-600 dark:text-gray-300'
                                                : 'text-gray-400 dark:text-gray-500',
                                        )}
                                    >
                                        {longDate(
                                            addDays(s.item.start_date, i),
                                        )}
                                    </td>,
                                    ...metrics.map((m) => (
                                        <td
                                            key={`${i}-${m}`}
                                            className="px-3 py-1.5 text-right whitespace-nowrap text-gray-800 tabular-nums dark:text-gray-100"
                                        >
                                            {p
                                                ? formatValue(
                                                      m,
                                                      metricValue(p, m),
                                                  )
                                                : ''}
                                        </td>
                                    )),
                                ];
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/* ───────────────────── page ───────────────────── */

export default function LaunchComparison({
    workspace,
    accounts,
    selectedAccounts,
    level,
    days,
    dayPresets,
    maxDays,
    perPageOptions,
    startRange,
    filters: serverFilters,
    objectives,
    members,
    items,
    pagination,
}: Props) {
    const base = `/workspaces/${workspace.slug}/integrations/meta/launch-comparison`;
    const levelMeta = LEVELS.find((l) => l.id === level) ?? LEVELS[0];

    const [metrics, setMetrics] = useState<string[]>(DEFAULT_METRICS);
    const [mode, setMode] = useState<Mode>('daily');
    const [timeline, setTimeline] = useState<{
        target: TimelineTarget;
        range: { since: string; until: string };
    } | null>(null);

    // Local copies give instant feedback; the visit is debounced where a few
    // quick edits (picker clicks, typing) should cost one reload, not one each.
    const [pendingAccounts, setPendingAccounts] = useState(selectedAccounts);
    const [searchValue, setSearchValue] = useState(serverFilters.search);
    const [creatorId, setCreatorId] = useState(serverFilters.creatorId ?? '');
    const [filters, setFilters] = useState<GridFilter[]>(() =>
        deserializeGridFilters(serverFilters.metric, serverFilters.date),
    );
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    interface Query {
        level: Level;
        days: number;
        accounts: string[];
        page: number;
        perPage: number;
        startRange: { from: string | null; to: string | null };
        search: string;
        creatorId: string;
        filters: GridFilter[];
    }

    // Every control goes through here: the URL is rebuilt from the whole
    // current state plus the one change, so no control can drop another's
    // setting. Any change but paging starts back on page 1.
    const visit = (next: Partial<Query>, debounce = false) => {
        const q: Query = {
            level,
            days,
            accounts: pendingAccounts,
            page: 1,
            perPage: pagination.perPage,
            startRange,
            search: searchValue,
            creatorId,
            filters,
            ...next,
        };
        const metricFilters = serializeMetricFilters(q.filters);
        const dateFilters = serializeDateFilters(q.filters);

        const params = {
            level: q.level,
            days: q.days,
            // All accounts is the default, so it stays out of the URL. No
            // accounts sends a sentinel that matches none — an empty list
            // would drop out of the URL and read as "all" again.
            ...(q.accounts.length !== accounts.length
                ? { accounts: q.accounts.length ? q.accounts : ['none'] }
                : {}),
            ...(q.startRange.from ? { start_from: q.startRange.from } : {}),
            ...(q.startRange.to ? { start_to: q.startRange.to } : {}),
            ...(q.search.trim() ? { search: q.search.trim() } : {}),
            // Creator is tagged per ad, so it only applies at the ad level.
            ...(q.level === 'ad' && q.creatorId
                ? { creator_id: q.creatorId }
                : {}),
            ...(metricFilters ? { metric_filters: metricFilters } : {}),
            ...(dateFilters ? { date_filters: dateFilters } : {}),
            ...(q.page > 1 ? { page: q.page } : {}),
            ...(q.perPage !== perPageOptions[0] ? { per_page: q.perPage } : {}),
        };
        const go = () =>
            router.get(base, params, {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            });

        if (timer.current) clearTimeout(timer.current);
        if (debounce) timer.current = setTimeout(go, 350);
        else go();
    };

    const series: Series[] = useMemo(
        () => items.map((item) => ({ item, points: seriesFor(item, mode) })),
        [items, mode],
    );

    // The row's own window — Day 1 to Day N, stopping at today — so the modal
    // charts the same days the row shows.
    const openTimeline = (item: Item) => {
        const today = toIsoDate(new Date());
        const lastDay = addDays(item.start_date, days - 1);

        setTimeline({
            target: { scopeBy: level, scope: item.id, label: item.name },
            range: {
                since: item.start_date,
                until: lastDay < today ? lastDay : today,
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Meta Ads · Launch Comparison" />

            <div className="w-full p-4 md:p-6">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="mb-1 font-mono text-[10px] font-medium tracking-[0.12em] text-emerald-600 uppercase dark:text-emerald-400">
                            Meta Ads
                        </p>
                        <h1 className="text-[26px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                            Launch Comparison
                        </h1>
                        <p className="mt-1 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                            Launches lined up by day since start — Day 1 is each{' '}
                            {levelMeta.label.toLowerCase()}'s own start date.
                        </p>
                    </div>

                    {/* Which launches to include, by when they started. The
                            day count below is separate: how far past each start
                            to look. */}
                    <DatePicker
                        id="launch-comparison-start-range"
                        mode="range"
                        placeholder="Any start date"
                        maxDate={toIsoDate(new Date())}
                        defaultDate={
                            startRange.from && startRange.to
                                ? [startRange.from, startRange.to]
                                : undefined
                        }
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                visit({
                                    startRange: {
                                        from: toIsoDate(dates[0]),
                                        to: toIsoDate(dates[1]),
                                    },
                                });
                            } else if (
                                dates.length === 0 &&
                                (startRange.from || startRange.to)
                            ) {
                                visit({
                                    startRange: { from: null, to: null },
                                });
                            }
                        }}
                    />
                </div>

                <div className="mb-4 space-y-2 rounded-2xl border border-black/6 bg-white/70 p-2 shadow-sm backdrop-blur-sm dark:border-white/6 dark:bg-zinc-900/70">
                    {/* Which launches — the Ads Manager's filter bar. */}
                    <div className="flex flex-wrap items-center gap-2">
                        <AccountMultiPicker
                            accounts={accounts}
                            selected={pendingAccounts}
                            onChange={(next) => {
                                setPendingAccounts(next);
                                visit({ accounts: next }, true);
                            }}
                        />
                        <Segmented
                            label="Group by"
                            value={level}
                            options={LEVELS.map((l) => ({
                                id: l.id,
                                label: l.label,
                            }))}
                            onChange={(next) => {
                                // Creator only exists per ad.
                                if (next !== 'ad') setCreatorId('');
                                visit({ level: next, creatorId: '' });
                            }}
                        />
                        <div className="relative min-w-[180px] flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                            <input
                                type="text"
                                placeholder={`Search ${levelMeta.plural}...`}
                                aria-label={`Search ${levelMeta.plural}`}
                                value={searchValue}
                                onChange={(e) => {
                                    setSearchValue(e.target.value);
                                    visit({ search: e.target.value }, true);
                                }}
                                className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400 dark:focus:bg-zinc-900"
                            />
                        </div>
                        {level === 'ad' && (
                            <Select
                                value={creatorId || 'all'}
                                onValueChange={(v) => {
                                    const next = v === 'all' ? '' : v;
                                    setCreatorId(next);
                                    visit({ creatorId: next });
                                }}
                            >
                                <SelectTrigger
                                    aria-label="Creator"
                                    className="h-9 w-[180px] font-mono text-[11px]"
                                >
                                    <SelectValue placeholder="All creators" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All creators
                                    </SelectItem>
                                    <SelectItem value="unassigned">
                                        Unassigned
                                    </SelectItem>
                                    {members.map((m) => (
                                        <SelectItem
                                            key={m.id}
                                            value={String(m.id)}
                                        >
                                            {m.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        <InsightFilterBuilder
                            filters={filters}
                            onChange={(next) => {
                                setFilters(next);
                                visit({ filters: next });
                            }}
                            groupBy={level}
                            objectives={objectives}
                        />
                    </div>

                    {/* How to compare them. */}
                    <div className="flex flex-wrap items-center gap-2">
                        <DaysControl
                            value={days}
                            presets={dayPresets}
                            max={maxDays}
                            onChange={(next) => visit({ days: next })}
                        />
                        <div className="ml-auto flex flex-wrap items-center gap-2">
                            <Segmented
                                label="Daily or running total"
                                value={mode}
                                options={[
                                    { id: 'daily', label: 'Daily' },
                                    {
                                        id: 'cumulative',
                                        label: 'Running total',
                                    },
                                ]}
                                onChange={setMode}
                            />
                            <MetricPicker
                                selected={metrics}
                                onChange={setMetrics}
                                options={METRIC_OPTIONS}
                                defaults={DEFAULT_METRICS}
                            />
                        </div>
                    </div>
                </div>

                {items.length === 0 ? (
                    <p className="rounded-2xl border border-dashed border-black/10 py-20 text-center font-mono text-[11px] text-gray-400 dark:border-white/10 dark:text-gray-500">
                        {pendingAccounts.length === 0
                            ? 'No ad accounts selected — pick at least one.'
                            : startRange.from ||
                                startRange.to ||
                                searchValue.trim() ||
                                creatorId ||
                                serializeMetricFilters(filters) ||
                                serializeDateFilters(filters)
                              ? `No ${levelMeta.plural} match these filters.`
                              : `No ${levelMeta.plural} have launched in the selected accounts yet.`}
                    </p>
                ) : (
                    <>
                        <ComparisonTable
                            metrics={metrics}
                            series={series}
                            days={days}
                            onOpenTimeline={openTimeline}
                        />

                        <p className="mt-3 px-1 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            {mode === 'cumulative'
                                ? 'Running totals: each day adds up everything since Day 1, and rates (ROAS, CPA, CTR…) are recomputed from those totals.'
                                : 'Daily values.'}{' '}
                            Blank days haven't happened yet, or are today and
                            haven't synced. Reach and frequency are left out —
                            unique people can't be added across days.
                        </p>

                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                <span>
                                    {pagination.from}–{pagination.to} of{' '}
                                    {pagination.total} {levelMeta.plural}
                                </span>
                                <Select
                                    value={String(pagination.perPage)}
                                    onValueChange={(v) =>
                                        visit({ perPage: Number(v) })
                                    }
                                >
                                    <SelectTrigger
                                        aria-label="Rows per page"
                                        className="h-8 w-28 rounded-lg border border-black/6 bg-stone-50 px-2 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {perPageOptions.map((n) => (
                                            <SelectItem
                                                key={n}
                                                value={String(n)}
                                                className="font-mono text-[11px]"
                                            >
                                                {n} / page
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            {pagination.lastPage > 1 && (
                                <Pagination
                                    currentPage={pagination.currentPage}
                                    totalPages={pagination.lastPage}
                                    onPageChange={(page) => visit({ page })}
                                />
                            )}
                        </div>
                    </>
                )}
            </div>

            <RowTimelineModal
                slug={workspace.slug}
                target={timeline?.target ?? null}
                dateRange={timeline?.range ?? { since: '', until: '' }}
                selectedAccounts={pendingAccounts}
                accountsTotal={accounts.length}
                groupLabel={levelMeta.label}
                onClose={() => setTimeline(null)}
            />
        </AppLayout>
    );
}
