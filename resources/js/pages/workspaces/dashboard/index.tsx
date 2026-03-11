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
import Filters, { FilterValue } from '@/components/filters/Filters';
import moment from 'moment';
import flatpickr from 'flatpickr';
import DateOption = flatpickr.Options.DateOption;
import StatisticsCards from '@/pages/workspaces/dashboard/partials/StatisticsCards';
import { StatisticBreakdown } from '@/pages/workspaces/dashboard/partials/StatisticBreakdown';
import ComponentCard from '@/components/common/ComponentCard';

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
                <StatisticsCards
                    dateRange={dateRange}
                    filter={filter}
                    workspace={workspace}
                />

                <ComponentCard className="mt-6">
                    <StatisticBreakdown filter={filter} dateRange={dateRange}  workspace={workspace}/>
                </ComponentCard>

            </div>
        </AppLayout>
    );
};

export default Dashboard;
