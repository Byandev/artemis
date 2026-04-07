import AppLayout from '@/layouts/app-layout';
import { useState, useCallback } from 'react';
import { Workspace } from '@/types/models/Workspace';
import {
    currencyFormatter,
    numberFormatter,
    percentageFormatter,
} from '@/lib/utils';
import DatePicker from '@/components/ui/date-picker';
import Filters, { FilterValue } from '@/components/filters/Filters';
import moment from 'moment';
import flatpickr from 'flatpickr';
import DateOption = flatpickr.Options.DateOption;
import StatisticCard from '@/pages/workspaces/dashboard/partials/StatisticCard';
import { StatisticBreakdown } from '@/pages/workspaces/dashboard/partials/StatisticBreakdown';
import ComponentCard from '@/components/common/ComponentCard';
import PageBreakdown from '@/pages/workspaces/dashboard/partials/PageBreakdown';
import ShopBreakdown from '@/pages/workspaces/dashboard/partials/ShopBreakdown';
import {
    Clock3,
    PackageCheck,
    PhilippinePeso,
    ReceiptText,
    Repeat,
    ShoppingCart,
    Truck,
    Undo2,
    Wallet,
} from 'lucide-react';
import UserBreakdown from '@/pages/workspaces/dashboard/partials/UserBreakdown';
import { formatDate } from 'date-fns';
import MetricPicker from '@/components/metrics/MetricPicker';
import { metricConfigs, MetricKey } from '@/types/metrics';
import PageHeader from '@/components/common/PageHeader';
import AskDataWidget, { DashboardData } from '@/components/ai/AskDataWidget';

interface Props {
    workspace: Workspace;
}

const Dashboard = ({ workspace }: Props) => {
    const STORAGE_KEY    = `dashboard_metrics_${workspace.id}`;
    const DATE_RANGE_KEY = `dashboard_date_range_${workspace.id}`;
    const FILTER_KEY     = `dashboard_filter_${workspace.id}`;

    const [dateRange, setDateRange] = useState<string[]>(() => {
        try {
            const saved = localStorage.getItem(DATE_RANGE_KEY);
            if (saved) return JSON.parse(saved) as string[];
        } catch {}
        return [
            moment().startOf('month').format('YYYY-MM-DD'),
            moment().endOf('month').format('YYYY-MM-DD'),
        ];
    });

    const [filter, setFilter] = useState<FilterValue>(() => {
        try {
            const saved = localStorage.getItem(FILTER_KEY);
            if (saved) return JSON.parse(saved) as FilterValue;
        } catch {}
        return { teamIds: [], productIds: [], shopIds: [], pageIds: [], userIds: [] };
    });

    const [selectedMetrics, setSelectedMetrics] = useState<MetricKey[]>(() => {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) return JSON.parse(saved) as MetricKey[];
        } catch {}
        return ['totalSales', 'totalOrders', 'aov', 'rtsRate'];
    });

    // Collect loaded data from breakdown components for the AI widget
    const [dashboardData, setDashboardData] = useState<DashboardData>({
        metrics: {},
        pages:   { data: [], metric: 'totalSales' },
        shops:   { data: [], metric: 'totalSales' },
        users:   { data: [], metric: 'totalSales' },
    });

    const onMetricLoaded = useCallback((metric: string, value: number) => {
        setDashboardData(prev => ({ ...prev, metrics: { ...prev.metrics, [metric]: value } }));
    }, []);

    const onPagesLoaded = useCallback((data: object[], metric: string) => {
        setDashboardData(prev => ({ ...prev, pages: { data, metric } }));
    }, []);

    const onShopsLoaded = useCallback((data: object[], metric: string) => {
        setDashboardData(prev => ({ ...prev, shops: { data, metric } }));
    }, []);

    const onUsersLoaded = useCallback((data: object[], metric: string) => {
        setDashboardData(prev => ({ ...prev, users: { data, metric } }));
    }, []);

    return (
        <AppLayout>
            <div className="p-4 md:p-6">
                <PageHeader
                    title="Dashboard"
                    description={`Performance overview · ${formatDate(new Date(dateRange[0]), 'MMM d')} – ${formatDate(new Date(dateRange[1]), 'MMM d, yyyy')}`}
                >
                    <MetricPicker
                        initialValue={selectedMetrics}
                        onChange={(value) => {
                            setSelectedMetrics(value);
                            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(value)); } catch {}
                        }}
                    />
                    <Filters
                        workspace={workspace}
                        initialValue={filter}
                        onChange={(value) => {
                            setFilter(value);
                            try { localStorage.setItem(FILTER_KEY, JSON.stringify(value)); } catch {}
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
                                try { localStorage.setItem(DATE_RANGE_KEY, JSON.stringify(range)); } catch {}
                            }
                        }}
                        defaultDate={dateRange as never as DateOption}
                    />
                </PageHeader>

                <div className="mt-6 grid grid-cols-1 gap-2 sm:grid-cols-2 md:gap-4 xl:grid-cols-4">
                    {metricConfigs
                        .filter(m => selectedMetrics.includes(m.key))
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

            {/* Floating AI chat — uses data already loaded on screen */}
            <AskDataWidget
                workspace={workspace}
                dateRange={dateRange}
                data={dashboardData}
            />
        </AppLayout>
    );
};

export default Dashboard;
