import AppLayout from '@/layouts/app-layout';
import { useEffect, useMemo, useState } from 'react';
import axios, { AxiosResponse } from 'axios';
import { Workspace } from '@/types/models/Workspace';
import {
    currencyFormatter,
    numberFormatter,
    percentageFormatter,
} from '@/lib/utils';
import DatePicker from '@/components/ui/date-picker';
import Filters from '@/components/filters/Filters';
import moment from 'moment';
import flatpickr from 'flatpickr';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    workspace: Workspace
}

interface Analytics {
    totalOrders: number;
    totalSales: number;
    aov: number;
    rtsRate: number;
    repeatOrderRatio: number;
    timeToFirstOrder: number;
    avgLifetimeValue: number;
    avgDeliveryDays: number;
}

const Dashboard = ({ workspace }: Props) => {
    const [dateRange, setDateRange] = useState([
        moment().startOf('month').format('YYYY-MM-DD'),
        moment().endOf('month').format('YYYY-MM-DD'),
    ]);

    const [analytics, setAnalytics] = useState<Analytics | null>();

    useEffect(() => {
        axios
            .get(`/api/v1/workspace/analytics`, {
                headers: {
                    'X-Workspace-Id': workspace.id,
                },
                params: {
                    'date_range[start_date]': moment(dateRange[0]).format(
                        'YYYY-MM-DD',
                    ),
                    'date_range[end_date]': moment(dateRange[1]).format(
                        'YYYY-MM-DD',
                    ),
                },
            })
            .then((response: AxiosResponse<Analytics>) => {
                setAnalytics(response.data);
            });
    }, [workspace.id, dateRange]);

    const cards = useMemo(() => {
        return [
            {
                label: 'Total Sales',
                value: currencyFormatter(analytics?.totalSales ?? 0),
            },
            {
                label: 'Total Orders',
                value: numberFormatter(analytics?.totalOrders ?? 0),
            },
            { label: 'AOV', value: currencyFormatter(analytics?.aov ?? 0) },
            {
                label: 'RTS Rate',
                value: percentageFormatter(analytics?.rtsRate ?? 0),
            },
            {
                label: 'Repeat Order Ratio',
                value: percentageFormatter(analytics?.repeatOrderRatio ?? 0),
            },
            {
                label: 'Time to first Order',
                value: `${analytics?.timeToFirstOrder ?? 0} Hrs`,
            },
            {
                label: 'Avg. Lifetime Value',
                value: currencyFormatter(analytics?.avgLifetimeValue ?? 0),
            },
            {
                label: 'Avg Delivery Days',
                value: `${analytics?.avgDeliveryDays ?? 0} Days`,
            },
        ];
    }, [analytics])

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 md:gap-6 xl:grid-cols-4">
                    <div className="sm:col-start-1 md:col-start-2 xl:col-start-3">
                        <Filters workspace={workspace} />
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
                        <div
                            key={card.label}
                            className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
                        >
                            <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                {card.label}
                            </p>

                            <div className="mt-3 flex items-end justify-between">
                                <div>
                                    <h4 className="text-2xl font-bold text-gray-800 dark:text-white/90">
                                        {card.value}
                                    </h4>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/*<ComponentCard title='Sales' className="mt-6 h-auto">*/}
                {/*    <BarChart categories={breakdown.map(a => a.period)} series={[*/}
                {/*        {*/}
                {/*            name: 'Sales',*/}
                {/*            data:breakdown.map(a => a.value)*/}
                {/*        }*/}
                {/*    ]}/>*/}
                {/*</ComponentCard>*/}
            </div>
        </AppLayout>
    );
}

export default Dashboard;
