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

    const cards = [
        {
            label: 'Total Sales',
            key: 'totalSales',
            formatter: currencyFormatter,
        },
        {
            label: 'Total Orders',
            key: 'totalOrders',
            formatter: numberFormatter,
        },
        {
            label: 'AOV',
            key: 'aov',
            formatter: currencyFormatter,
        },
        {
            label: 'RTS Rate',
            key: 'rtsRate',
            formatter: percentageFormatter,
        },
        {
            label: 'Repeat Order Ratio',
            key: 'repeatOrderRatio',
            formatter: percentageFormatter,
        },
        {
            label: 'Time to first Order',
            key: 'timeToFirstOrder',
            formatter: (value: number) => `${value ?? 0} Hrs`,
        },
        {
            label: 'Avg. Lifetime Value',
            key: 'avgLifetimeValue',
            formatter: currencyFormatter,
        },
        {
            label: 'Avg Delivery Days',
            key: 'avgDeliveryDays',
            formatter: (value: number) => `${value ?? 0} Days`,
        },
        {
            label: 'Avg Shipped Out Days',
            key: 'avgShippedOutDays',
            formatter: (value: number) => `${value ?? 0} Days`,
        },
    ];

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 md:gap-6 xl:grid-cols-4">
                    <div className="sm:col-start-1 md:col-start-2 xl:col-start-3">
                        {/* Optional: if your Filters supports disabled, pass it; otherwise just leave it */}
                        <Filters
                            workspace={workspace}
                            onChange={(value) => setFilter(value)}
                        />
                    </div>

                    <div className="sm:col-start-2 md:col-start-3 xl:col-start-4">
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
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                    {cards.map((card) => (
                        <StatisticCard
                            key={card.key}
                            filter={filter}
                            metric={card.key}
                            label={card.label}
                            workspace={workspace}
                            dateRange={dateRange}
                            formatter={card.formatter}
                        />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
};

export default Dashboard;
