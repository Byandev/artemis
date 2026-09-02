import PageHeader from '@/components/common/PageHeader';
import AddTestingItemDialog from '@/components/sales-marketing/new-creatives-tracker/add-testing-item-dialog';
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/date-picker';
import Pagination from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDebouncedState } from '@/hooks/use-debounced-state';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import flatpickr from 'flatpickr';
import { Pause, Play, Plus, Search, X } from 'lucide-react';
import moment from 'moment';
import { Fragment, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import DateOption = flatpickr.Options.DateOption;

interface Metrics {
    sales: number;
    ad_spent: number;
    roas: number | null;
}

interface DayMetrics extends Metrics {
    date: string | null;
}

type InternDecision = 'scale' | 'split_50_50' | 'killed';
type FinanceStatus = 'for_collection' | 'pending' | 'collected';

interface TestingItem {
    id: number;
    item_type: 'campaign' | 'ad_set';
    item_id: string;
    name: string | null;
    account_id: string | null;
    account_name: string | null;
    product: string | null;
    is_paused: boolean;
    start_date: string | null;
    days: Record<string, DayMetrics>;
    last_day: number;
    total: Metrics;
    intern_decision: InternDecision | null;
    finance_status: FinanceStatus | null;
    created_at: string | null;
}

/**
 * Both statuses are set by hand once a test's numbers are in — nothing derives
 * them, and null (still undecided) is a real state worth telling apart from any
 * of the choices, so each list starts with a blank.
 */
const DECISION_OPTIONS: { value: InternDecision; label: string }[] = [
    { value: 'scale', label: 'Scale' },
    { value: 'split_50_50', label: '50% Intern / 50% Company' },
    { value: 'killed', label: 'Killed' },
];

const FINANCE_OPTIONS: { value: FinanceStatus; label: string }[] = [
    { value: 'for_collection', label: 'For Collection' },
    { value: 'pending', label: 'Pending' },
    { value: 'collected', label: 'Collected' },
];

/** Tone per status, so a column can be read down without parsing each label. */
const STATUS_TONE: Record<InternDecision | FinanceStatus, string> = {
    scale: 'text-emerald-700 bg-emerald-50 border-emerald-200 dark:text-emerald-300 dark:bg-emerald-500/10 dark:border-emerald-500/30',
    split_50_50:
        'text-amber-700 bg-amber-50 border-amber-200 dark:text-amber-300 dark:bg-amber-500/10 dark:border-amber-500/30',
    killed: 'text-red-700 bg-red-50 border-red-200 dark:text-red-300 dark:bg-red-500/10 dark:border-red-500/30',
    for_collection:
        'text-blue-700 bg-blue-50 border-blue-200 dark:text-blue-300 dark:bg-blue-500/10 dark:border-blue-500/30',
    pending:
        'text-amber-700 bg-amber-50 border-amber-200 dark:text-amber-300 dark:bg-amber-500/10 dark:border-amber-500/30',
    collected:
        'text-emerald-700 bg-emerald-50 border-emerald-200 dark:text-emerald-300 dark:bg-emerald-500/10 dark:border-emerald-500/30',
};

const STATUS_UNSET =
    'text-gray-400 bg-white border-black/8 dark:text-gray-500 dark:bg-zinc-900 dark:border-white/8';

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface Filters {
    search: string | null;
    account: string | null;
    type: 'campaign' | 'ad_set' | null;
    start_from: string | null;
    start_to: string | null;
    per_page: number;
}

interface Props {
    workspace: Workspace;
    items: Paginator<TestingItem>;
    maxDay: number;
    accounts: { id: string; name: string }[];
    filters: Filters;
}

const TYPE_LABEL: Record<TestingItem['item_type'], string> = {
    campaign: 'Campaign',
    ad_set: 'Ad Set',
};

const money = (value: number) =>
    value.toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });

const roasText = (roas: number | null) => (roas === null ? '—' : `${roas}x`);

/**
 * The three ROAS bands the tracker judges a day by: under 3x is losing, 3x–6x
 * is watchable, 6x and up is working. Text colour and cell wash both read from
 * here, so a 4x day cannot end up amber behind green text.
 */
const roasBand = (roas: number | null): 'none' | 'bad' | 'mid' | 'good' => {
    if (roas === null) return 'none';
    if (roas < 3) return 'bad';
    if (roas < 6) return 'mid';
    return 'good';
};

const roasTone = (roas: number | null) =>
    ({
        none: 'text-gray-300 dark:text-gray-600',
        bad: 'text-red-600 dark:text-red-400',
        mid: 'text-amber-600 dark:text-amber-400',
        good: 'text-emerald-600 dark:text-emerald-400',
    })[roasBand(roas)];

/**
 * The day's verdict, washed across all three of its cells. Kept to the lightest
 * tint on the scale — it has to sit behind the numbers without fighting them —
 * and a day with no spend has no verdict to give, so it stays uncoloured.
 */
const roasBackground = (roas: number | null) =>
    ({
        none: '',
        bad: 'bg-red-50 dark:bg-red-500/10',
        mid: 'bg-amber-50 dark:bg-amber-500/10',
        good: 'bg-emerald-50 dark:bg-emerald-500/10',
    })[roasBand(roas)];

/**
 * Ad account and product on one line, split by a pipe. Either half can be
 * unknown — an account that never resolved, or a product the page/shop link
 * cannot reach — so the pipe only appears when there are two things to divide.
 */
const accountLine = (item: TestingItem) => {
    const account =
        item.account_name ??
        (item.account_id ? `Account ${item.account_id}` : 'No ad account');

    return item.product ? `${account} | ${item.product}` : account;
};

/**
 * One look for every control in the filter bar, minus the width — each control
 * sets its own. Putting a width here meant fighting it at every call site, and
 * a `w-auto` after a `w-full` does not win: Tailwind resolves that by stylesheet
 * order, not by where the class sits in the string.
 */
const filterInput =
    'h-9 rounded-[10px] border border-black/8 bg-stone-50 text-[12px] text-gray-700 outline-none transition-all placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200 dark:placeholder:text-gray-600';

const cell = 'px-2.5 py-2.5 text-right font-mono text-[11px] whitespace-nowrap';

/**
 * Rules for the pinned header and first column, drawn as inset shadows rather
 * than borders. Under border-collapse a border belongs to the table, not the
 * cell, so it can stop painting once a sticky cell detaches and scrolls — a
 * shadow is the cell's own and always shows.
 */
const stickyBottom =
    'shadow-[inset_0_-1px_0_rgb(0_0_0/0.10)] dark:shadow-[inset_0_-1px_0_rgb(255_255_255/0.12)]';
const stickyRight =
    'shadow-[inset_-1px_0_0_rgb(0_0_0/0.08)] dark:shadow-[inset_-1px_0_0_rgb(255_255_255/0.10)]';
const stickyCorner =
    'shadow-[inset_-1px_0_0_rgb(0_0_0/0.08),inset_0_-1px_0_rgb(0_0_0/0.10)] dark:shadow-[inset_-1px_0_0_rgb(255_255_255/0.10),inset_0_-1px_0_rgb(255_255_255/0.12)]';
/**
 * All rules are 1px and soft. The day boundary is a shade stronger than the one
 * between the three figures inside it, and that small difference is all the
 * grouping needs — anything heavier made the table tiring to read.
 */
const groupStart = 'border-l border-l-black/10 dark:border-l-white/10';
const groupEnd = 'border-r border-r-black/10 dark:border-r-white/10';
const divider = 'border-l border-l-black/6 dark:border-l-white/6';

/**
 * The Sales / Spend / ROAS trio, used for both a day and the totals. Returns
 * three cells rather than one so the columns line up across every row.
 */
function MetricCells({
    metrics,
    emphasise = false,
    tint = false,
}: {
    metrics?: Metrics;
    emphasise?: boolean;
    /** Wash the day's ROAS verdict across all three cells. */
    tint?: boolean;
}) {
    const tone = emphasise
        ? 'font-medium text-gray-800 dark:text-gray-100'
        : 'text-gray-600 dark:text-gray-300';
    const empty = <span className="text-gray-200 dark:text-gray-700">—</span>;
    const bg = tint && metrics ? roasBackground(metrics.roas) : '';

    return (
        <>
            <td className={`${cell} ${groupStart} ${bg} ${tone}`}>
                {metrics ? money(metrics.sales) : empty}
            </td>
            <td className={`${cell} ${divider} ${bg} ${tone}`}>
                {metrics ? money(metrics.ad_spent) : empty}
            </td>
            <td
                className={`${cell} ${divider} ${groupEnd} ${bg} font-medium ${metrics ? roasTone(metrics.roas) : ''}`}
            >
                {metrics ? roasText(metrics.roas) : empty}
            </td>
        </>
    );
}

/**
 * Sales / Spend / ROAS sub-headings under a day (or under Total). A step down
 * from the day headings above — smaller and lighter — but still meant to be
 * read, so the hierarchy comes from the gap between them rather than from
 * pushing these to the edge of legibility.
 */
function MetricHeadings() {
    const heading = `${cell} ${stickyBottom} sticky top-9 z-30 bg-stone-50 py-1.5 font-medium text-[10px] text-gray-400 dark:bg-zinc-800 dark:text-gray-500`;

    return (
        <>
            <th className={`${heading} ${groupStart}`}>Sales</th>
            <th className={`${heading} ${divider}`}>Spend</th>
            <th className={`${heading} ${divider} ${groupEnd}`}>ROAS</th>
        </>
    );
}

/**
 * A status picker in a table cell. Deliberately a native <select> rather than
 * the Radix one used elsewhere: this table scrolls horizontally with a pinned
 * first column, and a portalled popover has to be re-anchored on every scroll
 * to stay put. The native control is positioned by the browser and cannot drift.
 */
function StatusSelect<T extends InternDecision | FinanceStatus>({
    value,
    options,
    onChange,
    disabled,
    placeholder,
}: {
    value: T | null;
    options: { value: T; label: string }[];
    onChange: (value: T | null) => void;
    disabled: boolean;
    placeholder: string;
}) {
    return (
        <select
            value={value ?? ''}
            disabled={disabled}
            onChange={(e) => onChange((e.target.value || null) as T | null)}
            className={`w-full cursor-pointer rounded-md border px-2 py-1.5 text-[11px] font-medium transition-colors outline-none focus:ring-2 focus:ring-emerald-500/20 disabled:cursor-wait disabled:opacity-50 ${
                value ? STATUS_TONE[value] : STATUS_UNSET
            }`}
        >
            <option value="">{placeholder}</option>
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

export default function NewCreativesTracker({
    workspace,
    items,
    maxDay,
    accounts,
    filters,
}: Props) {
    const [pickerOpen, setPickerOpen] = useState(false);
    const [pending, setPending] = useState<number | null>(null);
    const [saving, setSaving] = useState<number | null>(null);

    const rows = items.data;
    const dayColumns = Array.from({ length: maxDay }, (_, i) => i + 1);
    const baseUrl = `/workspaces/${workspace.slug}/sales-marketing/new-creatives-tracker`;

    // The search box types faster than the server can answer, so it holds its
    // own value and only queries once typing settles.
    const {
        value: search,
        setValue: setSearch,
        debounced,
    } = useDebouncedState(filters.search ?? '');
    const firstRender = useRef(true);

    /**
     * Every filter lives in the query string — that is what makes a refresh, a
     * back button, or a pasted link land on the same view. `replace` keeps the
     * history from filling up with one entry per keystroke.
     */
    const applyFilters = (changes: Partial<Record<string, string | null>>) => {
        const next: Record<string, string> = {};

        const merged: Record<string, string | number | null | undefined> = {
            search: filters.search,
            account: filters.account,
            type: filters.type,
            start_from: filters.start_from,
            start_to: filters.start_to,
            // Kept across filter changes so the chosen page size sticks; the
            // default is left out of the URL rather than spelled out.
            per_page: filters.per_page === 25 ? null : filters.per_page,
            ...changes,
        };

        Object.entries(merged).forEach(([key, value]) => {
            if (value) next[key] = String(value);
        });

        router.get(baseUrl, next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Fire when the typed search settles — but not on the first render, which
    // would re-request the page the server just delivered.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        if ((filters.search ?? '') === debounced.trim()) return;

        applyFilters({ search: debounced.trim() || null });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debounced]);

    const hasFilters = Boolean(
        filters.search ||
        filters.account ||
        filters.type ||
        filters.start_from ||
        filters.start_to,
    );

    const clearFilters = () => {
        setSearch('');
        router.get(baseUrl, {}, { preserveScroll: true, replace: true });
    };

    /** Paging keeps the filters — applyFilters rebuilds them into the URL. */
    const goToPage = (page: number) => applyFilters({ page: String(page) });

    const togglePause = (item: TestingItem) => {
        setPending(item.id);

        router.patch(
            `/workspaces/${workspace.slug}/sales-marketing/new-creatives-tracker/items/${item.id}/pause`,
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(
                        item.is_paused
                            ? 'Tracking resumed.'
                            : 'Tracking paused.',
                    ),
                onError: () => toast.error('Could not change that item.'),
                onFinish: () => setPending(null),
            },
        );
    };

    const updateStatus = (
        item: TestingItem,
        field: 'intern_decision' | 'finance_status',
        value: string | null,
    ) => {
        setSaving(item.id);

        router.patch(
            `/workspaces/${workspace.slug}/sales-marketing/new-creatives-tracker/items/${item.id}`,
            { [field]: value },
            {
                preserveScroll: true,
                onError: () => toast.error('Could not save that.'),
                onFinish: () => setSaving(null),
            },
        );
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - New Creatives Tracker`} />
            <div className="p-4 md:p-6">
                <PageHeader
                    title="New Creatives Tracker"
                    description={
                        items.total
                            ? `${items.total} ${items.total === 1 ? 'item' : 'items'}${hasFilters ? ' matched' : ' under test'} · ${maxDay}-day window from each item's own start date`
                            : 'Track newly launched creatives'
                    }
                    stackActionsOnMobile
                >
                    <Button size="sm" onClick={() => setPickerOpen(true)}>
                        <Plus className="size-3.5" />
                        Add Testing Item
                    </Button>
                </PageHeader>

                {/* One line of controls at their own widths — no panel, no
                    stretching to the page edge, no wrapping. */}
                <div className="mb-4 flex items-center gap-2 overflow-x-auto pb-1">
                    <div className="relative w-[240px] shrink-0">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-gray-300 dark:text-gray-600" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search campaign or ad set…"
                            className={`${filterInput} w-full pr-3 pl-9`}
                        />
                    </div>

                    <select
                        value={filters.account ?? ''}
                        onChange={(e) =>
                            applyFilters({ account: e.target.value || null })
                        }
                        className={`${filterInput} w-[170px] shrink-0 cursor-pointer px-2.5`}
                    >
                        <option value="">All ad accounts</option>
                        {accounts.map((account) => (
                            <option key={account.id} value={account.id}>
                                {account.name}
                            </option>
                        ))}
                    </select>

                    <select
                        value={filters.type ?? ''}
                        onChange={(e) =>
                            applyFilters({ type: e.target.value || null })
                        }
                        className={`${filterInput} w-[130px] shrink-0 cursor-pointer px-2.5`}
                    >
                        <option value="">All types</option>
                        <option value="campaign">Campaigns</option>
                        <option value="ad_set">Ad sets</option>
                    </select>

                    {/* One range picker, not two date fields — start_from and
                        start_to are the two ends of a single window. */}
                    <div className="shrink-0">
                        <DatePicker
                            id="tracker-start-range"
                            mode="range"
                            placeholder="Start date"
                            defaultDate={
                                (filters.start_from && filters.start_to
                                    ? [filters.start_from, filters.start_to]
                                    : filters.start_from
                                      ? [filters.start_from]
                                      : []) as never as DateOption
                            }
                            onChange={(dates) => {
                                if (dates.length === 2) {
                                    applyFilters({
                                        start_from: moment(dates[0]).format(
                                            'YYYY-MM-DD',
                                        ),
                                        start_to: moment(dates[1]).format(
                                            'YYYY-MM-DD',
                                        ),
                                    });
                                } else if (dates.length === 0) {
                                    applyFilters({
                                        start_from: null,
                                        start_to: null,
                                    });
                                }
                                // A single date means the range is still
                                // half-picked — wait for the second click.
                            }}
                        />
                    </div>

                    {hasFilters && (
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="inline-flex h-9 shrink-0 items-center gap-1 rounded-[10px] px-2.5 text-[12px] text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        >
                            <X className="size-3.5" />
                            Clear
                        </button>
                    )}
                </div>

                {rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-black/8 py-16 text-center dark:border-white/8">
                        <p className="text-[13px] text-gray-500 dark:text-gray-400">
                            {hasFilters
                                ? 'Nothing matches those filters.'
                                : 'Nothing under test yet.'}
                        </p>
                        <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">
                            {hasFilters
                                ? 'Try widening the search or clearing a filter.'
                                : 'Add an active campaign or ad set to start tracking it.'}
                        </p>
                    </div>
                ) : (
                    // Three columns per day plus the totals is far wider than a
                    // page, so the grid scrolls sideways with the item column
                    // pinned to keep every row identifiable.
                    <div className="grid-scrollbar max-h-[70vh] overflow-auto rounded-xl border border-black/6 dark:border-white/6">
                        <table className="w-full border-collapse text-left">
                            <thead className="bg-stone-50 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:bg-zinc-800/50 dark:text-gray-500">
                                <tr>
                                    <th
                                        rowSpan={2}
                                        className={`${stickyCorner} sticky top-0 left-0 z-40 min-w-[280px] bg-stone-50 px-4 py-2.5 dark:bg-zinc-800/50`}
                                    >
                                        Item
                                    </th>
                                    {dayColumns.map((day) => (
                                        <th
                                            key={day}
                                            colSpan={3}
                                            className={`${groupStart} ${groupEnd} sticky top-0 z-30 h-9 border-b border-b-black/8 bg-stone-50 px-2.5 py-2 text-center text-[11px] font-semibold text-gray-600 dark:border-b-white/8 dark:bg-zinc-800 dark:text-gray-200`}
                                        >
                                            Day {day}
                                        </th>
                                    ))}
                                    {/* Totals sit after the days, so the row
                                        reads left to right as the test runs and
                                        finishes on the summary. */}
                                    <th
                                        colSpan={3}
                                        className={`${groupStart} ${groupEnd} sticky top-0 z-30 h-9 border-b border-b-black/8 bg-stone-100 px-2.5 py-2 text-center text-[11px] font-semibold text-gray-700 dark:border-b-white/8 dark:bg-zinc-800 dark:text-gray-100`}
                                    >
                                        Total
                                    </th>
                                    {/* The two calls made about a test, after
                                        the numbers they are based on. Both span
                                        the header's two rows — they have no
                                        Sales/Spend/ROAS breakdown under them. */}
                                    <th
                                        rowSpan={2}
                                        className={`${groupStart} ${stickyBottom} sticky top-0 z-30 bg-stone-50 px-3 py-2 text-center text-[11px] font-semibold text-gray-600 dark:bg-zinc-800 dark:text-gray-200`}
                                    >
                                        Intern&apos;s Final Decision
                                    </th>
                                    <th
                                        rowSpan={2}
                                        className={`${groupStart} ${stickyBottom} sticky top-0 z-30 bg-stone-50 px-3 py-2 text-center text-[11px] font-semibold text-gray-600 dark:bg-zinc-800 dark:text-gray-200`}
                                    >
                                        Finance Status
                                    </th>
                                </tr>
                                <tr className="border-b border-b-black/8 dark:border-b-white/8">
                                    {dayColumns.map((day) => (
                                        <Fragment key={day}>
                                            <MetricHeadings />
                                        </Fragment>
                                    ))}
                                    <MetricHeadings />
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="border-b border-b-black/5 last:border-0 dark:border-b-white/5"
                                    >
                                        <td
                                            className={`${stickyRight} sticky left-0 z-20 bg-white px-4 py-2.5 dark:bg-zinc-900`}
                                        >
                                            <div className="flex items-start gap-2.5">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        togglePause(item)
                                                    }
                                                    disabled={
                                                        pending === item.id
                                                    }
                                                    title={
                                                        item.is_paused
                                                            ? 'Resume tracking'
                                                            : 'Pause tracking'
                                                    }
                                                    aria-label={
                                                        item.is_paused
                                                            ? `Resume tracking ${item.name ?? item.item_id}`
                                                            : `Pause tracking ${item.name ?? item.item_id}`
                                                    }
                                                    className={`mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md border transition-colors disabled:opacity-40 ${
                                                        item.is_paused
                                                            ? 'border-amber-300 bg-amber-50 text-amber-600 hover:bg-amber-100 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-400'
                                                            : 'border-black/8 text-gray-400 hover:bg-stone-50 hover:text-gray-600 dark:border-white/8 dark:text-gray-500 dark:hover:bg-zinc-800 dark:hover:text-gray-300'
                                                    }`}
                                                >
                                                    {item.is_paused ? (
                                                        <Play className="size-3" />
                                                    ) : (
                                                        <Pause className="size-3" />
                                                    )}
                                                </button>

                                                <div className="min-w-0">
                                                    <span className="block truncate text-[13px] text-gray-800 dark:text-gray-100">
                                                        {/* A campaign that has since
                                                            synced away leaves the row
                                                            with no name — fall back to
                                                            the id so it stays
                                                            identifiable. */}
                                                        {item.name ??
                                                            item.item_id}
                                                    </span>
                                                    <span className="block truncate text-[11px] text-gray-400 dark:text-gray-500">
                                                        {
                                                            TYPE_LABEL[
                                                                item.item_type
                                                            ]
                                                        }
                                                        {item.start_date
                                                            ? ` · started ${item.start_date}`
                                                            : ' · no start date'}
                                                        {item.is_paused
                                                            ? ' · paused'
                                                            : ''}
                                                    </span>
                                                    {/* Ad account | product —
                                                        their own line, since the
                                                        meta line above already
                                                        carries three facts. */}
                                                    <span className="block truncate font-mono text-[10px] text-gray-300 dark:text-gray-600">
                                                        {accountLine(item)}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        {dayColumns.map((day) => (
                                            <MetricCells
                                                key={day}
                                                metrics={
                                                    item.days[String(day)] as
                                                        | DayMetrics
                                                        | undefined
                                                }
                                                tint
                                            />
                                        ))}

                                        <MetricCells
                                            metrics={item.total}
                                            emphasise
                                        />

                                        <td
                                            className={`${groupStart} min-w-[190px] px-3 py-2`}
                                        >
                                            <StatusSelect
                                                value={item.intern_decision}
                                                options={DECISION_OPTIONS}
                                                disabled={saving === item.id}
                                                placeholder="Not decided"
                                                onChange={(value) =>
                                                    updateStatus(
                                                        item,
                                                        'intern_decision',
                                                        value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td
                                            className={`${groupStart} min-w-[150px] px-3 py-2`}
                                        >
                                            <StatusSelect
                                                value={item.finance_status}
                                                options={FINANCE_OPTIONS}
                                                disabled={saving === item.id}
                                                placeholder="Not set"
                                                onChange={(value) =>
                                                    updateStatus(
                                                        item,
                                                        'finance_status',
                                                        value,
                                                    )
                                                }
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* The app's standard table footer — Rows selector, entry
                    count, then the shared Pagination control. Same markup the
                    DataTable renders elsewhere, so this table's footer is not a
                    one-off. */}
                {rows.length > 0 && (
                    <div className="mt-3 px-4 py-3">
                        <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex items-center gap-2">
                                    <span className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                        Rows
                                    </span>
                                    <Select
                                        value={String(items.per_page)}
                                        onValueChange={(value) =>
                                            applyFilters({
                                                per_page: value,
                                                page: null,
                                            })
                                        }
                                    >
                                        <SelectTrigger className="h-7 w-[72px] rounded-lg border border-black/6 bg-stone-50 px-2.5 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent className="min-w-[72px]">
                                            {[10, 25, 50, 100].map((n) => (
                                                <SelectItem
                                                    key={n}
                                                    value={String(n)}
                                                    className="font-mono! text-[11px]!"
                                                >
                                                    {n}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="h-4 w-px bg-black/6 dark:bg-white/6" />
                                <p className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                    Showing {items.from ?? 0} to {items.to ?? 0}{' '}
                                    of {items.total.toLocaleString()} entries
                                </p>
                            </div>

                            <Pagination
                                currentPage={items.current_page}
                                totalPages={items.last_page}
                                onPageChange={goToPage}
                            />
                        </div>
                    </div>
                )}

                {rows.length > 0 && (
                    <p className="border-t border-black/6 px-4 pt-3 text-[11px] text-gray-400 dark:border-white/6 dark:text-gray-500">
                        Each day carries its own sales, ad spend and ROAS, with
                        the period totals at the end of the row. A day is shaded
                        by its ROAS — red under 3x, amber 3x–6x, green from 6x.
                        Numbers come from Meta insights and refresh hourly.
                    </p>
                )}
            </div>

            <AddTestingItemDialog
                workspaceSlug={workspace.slug}
                open={pickerOpen}
                onOpenChange={setPickerOpen}
            />
        </AppLayout>
    );
}
