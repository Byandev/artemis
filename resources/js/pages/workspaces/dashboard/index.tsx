import { DashboardData } from '@/components/ai/AskDataWidget';
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
import { useCallback, useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    workspace: Workspace;
    metricSettings: {
        allowed: MetricKey[];
        defaults: MetricKey[];
    };
}

// Order sources surfaced in the dashboard filter. Static for now — these are the
// order_source_name values we persist for Facebook (-1) and Webcake (-7) orders.
const ORDER_SOURCES = ['Facebook', 'Webcake'];

const Dashboard = ({ workspace, metricSettings }: Props) => {
    const STORAGE_KEY = `dashboard_metrics_${workspace.id}`;
    const DATE_RANGE_KEY = `dashboard_date_range_${workspace.id}`;
    const FILTER_KEY = `dashboard_filter_${workspace.id}`;

    const [dateRange, setDateRange] = useState<string[]>(() => {
        try {
            const saved = localStorage.getItem(DATE_RANGE_KEY);
            if (saved) return JSON.parse(saved) as string[];
        } catch {}
        return [
            moment().startOf('month').format('YYYY-MM-DD'),
            moment().subtract(1, 'd').format('YYYY-MM-DD'),
        ];
    });

    const [filter, setFilter] = useState<FilterValue>(() => {
        try {
            const saved = localStorage.getItem(FILTER_KEY);
            if (saved) return JSON.parse(saved) as FilterValue;
        } catch {}
        return {
            teamIds: [],
            productIds: [],
            shopIds: [],
            pageIds: [],
            userIds: [],
            orderSourceNames: [],
        };
    });

    const [selectedMetrics, setSelectedMetrics] = useState<MetricKey[]>(() => {
        const allowedKeys = metricSettings.allowed;
        const availableMetrics = metricConfigs
            .filter((m) => allowedKeys.includes(m.key))
            .map((m) => m.key);

        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                const parsed = JSON.parse(saved) as MetricKey[];
                const validSaved = parsed.filter((key) =>
                    availableMetrics.includes(key),
                );

                if (validSaved.length !== parsed.length) {
                    localStorage.setItem(
                        STORAGE_KEY,
                        JSON.stringify(validSaved),
                    );
                }

                return validSaved.length > 0
                    ? validSaved
                    : metricSettings.defaults;
            }
        } catch {}

        return metricSettings.defaults.filter((key) =>
            availableMetrics.includes(key),
        ); // Safety
    });

    // Collect loaded data from breakdown components for the AI widget
    const [dashboardData, setDashboardData] = useState<DashboardData>({
        metrics: {},
        pages: { data: [], metric: 'totalSales' },
        shops: { data: [], metric: 'totalSales' },
        users: { data: [], metric: 'totalSales' },
    });

    const onMetricLoaded = useCallback((metric: string, value: number) => {
        setDashboardData((prev) => ({
            ...prev,
            metrics: { ...prev.metrics, [metric]: value },
        }));
    }, []);

    const onPagesLoaded = useCallback((data: object[], metric: string) => {
        setDashboardData((prev) => ({ ...prev, pages: { data, metric } }));
    }, []);

    const onShopsLoaded = useCallback((data: object[], metric: string) => {
        setDashboardData((prev) => ({ ...prev, shops: { data, metric } }));
    }, []);

    const onUsersLoaded = useCallback((data: object[], metric: string) => {
        setDashboardData((prev) => ({ ...prev, users: { data, metric } }));
    }, []);

    return (
        <AppLayout>
            <div className="p-4 md:p-6">
                <PageHeader
                    title="Dashboard"
                    description={`Performance overview · ${formatDate(new Date(dateRange[0]), 'MMM d')} – ${formatDate(new Date(dateRange[1]), 'MMM d, yyyy')}`}
                    stackActionsOnMobile
                >
                    <MetricPicker
                        metrics={metricConfigs.filter((m) =>
                            metricSettings.allowed.includes(m.key),
                        )} // ← ADD FILTER
                        initialValue={selectedMetrics}
                        onChange={(value) => {
                            setSelectedMetrics(value);
                            try {
                                localStorage.setItem(
                                    STORAGE_KEY,
                                    JSON.stringify(value),
                                );
                            } catch {}
                        }}
                    />
                    <Filters
                        workspace={workspace}
                        orderSources={ORDER_SOURCES}
                        initialValue={filter}
                        onChange={(value) => {
                            setFilter(value);
                            try {
                                localStorage.setItem(
                                    FILTER_KEY,
                                    JSON.stringify(value),
                                );
                            } catch {}
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
                                } catch {}
                            }
                        }}
                        defaultDate={dateRange as never as DateOption}
                    />
                </PageHeader>

                <div className="mt-6 grid grid-cols-1 gap-2 sm:grid-cols-2 md:gap-4 xl:grid-cols-4">
                    {metricConfigs
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
                                onValueLoaded={onMetricLoaded}
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
                        onDataLoaded={onPagesLoaded}
                    />
                </ComponentCard>

                <ComponentCard className="mt-6">
                    <ShopBreakdown
                        filter={filter}
                        dateRange={dateRange}
                        workspace={workspace}
                        metrics={selectedMetrics}
                        onDataLoaded={onShopsLoaded}
                    />
                </ComponentCard>

                <ComponentCard className="mt-6">
                    <UserBreakdown
                        filter={filter}
                        dateRange={dateRange}
                        workspace={workspace}
                        metrics={selectedMetrics}
                        onDataLoaded={onUsersLoaded}
                    />
                </ComponentCard>
            </div>
        </AppLayout>
    );
};

export default Dashboard;
