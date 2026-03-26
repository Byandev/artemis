import AppLayout from '@/layouts/app-layout';
import { useState } from 'react';
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

interface Props {
    workspace: Workspace;
}

const Dashboard = ({ workspace }: Props) => {
    const [dateRange, setDateRange] = useState([
        moment().startOf('month').format('YYYY-MM-DD'),
        moment().endOf('month').format('YYYY-MM-DD'),
    ]);

    const [filter, setFilter] = useState<FilterValue>({
        teamIds: [],
        productIds: [],
        shopIds: [],
        pageIds: [],
        userIds: [],
    });

    const STORAGE_KEY = `dashboard_metrics_${workspace.id}`;

    const [selectedMetrics, setSelectedMetrics] = useState<MetricKey[]>(() => {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) return JSON.parse(saved) as MetricKey[];
        } catch {}
        return ['totalSales', 'totalOrders', 'aov', 'rtsRate'];
    });


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
                        onChange={(value) => setFilter(value)}
                    />
                    <DatePicker
                        id={'dashboard-date-range'}
                        mode={'range'}
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

                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 md:gap-4 xl:grid-cols-4">
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
