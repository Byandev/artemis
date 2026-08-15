import {
    DashboardTab,
    DashboardTabNav,
} from '@/components/sales-marketing/dashboard-tabs';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import flatpickr from 'flatpickr';
import moment from 'moment';
import { Fragment, useState } from 'react';
import PageRoasFilters, {
    FilterOption,
    PageRoasFilterValue,
} from './page-roas-filters';
import DateOption = flatpickr.Options.DateOption;

interface Metrics {
    orders: number;
    sales: number;
    ad_spent: number;
    roas: number | null;
}

interface PageSeries {
    page_id: number | string;
    name: string;
    days: Record<string, Metrics>;
    total: Metrics;
    average: Metrics;
}

interface Props {
    workspace: Workspace;
    dates: string[];
    pages: PageSeries[];
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

const int = (n: number) => n.toLocaleString();
const dec = (n: number) =>
    n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
const roasText = (n: number | null) => (n && n > 0 ? n.toFixed(2) : '0');

/**
 * Colour ROAS so winners and losers read at a glance. 3.00 is the bar: at or
 * above it is green, between 2 and 3 is short of it, and below 2 is the problem
 * — the red deepens as it gets worse so a bad day stands out across a room.
 *
 * The deep band pairs white text with the dark fill; red-600 on dark text would
 * not clear contrast.
 */
const roasCell = (n: number | null) =>
    n === null || n === 0
        ? 'text-gray-400'
        : n >= 3
          ? 'bg-green6700 text-white dark:bg-green-600 dark:text-white'
          : n >= 2
            ? 'bg-red-200 text-red-900 dark:bg-red-400/25 dark:text-red-100'
            : 'bg-red-600 text-white dark:bg-red-600 dark:text-white';

export default function PageRoasTrackerIndex({
    workspace,
    dates,
    pages,
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

    // Shared cell styling. The first column of each page group gets a heavier
    // left border so the groups read as distinct blocks.
    const cell =
        'whitespace-nowrap px-3 py-2 border-b border-black/5 dark:border-white/5';
    const groupStart = 'border-l-2 border-l-black/10 dark:border-l-white/10';
    const stickyLeft =
        'sticky left-0 z-10 border-r border-black/10 dark:border-white/10';

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Page ROAS Tracker`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                {hasTabs && <DashboardTabNav tabs={tabs!} active={activeTab} />}

                {/* Page title on the left, filters + date range on the right. */}
                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <h1 className="my-0! text-[22px]! font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                        Page ROAS Tracker
                    </h1>
                    <div className="flex flex-wrap items-center gap-2">
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
                    <div className="mt-4 rounded-[14px] border border-black/6 bg-white py-16 text-center text-[13px] text-gray-400 dark:border-white/6 dark:bg-zinc-900">
                        No page performance for this range yet.
                    </div>
                ) : (
                    <div className="mt-4 overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <table className="border-collapse text-[12px] tabular-nums">
                            <thead>
                                {/* Page names span their four metric columns. */}
                                <tr>
                                    <th
                                        rowSpan={2}
                                        className={cn(
                                            cell,
                                            stickyLeft,
                                            'z-30 bg-gray-50 text-left text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:bg-zinc-800',
                                        )}
                                    >
                                        Date
                                    </th>
                                    {pages.map((page) => (
                                        <th
                                            key={page.page_id}
                                            colSpan={4}
                                            className={cn(
                                                cell,
                                                groupStart,
                                                'max-w-[22rem] truncate bg-blue-600 text-left font-semibold text-white',
                                            )}
                                            title={page.name}
                                        >
                                            {page.name}
                                        </th>
                                    ))}
                                </tr>
                                <tr>
                                    {pages.map((page) => (
                                        <Fragment key={page.page_id}>
                                            <th
                                                className={cn(
                                                    cell,
                                                    groupStart,
                                                    'bg-blue-500 text-center text-[11px] font-medium text-white',
                                                )}
                                            >
                                                Orders
                                            </th>
                                            <th
                                                className={cn(
                                                    cell,
                                                    'bg-blue-500 text-right text-[11px] font-medium text-white',
                                                )}
                                            >
                                                Sales
                                            </th>
                                            <th
                                                className={cn(
                                                    cell,
                                                    'bg-blue-500 text-right text-[11px] font-medium text-white',
                                                )}
                                            >
                                                Ad Spent
                                            </th>
                                            <th
                                                className={cn(
                                                    cell,
                                                    'bg-blue-500 text-right text-[11px] font-medium text-white',
                                                )}
                                            >
                                                ROAS
                                            </th>
                                        </Fragment>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {dates.map((date) => (
                                    <tr key={date}>
                                        <td
                                            className={cn(
                                                cell,
                                                stickyLeft,
                                                'bg-white font-medium text-gray-700 dark:bg-zinc-900 dark:text-gray-300',
                                            )}
                                        >
                                            {date}
                                        </td>
                                        {pages.map((page) => {
                                            const m = page.days[date];
                                            return (
                                                <Fragment key={page.page_id}>
                                                    <td
                                                        className={cn(
                                                            cell,
                                                            groupStart,
                                                            'text-center text-gray-700 dark:text-gray-300',
                                                        )}
                                                    >
                                                        {int(m.orders)}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            cell,
                                                            'text-right text-gray-700 dark:text-gray-300',
                                                        )}
                                                    >
                                                        {dec(m.sales)}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            cell,
                                                            'text-right text-gray-700 dark:text-gray-300',
                                                        )}
                                                    >
                                                        {dec(m.ad_spent)}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            cell,
                                                            'text-right font-semibold',
                                                            roasCell(m.roas),
                                                        )}
                                                    >
                                                        {roasText(m.roas)}
                                                    </td>
                                                </Fragment>
                                            );
                                        })}
                                    </tr>
                                ))}

                                {/* Total row. */}
                                <tr>
                                    <td
                                        className={cn(
                                            cell,
                                            stickyLeft,
                                            'bg-amber-200 font-semibold text-gray-900 dark:bg-amber-400/80',
                                        )}
                                    >
                                        Total Amount
                                    </td>
                                    {pages.map((page) => (
                                        <Fragment key={page.page_id}>
                                            <td
                                                className={cn(
                                                    cell,
                                                    groupStart,
                                                    'bg-amber-200 text-center font-semibold text-gray-900 dark:bg-amber-400/80',
                                                )}
                                            >
                                                {int(page.total.orders)}
                                            </td>
                                            <td
                                                className={cn(
                                                    cell,
                                                    'bg-amber-200 text-right font-semibold text-gray-900 dark:bg-amber-400/80',
                                                )}
                                            >
                                                {dec(page.total.sales)}
                                            </td>
                                            <td
                                                className={cn(
                                                    cell,
                                                    'bg-amber-200 text-right font-semibold text-gray-900 dark:bg-amber-400/80',
                                                )}
                                            >
                                                {dec(page.total.ad_spent)}
                                            </td>
                                            <td
                                                className={cn(
                                                    cell,
                                                    'bg-amber-200 text-right font-semibold text-gray-900 dark:bg-amber-400/80',
                                                )}
                                            >
                                                {roasText(page.total.roas)}
                                            </td>
                                        </Fragment>
                                    ))}
                                </tr>

                                {/* Average row. */}
                                <tr>
                                    <td
                                        className={cn(
                                            cell,
                                            stickyLeft,
                                            'bg-teal-200 font-semibold text-gray-900 dark:bg-teal-400/80',
                                        )}
                                    >
                                        Average
                                    </td>
                                    {pages.map((page) => (
                                        <Fragment key={page.page_id}>
                                            <td
                                                className={cn(
                                                    cell,
                                                    groupStart,
                                                    'bg-teal-200 text-center font-semibold text-gray-900 dark:bg-teal-400/80',
                                                )}
                                            >
                                                {int(page.average.orders)}
                                            </td>
                                            <td
                                                className={cn(
                                                    cell,
                                                    'bg-teal-200 text-right font-semibold text-gray-900 dark:bg-teal-400/80',
                                                )}
                                            >
                                                {dec(page.average.sales)}
                                            </td>
                                            <td
                                                className={cn(
                                                    cell,
                                                    'bg-teal-200 text-right font-semibold text-gray-900 dark:bg-teal-400/80',
                                                )}
                                            >
                                                {dec(page.average.ad_spent)}
                                            </td>
                                            <td
                                                className={cn(
                                                    cell,
                                                    'text-right font-semibold',
                                                    roasCell(page.average.roas),
                                                )}
                                            >
                                                {roasText(page.average.roas)}
                                            </td>
                                        </Fragment>
                                    ))}
                                </tr>
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
