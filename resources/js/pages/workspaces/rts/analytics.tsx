import { Workspace } from '@/types/models/Workspace';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import moment from 'moment/moment';
import Filters, { FilterValue } from '@/components/filters/Filters';
import {
    currencyFormatter,
    numberFormatter,
    percentageFormatter,
} from '@/lib/utils';
import { DollarSign, PackageCheck, Truck, Undo2 } from 'lucide-react';
import { formatDate } from 'date-fns';
import DatePicker from '@/components/ui/date-picker';
import StatisticCard from '@/pages/workspaces/dashboard/partials/StatisticCard';
import flatpickr from 'flatpickr';
import DateOption = flatpickr.Options.DateOption;
import { metricConfigs } from '@/types/metrics';

interface Props {
    workspace: Workspace
}

const Analytics = ({ workspace }: Props) => {
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
            label: 'RTS Rate',
            key: 'rtsRate',
            formatter: percentageFormatter,
            icon: Undo2,
            tooltipLabel:
                'Return-to-sender rate based on returned amount versus total amount.',
        },
        {
            label: 'Total Delivered',
            key: 'deliveredAmount',
            formatter: currencyFormatter,
            icon: DollarSign,
            tooltipLabel:
                'Total delivered sales within the selected date range.',
        },
        {
            label: 'Total Returning',
            key: 'returningAmount',
            formatter: currencyFormatter,
            icon: DollarSign,
            tooltipLabel:
                'Total returning sales within the selected date range.',
        },
        {
            label: 'Total Returned',
            key: 'returnedAmount',
            formatter: currencyFormatter,
            icon: DollarSign,
            tooltipLabel:
                'Total returned sales within the selected date range.',
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
        {
            label: 'Tracked Orders by PJ',
            key: 'trackedOrdersCount',
            formatter: numberFormatter,
            icon: DollarSign,
            tooltipLabel: 'Total orders tracked by parcel journey',
        },
    ];

    return (
        <AppLayout>

            <Head title={`${workspace.name} - RTS Analytics`} />

            <div className="p-4 md:p-6">

            <div className="mb-6 flex items-center justify-between gap-6">
                <div className="flex flex-col">
                    <h1 className="text-2xl font-bold">Dashboard</h1>

                    <p className="text-sm font-light text-gray-500">
                        Performance overview from{' '}
                        {formatDate(new Date(dateRange[0]), 'MMMM d yyyy')} to{' '}
                        {formatDate(new Date(dateRange[1]), 'MMMM d yyyy')}
                    </p>
                </div>

                <div className="flex items-center gap-4">
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

            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 md:gap-4 xl:grid-cols-4">
                {metricConfigs.map((card) => (
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
                    />
                ))}
            </div>

            </div>
        </AppLayout>
    );
}

export default Analytics
