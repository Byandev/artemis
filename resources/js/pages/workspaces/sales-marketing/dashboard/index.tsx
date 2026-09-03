import PageHeader from '@/components/common/PageHeader';
import KpiCard, {
    formatKpi,
} from '@/components/sales-marketing/dashboard/kpi-card';
import LeaderCard from '@/components/sales-marketing/dashboard/leader-card';
import TeamBreakdown from '@/components/sales-marketing/dashboard/team-breakdown';
import TeamComparison from '@/components/sales-marketing/dashboard/team-comparison';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import moment from 'moment';
import { useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    workspace: Workspace;
}

/** Start of this month → yesterday, the same default the main dashboard uses. */
const defaultRange = (): string[] => [
    moment().startOf('month').format('YYYY-MM-DD'),
    moment().subtract(1, 'd').format('YYYY-MM-DD'),
];

export default function SalesMarketingDashboard({ workspace }: Props) {
    // The window persists per workspace, so coming back to the page keeps the
    // range you were last looking at. Team narrowing is not a page concern —
    // the workspace-wide "viewing as team" switcher handles it.
    const DATE_RANGE_KEY = `sm_dashboard_date_range_${workspace.id}`;

    const [dateRange, setDateRange] = useState<string[]>(() => {
        try {
            const saved = localStorage.getItem(DATE_RANGE_KEY);
            if (saved) return JSON.parse(saved) as string[];
        } catch {
            // Unreadable storage — fall through to the default window.
        }
        return defaultRange();
    });

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Sales & Marketing Dashboard`} />
            <div className="p-4 md:p-6">
                <PageHeader
                    title="Dashboard"
                    description={`Performance overview · ${formatDate(new Date(dateRange[0]), 'MMM d')} – ${formatDate(new Date(dateRange[1]), 'MMM d, yyyy')}`}
                    stackActionsOnMobile
                >
                    <DatePicker
                        id="sm-dashboard-date-range"
                        mode="range"
                        onChange={(dates) => {
                            let range: string[] | null = null;
                            if (dates.length === 2) {
                                range = [
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                ];
                            } else if (dates.length === 0) {
                                // Cleared → back to the default window.
                                range = defaultRange();
                            }
                            if (range) {
                                setDateRange(range);
                                try {
                                    localStorage.setItem(
                                        DATE_RANGE_KEY,
                                        JSON.stringify(range),
                                    );
                                } catch {
                                    // Storage is best-effort; the range still applies.
                                }
                            }
                        }}
                        defaultDate={dateRange as never as DateOption}
                    />
                </PageHeader>

                {/* KPIs load one request each, so the page paints before the
                    numbers do. More tiles slot into this grid as they land. */}
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 md:gap-4 xl:grid-cols-4">
                    <KpiCard
                        slug={workspace.slug}
                        label="Total Sales"
                        endpoint="kpi/total-sales"
                        dateRange={dateRange}
                    />
                    <KpiCard
                        slug={workspace.slug}
                        label="Total Ad Spend"
                        endpoint="kpi/total-ad-spend"
                        dateRange={dateRange}
                    />
                    <KpiCard
                        slug={workspace.slug}
                        label="Blended ROAS"
                        endpoint="kpi/blended-roas"
                        dateRange={dateRange}
                        as="ratio"
                        emptyHint="no ad spend in this period"
                        // The attributed ratio earns its place beside the
                        // comparison: the gap between the two is the point.
                        caption={(data, prior) =>
                            [
                                prior?.value != null &&
                                    `vs ${formatKpi(prior.value, 'ratio')}`,
                                data.actual != null &&
                                    `actual ${formatKpi(data.actual, 'ratio')}`,
                            ]
                                .filter(Boolean)
                                .join(' · ') || null
                        }
                    />
                    <KpiCard
                        slug={workspace.slug}
                        label="RTS Rate"
                        endpoint="kpi/rts-rate"
                        dateRange={dateRange}
                        as="percent"
                        // A rising return rate is bad news, so the trend colours
                        // the other way round from the cards beside it.
                        reverse
                        // The money at stake says more than the previous rate.
                        caption={(data) =>
                            data.returning_amount != null
                                ? `${formatKpi(data.returning_amount, 'currencyExact')} returned to sender`
                                : null
                        }
                    />
                </div>

                {/* Who topped each figure over the same window. Its own
                    endpoints, so the section loads after the KPI row rather
                    than holding it up. */}
                <h2 className="mt-10 mb-4 text-[11px] font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                    Leaders for the period
                </h2>
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 md:gap-4 xl:grid-cols-4">
                    <LeaderCard
                        slug={workspace.slug}
                        label="Highest Ad Spend"
                        endpoint="leaders/highest-ad-spend"
                        dateRange={dateRange}
                        emptyHint="no ad spend in this period"
                        // What the spend bought: its own return, then the
                        // sales figure that return is drawn from.
                        caption={(d) => {
                            const sales = Number(d.sales ?? 0);
                            const roas = d.value > 0 ? sales / d.value : null;

                            return [
                                roas !== null &&
                                    `ROAS ${formatKpi(roas, 'ratio')}`,
                                `${formatKpi(sales, 'currencyExact')} sales`,
                            ]
                                .filter(Boolean)
                                .join(' · ');
                        }}
                    />
                    <LeaderCard
                        slug={workspace.slug}
                        label="Lowest RTS"
                        endpoint="leaders/lowest-rts"
                        dateRange={dateRange}
                        as="percent"
                        // Lowest wins here, so the corner names the achievement
                        // rather than a share of anything.
                        aside="Best delivery rate"
                        emptyHint="nothing delivered or returned yet"
                        caption={(d) =>
                            `${formatKpi(Number(d.returned_amount ?? 0), 'currencyExact')} returned to sender`
                        }
                    />
                    <LeaderCard
                        slug={workspace.slug}
                        label="Highest ROAS"
                        endpoint="leaders/highest-roas"
                        dateRange={dateRange}
                        as="ratio"
                        // A ratio is not a slice of a total, so the corner says
                        // what the number means instead of a share.
                        aside="Best efficiency"
                        emptyHint="no ad spend in this period"
                        // Both sides of the ratio, so the figure can be judged
                        // against the size of the bet behind it.
                        caption={(d) =>
                            `${formatKpi(Number(d.ad_spend ?? 0), 'currencyExact')} spend on ${formatKpi(Number(d.sales ?? 0), 'currencyExact')} sales`
                        }
                    />
                    <LeaderCard
                        slug={workspace.slug}
                        label="Highest Sales"
                        endpoint="leaders/highest-sales"
                        dateRange={dateRange}
                        emptyHint="no sales in this period"
                        // What the sales are made of: how many orders, and how
                        // big the average one was.
                        caption={(d) => {
                            const orders = Number(d.orders ?? 0);
                            if (orders <= 0) return null;

                            return [
                                `${formatKpi(orders, 'number')} orders`,
                                `AOV ${formatKpi(d.value / orders, 'currencyExact')}`,
                            ].join(' · ');
                        }}
                    />
                </div>

                <TeamComparison slug={workspace.slug} dateRange={dateRange} />

                <TeamBreakdown slug={workspace.slug} dateRange={dateRange} />
            </div>
        </AppLayout>
    );
}
