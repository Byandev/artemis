import ComponentCard from '@/components/common/ComponentCard';
import PageHeader from '@/components/common/PageHeader';
import Filters, { FilterValue } from '@/components/filters/Filters';
import MetricPicker from '@/components/metrics/MetricPicker';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import PageBreakdown from '@/pages/workspaces/dashboard/partials/PageBreakdown';
import ShopBreakdown from '@/pages/workspaces/dashboard/partials/ShopBreakdown';
import { StatisticBreakdown } from '@/pages/workspaces/dashboard/partials/StatisticBreakdown';
import StatisticCard from '@/pages/workspaces/dashboard/partials/StatisticCard';
import UserBreakdown from '@/pages/workspaces/dashboard/partials/UserBreakdown';
import { metricConfigs, MetricKey } from '@/types/metrics';
import { Workspace } from '@/types/models/Workspace';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    workspace: Workspace;
    metricSettings: {
        allowed: MetricKey[];
        defaults: MetricKey[];
    };
}

const Dashboard = ({ workspace, metricSettings }: Props) => {
    const STORAGE_KEY = `dashboard_metrics_${workspace.id}`;
    const DATE_RANGE_KEY = `dashboard_date_range_${workspace.id}`;
    const FILTER_KEY = `dashboard_filter_${workspace.id}`;
    const availableMetrics = useMemo(
        () =>
            metricConfigs.filter((metric) =>
                metricSettings.allowed.includes(metric.key),
            ),
        [metricSettings.allowed],
    );
    const availableMetricKeys = useMemo(
        () => availableMetrics.map((metric) => metric.key),
        [availableMetrics],
    );
    const defaultMetricKeys = useMemo(
        () =>
            metricSettings.defaults.filter((key) =>
                availableMetricKeys.includes(key),
            ),
        [availableMetricKeys, metricSettings.defaults],
    );

    const [dateRange, setDateRange] = useState<string[]>(() => {
        try {
            const saved = localStorage.getItem(DATE_RANGE_KEY);
            if (saved) return JSON.parse(saved) as string[];
        } catch {
            // Ignore invalid saved dashboard date ranges.
        }
        return [
            moment().startOf('month').format('YYYY-MM-DD'),
            moment().subtract(1, 'd').format('YYYY-MM-DD'),
        ];
    });

    const [filter, setFilter] = useState<FilterValue>(() => {
        try {
            const saved = localStorage.getItem(FILTER_KEY);
            if (saved) return JSON.parse(saved) as FilterValue;
        } catch {
            // Ignore invalid saved dashboard filters.
        }
        return {
            teamIds: [],
            productIds: [],
            shopIds: [],
            pageIds: [],
            userIds: [],
        };
    });

    const [selectedMetrics, setSelectedMetrics] = useState<MetricKey[]>(() => {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                const parsed = JSON.parse(saved) as MetricKey[];
                const validSaved = parsed.filter((key) =>
                    availableMetricKeys.includes(key),
                );

                if (validSaved.length !== parsed.length) {
                    localStorage.setItem(
                        STORAGE_KEY,
                        JSON.stringify(validSaved),
                    );
                }

                return validSaved.length > 0 ? validSaved : defaultMetricKeys;
            }
        } catch {
            // Ignore invalid saved dashboard metrics.
        }

        return defaultMetricKeys;
    });

    useEffect(() => {
        setSelectedMetrics((current) => {
            const validCurrent = current.filter((key) =>
                availableMetricKeys.includes(key),
            );
            // Preserve user selections; only use defaults if user hasn't selected any metrics
            const next =
                validCurrent.length > 0 ? validCurrent : defaultMetricKeys;
            const changed =
                next.length !== current.length ||
                next.some((key, index) => key !== current[index]);

            if (changed) {
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
                } catch {
                    // Ignore localStorage write failures.
                }
            }

            return changed ? next : current;
        });
    }, [STORAGE_KEY, availableMetricKeys, defaultMetricKeys]);

    return (
        <AppLayout>
            <div className="p-4 md:p-6">
                <PageHeader
                    title="Dashboard"
                    description={`Performance overview · ${formatDate(new Date(dateRange[0]), 'MMM d')} – ${formatDate(new Date(dateRange[1]), 'MMM d, yyyy')}`}
                    stackActionsOnMobile
                >
                    <MetricPicker
                        metrics={availableMetrics}
                        initialValue={selectedMetrics}
                        onChange={(value) => {
                            const validValue = value.filter((key) =>
                                availableMetricKeys.includes(key),
                            );

                            setSelectedMetrics(validValue);
                            try {
                                localStorage.setItem(
                                    STORAGE_KEY,
                                    JSON.stringify(validValue),
                                );
                            } catch {
                                // Ignore localStorage write failures.
                            }
                        }}
                    />
                    <Filters
                        workspace={workspace}
                        initialValue={filter}
                        onChange={(value) => {
                            setFilter(value);
                            try {
                                localStorage.setItem(
                                    FILTER_KEY,
                                    JSON.stringify(value),
                                );
                            } catch {
                                // Ignore localStorage write failures.
                            }
                        }}
                    />
                    <DatePicker
                        id={'dashboard-date-range'}
                        mode={'range'}
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                const range = [
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                ];
                                setDateRange(range);
                                try {
                                    localStorage.setItem(
                                        DATE_RANGE_KEY,
                                        JSON.stringify(range),
                                    );
                                } catch {
                                    // Ignore localStorage write failures.
                                }
                            }
                        }}
                        defaultDate={dateRange as never as DateOption}
                    />
                </PageHeader>

                <div className="mt-6 grid grid-cols-1 gap-2 sm:grid-cols-2 md:gap-4 xl:grid-cols-4">
                    {availableMetrics
                        .filter((m) => selectedMetrics.includes(m.key))
                        .map((card) => (
                            <StatisticCard
                                key={card.key}
                                label={card.name}
                                metric={card.key}
                                workspace={workspace}
                                filter={filter}
                                dateRange={dateRange}
                                formatter={card.formatter}
                                icon={card.icon}
                                tooltipLabel={card.description}
                                reverseTrend={card.reverse}
                            />
                        ))}
                </div>

                <ComponentCard className="mt-12">
                    <StatisticBreakdown
                        metrics={selectedMetrics}
                        filter={filter}
                        dateRange={dateRange}
                        workspace={workspace}
                    />
                </ComponentCard>

                <ComponentCard className="mt-12">
                    <PageBreakdown
                        dateRange={dateRange}
                        workspace={workspace}
                        filter={filter}
                        metrics={selectedMetrics}
                    />
                </ComponentCard>

                <ComponentCard className="mt-6">
                    <ShopBreakdown
                        filter={filter}
                        dateRange={dateRange}
                        workspace={workspace}
                        metrics={selectedMetrics}
                    />
                </ComponentCard>

                <ComponentCard className="mt-6">
                    <UserBreakdown
                        filter={filter}
                        dateRange={dateRange}
                        workspace={workspace}
                        metrics={selectedMetrics}
                    />
                </ComponentCard>
            </div>
        </AppLayout>
    );
};

export default Dashboard;
