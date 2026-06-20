import PageHeader from '@/components/common/PageHeader';
import DatePicker from '@/components/ui/date-picker';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { currencyFormatter, numberFormatter } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import { Search } from 'lucide-react';
import moment from 'moment';
import type { ReactNode } from 'react';
import { FormEvent, useMemo, useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

type Workspace = {
    id: number;
    name: string;
    slug: string;
};

type Option = {
    id: number;
    name: string;
};

type StatusOption = {
    value: string;
    label: string;
};

type Cell = {
    orders: number;
    sales: number;
    adSpent: number;
    roas: number;
    cpp: number;
};

type PageColumn = {
    id: string;
    name: string;
    shopName?: string | null;
    status?: string | null;
};

type TrackerRow = {
    date: string;
    cells: Record<string, Cell>;
};

type SummaryRow = {
    label: string;
    kind: 'total' | 'average';
    cells: Record<string, Cell>;
};

type Filters = {
    startDate: string;
    endDate: string;
    shopId?: string | number | null;
    pageId?: string | number | null;
    pageStatus: string;
    search: string;
    statuses: string[];
    showCpp: boolean;
};

type Props = {
    workspace: Workspace;
    filters: Filters;
    statusOptions: StatusOption[];
    shops: Option[];
    pages: Option[];
    pageColumns: PageColumn[];
    rows: TrackerRow[];
    summaryRows: SummaryRow[];
    showCpp: boolean;
    analysis: {
        totalOrders: number;
        totalSales: number;
        totalAdSpent: number;
        roas: number;
        cpp: number;
        activePageCount: number;
        bestPage: (Cell & { id: string; name: string }) | null;
        worstPage: (Cell & { id: string; name: string }) | null;
        topSpendPage: (Cell & { id: string; name: string }) | null;
    };
};

export default function PageRoasTracker({
    workspace,
    filters,
    statusOptions,
    shops,
    pages,
    pageColumns,
    rows,
    summaryRows,
    showCpp,
    analysis,
}: Props) {
    const [localFilters, setLocalFilters] = useState({
        shopId: filters.shopId ? String(filters.shopId) : 'all',
        pageId: filters.pageId ? String(filters.pageId) : 'all',
        pageStatus: filters.pageStatus || 'all',
        search: filters.search || '',
        statuses: filters.statuses.length ? filters.statuses : ['confirmed'],
        showCpp: filters.showCpp,
    });
    const [dateRange, setDateRange] = useState([
        filters.startDate,
        filters.endDate,
    ]);

    const metricColumns = useMemo(
        () => [
            'orders',
            'sales',
            'adSpent',
            'roas',
            ...(showCpp ? ['cpp'] : []),
        ],
        [showCpp],
    );

    const applyFilters = (event?: FormEvent) => {
        event?.preventDefault();

        router.get(
            `/workspaces/${workspace.slug}/rts/page-roas-tracker`,
            {
                start_date: dateRange[0],
                end_date: dateRange[1],
                shop_id:
                    localFilters.shopId === 'all'
                        ? undefined
                        : localFilters.shopId,
                page_id:
                    localFilters.pageId === 'all'
                        ? undefined
                        : localFilters.pageId,
                page_status:
                    localFilters.pageStatus === 'all'
                        ? undefined
                        : localFilters.pageStatus,
                search: localFilters.search || undefined,
                statuses: localFilters.statuses,
                show_cpp: localFilters.showCpp ? 1 : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const toggleStatus = (status: string) => {
        setLocalFilters((current) => {
            const statuses = current.statuses.includes(status)
                ? current.statuses.filter((item) => item !== status)
                : [...current.statuses, status];

            return {
                ...current,
                statuses: statuses.length ? statuses : ['confirmed'],
            };
        });
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Page ROAS Tracker`} />

            <div className="space-y-5 p-4 md:p-6">
                <PageHeader
                    title="Page ROAS Tracker"
                    description={`${formatDate(new Date(filters.startDate), 'MMM d')} - ${formatDate(new Date(filters.endDate), 'MMM d, yyyy')}`}
                    stackActionsOnMobile
                >
                    <DatePicker
                        id="page-roas-date-range"
                        mode="range"
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setDateRange([
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                ]);
                            }
                        }}
                        defaultDate={dateRange as never as DateOption}
                    />
                </PageHeader>

                <form
                    onSubmit={applyFilters}
                    className="rounded-2xl border border-black/6 bg-white p-4 shadow-sm dark:border-white/6 dark:bg-zinc-900"
                >
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <Field label="Shop">
                            <Select
                                value={localFilters.shopId}
                                onValueChange={(value) =>
                                    setLocalFilters((current) => ({
                                        ...current,
                                        shopId: value,
                                    }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select shop" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All shops
                                    </SelectItem>
                                    {shops.map((shop) => (
                                        <SelectItem
                                            key={shop.id}
                                            value={String(shop.id)}
                                        >
                                            {shop.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>

                        <Field label="Page status">
                            <Select
                                value={localFilters.pageStatus}
                                onValueChange={(value) =>
                                    setLocalFilters((current) => ({
                                        ...current,
                                        pageStatus: value,
                                    }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    <SelectItem value="active">
                                        Active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inactive
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>

                        <Field label="Page">
                            <Select
                                value={localFilters.pageId}
                                onValueChange={(value) =>
                                    setLocalFilters((current) => ({
                                        ...current,
                                        pageId: value,
                                    }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select page" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All pages
                                    </SelectItem>
                                    {pages.map((page) => (
                                        <SelectItem
                                            key={page.id}
                                            value={String(page.id)}
                                        >
                                            {page.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>

                        <Field label="Search">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                                <input
                                    value={localFilters.search}
                                    onChange={(event) =>
                                        setLocalFilters((current) => ({
                                            ...current,
                                            search: event.target.value,
                                        }))
                                    }
                                    className="h-8 w-full rounded-lg border border-black/6 bg-stone-100 pr-3 pl-8 text-[13px] text-gray-800 transition outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                                    placeholder="Filter page"
                                />
                            </div>
                        </Field>

                        <div className="flex items-end">
                            <button
                                type="submit"
                                className="h-8 w-full rounded-lg bg-emerald-600 px-4 text-[13px] font-medium text-white transition hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-400"
                            >
                                Submit
                            </button>
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-x-5 gap-y-2">
                        {statusOptions.map((option) => (
                            <label
                                key={option.value}
                                className="flex cursor-pointer items-center gap-2 text-[12px] text-gray-600 dark:text-gray-300"
                            >
                                <input
                                    type="checkbox"
                                    checked={localFilters.statuses.includes(
                                        option.value,
                                    )}
                                    onChange={() => toggleStatus(option.value)}
                                    className="h-3.5 w-3.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                {option.label}
                            </label>
                        ))}
                        <label className="flex cursor-pointer items-center gap-2 text-[12px] text-gray-600 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={localFilters.showCpp}
                                onChange={() =>
                                    setLocalFilters((current) => ({
                                        ...current,
                                        showCpp: !current.showCpp,
                                    }))
                                }
                                className="h-3.5 w-3.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            CPP
                        </label>
                    </div>
                </form>

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <AnalysisCard
                        label="Total ROAS"
                        value={analysis.roas.toFixed(2)}
                        detail={`${currencyFormatter(analysis.totalSales)} sales / ${currencyFormatter(analysis.totalAdSpent)} ad spend`}
                    />
                    <AnalysisCard
                        label="Total Orders"
                        value={numberFormatter(analysis.totalOrders)}
                        detail={`${analysis.activePageCount} active pages in range`}
                    />
                    <AnalysisCard
                        label="CPP"
                        value={currencyFormatter(analysis.cpp)}
                        detail="Ad spend per tracked order"
                    />
                    <AnalysisCard
                        label="Best Page"
                        value={analysis.bestPage?.roas.toFixed(2) ?? '0.00'}
                        detail={analysis.bestPage?.name ?? 'No spend yet'}
                    />
                    <AnalysisCard
                        label="Worst Page"
                        value={analysis.worstPage?.roas.toFixed(2) ?? '0.00'}
                        detail={analysis.worstPage?.name ?? 'No spend yet'}
                    />
                    <AnalysisCard
                        label="Top Spend"
                        value={currencyFormatter(
                            analysis.topSpendPage?.adSpent ?? 0,
                        )}
                        detail={analysis.topSpendPage?.name ?? 'No spend yet'}
                    />
                </div>

                <div className="overflow-hidden rounded-2xl border border-black/6 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900">
                    <div className="overflow-x-auto">
                        <table className="min-w-full border-separate border-spacing-0 text-[12px]">
                            <thead>
                                <tr>
                                    <th
                                        rowSpan={2}
                                        className="sticky left-0 z-30 w-32 border-r border-b border-black/8 bg-gray-100 px-3 py-2 text-left font-semibold text-gray-700 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                                    >
                                        Date
                                    </th>
                                    {pageColumns.map((page) => (
                                        <th
                                            key={page.id}
                                            colSpan={metricColumns.length}
                                            className="border-r border-b border-black/8 bg-gray-100 px-3 py-2 text-center font-semibold text-gray-700 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                                        >
                                            <span className="block max-w-72 truncate">
                                                {page.name}
                                            </span>
                                            {page.shopName && (
                                                <span className="mt-0.5 block text-[10px] font-normal text-gray-400 dark:text-gray-500">
                                                    {page.shopName}
                                                </span>
                                            )}
                                        </th>
                                    ))}
                                </tr>
                                <tr>
                                    {pageColumns.map((page) =>
                                        metricColumns.map((metric) => (
                                            <th
                                                key={`${page.id}-${metric}`}
                                                className="border-r border-b border-black/8 bg-gray-50 px-2 py-2 text-center font-mono text-[10px] font-semibold tracking-wider text-gray-400 uppercase dark:border-white/8 dark:bg-zinc-800/60 dark:text-gray-500"
                                            >
                                                {metricLabel(metric)}
                                            </th>
                                        )),
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ||
                                pageColumns.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={
                                                1 +
                                                pageColumns.length *
                                                    metricColumns.length
                                            }
                                            className="px-4 py-10 text-center text-sm text-gray-400"
                                        >
                                            No page ROAS data found for this
                                            range.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row) => (
                                        <tr key={row.date}>
                                            <td className="sticky left-0 z-20 border-r border-b border-black/8 bg-white px-3 py-2 font-semibold whitespace-nowrap text-gray-800 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-100">
                                                {row.date}
                                            </td>
                                            {pageColumns.map((page) =>
                                                metricColumns.map((metric) => (
                                                    <MetricCell
                                                        key={`${row.date}-${page.id}-${metric}`}
                                                        metric={metric}
                                                        cell={
                                                            row.cells[page.id]
                                                        }
                                                    />
                                                )),
                                            )}
                                        </tr>
                                    ))
                                )}

                                {summaryRows.map((summary) => (
                                    <tr key={summary.kind}>
                                        <td
                                            className={[
                                                'sticky left-0 z-20 border-r border-b border-black/8 px-3 py-2 font-semibold whitespace-nowrap dark:border-white/8',
                                                summary.kind === 'total'
                                                    ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200'
                                                    : 'bg-gray-50 text-gray-700 dark:bg-zinc-800 dark:text-gray-200',
                                            ].join(' ')}
                                        >
                                            {summary.label}
                                        </td>
                                        {pageColumns.map((page) =>
                                            metricColumns.map((metric) => (
                                                <MetricCell
                                                    key={`${summary.kind}-${page.id}-${metric}`}
                                                    metric={metric}
                                                    cell={
                                                        summary.cells[page.id]
                                                    }
                                                    summary={summary.kind}
                                                />
                                            )),
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-[11px] font-semibold text-gray-600 dark:text-gray-400">
                {label}
            </span>
            {children}
        </label>
    );
}

function AnalysisCard({
    label,
    value,
    detail,
}: {
    label: string;
    value: string;
    detail: string;
}) {
    return (
        <div className="rounded-2xl border border-black/6 bg-white p-4 shadow-sm dark:border-white/6 dark:bg-zinc-900">
            <div className="text-[11px] font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                {label}
            </div>
            <div className="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                {value}
            </div>
            <div className="mt-1 truncate text-[12px] text-gray-500 dark:text-gray-400">
                {detail}
            </div>
        </div>
    );
}

function MetricCell({
    metric,
    cell,
    summary,
}: {
    metric: string;
    cell?: Cell;
    summary?: 'total' | 'average';
}) {
    const value = Number(cell?.[metric as keyof Cell] ?? 0);
    const isRoas = metric === 'roas';
    const isMoney = ['sales', 'adSpent', 'cpp'].includes(metric);

    const display = isRoas
        ? value.toFixed(2)
        : isMoney
          ? currencyFormatter(value)
          : numberFormatter(value);

    return (
        <td
            className={[
                'min-w-20 border-r border-b border-black/8 px-2 py-2 text-center text-gray-700 tabular-nums dark:border-white/8 dark:text-gray-200',
                isRoas ? roasClass(value) : '',
                summary === 'total' && !isRoas
                    ? 'bg-emerald-50/60 dark:bg-emerald-500/5'
                    : '',
                summary === 'average' && !isRoas
                    ? 'bg-gray-50/80 dark:bg-zinc-800/40'
                    : '',
                !summary && !isRoas
                    ? 'bg-white odd:bg-stone-50 dark:bg-zinc-900 dark:odd:bg-zinc-800/30'
                    : '',
            ].join(' ')}
        >
            {display}
        </td>
    );
}

function metricLabel(metric: string) {
    return (
        {
            orders: 'Orders',
            sales: 'Sales',
            adSpent: 'Ad Spent',
            roas: 'ROAS',
            cpp: 'CPP',
        }[metric] ?? metric
    );
}

// Profitability heatmap, aligned with the app's green=good / red=bad palette:
// strong ROAS reads green, marginal reads amber, unprofitable reads rose.
function roasClass(roas: number) {
    if (roas >= 3)
        return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-500/15 dark:text-emerald-200';
    if (roas >= 1)
        return 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200';
    if (roas > 0)
        return 'bg-rose-100 text-rose-900 dark:bg-rose-500/15 dark:text-rose-200';
    return 'bg-white dark:bg-zinc-900';
}
