import {
    DashboardTab,
    DashboardTabNav,
} from '@/components/sales-marketing/dashboard-tabs';
import {
    ColumnOption,
    ColumnsDropdown,
    useColumnVisibility,
} from '@/components/ui/columns-dropdown';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import flatpickr from 'flatpickr';
import moment from 'moment';
import { Fragment, useMemo, useState } from 'react';
import PageRoasFilters, {
    FilterOption,
    PageRoasFilterValue,
} from './page-roas-filters';
import DateOption = flatpickr.Options.DateOption;

interface Metrics {
    orders: number;
    sales: number;
    ad_spent: number;
    ad_sales: number;
    delivered_amount: number;
    returning_amount: number;
    roas: number | null;
    ad_roas: number | null;
    rts_rate: number | null;
    ad_cpp: number | null;
    cpp: number | null;
}

type MetricKey = keyof Metrics;

interface PageSeries {
    page_id: number | string;
    name: string;
    days: Record<string, Metrics>;
    total: Metrics;
    average: Metrics;
}

/** The all-pages roll-up: same shape as a page series, minus the identity. */
type OverallSeries = Pick<PageSeries, 'days' | 'total' | 'average'>;

interface Props {
    workspace: Workspace;
    dates: string[];
    pages: PageSeries[];
    overall: OverallSeries | null;
    filterOptions: {
        pages: FilterOption[];
        shops: FilterOption[];
        users: FilterOption[];
    };
    query: {
        start: string;
        end: string;
        pages: string[];
        shops: string[];
        users: string[];
    };
    // S&M dashboard tabs — this page is the "Page ROAS Tracker" tab.
    tabs?: DashboardTab[];
    activeTab?: string;
}

/* ── Formatting ──────────────────────────────────────────────────────────── */

const EMPTY = '—';

/**
 * Amounts show whole units. At a page-per-day grain the centavos are noise —
 * repeated across ten columns and a month of rows they cost far more legibility
 * than they carry information — so the exact figure moves to the cell's title.
 */
const whole = (n: number) => Math.round(n).toLocaleString();
const exact = (n: number) =>
    n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const format: Record<string, (n: number | null) => string> = {
    int: (n) => (n === null ? EMPTY : n.toLocaleString()),
    amount: (n) => (n === null ? EMPTY : whole(n)),
    ratio: (n) => (n === null ? EMPTY : n.toFixed(2)),
    percent: (n) => (n === null ? EMPTY : `${n.toFixed(1)}%`),
};

/* ── Tone ────────────────────────────────────────────────────────────────── */

type Tone = 'good' | 'warn' | 'bad' | 'muted';

/**
 * Tone is carried by the digits alone — no cell fills. With three toned columns
 * repeated across every page group, a wash on each one turned the grid into a
 * quilt and buried the numbers it was meant to rank.
 */
const TONE: Record<Tone, string> = {
    good: 'text-emerald-600 dark:text-emerald-400',
    warn: 'text-amber-600 dark:text-amber-400',
    bad: 'text-rose-600 dark:text-rose-400',
    muted: 'text-gray-300 dark:text-gray-600',
};

/** 3.00 is the bar: at or above it is a winner, 2–3 is short of it, under 2 is the problem. */
const roasTone = (n: number | null): Tone =>
    n === null || n === 0 ? 'muted' : n >= 3 ? 'good' : n >= 2 ? 'warn' : 'bad';

/** RTS is inverted — less of it is better. Pitched at the 25–35% band COD runs at. */
const rtsTone = (n: number | null): Tone =>
    n === null ? 'muted' : n <= 25 ? 'good' : n <= 35 ? 'warn' : 'bad';

/* ── Columns ─────────────────────────────────────────────────────────────── */

interface MetricColumn extends ColumnOption {
    id: MetricKey;
    /** Header text — shorter than the label the dropdown uses. */
    head: string;
    format: keyof typeof format;
    tone?: (n: number | null) => Tone;
}

/**
 * The four the tracker has always shown stay on by default; everything the
 * daily builder gained later is opt-in, so the table does not get wider for
 * people who never asked for it.
 */
const COLUMNS: MetricColumn[] = [
    {
        id: 'orders',
        label: 'Orders',
        head: 'Orders',
        group: 'Core',
        format: 'int',
    },
    {
        id: 'sales',
        label: 'Sales',
        head: 'Sales',
        group: 'Core',
        format: 'amount',
    },
    {
        id: 'ad_spent',
        label: 'Ad Spent',
        head: 'Ad Spent',
        group: 'Core',
        format: 'amount',
    },
    {
        id: 'roas',
        label: 'ROAS',
        head: 'ROAS',
        group: 'Core',
        format: 'ratio',
        tone: roasTone,
    },

    {
        id: 'ad_sales',
        label: 'Ad Sales',
        head: 'Ad Sales',
        group: 'Meta Ads',
        format: 'amount',
        defaultVisible: false,
    },
    {
        id: 'ad_roas',
        label: 'Ad ROAS',
        head: 'Ad ROAS',
        group: 'Meta Ads',
        format: 'ratio',
        tone: roasTone,
        defaultVisible: false,
    },
    {
        id: 'ad_cpp',
        label: 'Ad CPP (spend / Meta purchases)',
        head: 'Ad CPP',
        group: 'Meta Ads',
        format: 'amount',
        defaultVisible: false,
    },
    {
        id: 'cpp',
        label: 'CPP (spend / orders)',
        head: 'CPP',
        group: 'Meta Ads',
        format: 'amount',
        defaultVisible: false,
    },

    {
        id: 'delivered_amount',
        label: 'Delivered',
        head: 'Delivered',
        group: 'Delivery',
        format: 'amount',
        defaultVisible: false,
    },
    {
        id: 'returning_amount',
        label: 'Returning',
        head: 'Returning',
        group: 'Delivery',
        format: 'amount',
        defaultVisible: false,
    },
    {
        id: 'rts_rate',
        label: 'RTS Rate',
        head: 'RTS',
        group: 'Delivery',
        format: 'percent',
        tone: rtsTone,
        defaultVisible: false,
    },
];

/**
 * The all-pages group toggles like a column but is not one — it is a whole extra
 * page group, so it rides in the same menu while being filtered out of `shown`.
 */
const ROLLUP_ID = '__rollup';

const COLUMN_OPTIONS: ColumnOption[] = [
    ...COLUMNS,
    { id: ROLLUP_ID, label: 'All-pages total', group: 'Summary' },
];

const COLUMNS_STORAGE_KEY = 'page-roas-tracker-cols';

/** Everything one cell needs: the figure, its tone, and whether it is worth ink. */
function readCell(m: Metrics, col: MetricColumn) {
    const raw = (m?.[col.id] ?? null) as number | null;

    return {
        text: format[col.format](raw),
        title: col.format === 'amount' && raw !== null ? exact(raw) : undefined,
        tone: col.tone?.(raw),
        // A zero carries no signal in a grid this dense — keep it, but let the
        // eye slide over it so the real figures are what stand out.
        blank: raw === null || raw === 0,
    };
}

/* ── Page ────────────────────────────────────────────────────────────────── */

export default function PageRoasTrackerIndex({
    workspace,
    dates,
    pages,
    overall,
    filterOptions,
    query,
    tabs,
    activeTab,
}: Props) {
    // The Page ROAS Tracker now lives as a tab under the S&M dashboard, so its
    // filter visits target that URL.
    const baseUrl = `/workspaces/${workspace.slug}/sales-marketing/dashboard/page-roas-tracker`;
    const hasTabs = !!tabs && tabs.length > 0;

    const [filters, setFilters] = useState<PageRoasFilterValue>({
        pages: query.pages ?? [],
        shops: query.shops ?? [],
        users: query.users ?? [],
    });

    const { visibility, setVisibility } = useColumnVisibility(
        COLUMN_OPTIONS,
        COLUMNS_STORAGE_KEY,
    );

    const shown = useMemo(
        () => COLUMNS.filter((c) => visibility[c.id] !== false),
        [visibility],
    );

    const rollUp = visibility[ROLLUP_ID] !== false && overall !== null;

    const visit = (next: PageRoasFilterValue, start: string, end: string) => {
        router.get(
            baseUrl,
            { start, end, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const onApply = (next: PageRoasFilterValue) => {
        setFilters(next);
        visit(next, query.start, query.end);
    };

    /* Cell rhythm. Every figure is right-aligned on the same rail, tabular and
       slashed-zero, so a column of numbers lines up digit for digit. */
    const cell = 'h-8 whitespace-nowrap px-2.5 text-right';
    const rowLine = 'border-b border-black/4 dark:border-white/4';
    const groupStart = 'border-l-2 border-l-black/10 dark:border-l-white/12';
    // The roll-up reads as a summary rather than a twentieth page.
    const rollUpCell = 'bg-brand-500/5 dark:bg-brand-500/8';
    const stickyLeft =
        'sticky left-0 border-r border-black/8 dark:border-white/8';

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Page ROAS Tracker`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                {hasTabs && <DashboardTabNav tabs={tabs!} active={activeTab} />}

                {/* Title on the left, columns + filters + date range on the right. */}
                <div className="mt-5 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="my-0! text-[22px]! font-semibold tracking-tight text-gray-900 dark:text-gray-50">
                            Page ROAS Tracker
                        </h1>
                        <p className="mt-1 font-mono text-[11px] tracking-wide text-gray-400 dark:text-gray-500">
                            {moment(query.start).format('MMM D')} –{' '}
                            {moment(query.end).format('MMM D, YYYY')}
                            <span className="mx-2 text-gray-300 dark:text-gray-600">
                                /
                            </span>
                            {dates.length}d
                            <span className="mx-2 text-gray-300 dark:text-gray-600">
                                /
                            </span>
                            {pages.length} page{pages.length === 1 ? '' : 's'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <ColumnsDropdown
                            options={COLUMN_OPTIONS}
                            visibility={visibility}
                            onChange={setVisibility}
                        />
                        <PageRoasFilters
                            value={filters}
                            pageOptions={filterOptions.pages}
                            shopOptions={filterOptions.shops}
                            userOptions={filterOptions.users}
                            onApply={onApply}
                        />
                        <DatePicker
                            id={`roas-range-${query.start}-${query.end}`}
                            key={`${query.start}-${query.end}`}
                            mode="range"
                            placeholder="Pick a date range"
                            defaultDate={
                                [query.start, query.end] as never as DateOption
                            }
                            onChange={(picked) => {
                                if (picked.length === 2) {
                                    visit(
                                        filters,
                                        moment(picked[0]).format('YYYY-MM-DD'),
                                        moment(picked[1]).format('YYYY-MM-DD'),
                                    );
                                }
                            }}
                        />
                    </div>
                </div>

                {pages.length === 0 ? (
                    <div className="mt-4 rounded-[14px] border border-black/6 bg-white py-20 text-center dark:border-white/6 dark:bg-zinc-900">
                        <p className="text-[13px] font-medium text-gray-500 dark:text-gray-400">
                            No page performance for this range yet.
                        </p>
                        <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">
                            Widen the date range, or clear a filter.
                        </p>
                    </div>
                ) : shown.length === 0 ? (
                    <div className="mt-4 rounded-[14px] border border-black/6 bg-white py-20 text-center dark:border-white/6 dark:bg-zinc-900">
                        <p className="text-[13px] font-medium text-gray-500 dark:text-gray-400">
                            Every column is hidden.
                        </p>
                        <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">
                            Pick at least one from the Columns menu.
                        </p>
                    </div>
                ) : (
                    <div className="mt-4 overflow-hidden rounded-[14px] border border-black/8 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.03)] dark:border-white/8 dark:bg-zinc-900 dark:shadow-none">
                        <div className="max-h-[calc(100vh-16rem)] overflow-auto overscroll-contain">
                            <table className="border-separate border-spacing-0 font-mono text-[11px] slashed-zero tabular-nums">
                                <thead>
                                    {/* Page names span their visible metric columns. */}
                                    <tr>
                                        <th
                                            rowSpan={2}
                                            className={cn(
                                                cell,
                                                stickyLeft,
                                                'sticky top-0 z-40 border-b border-black/10 bg-stone-100 text-left font-mono text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:border-white/10 dark:bg-zinc-800 dark:text-gray-400',
                                            )}
                                        >
                                            Date
                                        </th>
                                        {pages.map((page) => (
                                            <th
                                                key={page.page_id}
                                                colSpan={shown.length}
                                                className={cn(
                                                    cell,
                                                    groupStart,
                                                    'sticky top-0 z-30 max-w-[22rem] truncate bg-stone-100 text-left text-[12px] font-semibold text-gray-800 dark:bg-zinc-800 dark:text-gray-100',
                                                )}
                                                title={page.name}
                                            >
                                                <span className="flex items-center gap-2">
                                                    <span className="h-3 w-[3px] shrink-0 rounded-full bg-brand-500" />
                                                    <span className="truncate font-sans text-[12px]">
                                                        {page.name}
                                                    </span>
                                                </span>
                                            </th>
                                        ))}
                                        {rollUp && (
                                            <th
                                                colSpan={shown.length}
                                                className={cn(
                                                    cell,
                                                    groupStart,
                                                    rollUpCell,
                                                    'sticky top-0 z-30 text-left text-[12px] font-semibold text-gray-900 dark:text-gray-50',
                                                )}
                                            >
                                                <span className="font-sans text-[12px]">
                                                    All Pages
                                                </span>
                                            </th>
                                        )}
                                    </tr>
                                    <tr>
                                        {pages.map((page) => (
                                            <Fragment key={page.page_id}>
                                                {shown.map((col, i) => (
                                                    <th
                                                        key={col.id}
                                                        title={col.label}
                                                        className={cn(
                                                            cell,
                                                            i === 0 &&
                                                                groupStart,
                                                            'sticky top-8 z-30 border-b border-black/10 bg-white font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:border-white/10 dark:bg-zinc-900 dark:text-gray-500',
                                                        )}
                                                    >
                                                        {col.head}
                                                    </th>
                                                ))}
                                            </Fragment>
                                        ))}
                                        {rollUp &&
                                            shown.map((col, i) => (
                                                <th
                                                    key={col.id}
                                                    title={col.label}
                                                    className={cn(
                                                        cell,
                                                        i === 0 && groupStart,
                                                        rollUpCell,
                                                        'sticky top-8 z-30 border-b border-black/10 font-mono text-[10px] font-medium tracking-wider text-gray-500 uppercase dark:border-white/10 dark:text-gray-400',
                                                    )}
                                                >
                                                    {col.head}
                                                </th>
                                            ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {dates.map((date) => {
                                        // Weekends band the grid, which doubles as
                                        // the zebra a table this wide needs.
                                        const weekend = [0, 6].includes(
                                            moment(date).day(),
                                        );

                                        return (
                                            <tr
                                                key={date}
                                                className="group/row"
                                            >
                                                <td
                                                    className={cn(
                                                        cell,
                                                        rowLine,
                                                        stickyLeft,
                                                        'z-20 text-left group-hover/row:bg-brand-500/6',
                                                        weekend
                                                            ? 'bg-stone-50 text-gray-400 dark:bg-white/3 dark:text-gray-500'
                                                            : 'bg-white text-gray-600 dark:bg-zinc-900 dark:text-gray-400',
                                                    )}
                                                    title={date}
                                                >
                                                    {moment(date).format(
                                                        'ddd DD MMM',
                                                    )}
                                                </td>
                                                {pages.map((page) => (
                                                    <Fragment
                                                        key={page.page_id}
                                                    >
                                                        {shown.map((col, i) => {
                                                            const c = readCell(
                                                                page.days[date],
                                                                col,
                                                            );
                                                            return (
                                                                <td
                                                                    key={col.id}
                                                                    title={
                                                                        c.title
                                                                    }
                                                                    className={cn(
                                                                        cell,
                                                                        rowLine,
                                                                        i ===
                                                                            0 &&
                                                                            groupStart,
                                                                        'group-hover/row:bg-brand-500/6',
                                                                        weekend &&
                                                                            'bg-stone-50/70 dark:bg-white/2',
                                                                        c.tone
                                                                            ? TONE[
                                                                                  c
                                                                                      .tone
                                                                              ]
                                                                            : c.blank
                                                                              ? 'text-gray-300 dark:text-gray-600'
                                                                              : 'text-gray-700 dark:text-gray-200',
                                                                    )}
                                                                >
                                                                    {c.text}
                                                                </td>
                                                            );
                                                        })}
                                                    </Fragment>
                                                ))}
                                                {rollUp &&
                                                    shown.map((col, i) => {
                                                        const c = readCell(
                                                            overall!.days[date],
                                                            col,
                                                        );
                                                        return (
                                                            <td
                                                                key={col.id}
                                                                title={c.title}
                                                                className={cn(
                                                                    cell,
                                                                    rowLine,
                                                                    i === 0 &&
                                                                        groupStart,
                                                                    rollUpCell,
                                                                    'font-semibold group-hover/row:bg-brand-500/10',
                                                                    c.tone
                                                                        ? TONE[
                                                                              c
                                                                                  .tone
                                                                          ]
                                                                        : c.blank
                                                                          ? 'text-gray-400 dark:text-gray-600'
                                                                          : 'text-gray-900 dark:text-gray-100',
                                                                )}
                                                            >
                                                                {c.text}
                                                            </td>
                                                        );
                                                    })}
                                            </tr>
                                        );
                                    })}

                                    {/* Summary rows — sums for the amounts, blended
                                        for the ratios. Not pinned: a sticky bottom
                                        row sits under the horizontal scrollbar,
                                        which clipped the Average clean in half. */}
                                    {(
                                        [
                                            ['Total', 'total'],
                                            ['Average', 'average'],
                                        ] as const
                                    ).map(([label, key], rowIndex) => (
                                        <tr key={key}>
                                            <td
                                                className={cn(
                                                    cell,
                                                    stickyLeft,
                                                    'z-20 bg-stone-100 text-left font-mono text-[10px] font-semibold tracking-wider text-gray-600 uppercase dark:bg-zinc-800 dark:text-gray-300',
                                                    rowIndex === 0 &&
                                                        'border-t border-black/12 dark:border-white/12',
                                                )}
                                            >
                                                {label}
                                            </td>
                                            {pages.map((page) => (
                                                <Fragment key={page.page_id}>
                                                    {shown.map((col, i) => {
                                                        const c = readCell(
                                                            page[key],
                                                            col,
                                                        );
                                                        return (
                                                            <td
                                                                key={col.id}
                                                                title={c.title}
                                                                className={cn(
                                                                    cell,
                                                                    i === 0 &&
                                                                        groupStart,
                                                                    'bg-stone-100 font-semibold dark:bg-zinc-800',
                                                                    rowIndex ===
                                                                        0 &&
                                                                        'border-t border-black/12 dark:border-white/12',
                                                                    c.tone
                                                                        ? TONE[
                                                                              c
                                                                                  .tone
                                                                          ]
                                                                        : c.blank
                                                                          ? 'text-gray-400 dark:text-gray-600'
                                                                          : 'text-gray-900 dark:text-gray-100',
                                                                )}
                                                            >
                                                                {c.text}
                                                            </td>
                                                        );
                                                    })}
                                                </Fragment>
                                            ))}
                                            {rollUp &&
                                                shown.map((col, i) => {
                                                    const c = readCell(
                                                        overall![key],
                                                        col,
                                                    );
                                                    return (
                                                        <td
                                                            key={col.id}
                                                            title={c.title}
                                                            className={cn(
                                                                cell,
                                                                i === 0 &&
                                                                    groupStart,
                                                                'bg-brand-500/12 font-semibold dark:bg-brand-500/15',
                                                                rowIndex ===
                                                                    0 &&
                                                                    'border-t border-black/12 dark:border-white/12',
                                                                c.tone
                                                                    ? TONE[
                                                                          c.tone
                                                                      ]
                                                                    : c.blank
                                                                      ? 'text-gray-400 dark:text-gray-600'
                                                                      : 'text-gray-900 dark:text-gray-100',
                                                            )}
                                                        >
                                                            {c.text}
                                                        </td>
                                                    );
                                                })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
