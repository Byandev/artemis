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
    DollarSign,
    PackageCheck,
    ReceiptText,
    Repeat,
    ShoppingCart,
    Truck,
    Undo2,
    Wallet,
} from 'lucide-react';

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
            icon: DollarSign,
            tooltipLabel:
                'Total confirmed sales within the selected date range.',
        },
        {
            label: 'Total Orders',
            key: 'totalOrders',
            formatter: numberFormatter,
            icon: ShoppingCart,
            tooltipLabel:
                'Total number of valid orders for the selected filters.',
        },
        {
            label: 'AOV',
            key: 'aov',
            formatter: currencyFormatter,
            icon: ReceiptText,
            tooltipLabel:
                'Average Order Value. Computed as total sales divided by total orders.',
        },
        {
            label: 'RTS Rate',
            key: 'rtsRate',
            formatter: percentageFormatter,
            icon: Undo2,
            tooltipLabel:
                'Return-to-sender rate based on returned amount versus total amount.',
        },
        {
            label: 'Repeat Order Ratio',
            key: 'repeatOrderRatio',
            formatter: percentageFormatter,
            icon: Repeat,
            tooltipLabel: 'Percentage of orders coming from repeat customers.',
        },
        {
            label: 'Time to First Order',
            key: 'timeToFirstOrder',
            formatter: (value: number) => `${value ?? 0} Hrs`,
            icon: Clock3,
            tooltipLabel:
                'Average time from customer creation to first confirmed order.',
        },
        {
            label: 'Avg. Lifetime Value',
            key: 'avgLifetimeValue',
            formatter: currencyFormatter,
            icon: Wallet,
            tooltipLabel:
                'Average revenue generated per customer over their lifetime.',
        },
        {
            label: 'Avg Delivery Days',
            key: 'avgDeliveryDays',
            formatter: (value: number) => `${value ?? 0} Days`,
            icon: Truck,
            tooltipLabel:
                'Average number of days from shipped date to delivered date.',
        },
        {
            label: 'Avg Shipped Out Days',
            key: 'avgShippedOutDays',
            formatter: (value: number) => `${value ?? 0} Days`,
            icon: PackageCheck,
            tooltipLabel:
                'Average number of days from confirmed date to shipped out date.',
        },
    ];

    return (
        <AppLayout>
            <div className="max-w-(--breakpoint-2xl) mx-auto w-full p-4 md:p-6">
                <div className=" flex justify-between  items-center gap-6 mb-6">
                    <h1 className='text-2xl font-bold'>Dashboard</h1>
                    <div className='flex gap-4 items-center'>
                            {/* Optional: if your Filters supports disabled, pass it; otherwise just leave it */}
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
                    </div>

                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-3 ">
                    {cards.map((card) => (
                        <StatisticCard
                            key={card.key}
                            label={card.label}
                            metric={card.key}
                            workspace={workspace}
                            filter={filter}
                            dateRange={dateRange}
                            formatter={card.formatter}
                            icon={card.icon}
                            tooltipLabel={card.tooltipLabel}
                        />
                    ))}
                </div>
                <ComponentCard className="mt-6">
                    <StatisticBreakdown
                        filter={filter}
                        dateRange={dateRange}
                        workspace={workspace}
                    />
                </ComponentCard>

                <ComponentCard className="mt-6">
                    <PageBreakdown
                        dateRange={dateRange}
                        workspace={workspace}
                        filter={filter}
                    />
                </ComponentCard>

                <ComponentCard className="mt-6">
                    <ShopBreakdown
                        filter={filter}
                        dateRange={dateRange}
                        workspace={workspace}
                    />
                </ComponentCard>
            </div>
        </AppLayout>
    );
};

export default Dashboard;
