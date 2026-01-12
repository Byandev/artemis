
import { SimpleDateRangePicker } from '@/components/ui/simple-date-range-picker';
import { useDateRange } from '@/hooks/use-date-range';
import AdSpentBreakdown from '@/pages/workspaces/products/partials/analytics/AdSpentBreakdown';
import RoasBreakdown from '@/pages/workspaces/products/partials/analytics/RoasBreakdown';
import RtsBreakdown from '@/pages/workspaces/products/partials/analytics/RtsBreakdown';
import SalesBreakdown from '@/pages/workspaces/products/partials/analytics/SalesBreakdown';
import StatusBreakdown from '@/pages/workspaces/products/partials/analytics/StatusBreakdown';
import TopProducts from '@/pages/workspaces/products/partials/analytics/TopProducts';
import ProductLayout from '@/pages/workspaces/products/partials/layout';
import { Workspace } from '@/types/models/Workspace';
import moment from 'moment';
import { useMemo } from 'react';

interface Props {
    workspace: Workspace;
    summary: {
        scaling_product_count: number;
        testing_product_count: number;
        inactive_product_count: number;
        total_product_count: number
    }
}
const Analytics = ({ workspace, summary }: Props) => {
    const items = useMemo(() => {
        return [
            { title: 'Total Products', value: summary.total_product_count },
            { title: 'Scaling Products', value: summary.scaling_product_count },
            { title: 'Testing Products', value: summary.testing_product_count },
            {
                title: 'Inactive Products',
                value: summary.inactive_product_count,
            },
        ];
    }, [summary]);

    // Use global date range state with automatic initialization
    const { dateRange, setDateRange } = useDateRange();

    const dateRangeStr = useMemo(() => ({
        to: moment(dateRange?.to).format('YYYY-MM-DD'),
        from: moment(dateRange?.from).format('YYYY-MM-DD'),
    }), [dateRange])

    return (
        <ProductLayout workspace={workspace}>
            <div className="space-y-5 sm:space-y-6">
                <div>
                    <SimpleDateRangePicker useGlobalState />
                </div>

                <div className='grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4'>
                    {items.map((item) => (
                        <div key={item.title} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                            <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                {item.title}
                            </p>
                            <div className="mt-3 flex items-end justify-between">
                                <div>
                                    <h4 className="text-2xl font-bold text-gray-800 dark:text-white/90">
                                        {item.value}
                                    </h4>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="grid lg:grid-cols-2 gap-4">
                    <TopProducts workspace={workspace} startDate={dateRangeStr.from} endDate={dateRangeStr.to} />

                    <StatusBreakdown
                        scaling_product_count={summary.scaling_product_count}
                        inactive_product_count={summary.inactive_product_count}
                        testing_product_count={summary.testing_product_count}
                    />
                </div>

                <div className="grid gap-4">
                    <SalesBreakdown workspace={workspace} startDate={dateRangeStr.from} endDate={dateRangeStr.to} />

                    <AdSpentBreakdown workspace={workspace} startDate={dateRangeStr.from} endDate={dateRangeStr.to} />

                    <RoasBreakdown workspace={workspace} startDate={dateRangeStr.from} endDate={dateRangeStr.to} />

                    <RtsBreakdown workspace={workspace} startDate={dateRangeStr.from} endDate={dateRangeStr.to} />
                </div>
            </div>
        </ProductLayout>
    );
};

export default Analytics;
